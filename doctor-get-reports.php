<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function sendResponse($success, $message, $reports = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'reports' => $reports
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Doctor authentication
if (
    ($_SESSION['user_role'] ?? '') !== 'doctor' ||
    empty($_SESSION['doctor_id'])
) {
    http_response_code(401);
    sendResponse(false, 'Please log in as a doctor.');
}

try {
    $doctorId = (int) $_SESSION['doctor_id'];

    // Fetch patient-uploaded reports assigned to this doctor
    $sql = "
        SELECT
            mr.id,
            mr.`Patient_id` AS patient_db_id,
            p.patient_id AS patient_code,
            p.name AS patient_name,
            mr.report_title,
            mr.report_type,
            mr.report_date,
            mr.doctor_name,
            mr.file_path,
            mr.notes,
            mr.created_at
        FROM medical_reports AS mr
        INNER JOIN patients AS p
            ON p.id = mr.`Patient_id`
        WHERE mr.doctor_id = :doctor_id
        ORDER BY
            COALESCE(mr.report_date, DATE(mr.created_at)) DESC,
            mr.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':doctor_id' => $doctorId
    ]);

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(
        true,
        'Medical reports loaded successfully.',
        $reports
    );

} catch (Throwable $e) {
    error_log(
        'MediConnect doctor-get-reports.php: ' . $e->getMessage()
    );

    http_response_code(500);
    sendResponse(false, 'Unable to load medical reports.');
}