<?php
session_start();
require_once __DIR__ . "/config.php";

if (
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "patient" ||
    !isset($_SESSION["patient_db_id"])
) {
    header("Location: login.php");
    exit;
}

$message = "";
$messageType = "error";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (
        $currentPassword === "" ||
        $newPassword === "" ||
        $confirmPassword === ""
    ) {
        $message = "Please fill in all password fields.";
    } elseif (strlen($newPassword) < 8) {
        $message = "New password must be at least 8 characters long.";
    } elseif (strlen($newPassword) > 72) {
        $message = "New password must not exceed 72 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New password and confirmation do not match.";
    } elseif ($currentPassword === $newPassword) {
        $message = "Your new password must differ from your current password.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT password FROM patients WHERE id = :id LIMIT 1"
            );
            $stmt->execute([
                "id" => (int) $_SESSION["patient_db_id"]
            ]);
            $patient = $stmt->fetch();

            if (!$patient) {
                session_unset();
                session_destroy();
                header("Location: login.php");
                exit;
            }

            if (!password_verify($currentPassword, $patient["password"])) {
                $message = "Your current password is incorrect.";
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

                $update = $pdo->prepare(
                    "UPDATE patients SET password = :password WHERE id = :id"
                );
                $update->execute([
                    "password" => $newHash,
                    "id" => (int) $_SESSION["patient_db_id"]
                ]);

                $message = "Password changed successfully.";
                $messageType = "success";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Unable to change your password right now. Please try again.";
        }
    }
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password | MediConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,sans-serif;background:#f5f8fc;color:#26394e;line-height:1.6}
a{text-decoration:none}
.navbar{min-height:76px;padding:15px 5%;background:#fff;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e8edf2}
.brand{font-size:24px;font-weight:800;color:#153751}
.brand span,.footer span{color:#0ba59e}
.nav-links{display:flex;gap:22px}
.nav-links a{font-size:13px;font-weight:600;color:#607184}
.layout{display:grid;grid-template-columns:245px minmax(0,1fr);min-height:calc(100vh - 136px)}
.sidebar{padding:30px 17px;background:#fff;border-right:1px solid #e8edf2}
.sidebar-label{padding:0 14px;margin-bottom:12px;color:#9aa5b2;font-size:10px;font-weight:800;letter-spacing:1.2px}
.sidebar-nav{display:grid;gap:6px}
.sidebar-nav a{display:flex;align-items:center;gap:13px;padding:12px 14px;color:#64758a;border-radius:8px;font-size:13px;font-weight:600}
.sidebar-nav a i{width:18px}
.sidebar-nav a:hover,.sidebar-nav a.active{background:#eaf8f6;color:#078f91}
.main{width:100%;max-width:1000px;margin:0 auto;padding:38px}
.heading{margin-bottom:25px}
.heading h1{font-size:27px;color:#183751}
.heading p{font-size:13px;color:#8491a0;margin-top:5px}
.panel{background:#fff;border:1px solid #e7edf2;border-radius:12px;padding:28px;max-width:680px}
.panel-title{display:flex;align-items:center;gap:13px;margin-bottom:24px}
.icon{width:44px;height:44px;display:flex;align-items:center;justify-content:center;background:#eaf8f6;color:#078f91;border-radius:10px;font-size:18px}
.panel-title h2{font-size:17px;color:#193750}
.panel-title p{font-size:11px;color:#8491a0;margin-top:3px}
.notice{padding:13px;border-radius:8px;font-size:12px;margin-bottom:18px}
.notice.error{background:#fff0f0;color:#b42318}
.notice.success{background:#e7f7ed;color:#278650}
.field{margin-bottom:19px}
.field label{display:block;font-size:12px;font-weight:700;color:#42596d;margin-bottom:7px}
.field input{width:100%;padding:13px;border:1px solid #dfe7ed;border-radius:8px;font-size:13px;outline:none}
.field input:focus{border-color:#079e99;box-shadow:0 0 0 3px rgba(7,158,153,.08)}
.hint{font-size:11px;color:#8491a0;margin-top:6px}
button{display:inline-flex;justify-content:center;align-items:center;gap:9px;border:0;border-radius:8px;padding:13px 18px;background:#079e99;color:#fff;font-weight:700;font-size:12px;cursor:pointer}
button:hover{background:#087d79}
.back{display:inline-block;margin-top:20px;color:#078f91;font-size:12px;font-weight:700}
.footer{padding:20px;background:#102c43;color:#d6e1e9;text-align:center;font-size:11px}
@media(max-width:800px){.layout{grid-template-columns:1fr}.sidebar{padding:14px;border-right:none;border-bottom:1px solid #e8edf2;overflow-x:auto}.sidebar-label{display:none}.sidebar-nav{display:flex;width:max-content}.sidebar-nav a{white-space:nowrap}.main{padding:25px 17px}.nav-links{display:none}}
@media(max-width:520px){.navbar{padding:15px 17px}.brand{font-size:21px}.panel{padding:19px}.heading h1{font-size:23px}button{width:100%}}
</style>
</head>
<body>
<header class="navbar">
    <a href="index.php" class="brand">Medi<span>Connect</span></a>
    <nav class="nav-links">
        <a href="index.php">Home</a>
        <a href="doctors.html">Find Doctors</a>
    </nav>
</header>

<div class="layout">
<aside class="sidebar">
    <p class="sidebar-label">PATIENT MENU</p>
    <nav class="sidebar-nav">
        <a href="patient-dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
        <a href="appointment.html"><i class="fa-solid fa-calendar-check"></i>Book Appointment</a>
        <a href="doctors.html"><i class="fa-solid fa-user-doctor"></i>Find Doctors</a>
        <a href="medical-reports.html"><i class="fa-solid fa-file-medical"></i>Medical Reports</a>
        <a href="consultation-history.html"><i class="fa-solid fa-clock-rotate-left"></i>Consultation History</a>
        <a href="patient-profile.php"><i class="fa-solid fa-user-gear"></i>My Profile</a>
        <a href="change-password.php" class="active"><i class="fa-solid fa-lock"></i>Change Password</a>
        <a href="patient-dashboard.php?logout=1"><i class="fa-solid fa-right-from-bracket"></i>Sign Out</a>
    </nav>
</aside>

<main class="main">
    <section class="heading">
        <h1>Change Password</h1>
        <p>Keep your MediConnect account secure with a strong password.</p>
    </section>

    <section class="panel">
        <div class="panel-title">
            <div class="icon"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
                <h2>Update Your Password</h2>
                <p>Verify your current password before choosing a new one.</p>
            </div>
        </div>

        <?php if ($message !== ""): ?>
            <div class="notice <?= h($messageType) ?>" role="status">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="change-password.php">
            <div class="field">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            </div>

            <div class="field">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required>
                <p class="hint">Use at least 8 characters. Avoid reusing an old password.</p>
            </div>

            <div class="field">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>

            <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Update Password</button>
        </form>

        <a class="back" href="patient-profile.php">← Back to My Profile</a>
    </section>
</main>
</div>

<footer class="footer">&copy; 2026 Medi<span>Connect</span>. All rights reserved.</footer>
</body>
</html>