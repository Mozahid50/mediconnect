<?php
session_start();
require_once __DIR__ . "/config.php";

// Only logged-in patients can access this page.
if (
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "patient" ||
    !isset($_SESSION["patient_db_id"])
) {
    header("Location: login.php");
    exit;
}

$patientDbId = (int) $_SESSION["patient_db_id"];
$message = "";
$messageType = "success";

try {
    // Load the logged-in patient's record.
    $stmt = $pdo->prepare(
        "SELECT id, patient_id, name, email, phone, dob, gender,
                blood_group, address, emergency_contact
         FROM patients
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute(["id" => $patientDbId]);
    $patient = $stmt->fetch();

    if (!$patient) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $name = trim($_POST["fullName"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $dob = trim($_POST["birthDate"] ?? "");
        $gender = trim($_POST["gender"] ?? "");
        $bloodGroup = trim($_POST["bloodGroup"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $emergencyContact = trim($_POST["emergencyContact"] ?? "");

        $allowedGenders = [
            "Male", "Female", "Other", "Prefer not to say"
        ];
        $allowedBloodGroups = [
            "A+", "A-", "B+", "B-", "AB+", "AB-",
            "O+", "O-", "Unknown"
        ];

        if ($name === "" || mb_strlen($name) > 100) {
            $message = "Please enter a valid name (maximum 100 characters).";
            $messageType = "error";
        } elseif (!preg_match('/^01[3-9][0-9]{8}$/', $phone)) {
            $message = "Enter a valid 11-digit Bangladesh mobile number.";
            $messageType = "error";
        } elseif (
            $dob === "" ||
            !DateTime::createFromFormat("!Y-m-d", $dob) ||
            DateTime::createFromFormat("!Y-m-d", $dob)->format("Y-m-d") !== $dob ||
            $dob > date("Y-m-d")
        ) {
            $message = "Please enter a valid date of birth.";
            $messageType = "error";
        } elseif (!in_array($gender, $allowedGenders, true)) {
            $message = "Please select a valid gender.";
            $messageType = "error";
        } elseif (
            $bloodGroup !== "" &&
            !in_array($bloodGroup, $allowedBloodGroups, true)
        ) {
            $message = "Please select a valid blood group.";
            $messageType = "error";
        } elseif (
            mb_strlen($address) > 2000 ||
            strlen($emergencyContact) > 20 ||
            ($emergencyContact !== "" &&
             !preg_match('/^[0-9+\-\s()]{7,20}$/', $emergencyContact))
        ) {
            $message = "Please check your address or emergency contact.";
            $messageType = "error";
        } else {
            // Keep the phone number unique across patient accounts.
            $check = $pdo->prepare(
                "SELECT id FROM patients
                 WHERE phone = :phone AND id <> :id LIMIT 1"
            );
            $check->execute([
                "phone" => $phone,
                "id" => $patientDbId
            ]);

            if ($check->fetch()) {
                $message = "This phone number is already used by another account.";
                $messageType = "error";
            } else {
                $update = $pdo->prepare(
                    "UPDATE patients
                     SET name = :name,
                         phone = :phone,
                         dob = :dob,
                         gender = :gender,
                         blood_group = :blood_group,
                         address = :address,
                         emergency_contact = :emergency_contact
                     WHERE id = :id"
                );

                $update->execute([
                    "name" => $name,
                    "phone" => $phone,
                    "dob" => $dob,
                    "gender" => $gender,
                    "blood_group" => $bloodGroup !== "" ? $bloodGroup : null,
                    "address" => $address !== "" ? $address : null,
                    "emergency_contact" =>
                        $emergencyContact !== "" ? $emergencyContact : null,
                    "id" => $patientDbId
                ]);

                $message = "Your profile has been updated successfully.";
                $messageType = "success";

                // Reload saved data from the database.
                $stmt->execute(["id" => $patientDbId]);
                $patient = $stmt->fetch();

                $_SESSION["patient_name"] = $patient["name"];
                $_SESSION["patient_id"] = $patient["patient_id"];
                $_SESSION["patient_email"] = $patient["email"];
            }
        }

        // Preserve submitted values if validation fails.
        if ($messageType === "error") {
            $patient["name"] = $name;
            $patient["phone"] = $phone;
            $patient["dob"] = $dob;
            $patient["gender"] = $gender;
            $patient["blood_group"] = $bloodGroup;
            $patient["address"] = $address;
            $patient["emergency_contact"] = $emergencyContact;
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Unable to load or save your profile right now.");
}

function h($value): string {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | MediConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,sans-serif;background:#f5f8fc;color:#26394e;line-height:1.6}
a{text-decoration:none}
button,input,select,textarea{font-family:inherit}
.navbar{min-height:76px;padding:15px 5%;background:#fff;display:flex;justify-content:space-between;align-items:center;gap:20px;border-bottom:1px solid #e8edf2}
.brand{font-size:24px;font-weight:800;color:#153751;white-space:nowrap}
.brand span,.footer span{color:#0ba59e}
.nav-right{display:flex;align-items:center;gap:22px}
.nav-right>a{color:#607184;font-size:13px;font-weight:600}
.profile-chip{display:flex;align-items:center;gap:10px;padding-left:20px;border-left:1px solid #e5ebf0}
.avatar{width:38px;height:38px;border-radius:50%;background:#e2f5f3;color:#078f91;display:flex;align-items:center;justify-content:center}
.profile-chip strong{display:block;font-size:12px}
.profile-chip small{display:block;color:#8a97a6;font-size:10px}
.layout{display:grid;grid-template-columns:245px minmax(0,1fr);min-height:calc(100vh - 76px)}
.sidebar{padding:30px 17px;background:#fff;border-right:1px solid #e8edf2}
.sidebar-label{padding:0 14px;margin-bottom:12px;color:#9aa5b2;font-size:10px;font-weight:800;letter-spacing:1.2px}
.sidebar-nav{display:grid;gap:6px}
.sidebar-nav a{display:flex;align-items:center;gap:13px;padding:12px 14px;color:#64758a;border-radius:8px;font-size:13px;font-weight:600}
.sidebar-nav a i{width:18px}
.sidebar-nav a:hover,.sidebar-nav a.active{background:#eaf8f6;color:#078f91}
.sidebar-help{margin-top:40px;padding:17px;background:#f0f9f8;border-radius:12px}
.sidebar-help i{color:#078f91;font-size:20px;margin-bottom:10px}
.sidebar-help h4{font-size:13px;margin-bottom:6px}
.sidebar-help p{color:#718395;font-size:11px;margin-bottom:12px}
.sidebar-help a{color:#078f91;font-size:11px;font-weight:800}
.main-content{width:100%;max-width:1500px;min-width:0;margin:0 auto;padding:35px}
.page-heading{margin-bottom:27px}
.page-heading h1{color:#183751;font-size:27px;margin-bottom:5px}
.page-heading p{color:#8491a0;font-size:13px}
.profile-layout{display:grid;grid-template-columns:minmax(230px,.75fr) minmax(0,1.7fr);align-items:start;gap:22px}
.left-column,.right-column{display:grid;gap:22px;min-width:0}
.panel{padding:24px;background:#fff;border:1px solid #e7edf2;border-radius:12px;min-width:0}
.profile-summary{text-align:center}
.large-avatar{display:flex;align-items:center;justify-content:center;width:100px;height:100px;margin:0 auto 15px;border:5px solid #e2f5f3;border-radius:50%;background:#d9f1ee;color:#078f91;font-size:38px}
.profile-summary h2{color:#193750;font-size:19px;overflow-wrap:anywhere}
.profile-summary p{color:#8491a0;font-size:12px;margin-top:4px}
.account-badge{display:inline-block;margin-top:12px;padding:6px 11px;border-radius:20px;background:#e7f7ed;color:#278650;font-size:10px;font-weight:800}
.summary-divider{height:1px;background:#edf1f4;margin:23px 0}
.summary-item{display:flex;align-items:center;gap:12px;text-align:left;margin-bottom:19px}
.summary-item:last-child{margin-bottom:0}
.summary-icon,.section-icon{width:39px;height:39px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:#eaf8f6;color:#078f91;border-radius:9px}
.summary-item small{display:block;color:#8a98a7;font-size:10px}
.summary-item strong{display:block;color:#344b60;font-size:11px;overflow-wrap:anywhere}
.panel-heading{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:22px}
.panel-heading h2{color:#193750;font-size:17px}
.panel-heading p{color:#8491a0;font-size:11px;margin-top:4px}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:19px}
.form-group{min-width:0}
.form-group.full-width{grid-column:1/-1}
.form-group label{display:block;color:#42596d;font-size:11px;font-weight:700;margin-bottom:7px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px;border:1px solid #dfe7ed;border-radius:8px;outline:none;background:#fff;color:#344b60;font-size:12px}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#079e99;box-shadow:0 0 0 3px rgba(7,158,153,.08)}
.form-group textarea{min-height:85px;resize:vertical}
.field-note{display:block;color:#8a98a7;font-size:10px;margin-top:6px}
.form-actions{display:flex;justify-content:flex-end;flex-wrap:wrap;gap:10px;margin-top:25px;padding-top:20px;border-top:1px solid #edf1f4}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 16px;border:1px solid #dce7ec;border-radius:8px;background:#fff;color:#536b7d;font-size:11px;font-weight:700;cursor:pointer}
.btn-primary{background:#079e99;color:#fff;border-color:#079e99}
.message{margin-bottom:20px;padding:12px;border-radius:8px;font-size:12px}
.message.success{background:#e7f7ed;color:#278650}
.message.error{background:#fff0f0;color:#b42318}
.security-item{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:15px 0;border-bottom:1px solid #edf1f4}
.security-info{display:flex;align-items:center;gap:12px}
.security-info h3{color:#30475c;font-size:12px;margin-bottom:3px}
.security-info p{color:#8491a0;font-size:10px}
.security-item a{color:#078f91;font-size:11px;font-weight:700;white-space:nowrap}
.privacy-note{display:flex;align-items:flex-start;gap:13px;padding:17px;border:1px solid #dcefe9;border-radius:10px;background:#f0f9f6}
.privacy-note i{color:#078f91;font-size:19px;margin-top:2px}
.privacy-note h3{color:#234459;font-size:12px;margin-bottom:5px}
.privacy-note p{color:#718395;font-size:11px}
.footer{padding:20px;background:#102c43;color:#d6e1e9;text-align:center;font-size:11px}
@media(max-width:1100px){.layout{grid-template-columns:220px minmax(0,1fr)}.profile-layout{grid-template-columns:1fr}.profile-summary{text-align:left}.large-avatar{margin-left:0}}
@media(max-width:800px){.layout{grid-template-columns:1fr}.sidebar{padding:15px;border-right:none;border-bottom:1px solid #e8edf2;overflow-x:auto}.sidebar-label,.sidebar-help{display:none}.sidebar-nav{display:flex;width:max-content;gap:7px}.sidebar-nav a{white-space:nowrap;padding:10px 12px}.main-content{padding:24px 18px}.nav-right>a{display:none}}
@media(max-width:520px){.navbar{padding:14px 17px}.brand{font-size:21px}.profile-chip{padding-left:12px}.profile-chip>div:last-child{display:none}.page-heading h1{font-size:23px}.panel{padding:17px}.form-grid{grid-template-columns:1fr;gap:15px}.form-group.full-width{grid-column:auto}.form-actions{flex-direction:column-reverse}.form-actions .btn{width:100%}}
</style>
</head>
<body>
<header class="navbar">
    <a href="index.php" class="brand">Medi<span>Connect</span></a>
    <div class="nav-right">
        <a href="index.php">Home</a>
        <a href="doctors.html">Find Doctors</a>
        <div class="profile-chip">
            <div class="avatar"><i class="fa-solid fa-user"></i></div>
            <div>
                <strong><?= h($patient["name"]) ?></strong>
                <small>Patient Account</small>
            </div>
        </div>
    </div>
</header>

<div class="layout">
<aside class="sidebar">
    <p class="sidebar-label">PATIENT MENU</p>
    <nav class="sidebar-nav">
        <a href="patient-dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
        <a href="appointment.html"><i class="fa-solid fa-calendar-check"></i>Book Appointment</a>
        <a href="doctors.html"><i class="fa-solid fa-user-doctor"></i>Find Doctors</a>
        <a href="medical-reports.php"><i class="fa-solid fa-file-medical"></i>Medical Reports</a>
        <a href="consultation-history.php"><i class="fa-solid fa-clock-rotate-left"></i>Consultation History</a>
        <a href="patient-profile.php" class="active"><i class="fa-solid fa-user-gear"></i>My Profile</a>
        <a href="patient-dashboard.php?logout=1"><i class="fa-solid fa-right-from-bracket"></i>Sign Out</a>
    </nav>
    <div class="sidebar-help">
        <i class="fa-solid fa-headset"></i>
        <h4>Need Assistance?</h4>
        <p>Need help with your patient account?</p>
        <a href="help-support.html">CONTACT SUPPORT →</a>
    </div>
</aside>

<main class="main-content">
<section class="page-heading">
    <h1>My Profile</h1>
    <p>Manage your personal information and account preferences.</p>
</section>

<?php if ($message !== ""): ?>
<div class="message <?= h($messageType) ?>" role="status"><?= h($message) ?></div>
<?php endif; ?>

<div class="profile-layout">
<div class="left-column">
<section class="panel profile-summary">
    <div class="large-avatar"><i class="fa-solid fa-user"></i></div>
    <h2><?= h($patient["name"]) ?></h2>
    <p>Patient ID: <?= h($patient["patient_id"] ?: "Not assigned") ?></p>
    <span class="account-badge"><i class="fa-solid fa-circle-check"></i> Registered Account</span>
    <div class="summary-divider"></div>

    <div class="summary-item">
        <div class="summary-icon"><i class="fa-solid fa-envelope"></i></div>
        <div><small>Email Address</small><strong><?= h($patient["email"]) ?></strong></div>
    </div>
    <div class="summary-item">
        <div class="summary-icon"><i class="fa-solid fa-phone"></i></div>
        <div><small>Phone Number</small><strong><?= h($patient["phone"]) ?></strong></div>
    </div>
    <div class="summary-item">
        <div class="summary-icon"><i class="fa-solid fa-location-dot"></i></div>
        <div><small>Address</small><strong><?= h($patient["address"] ?: "Not provided") ?></strong></div>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <div><h2>Account Security</h2><p>Manage your account access.</p></div>
        <div class="section-icon"><i class="fa-solid fa-shield-halved"></i></div>
    </div>
    <div class="security-item">
        <div class="security-info">
            <div class="summary-icon"><i class="fa-solid fa-lock"></i></div>
            <div><h3>Password</h3><p>Protect your account.</p></div>
        </div>
        <a href="change-password.html">Change</a>
    </div>
    <div class="security-item">
        <div class="security-info">
            <div class="summary-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
            <div><h3>Sign Out</h3><p>Leave your current account.</p></div>
        </div>
        <a href="patient-dashboard.php?logout=1">Sign Out</a>
    </div>
</section>
</div>

<div class="right-column">
<section class="panel">
    <div class="panel-heading">
        <div><h2>Personal Information</h2><p>Update your personal details below.</p></div>
        <div class="section-icon"><i class="fa-solid fa-user-pen"></i></div>
    </div>

    <form method="POST" action="patient-profile.php">
        <div class="form-grid">
            <div class="form-group">
                <label for="full-name">Full Name *</label>
                <input type="text" id="full-name" name="fullName" maxlength="100" value="<?= h($patient["name"]) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" value="<?= h($patient["email"]) ?>" disabled>
                <small class="field-note">Email address cannot be changed here.</small>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number *</label>
                <input type="tel" id="phone" name="phone" pattern="01[3-9][0-9]{8}" maxlength="11" value="<?= h($patient["phone"]) ?>" required>
            </div>
            <div class="form-group">
                <label for="birth-date">Date of Birth *</label>
                <input type="date" id="birth-date" name="birthDate" max="<?= date("Y-m-d") ?>" value="<?= h($patient["dob"]) ?>" required>
            </div>
            <div class="form-group">
                <label for="gender">Gender *</label>
                <select id="gender" name="gender" required>
                    <option value="">Select gender</option>
                    <?php foreach (["Female","Male","Other","Prefer not to say"] as $g): ?>
                    <option value="<?= h($g) ?>" <?= $patient["gender"] === $g ? "selected" : "" ?>><?= h($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="blood-group">Blood Group</label>
                <select id="blood-group" name="bloodGroup">
                    <option value="">Select blood group</option>
                    <?php foreach (["A+","A-","B+","B-","AB+","AB-","O+","O-","Unknown"] as $bg): ?>
                    <option value="<?= h($bg) ?>" <?= $patient["blood_group"] === $bg ? "selected" : "" ?>><?= h($bg) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full-width">
                <label for="address">Address</label>
                <textarea id="address" name="address" maxlength="2000" placeholder="Enter your present address"><?= h($patient["address"]) ?></textarea>
            </div>
            <div class="form-group full-width">
                <label for="emergency-contact">Emergency Contact</label>
                <input type="tel" id="emergency-contact" name="emergencyContact" maxlength="20" value="<?= h($patient["emergency_contact"]) ?>" placeholder="Emergency contact number">
            </div>
        </div>

        <div class="form-actions">
            <button type="reset" class="btn"><i class="fa-solid fa-rotate-left"></i> Reset</button>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
        </div>
    </form>
</section>

<div class="privacy-note">
    <i class="fa-solid fa-shield-halved"></i>
    <div>
        <h3>Protect Your Personal Information</h3>
        <p>Your profile is connected to your account and saved in the database. Only share your account credentials with trusted people.</p>
    </div>
</div>
</div>
</div>
</main>
</div>

<footer class="footer">&copy; 2026 Medi<span>Connect</span>. All rights reserved.</footer>
</body>
</html>