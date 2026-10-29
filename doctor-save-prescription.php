<?php
session_start();

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, [
        'success' => false,
        'message' => 'POST request required.'
    ]);
}

if (
    ($_SESSION['user_role'] ?? '') !== 'doctor' ||
    empty($_SESSION['doctor_id'])
) {
    respond(401, [
        'success' => false,
        'message' => 'Doctor session missing or expired. Please log in again.'
    ]);
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    respond(500, [
        'success' => false,
        'message' => 'Database connection is unavailable.'
    ]);
}

$patientCode = trim($_POST['patient_id'] ?? '');
$patientName = trim($_POST['patient_name'] ?? '');
$diagnosis = trim($_POST['diagnosis'] ?? '');
$medicines = trim($_POST['medicines'] ?? '');
$dosage = trim($_POST['dosage'] ?? '');
$frequency = trim($_POST['frequency'] ?? '');
$duration = trim($_POST['duration'] ?? '');
$instructions = trim($_POST['instructions'] ?? '');
$followUpDate = trim($_POST['follow_up_date'] ?? '');

if (
    $patientCode === '' ||
    $diagnosis === '' ||
    $medicines === '' ||
    $dosage === '' ||
    $duration === ''
) {
    respond(422, [
        'success' => false,
        'message' => 'Patient ID, diagnosis, medicine, dosage and duration are required.'
    ]);
}

try {
    // Verify the authenticated doctor exists in the database.
    $doctorId = filter_var(
        $_SESSION['doctor_id'],
        FILTER_VALIDATE_INT
    );

    if (!$doctorId || $doctorId < 1) {
        respond(401, [
            'success' => false,
            'message' => 'Invalid doctor session ID. Please log in again.'
        ]);
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM doctors WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        respond(403, [
            'success' => false,
            'message' => 'Doctor ID from session was not found in the doctors table.'
        ]);
    }

    // Find the patient by their public patient code, e.g. MC-P001.
    $stmt = $pdo->prepare(
        'SELECT id, patient_id, name
         FROM patients
         WHERE patient_id = ?
         LIMIT 1'
    );
    $stmt->execute([$patientCode]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        respond(404, [
            'success' => false,
            'message' => 'Patient ID not found: ' . $patientCode
        ]);
    }

    if (
        $patientName !== '' &&
        mb_strtolower($patientName, 'UTF-8') !==
        mb_strtolower((string) $patient['name'], 'UTF-8')
    ) {
        respond(422, [
            'success' => false,
            'message' => 'Patient name does not match the selected Patient ID.'
        ]);
    }

    if ($followUpDate !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $followUpDate);

        if (!$date || $date->format('Y-m-d') !== $followUpDate) {
            respond(422, [
                'success' => false,
                'message' => 'Follow-up date must be a valid YYYY-MM-DD date.'
            ]);
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO prescriptions (
            patient_id,
            doctor_id,
            diagnosis,
            medicines,
            dosage,
            frequency,
            duration,
            instructions,
            follow_up_date,
            created_at
        ) VALUES (
            :patient_id,
            :doctor_id,
            :diagnosis,
            :medicines,
            :dosage,
            :frequency,
            :duration,
            :instructions,
            :follow_up_date,
            NOW()
        )'
    );

    $stmt->execute([
        ':patient_id' => (int) $patient['id'],
        ':doctor_id' => (int) $doctor['id'],
        ':diagnosis' => $diagnosis,
        ':medicines' => $medicines,
        ':dosage' => $dosage,
        ':frequency' => $frequency,
        ':duration' => $duration,
        ':instructions' => $instructions,
        ':follow_up_date' => $followUpDate !== ''
            ? $followUpDate
            : null
    ]);

    $newId = (int) $pdo->lastInsertId();

    if ($newId < 1) {
        throw new RuntimeException(
            'The database did not return a valid prescription ID.'
        );
    }

    // Verify the saved row before committing.
    $verify = $pdo->prepare(
        'SELECT id, patient_id, doctor_id
         FROM prescriptions
         WHERE id = ?
         LIMIT 1'
    );
    $verify->execute([$newId]);
    $saved = $verify->fetch(PDO::FETCH_ASSOC);

    if (
        !$saved ||
        (int) $saved['patient_id'] !== (int) $patient['id'] ||
        (int) $saved['doctor_id'] !== (int) $doctor['id']
    ) {
        throw new RuntimeException(
            'Saved prescription could not be verified.'
        );
    }

    $pdo->commit();

    respond(200, [
        'success' => true,
        'message' => 'Prescription saved and verified in MySQL.',
        'prescription_id' => $newId,
        'patient_id' => $patient['patient_id'],
        'patient_name' => $patient['name']
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'MediConnect prescription save error: ' .
        $e->getMessage()
    );

    respond(500, [
        'success' => false,
        'message' => 'Prescription could not be saved.',
        'debug' => $e->getMessage()
    ]);
}