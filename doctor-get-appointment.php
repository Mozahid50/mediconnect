<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond($success, $message, $appointments = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'appointments' => $appointments
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    ($_SESSION['user_role'] ?? '') !== 'doctor' ||
    empty($_SESSION['doctor_id'])
) {
    http_response_code(401);
    respond(false, 'Please log in as a doctor.');
}

try {
    $doctorId = (int) $_SESSION['doctor_id'];

    $sql = "
        SELECT
            a.id,
            a.patient_id AS patient_db_id,
            a.doctor_id,
            a.appointment_date AS date,
            a.appointment_time AS time,
            a.reason,
            a.status,
            a.created_at,
            p.patient_id AS patient_code,
            p.name AS patient_name,
            p.phone,
            p.email
        FROM appointments a
        INNER JOIN patients p ON p.id = a.patient_id
        WHERE a.doctor_id = :doctor_id
        ORDER BY
            CASE WHEN LOWER(a.status) = 'pending' THEN 0 ELSE 1 END,
            a.appointment_date ASC,
            a.appointment_time ASC,
            a.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':doctor_id' => $doctorId]);

    respond(
        true,
        'Appointments loaded successfully.',
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

} catch (Throwable $e) {
    error_log('doctor-get-appointments.php: ' . $e->getMessage());

    http_response_code(500);

    respond(
        false,
        'Database error: ' . $e->getMessage()
    );
}