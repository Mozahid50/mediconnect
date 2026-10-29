<?php
session_start();
require_once __DIR__ . "/config.php";

function jsonResponse(array $data, int $status = 200)
{
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        jsonResponse([
            "success" => false,
            "message" => "Invalid request. Please try again."
        ], 400);
    }

    $role = $data["role"] ?? "patient";
    $name = trim($data["name"] ?? "");
    $email = strtolower(trim($data["email"] ?? ""));
    $phone = trim($data["phone"] ?? "");
    $password = $data["password"] ?? "";
    $confirmPassword = $data["confirmPassword"] ?? "";
    $terms = !empty($data["terms"]);

    if (!in_array($role, ["patient", "doctor"], true)) {
        jsonResponse([
            "success" => false,
            "message" => "Select a valid account type."
        ], 400);
    }

    if (strlen($name) < 2 || strlen($name) > 100) {
        jsonResponse([
            "success" => false,
            "message" => "Enter your name (2–100 characters)."
        ], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        jsonResponse([
            "success" => false,
            "message" => "Enter a valid email address."
        ], 400);
    }

    if (!preg_match('/^01[0-9]{9}$/', $phone)) {
        jsonResponse([
            "success" => false,
            "message" => "Enter a valid 11-digit Bangladesh mobile number."
        ], 400);
    }

    if (strlen($password) < 8 || strlen($password) > 128) {
        jsonResponse([
            "success" => false,
            "message" => "Password must be 8–128 characters."
        ], 400);
    }

    if (!hash_equals($password, $confirmPassword)) {
        jsonResponse([
            "success" => false,
            "message" => "Passwords do not match."
        ], 400);
    }

    if (!$terms) {
        jsonResponse([
            "success" => false,
            "message" => "Please accept the registration terms."
        ], 400);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        /*
         * PATIENT REGISTRATION
         */
        if ($role === "patient") {
            $dob = $data["dob"] ?? "";
            $gender = $data["gender"] ?? "";
            $bloodGroup = $data["bloodGroup"] ?? "";

            $validGenders = [
                "female",
                "male",
                "other",
                "prefer-not-to-say"
            ];

            $validBloodGroups = [
                "",
                "A+", "A-",
                "B+", "B-",
                "AB+", "AB-",
                "O+", "O-"
            ];

            $date = DateTime::createFromFormat("!Y-m-d", $dob);

            $validDate = $date
                && $date->format("Y-m-d") === $dob
                && $dob <= date("Y-m-d");

            if (!$validDate) {
                $pdo->rollBack();
                jsonResponse([
                    "success" => false,
                    "message" => "Enter a valid date of birth."
                ], 400);
            }

            if (!in_array($gender, $validGenders, true)) {
                $pdo->rollBack();
                jsonResponse([
                    "success" => false,
                    "message" => "Select a valid gender."
                ], 400);
            }

            if (!in_array($bloodGroup, $validBloodGroups, true)) {
                $pdo->rollBack();
                jsonResponse([
                    "success" => false,
                    "message" => "Select a valid blood group."
                ], 400);
            }

            $check = $pdo->prepare(
                "SELECT id FROM patients
                 WHERE email = ? OR phone = ?
                 LIMIT 1"
            );
            $check->execute([$email, $phone]);

            if ($check->fetch()) {
                $pdo->rollBack();
                jsonResponse([
                    "success" => false,
                    "message" => "This email or phone number is already registered."
                ], 409);
            }

            // Generate a unique public Patient ID.
            $patientID = "";

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $candidate = "MC-P" . strtoupper(bin2hex(random_bytes(4)));

                $checkID = $pdo->prepare(
                    "SELECT id FROM patients
                     WHERE patient_id = ?
                     LIMIT 1"
                );
                $checkID->execute([$candidate]);

                if (!$checkID->fetch()) {
                    $patientID = $candidate;
                    break;
                }
            }

            if ($patientID === "") {
                throw new RuntimeException(
                    "Could not generate a patient ID."
                );
            }

            $stmt = $pdo->prepare(
                "INSERT INTO patients
                (
                    patient_id,
                    name,
                    email,
                    password,
                    phone,
                    dob,
                    gender,
                    blood_group
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $patientID,
                $name,
                $email,
                $passwordHash,
                $phone,
                $dob,
                $gender,
                $bloodGroup !== "" ? $bloodGroup : null
            ]);

            $patientDbID = (int) $pdo->lastInsertId();

            $pdo->commit();

            session_regenerate_id(true);

            $_SESSION["user_role"] = "patient";
            $_SESSION["patient_db_id"] = $patientDbID;
            $_SESSION["patient_id"] = $patientID;
            $_SESSION["patient_name"] = $name;
            $_SESSION["patient_email"] = $email;

            jsonResponse([
                "success" => true,
                "message" => "Patient account created successfully.",
                "patientID" => $patientID,
                "redirect" => "patient-dashboard.php"
            ]);
        }

        /*
         * DOCTOR REGISTRATION
         * No registration key is required.
         */
        else {
            $specialty = trim($data["specialty"] ?? "");
            $qualification = trim($data["qualification"] ?? "");

            $experience = filter_var(
                $data["experience"] ?? null,
                FILTER_VALIDATE_INT
            );

            $fee = filter_var(
                $data["fee"] ?? null,
                FILTER_VALIDATE_FLOAT
            );

            if ($specialty === "" || strlen($specialty) > 100) {
                $pdo->rollBack();
                jsonResponse([
                    "success" => false,
                    "message" => "Enter a valid specialty."
                ], 400);
            }

            if ($qualification === "" || strlen($qualification) > 255) {
                $pdo->rollBack();
                jsonResponse([
                    "success" => false,
                    "message" => "Enter a valid qualification."
                ], 400);
            }

            if (
                $experience === false ||
                $experience === null ||
                $experience < 0 ||
                $experience > 100
            ) {
                $pdo->rollBack();
                jsonResponse([
                    "success" => false,
                    "message" => "Enter valid years of experience."
                ], 400);
            }

            if (
                $fee === false ||
                $fee === null ||
                !is_finite((float) $fee) ||
                $fee < 0 ||
                $fee > 99999999
            ) {
                $pdo->rollBack();
                jsonResponse([
                    "success" => false,
                    "message" => "Enter a valid consultation fee."
                ], 400);
            }

            // Check email across patient and doctor accounts.
            $check = $pdo->prepare(
                "SELECT email FROM doctor_accounts WHERE email = ?
                 UNION
                 SELECT email FROM patients WHERE email = ?
                 LIMIT 1"
            );

            $check->execute([$email, $email]);

            if ($check->fetch()) {
                $pdo->rollBack();
                jsonResponse([
                    "success" => false,
                    "message" => "This email is already registered."
                ], 409);
            }

            // Prevent a phone number already used by a patient.
            $checkPhone = $pdo->prepare(
                "SELECT id FROM patients
                 WHERE phone = ?
                 LIMIT 1"
            );
            $checkPhone->execute([$phone]);

            if ($checkPhone->fetch()) {
                $pdo->rollBack();
                jsonResponse([
                    "success" => false,
                    "message" => "This phone number is already registered."
                ], 409);
            }

            // Create doctor profile.
            $stmt = $pdo->prepare(
                "INSERT INTO doctors
                (
                    name,
                    specialty,
                    qualification,
                    experience,
                    fee,
                    phone,
                    photo
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $name,
                $specialty,
                $qualification,
                $experience,
                $fee,
                $phone,
                ""
            ]);

            $doctorID = (int) $pdo->lastInsertId();

            // Create doctor login credentials.
            $stmt = $pdo->prepare(
                "INSERT INTO doctor_accounts
                (doctor_id, email, password)
                VALUES (?, ?, ?)"
            );

            $stmt->execute([
                $doctorID,
                $email,
                $passwordHash
            ]);

            $doctorAccountID = (int) $pdo->lastInsertId();

            $pdo->commit();

            session_regenerate_id(true);

            $_SESSION["user_role"] = "doctor";
            $_SESSION["doctor_id"] = $doctorID;
            $_SESSION["doctor_account_id"] = $doctorAccountID;
            $_SESSION["doctor_name"] = $name;
            $_SESSION["doctor_email"] = $email;

            jsonResponse([
                "success" => true,
                "message" => "Doctor account created successfully.",
                "redirect" => "doctor-dashboard.php"
            ]);
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());

        if (
            $e instanceof PDOException &&
            $e->getCode() === "23000"
        ) {
            jsonResponse([
                "success" => false,
                "message" => "This email or phone is already registered."
            ], 409);
        }

        jsonResponse([
            "success" => false,
            "message" => "Registration failed. Check your database structure and try again."
        ], 500);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registration | MediConnect</title>

<link rel="stylesheet" href="style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:#f0f8fa;font-family:Inter,sans-serif;color:#17324d}
.page{min-height:100vh;display:grid;grid-template-columns:.9fr 1.1fr}
.visual{display:flex;align-items:center;justify-content:center;padding:50px;color:white;background:linear-gradient(140deg,rgba(8,43,62,.95),rgba(8,127,140,.82)),url("images/medical-bg.jpg") center/cover no-repeat}
.visual-content{max-width:470px}
.brand{display:inline-flex;gap:12px;align-items:center;margin-bottom:35px;color:white;text-decoration:none;font-size:24px;font-weight:800}
.brand i{font-size:36px;color:#65e1d0}
.brand strong{color:#65e1d0}
.visual h1{font-size:clamp(32px,3.5vw,46px);line-height:1.25}
.visual p{color:#d9e9ee;font-size:14px;line-height:1.9}
.features{display:grid;gap:18px;margin-top:30px;font-size:13px}
.features i{color:#65e1d0;margin-right:10px}
.form-side{padding:35px 20px;display:flex;align-items:center;justify-content:center}
.card{width:100%;max-width:620px;padding:32px;background:#fff;border:1px solid #e2edf2;border-radius:18px;box-shadow:0 15px 50px #16374d12}
.mobile-brand{display:none;margin-bottom:20px}
.heading{margin-bottom:23px}
.heading span{font-size:11px;font-weight:800;letter-spacing:1.8px;color:#087f8c}
.heading h2{margin:9px 0;color:#17324d;font-size:28px}
.heading p{font-size:12px;color:#64748b;line-height:1.7}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.group{margin-bottom:15px}
.group label{display:block;margin-bottom:7px;font-size:11px;font-weight:700;color:#263e54}
.group input,.group select{width:100%;min-height:44px;padding:11px 12px;border:1px solid #dce7ed;border-radius:8px;background:#fbfdfe;color:#17324d;font:inherit;font-size:12px;outline:none}
.group input:focus,.group select:focus{border-color:#087f8c;box-shadow:0 0 0 3px #087f8c19}
.password{position:relative}
.password input{padding-right:42px}
.toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:0;background:transparent;color:#8496a7;cursor:pointer}
.terms{display:flex;gap:9px;align-items:flex-start;font-size:11px;color:#64748b;line-height:1.8;margin:10px 0 18px}
.terms input{margin-top:4px;accent-color:#087f8c}
.terms a,.login a{color:#087f8c;font-weight:700}
.submit{width:100%;min-height:48px;border:0;border-radius:8px;background:#087f8c;color:white;font-size:13px;font-weight:700;cursor:pointer}
.submit:hover{background:#066570}
.submit:disabled{opacity:.65;cursor:wait}
.message{display:none;padding:12px;margin-top:14px;border-radius:8px;font-size:12px;line-height:1.7;overflow-wrap:anywhere}
.message.show{display:block}
.login{text-align:center;margin-top:22px;font-size:12px;color:#64748b}
.security{text-align:center;border-top:1px solid #edf2f5;margin-top:20px;padding-top:16px;color:#8192a1;font-size:10px}
.security i{color:#0e9d79;margin-right:7px}
[hidden]{display:none!important}
@media(max-width:900px){.page{grid-template-columns:1fr}.visual{display:none}.form-side{min-height:100vh;padding:20px 14px}.mobile-brand{display:inline-flex}}
@media(max-width:500px){.card{padding:23px 18px}.grid{grid-template-columns:1fr;gap:0}.heading h2{font-size:25px}}
</style>
</head>

<body>
<div class="page">

<section class="visual">
    <div class="visual-content">
        <a href="index.php" class="brand">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>Medi<strong>Connect</strong></span>
        </a>

        <h1 id="visualTitle">Start Your Journey<br>to Better Health.</h1>

        <p id="visualText">
            Create your account to manage appointments, consult doctors
            online and keep medical records organized in one place.
        </p>

        <div class="features">
            <div><i class="fa-solid fa-circle-check"></i>Online doctor consultations</div>
            <div><i class="fa-solid fa-circle-check"></i>Medical report management</div>
            <div><i class="fa-solid fa-circle-check"></i>Digital prescriptions</div>
        </div>
    </div>
</section>

<main class="form-side">
<div class="card">

    <a href="index.php" class="brand mobile-brand">
        <i class="fa-solid fa-heart-pulse"></i>
        <span>Medi<strong>Connect</strong></span>
    </a>

    <div class="heading">
        <span>CREATE YOUR ACCOUNT</span>
        <h2 id="formTitle">Patient Registration</h2>
        <p id="formDescription">Enter your details to create a patient account.</p>
    </div>

    <form id="registerForm">

        <div class="group">
            <label for="role">Register As</label>
            <select id="role" name="role" required>
                <option value="patient">Patient</option>
                <option value="doctor">Doctor</option>
            </select>
        </div>

        <div class="grid">

            <div class="group">
                <label for="name">Full Name</label>
                <input id="name" name="name" minlength="2" maxlength="100" autocomplete="name" required>
            </div>

            <div class="group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" maxlength="150" autocomplete="email" required>
            </div>

            <div class="group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" pattern="01[0-9]{9}" title="Enter an 11-digit Bangladesh mobile number starting with 01." required>
            </div>

            <div class="group patient-field">
                <label for="dob">Date of Birth</label>
                <input type="date" id="dob" name="dob" required>
            </div>

            <div class="group patient-field">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    <option value="">Select gender</option>
                    <option value="female">Female</option>
                    <option value="male">Male</option>
                    <option value="other">Other</option>
                    <option value="prefer-not-to-say">Prefer not to say</option>
                </select>
            </div>

            <div class="group patient-field">
                <label for="bloodGroup">Blood Group (Optional)</label>
                <select id="bloodGroup" name="bloodGroup">
                    <option value="">Select blood group</option>
                    <option value="A+">A+</option>
                    <option value="A-">A-</option>
                    <option value="B+">B+</option>
                    <option value="B-">B-</option>
                    <option value="AB+">AB+</option>
                    <option value="AB-">AB-</option>
                    <option value="O+">O+</option>
                    <option value="O-">O-</option>
                </select>
            </div>

            <div class="group doctor-field" hidden>
                <label for="specialty">Specialty</label>
                <input id="specialty" name="specialty" maxlength="100" placeholder="e.g. Cardiologist">
            </div>

            <div class="group doctor-field" hidden>
                <label for="qualification">Qualification</label>
                <input id="qualification" name="qualification" maxlength="255" placeholder="e.g. MBBS, FCPS">
            </div>

            <div class="group doctor-field" hidden>
                <label for="experience">Experience (years)</label>
                <input type="number" id="experience" name="experience" min="0" max="100" value="0">
            </div>

            <div class="group doctor-field" hidden>
                <label for="fee">Consultation Fee (৳)</label>
                <input type="number" id="fee" name="fee" min="0" max="99999999" step="0.01" value="0">
            </div>

        </div>

        <div class="group">
            <label for="password">Create Password</label>
            <div class="password">
                <input type="password" id="password" name="password" minlength="8" maxlength="128" autocomplete="new-password" required>
                <button class="toggle" type="button" data-target="password" aria-label="Show password">
                    <i class="fa-regular fa-eye"></i>
                </button>
            </div>
        </div>

        <div class="group">
            <label for="confirmPassword">Confirm Password</label>
            <div class="password">
                <input type="password" id="confirmPassword" name="confirmPassword" minlength="8" maxlength="128" autocomplete="new-password" required>
                <button class="toggle" type="button" data-target="confirmPassword" aria-label="Show password">
                    <i class="fa-regular fa-eye"></i>
                </button>
            </div>
        </div>

        <label class="terms">
            <input type="checkbox" id="terms" required>
            <span>
                I agree to the <a href="#" id="termsLink">Terms of Service</a>
                and understand the importance of protecting my personal information.
            </span>
        </label>

        <button class="submit" id="submitButton" type="submit">
            Create Patient Account <i class="fa-solid fa-arrow-right"></i>
        </button>

        <div class="message" id="message" role="status" aria-live="polite"></div>

    </form>

    <div class="login">
        Already have an account? <a href="login.php">Login Here</a>
    </div>

    <div class="security">
        <i class="fa-solid fa-shield-halved"></i>
        Your privacy matters to us.
    </div>

</div>
</main>
</div>

<script>
"use strict";

const form = document.getElementById("registerForm");
const role = document.getElementById("role");
const message = document.getElementById("message");
const submitButton = document.getElementById("submitButton");
const dob = document.getElementById("dob");

dob.max = new Date().toISOString().slice(0, 10);

function updateRoleFields() {
    const isDoctor = role.value === "doctor";

    document.querySelectorAll(".patient-field").forEach(el => {
        el.hidden = isDoctor;

        el.querySelectorAll("input, select").forEach(input => {
            input.required = !isDoctor && input.id !== "bloodGroup";
        });
    });

    document.querySelectorAll(".doctor-field").forEach(el => {
        el.hidden = !isDoctor;

        el.querySelectorAll("input").forEach(input => {
            input.required = isDoctor;
        });
    });

    document.getElementById("formTitle").textContent =
        isDoctor ? "Doctor Registration" : "Patient Registration";

    document.getElementById("formDescription").textContent =
        isDoctor
        ? "Enter your professional and account details."
        : "Enter your details to create a patient account.";

    document.getElementById("visualTitle").innerHTML =
        isDoctor
        ? "Join Our Healthcare<br>Professional Network."
        : "Start Your Journey<br>to Better Health.";

    document.getElementById("visualText").textContent =
        isDoctor
        ? "Create your doctor account to manage appointments and provide online healthcare consultations."
        : "Create your patient account to manage appointments, consult doctors online and access medical records.";

    submitButton.innerHTML = isDoctor
        ? 'Create Doctor Account <i class="fa-solid fa-arrow-right"></i>'
        : 'Create Patient Account <i class="fa-solid fa-arrow-right"></i>';
}

role.addEventListener("change", () => {
    updateRoleFields();
    message.classList.remove("show");
});

document.querySelectorAll(".toggle").forEach(button => {
    button.addEventListener("click", () => {
        const input = document.getElementById(button.dataset.target);
        const show = input.type === "password";

        input.type = show ? "text" : "password";

        button.innerHTML = show
            ? '<i class="fa-regular fa-eye-slash"></i>'
            : '<i class="fa-regular fa-eye"></i>';

        button.setAttribute(
            "aria-label",
            show ? "Hide password" : "Show password"
        );
    });
});

function showMessage(text, success = false) {
    message.classList.add("show");
    message.style.color = success ? "#086b4d" : "#795b12";
    message.style.background = success ? "#e7f8ef" : "#fff7df";
    message.style.border = success
        ? "1px solid #b5e8cb"
        : "1px solid #f3e1a6";

    message.textContent = text;
}

form.addEventListener("submit", async event => {
    event.preventDefault();

    if (!form.reportValidity()) {
        return;
    }

    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirmPassword").value;

    if (password !== confirmPassword) {
        showMessage("Passwords do not match.");
        document.getElementById("confirmPassword").focus();
        return;
    }

    const payload = {
        role: role.value,
        name: document.getElementById("name").value.trim(),
        email: document.getElementById("email").value.trim().toLowerCase(),
        phone: document.getElementById("phone").value.trim(),
        password: password,
        confirmPassword: confirmPassword,
        terms: document.getElementById("terms").checked
    };

    if (role.value === "patient") {
        payload.dob = dob.value;
        payload.gender = document.getElementById("gender").value;
        payload.bloodGroup = document.getElementById("bloodGroup").value;
    } else {
        payload.specialty = document.getElementById("specialty").value.trim();
        payload.qualification = document.getElementById("qualification").value.trim();
        payload.experience = document.getElementById("experience").value;
        payload.fee = document.getElementById("fee").value;
    }

    submitButton.disabled = true;
    submitButton.innerHTML =
        'Creating Account... <i class="fa-solid fa-spinner fa-spin"></i>';

    try {
        const response = await fetch("register.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            credentials: "same-origin",
            body: JSON.stringify(payload)
        });

        let result;

        try {
            result = await response.json();
        } catch {
            throw new Error(
                "Server returned an invalid response. Check PHP errors."
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || "Registration failed.");
        }

        showMessage(result.message, true);

        if (result.patientID) {
            message.textContent += " Your Patient ID: " + result.patientID;
        }

        if (result.redirect) {
            window.location.href = result.redirect;
            return;
        }

    } catch (error) {
        showMessage(error.message || "Unable to connect to the server.");
    } finally {
        submitButton.disabled = false;
        updateRoleFields();
    }
});

document.getElementById("termsLink").addEventListener("click", event => {
    event.preventDefault();
    alert("Add your actual Terms of Service before publishing.");
});

updateRoleFields();
</script>

</body>
</html>