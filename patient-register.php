<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/config.php";

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    respond(405, [
        "success" => false,
        "message" => "Only POST requests are allowed."
    ]);
}

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        respond(400, [
            "success" => false,
            "message" => "Invalid request data."
        ]);
    }

    $name = trim((string)($data["name"] ?? ""));
    $email = strtolower(trim((string)($data["email"] ?? "")));
    $phone = trim((string)($data["phone"] ?? ""));
    $dob = trim((string)($data["dob"] ?? ""));
    $gender = trim((string)($data["gender"] ?? ""));
    $bloodGroup = trim((string)($data["bloodGroup"] ?? ""));
    $password = (string)($data["password"] ?? "");

    if (
        $name === "" ||
        $email === "" ||
        $phone === "" ||
        $dob === "" ||
        $gender === "" ||
        $password === ""
    ) {
        respond(422, [
            "success" => false,
            "message" => "Please fill in all required fields."
        ]);
    }

    if (strlen($name) > 100) {
        respond(422, [
            "success" => false,
            "message" => "Name must not exceed 100 characters."
        ]);
    }

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150
    ) {
        respond(422, [
            "success" => false,
            "message" => "Please enter a valid email address."
        ]);
    }

    if (!preg_match('/^01[0-9]{9}$/', $phone)) {
        respond(422, [
            "success" => false,
            "message" => "Enter a valid 11-digit Bangladesh mobile number."
        ]);
    }

    if (strlen($password) < 8 || strlen($password) > 128) {
        respond(422, [
            "success" => false,
            "message" => "Password must be between 8 and 128 characters."
        ]);
    }

    $allowedGenders = [
        "female",
        "male",
        "other",
        "prefer-not-to-say"
    ];

    if (!in_array($gender, $allowedGenders, true)) {
        respond(422, [
            "success" => false,
            "message" => "Please select a valid gender."
        ]);
    }

    $allowedBloodGroups = [
        "", "A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"
    ];

    if (!in_array($bloodGroup, $allowedBloodGroups, true)) {
        respond(422, [
            "success" => false,
            "message" => "Please select a valid blood group."
        ]);
    }

    $date = DateTime::createFromFormat("!Y-m-d", $dob);
    $dateErrors = DateTime::getLastErrors();

    if (
        !$date ||
        ($dateErrors !== false &&
            ($dateErrors["warning_count"] > 0 ||
             $dateErrors["error_count"] > 0)) ||
        $date->format("Y-m-d") !== $dob ||
        $dob > date("Y-m-d")
    ) {
        respond(422, [
            "success" => false,
            "message" => "Please enter a valid date of birth."
        ]);
    }

    $check = $pdo->prepare(
        "SELECT email, phone
         FROM patients
         WHERE email = :email OR phone = :phone
         LIMIT 1"
    );

    $check->execute([
        "email" => $email,
        "phone" => $phone
    ]);

    $existing = $check->fetch();

    if ($existing) {
        if (strcasecmp($existing["email"], $email) === 0) {
            respond(409, [
                "success" => false,
                "message" => "This email is already registered."
            ]);
        }

        respond(409, [
            "success" => false,
            "message" => "This phone number is already registered."
        ]);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO patients
        (patient_id, name, email, password, phone, dob, gender, blood_group)
        VALUES (NULL, :name, :email, :password, :phone, :dob, :gender, :blood_group)"
    );

    $stmt->execute([
        "name" => $name,
        "email" => $email,
        "password" => $hashedPassword,
        "phone" => $phone,
        "dob" => $dob,
        "gender" => $gender,
        "blood_group" => $bloodGroup !== "" ? $bloodGroup : null
    ]);

    $id = (int)$pdo->lastInsertId();

    $patientID = "MC-P" . str_pad(
        (string)$id,
        3,
        "0",
        STR_PAD_LEFT
    );

    $update = $pdo->prepare(
        "UPDATE patients SET patient_id = :patient_id WHERE id = :id"
    );

    $update->execute([
        "patient_id" => $patientID,
        "id" => $id
    ]);

    $pdo->commit();

    respond(201, [
        "success" => true,
        "message" => "Registration successful.",
        "patientID" => $patientID
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("MediConnect registration database error: " . $e->getMessage());

    if ($e->getCode() === "23000") {
        respond(409, [
            "success" => false,
            "message" => "This email or phone number is already registered."
        ]);
    }

    respond(500, [
        "success" => false,
        "message" => "Registration failed. Please check the database configuration."
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("MediConnect registration error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "An unexpected error occurred. Please try again."
    ]);
}