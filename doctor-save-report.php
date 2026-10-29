<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function sendResponse($success, $message, $extra = [])
{
    echo json_encode(
        array_merge([
            'success' => $success,
            'message' => $message
        ], $extra),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// Only POST requests are allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, 'Invalid request method.');
}

// Verify doctor login
if (
    ($_SESSION['user_role'] ?? '') !== 'doctor' ||
    empty($_SESSION['doctor_id'])
) {
    http_response_code(401);
    sendResponse(false, 'Doctor login required.');
}

// Read submitted form data
$patientPublicId = trim($_POST['patient_id'] ?? '');
$patientName     = trim($_POST['patient_name'] ?? '');
$reportTitle     = trim($_POST['report_title'] ?? '');
$reportType      = trim($_POST['report_type'] ?? '');
$reportDate      = trim($_POST['report_date'] ?? '');
$notes           = trim($_POST['notes'] ?? '');

// Validate required fields
if (
    $patientPublicId === '' ||
    $reportTitle === '' ||
    $reportType === '' ||
    $reportDate === '' ||
    $notes === ''
) {
    http_response_code(422);
    sendResponse(false, 'Please fill in all required fields.');
}

// Validate date format
$dateObject = DateTime::createFromFormat('!Y-m-d', $reportDate);

if (
    !$dateObject ||
    $dateObject->format('Y-m-d') !== $reportDate
) {
    http_response_code(422);
    sendResponse(false, 'Invalid report date.');
}

try {
    // Find patient using the public ID, e.g. MC-P001
    $patientStmt = $pdo->prepare(
        "SELECT id, patient_id, name
         FROM patients
         WHERE patient_id = :patient_id
         LIMIT 1"
    );

    $patientStmt->execute([
        'patient_id' => $patientPublicId
    ]);

    $patient = $patientStmt->fetch();

    if (!$patient) {
        http_response_code(404);
        sendResponse(false, 'Patient ID not found.');
    }

    // Check the submitted patient name if provided
    if (
        $patientName !== '' &&
        strcasecmp($patientName, $patient['name']) !== 0
    ) {
        http_response_code(422);
        sendResponse(
            false,
            'Patient name does not match the selected Patient ID.'
        );
    }

    // Verify the logged-in doctor exists
    $doctorStmt = $pdo->prepare(
        "SELECT id, name
         FROM doctors
         WHERE id = :doctor_id
         LIMIT 1"
    );

    $doctorStmt->execute([
        'doctor_id' => $_SESSION['doctor_id']
    ]);

    $doctor = $doctorStmt->fetch();

    if (!$doctor) {
        http_response_code(404);
        sendResponse(false, 'Doctor account was not found.');
    }

    // Insert into your existing medical_reports table
    $insertStmt = $pdo->prepare(
    "INSERT INTO medical_reports
    (
        `Patient_id`,
        doctor_id,
        report_title,
        report_type,
        report_date,
        doctor_name,
        file_path,
        notes,
        created_at
    )
    VALUES
    (
        :patient_id,
        :doctor_id,
        :report_title,
        :report_type,
        :report_date,
        :doctor_name,
        '',
        :notes,
        NOW()
    )"
);

$insertStmt->execute([
    'patient_id'   => $patient['id'],
    'doctor_id'    => $doctor['id'],
    'report_title' => $reportTitle,
    'report_type'  => $reportType,
    'report_date'  => $reportDate,
    'doctor_name'  => $doctor['name'],
    'notes'        => $notes
]);
       
    sendResponse(true, 'Medical report saved successfully.', [
        'report_id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    error_log('Doctor save report error: ' . $e->getMessage());

    http_response_code(500);
    sendResponse(
        false,
        'Database error occurred while saving the medical report.'
    );
}