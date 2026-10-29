<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, [
        'success' => false,
        'message' => 'GET request required.'
    ]);
}

if (
    ($_SESSION['user_role'] ?? '') !== 'doctor' ||
    empty($_SESSION['doctor_id'])
) {
    respond(401, [
        'success' => false,
        'message' => 'Doctor login required. Please log in again.'
    ]);
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    respond(500, [
        'success' => false,
        'message' => 'Database connection unavailable.'
    ]);
}

try {
    $doctorId = (int) $_SESSION['doctor_id'];

    $sql = "
        SELECT
            rx.id,
            rx.patient_id,
            p.patient_id AS patient_code,
            p.name AS patient_name,
            rx.doctor_id,
            d.name AS doctor_name,
            rx.diagnosis,
            rx.medicines,
            rx.dosage,
            rx.frequency,
            rx.duration,
            rx.instructions,
            rx.follow_up_date,
            rx.created_at
        FROM prescriptions rx
        LEFT JOIN patients p
            ON p.id = rx.patient_id
        LEFT JOIN doctors d
            ON d.id = rx.doctor_id
        WHERE rx.doctor_id = :doctor_id
        ORDER BY rx.created_at DESC, rx.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'doctor_id' => $doctorId
    ]);

    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(200, [
        'success' => true,
        'prescriptions' => $prescriptions,
        'count' => count($prescriptions)
    ]);

} catch (Throwable $e) {
    error_log(
        'MediConnect get prescriptions error: ' .
        $e->getMessage()
    );

    respond(500, [
        'success' => false,
        'message' => 'Could not load prescriptions. Check the PHP error log.'
    ]);
}