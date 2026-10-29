<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond($success, $message, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    ($_SESSION['user_role'] ?? '') !== 'doctor' ||
    empty($_SESSION['doctor_id'])
) {
    respond(false, 'Doctor login required.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(false, 'POST method required.', 405);
}

$input = $_POST;

if (!$input) {
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json)) {
        $input = $json;
    }
}

$appointmentId = filter_var(
    $input['appointment_id'] ?? null,
    FILTER_VALIDATE_INT
);

$action = strtolower(trim((string)($input['action'] ?? '')));

if (!$appointmentId || $appointmentId < 1) {
    respond(false, 'Invalid appointment ID.', 400);
}

$statusMap = [
    'accept'  => 'Accepted',
    'confirm' => 'Confirmed',
    'cancel'  => 'Cancelled'
];

if (!isset($statusMap[$action])) {
    respond(false, 'Invalid appointment action.', 400);
}

try {
    $doctorId = (int) $_SESSION['doctor_id'];
    $newStatus = $statusMap[$action];

    $stmt = $pdo->prepare("
        UPDATE appointments
        SET status = :new_status
        WHERE id = :appointment_id
          AND doctor_id = :doctor_id
          AND LOWER(TRIM(status)) = 'pending'
    ");

    $stmt->execute([
        ':new_status' => $newStatus,
        ':appointment_id' => $appointmentId,
        ':doctor_id' => $doctorId
    ]);

    if ($stmt->rowCount() !== 1) {
        $check = $pdo->prepare("
            SELECT status
            FROM appointments
            WHERE id = :appointment_id
              AND doctor_id = :doctor_id
            LIMIT 1
        ");

        $check->execute([
            ':appointment_id' => $appointmentId,
            ':doctor_id' => $doctorId
        ]);

        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            respond(false, 'Appointment not found for this doctor.', 404);
        }

        respond(
            false,
            'Appointment is not pending. Current status: '
                . $existing['status'],
            409
        );
    }

    respond(true, 'Appointment status updated to ' . $newStatus . '.');

} catch (Throwable $e) {
    error_log('doctor-update-appointment.php: ' . $e->getMessage());
    respond(false, 'Database error while updating appointment.', 500);
}