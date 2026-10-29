<?php
session_start();
require_once __DIR__ . '/config.php';

// Only logged-in doctors can open reports.
if (
    ($_SESSION['user_role'] ?? '') !== 'doctor' ||
    empty($_SESSION['doctor_id'])
) {
    header('Location: login.php');
    exit;
}

$doctorId = (int) $_SESSION['doctor_id'];
$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reportId || $reportId < 1) {
    http_response_code(400);
    exit('Invalid report ID.');
}

try {
    // Only allow the assigned doctor to access this patient-uploaded file.
    $stmt = $pdo->prepare("
        SELECT file_path, report_title, report_type
        FROM medical_reports
        WHERE id = :report_id
          AND doctor_id = :doctor_id
          AND file_path IS NOT NULL
          AND TRIM(file_path) <> ''
        LIMIT 1
    ");

    $stmt->execute([
        ':report_id' => $reportId,
        ':doctor_id' => $doctorId
    ]);

    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        http_response_code(404);
        exit('Report not found, or you do not have permission to view it.');
    }

    $relativePath = str_replace('\\', '/', $report['file_path']);

    // Prevent paths from escaping the project uploads directory.
    if (
        str_contains($relativePath, '..') ||
        preg_match('/^[a-zA-Z]:/', $relativePath) ||
        str_starts_with($relativePath, '/')
    ) {
        http_response_code(403);
        exit('Invalid report file path.');
    }

    $uploadsRoot = realpath(__DIR__ . '/uploads/medical_reports');
    $filePath = realpath(__DIR__ . '/' . $relativePath);

    if (
        $uploadsRoot === false ||
        $filePath === false ||
        !is_file($filePath)
    ) {
        http_response_code(404);
        exit('The report file could not be found on the server.');
    }

    $rootPrefix = rtrim($uploadsRoot, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR;

    if (strpos($filePath, $rootPrefix) !== 0) {
        http_response_code(403);
        exit('Access to this file is not allowed.');
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    $allowedTypes = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png'
    ];

    if (!isset($allowedTypes[$extension])) {
        http_response_code(415);
        exit('This report file type is not supported.');
    }

    if (!is_readable($filePath)) {
        http_response_code(403);
        exit('The report file cannot be read by the server.');
    }

    header('Content-Type: ' . $allowedTypes[$extension]);
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: inline; filename="medical-report-'
        . $reportId . '.' . $extension . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');

    readfile($filePath);
    exit;

} catch (Throwable $e) {
    error_log('MediConnect doctor-view-report.php: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to open the report right now.');
}