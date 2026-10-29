<?php
session_start();
require_once __DIR__ . "/config.php";

$error = "";

/*
|--------------------------------------------------------------------------
| Patient Redirect Destinations
|--------------------------------------------------------------------------
*/
$allowedPatientRedirects = [
    "patient-dashboard.php",
    "video-consultations.php",
    "digital-prescription.php",
    "medical-reports.php"
];

function getSafeRedirect(string $destination, array $allowed): string
{
    return in_array($destination, $allowed, true)
        ? $destination
        : "patient-dashboard.php";
}

$requestedRedirect = $_SERVER["REQUEST_METHOD"] === "POST"
    ? ($_POST["redirect"] ?? "")
    : ($_GET["redirect"] ?? "");

$redirect = getSafeRedirect(
    $requestedRedirect,
    $allowedPatientRedirects
);

/*
|--------------------------------------------------------------------------
| Login Processing
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $role = $_POST["userRole"] ?? "";
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    if (!in_array($role, ["patient", "doctor"], true)) {

        $error = "Please select a valid account type.";

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $password === ""
    ) {

        $error = "Please enter a valid email and password.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | PATIENT LOGIN
            |--------------------------------------------------------------------------
            */
            if ($role === "patient") {

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        patient_id,
                        name,
                        email,
                        password
                    FROM patients
                    WHERE email = :email
                    LIMIT 1
                ");

                $stmt->execute([
                    "email" => $email
                ]);

                $patient = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    $patient &&
                    password_verify($password, $patient["password"])
                ) {

                    session_regenerate_id(true);

                    // Remove old role-specific session information.
                    unset(
                        $_SESSION["doctor_id"],
                        $_SESSION["doctor_account_id"],
                        $_SESSION["doctor_name"],
                        $_SESSION["doctor_email"]
                    );

                    $_SESSION["user_role"] = "patient";
                    $_SESSION["patient_db_id"] = (int) $patient["id"];
                    $_SESSION["patient_id"] = $patient["patient_id"];
                    $_SESSION["patient_name"] = $patient["name"];
                    $_SESSION["patient_email"] = $patient["email"];

                    header(
                        "Location: " .
                        getSafeRedirect(
                            $redirect,
                            $allowedPatientRedirects
                        )
                    );
                    exit;

                } else {

                    $error = "Invalid email or password.";
                }

            /*
            |--------------------------------------------------------------------------
            | DOCTOR LOGIN
            |--------------------------------------------------------------------------
            */
            } elseif ($role === "doctor") {

                $stmt = $pdo->prepare("
                    SELECT
                        da.id AS doctor_account_id,
                        da.doctor_id,
                        da.email,
                        da.password,
                        d.name AS doctor_name
                    FROM doctor_accounts AS da
                    INNER JOIN doctors AS d
                        ON d.id = da.doctor_id
                    WHERE da.email = :email
                    LIMIT 1
                ");

                $stmt->execute([
                    "email" => $email
                ]);

                $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    $doctor &&
                    password_verify($password, $doctor["password"])
                ) {

                    session_regenerate_id(true);

                    // Remove old patient session information.
                    unset(
                        $_SESSION["patient_db_id"],
                        $_SESSION["patient_id"],
                        $_SESSION["patient_name"],
                        $_SESSION["patient_email"]
                    );

                    // Save authenticated doctor information.
                    $_SESSION["user_role"] = "doctor";
                    $_SESSION["doctor_id"] = (int) $doctor["doctor_id"];
                    $_SESSION["doctor_account_id"] =
                        (int) $doctor["doctor_account_id"];
                    $_SESSION["doctor_name"] = $doctor["doctor_name"];
                    $_SESSION["doctor_email"] = $doctor["email"];

                    // Doctor login goes to the Doctor Dashboard.
                    header("Location: doctor-dashboard.php");
                    exit;

                } else {

                    $error = "Invalid doctor email or password.";
                }
            }

        } catch (PDOException $e) {

            error_log("MediConnect login error: " . $e->getMessage());

            $error = "Unable to log in right now. Please check your database configuration and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login | MediConnect</title>

    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <style>
        body{min-height:100vh;background:#f0f8fa}
        .auth-page{min-height:100vh;display:grid;grid-template-columns:1fr 1fr}
        .auth-visual{position:relative;display:flex;align-items:center;justify-content:center;padding:60px;color:white;overflow:hidden;background:linear-gradient(135deg,rgba(8,43,62,.92),rgba(8,127,140,.78)),url("images/medical-bg.jpg") center/cover no-repeat}
        .auth-visual-content{max-width:500px;position:relative;z-index:1}
        .auth-visual-content .visual-icon{width:72px;height:72px;display:grid;place-items:center;margin-bottom:30px;font-size:35px;color:#65e1d0;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:20px}
        .auth-visual h1{font-size:clamp(34px,4vw,49px);line-height:1.25;margin-bottom:20px}
        .auth-visual-content>p{color:#dcecef;font-size:14px;line-height:1.9}
        .auth-benefits{margin-top:35px;display:grid;gap:18px}
        .auth-benefit{display:flex;align-items:center;gap:13px;font-size:13px}
        .auth-benefit i{color:#65e1d0;font-size:18px}
        .auth-form-side{display:flex;align-items:center;justify-content:center;padding:45px 25px}
        .auth-card{width:100%;max-width:430px;padding:38px;background:white;border:1px solid #e3edf2;border-radius:18px;box-shadow:0 15px 50px rgba(22,55,77,.07)}
        .auth-mobile-logo{display:none;margin-bottom:35px}
        .auth-heading{margin-bottom:30px}
        .auth-heading .eyebrow{color:#087f8c;font-size:11px;font-weight:800;letter-spacing:1.8px}
        .auth-heading h2{margin:10px 0;color:#17324d;font-size:30px;line-height:1.3}
        .auth-heading p{color:#64748b;font-size:13px}
        .auth-form .form-group{margin-bottom:20px}
        .auth-form label{display:block;margin-bottom:8px;color:#263e54;font-size:12px;font-weight:700}
        .auth-input-wrap{position:relative}
        .auth-input-wrap>i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#8496a7;font-size:14px}
        .auth-input-wrap input{width:100%;min-height:49px;padding:12px 44px;color:#17324d;background:#fbfdfe;border:1px solid #dce7ed;border-radius:8px;outline:none;font-size:13px;transition:border-color .25s,box-shadow .25s}
        .auth-input-wrap input:focus{border-color:#087f8c;box-shadow:0 0 0 3px rgba(8,127,140,.1)}
        .toggle-password{position:absolute;top:50%;right:14px;transform:translateY(-50%);padding:5px;color:#8293a3;background:transparent;border:0;cursor:pointer}
        .auth-options{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:5px 0 24px;font-size:11px}
        .remember-option{display:flex!important;align-items:center;gap:7px;color:#64748b}
        .remember-option input{accent-color:#087f8c}
        .auth-options a,.auth-switch a{color:#087f8c;font-weight:700}
        .auth-submit{width:100%;min-height:49px;display:flex;justify-content:center;align-items:center;gap:10px;color:white;background:#087f8c;border:0;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;transition:background .25s,transform .25s}
        .auth-submit:hover{background:#066570;transform:translateY(-2px)}
        .auth-message{margin-bottom:18px;padding:11px;border-radius:8px;font-size:12px;line-height:1.6;color:#8b2929;background:#fff0f0;border:1px solid #f1cccc}
        .auth-switch{margin-top:24px;text-align:center;color:#64748b;font-size:12px}
        .auth-security{margin-top:25px;padding-top:18px;border-top:1px solid #edf2f5;display:flex;justify-content:center;align-items:center;gap:8px;color:#8192a1;font-size:10px}
        .auth-security i{color:#0e9d79}
        .role-select{width:100%;padding:12px;border:1px solid #dce7ed;border-radius:8px;background:#fbfdfe;color:#17324d}
        @media(max-width:850px){.auth-page{grid-template-columns:1fr}.auth-visual{display:none}.auth-form-side{min-height:100vh;padding:30px 18px}.auth-card{max-width:450px;padding:30px}.auth-mobile-logo{display:inline-flex}}
        @media(max-width:420px){.auth-card{padding:24px 19px}.auth-heading h2{font-size:26px}.auth-options{font-size:10px}}
    </style>
</head>
<body>
<div class="auth-page">

    <section class="auth-visual">
        <div class="auth-visual-content">
            <div class="visual-icon"><i class="fa-solid fa-heart-pulse"></i></div>
            <h1>Better Healthcare,<br>Closer to Home.</h1>
            <p>Access your healthcare journey from one place. Connect with doctors, manage medical reports, and view prescriptions online.</p>
            <div class="auth-benefits">
                <div class="auth-benefit"><i class="fa-solid fa-circle-check"></i> Convenient online consultations</div>
                <div class="auth-benefit"><i class="fa-solid fa-circle-check"></i> Organized medical records</div>
                <div class="auth-benefit"><i class="fa-solid fa-circle-check"></i> Accessible healthcare services</div>
            </div>
        </div>
    </section>

    <main class="auth-form-side">
        <div class="auth-card">

            <a href="index.php" class="logo auth-mobile-logo">
                <i class="fa-solid fa-heart-pulse"></i>
                <span>Medi<strong>Connect</strong><small>Your Health, Our Priority</small></span>
            </a>

            <div class="auth-heading">
                <span class="eyebrow">WELCOME BACK</span>
                <h2 id="loginSubtitle">Patient Login</h2>
                <p>Enter your account details to continue.</p>
            </div>

            <?php if ($error !== ""): ?>
                <div class="auth-message" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" id="loginForm" method="POST" action="login.php">
                <input type="hidden" name="redirect"
       value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="userRole">Login As</label>
                    <select class="role-select" id="userRole" name="userRole" required>
                        <option value="patient" <?= (($_POST["userRole"] ?? "patient") === "patient") ? "selected" : "" ?>>Patient</option>
                        <option value="doctor" <?= (($_POST["userRole"] ?? "") === "doctor") ? "selected" : "" ?>>Doctor</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="auth-input-wrap">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" id="email" name="email"
                               placeholder="you@example.com" autocomplete="email"
                               value="<?= htmlspecialchars($_POST["email"] ?? "", ENT_QUOTES, "UTF-8") ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="auth-input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" autocomplete="current-password" required>
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="auth-options">
                    <label class="remember-option">
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>
                    <a href="forgot-password.html">Forgot Password?</a>
                </div>

                <button type="submit" class="auth-submit">
                    Login to Account <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="auth-switch">
                Don't have an account?
                <a href="register.html">Create Account</a>
            </div>

            <div class="auth-security">
                <i class="fa-solid fa-shield-halved"></i>
                Protecting your privacy matters to us.
            </div>
        </div>
    </main>
</div>

<script>
"use strict";

const passwordInput = document.getElementById("password");
const togglePassword = document.getElementById("togglePassword");
const roleSelect = document.getElementById("userRole");
const loginSubtitle = document.getElementById("loginSubtitle");

function updateLoginSubtitle() {
    loginSubtitle.textContent =
        roleSelect.value === "doctor" ? "Doctor Login" : "Patient Login";
}

roleSelect.addEventListener("change", updateLoginSubtitle);
updateLoginSubtitle();

togglePassword.addEventListener("click", function () {
    const show = passwordInput.type === "password";
    passwordInput.type = show ? "text" : "password";
    togglePassword.innerHTML = show
        ? '<i class="fa-regular fa-eye-slash"></i>'
        : '<i class="fa-regular fa-eye"></i>';
    togglePassword.setAttribute("aria-label", show ? "Hide password" : "Show password");
});
</script>
</body>
</html>