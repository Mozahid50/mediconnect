<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . "/config.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, private");
header("X-Content-Type-Options: nosniff");

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

/* Check login */
$role = $_SESSION["user_role"] ?? "";

if (!in_array($role, ["patient", "doctor"], true)) {
    respond(401, ["error" => "Please log in first."]);
}

/* Read appointment ID */
$rawId = $_GET["appointment_id"] ?? "";

if (!is_string($rawId) || !ctype_digit($rawId)) {
    respond(400, ["error" => "Invalid appointment ID."]);
}

$appointmentId = (int) $rawId;

if ($appointmentId < 1) {
    respond(400, ["error" => "Invalid appointment ID."]);
}

/*
 * Read the same .env file used by Node.js.
 * This replaces the separate PHP secret file.
 */
$envFile = __DIR__ . "/signaling-server/.env";

if (!is_file($envFile) || !is_readable($envFile)) {
    error_log("MediConnect: signaling .env file is missing.");
    respond(500, ["error" => "Video service configuration error."]);
}

$secret = null;

foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
    $line = trim($line);

    if ($line === "" || str_starts_with($line, "#")) {
        continue;
    }

    if (str_starts_with($line, "SIGNALING_SECRET=")) {
        $secret = trim(substr($line, strlen("SIGNALING_SECRET=")));
        $secret = trim($secret, "\"'");
        break;
    }
}

if (!is_string($secret) || strlen($secret) < 32) {
    error_log("MediConnect: SIGNALING_SECRET missing or invalid.");
    respond(500, ["error" => "Video service configuration error."]);
}

try {
    /* Verify appointment ownership */
    if ($role === "patient") {
        $stmt = $pdo->prepare("
            SELECT id, patient_id, doctor_id, status
            FROM appointments
            WHERE id = ? AND patient_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $appointmentId,
            (int) ($_SESSION["patient_db_id"] ?? 0)
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, patient_id, doctor_id, status
            FROM appointments
            WHERE id = ? AND doctor_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $appointmentId,
            (int) ($_SESSION["doctor_id"] ?? 0)
        ]);
    }

    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        respond(403, ["error" => "You cannot access this appointment."]);
    }

    if (trim((string) $appointment["status"]) !== "Confirmed") {
        respond(403, ["error" => "Appointment is not confirmed."]);
    }

    /* Create a signed token valid for 120 seconds */
    $now = time();

    $payload = [
        "iss" => "mediconnect",
        "sub" => $role === "patient"
            ? (int) ($_SESSION["patient_db_id"] ?? 0)
            : (int) ($_SESSION["doctor_id"] ?? 0),
        "role" => $role,
        "appointment_id" => (int) $appointment["id"],
        "patient_id" => (int) $appointment["patient_id"],
        "doctor_id" => (int) $appointment["doctor_id"],
        "iat" => $now,
        "exp" => $now + 120,
        "jti" => bin2hex(random_bytes(16))
    ];

    $encode = static function (string $value): string {
        return rtrim(strtr(base64_encode($value), "+/", "-_"), "=");
    };

    $body = $encode(json_encode($payload, JSON_THROW_ON_ERROR));
    $signature = hash_hmac("sha256", $body, $secret, true);
    $token = $body . "." . $encode($signature);

    respond(200, [
        "token" => $token,
        "expires_in" => 120,
        "appointment_id" => (int) $appointment["id"]
    ]);

} catch (Throwable $e) {
    error_log("MediConnect token error: " . $e->getMessage());
    respond(500, ["error" => "Unable to prepare video consultation."]);
}