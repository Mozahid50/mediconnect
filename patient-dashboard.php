<?php
session_start();
require_once __DIR__ . "/config.php";

/* Logout */
if (isset($_GET["logout"])) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
    header("Location: login.php");
    exit;
}

/* Protect patient dashboard */
if (
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "patient" ||
    !isset($_SESSION["patient_db_id"])
) {
    header("Location: login.php");
    exit;
}

$patientDbId = (int) $_SESSION["patient_db_id"];

$patient = [];
$patientAppointments = [];
$recentReports = [];
$recentConsultations = [];

$appointmentCounts = [
    "upcoming" => 0,
    "pending" => 0
];

$reportCount = 0;
$consultationCount = 0;
$pageErrors = [];

try {
    /* Load logged-in patient's profile */
    $patientStmt = $pdo->prepare("
        SELECT id, patient_id, name, email, phone, blood_group
        FROM patients
        WHERE id = ?
        LIMIT 1
    ");
    $patientStmt->execute([$patientDbId]);
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $_SESSION = [];
        session_destroy();
        header("Location: login.php");
        exit;
    }

    $_SESSION["patient_id"] = $patient["patient_id"];
    $_SESSION["patient_name"] = $patient["name"];
    $_SESSION["patient_email"] = $patient["email"];

    /* Load appointments belonging to this patient */
    try {
        $appointmentStmt = $pdo->prepare("
    SELECT
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.reason,
        a.doctor_id,
        d.name AS doctor_name,
        d.specialty AS doctor_specialty
    FROM appointments a
    INNER JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id = ?
    ORDER BY
        CASE
            WHEN a.appointment_date >= CURDATE()
             AND a.status IN ('Pending', 'Confirmed')
            THEN 0
            ELSE 1
        END,
        a.appointment_date ASC,
        a.appointment_time ASC
    LIMIT 5
");
        $appointmentStmt->execute([$patientDbId]);
        $patientAppointments = $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $pdo->prepare("
            SELECT
                SUM(
                    CASE
                        WHEN appointment_date >= CURDATE()
                         AND status IN ('Pending', 'Confirmed')
                        THEN 1 ELSE 0
                    END
                ) AS upcoming,
                SUM(
                    CASE
                        WHEN status = 'Pending'
                        THEN 1 ELSE 0
                    END
                ) AS pending
            FROM appointments
            WHERE patient_id = ?
        ");
        $countStmt->execute([$patientDbId]);
        $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

        if ($counts) {
            $appointmentCounts["upcoming"] = (int) ($counts["upcoming"] ?? 0);
            $appointmentCounts["pending"] = (int) ($counts["pending"] ?? 0);
        }
    } catch (PDOException $e) {
        error_log("MediConnect appointments: " . $e->getMessage());
        $pageErrors[] = "Appointments could not be loaded. Please try again later.";
    }

    /* Medical report count and recent reports */
    try {
        $reportCountStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM medical_reports
            WHERE patient_id = ?
        ");
        $reportCountStmt->execute([$patientDbId]);
        $reportCount = (int) $reportCountStmt->fetchColumn();

        $recentReportsStmt = $pdo->prepare("
            SELECT id, report_title, report_type, report_date, doctor_name
            FROM medical_reports
            WHERE patient_id = ?
            ORDER BY report_date DESC, id DESC
            LIMIT 5
        ");
        $recentReportsStmt->execute([$patientDbId]);
        $recentReports = $recentReportsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("MediConnect reports: " . $e->getMessage());
        $pageErrors[] = "Medical reports could not be loaded.";
    }

    /* Consultation count and recent consultations */
    try {
        $consultationCountStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM consultation_history
            WHERE patient_id = ?
        ");
        $consultationCountStmt->execute([$patientDbId]);
        $consultationCount = (int) $consultationCountStmt->fetchColumn();

        $recentConsultationsStmt = $pdo->prepare("
            SELECT id, doctor_name, consultation_date,
                   consultation_type, status, diagnosis
            FROM consultation_history
            WHERE patient_id = ?
            ORDER BY consultation_date DESC, id DESC
            LIMIT 5
        ");
        $recentConsultationsStmt->execute([$patientDbId]);
        $recentConsultations = $recentConsultationsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("MediConnect consultations: " . $e->getMessage());
        $pageErrors[] = "Consultation history could not be loaded.";
    }

} catch (PDOException $e) {
    error_log("MediConnect dashboard: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to load your dashboard right now. Please check the database connection.");
}

$patientName = $patient["name"] ?? "Patient";
$patientID = $patient["patient_id"] ?? "";
$patientEmail = $patient["email"] ?? "";
$patientPhone = $patient["phone"] ?? "";
$patientBloodGroup = $patient["blood_group"] ?? "";
$initial = strtoupper(substr(trim($patientName), 0, 1));

function h($value): string {
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

function statusClass($status): string {
    $status = strtolower(trim((string) $status));

    if (in_array($status, ["confirmed", "completed"], true)) {
        return "status-success";
    }

    if (in_array($status, ["rejected", "cancelled"], true)) {
        return "status-danger";
    }

    return "status-pending";
}

function displayDate($date): string {
    if (!$date) {
        return "—";
    }

    $timestamp = strtotime($date);
    return $timestamp ? date("d M Y", $timestamp) : h($date);
}

function displayTime($time): string {
    if (!$time) {
        return "";
    }

    $timestamp = strtotime($time);
    return $timestamp ? date("h:i A", $timestamp) : h($time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard | MediConnect</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --primary: #087f8c;
            --primary-dark: #066570;
            --primary-light: #e8f7f8;
            --background: #f4f8fb;
            --white: #ffffff;
            --text: #172b4d;
            --muted: #738198;
            --border: #e6edf3;
            --shadow: 0 5px 22px rgba(26, 54, 93, .055);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background: var(--background);
        }

        a { color: inherit; text-decoration: none; }
        button, input { font: inherit; }

        .layout { min-height: 100vh; }

        .sidebar {
            width: 255px;
            position: fixed;
            inset: 0 auto 0 0;
            background: var(--white);
            border-right: 1px solid var(--border);
            padding: 25px 17px;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 0 9px 30px;
            font-size: 21px;
            font-weight: 800;
            color: var(--primary);
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            color: white;
            background: var(--primary);
            font-size: 21px;
        }

        .nav-label {
            padding: 8px 12px;
            color: #9aa7b7;
            font-size: 10px;
            letter-spacing: 1.4px;
            font-weight: 800;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav-link {
            min-height: 45px;
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 0 13px;
            border-radius: 10px;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
        }

        .nav-link i {
            width: 19px;
            text-align: center;
            font-size: 15px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--primary-light);
            color: var(--primary);
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 25px;
        }

        .help-card {
            background: #f0f9fa;
            padding: 16px;
            border-radius: 13px;
            margin: 10px 3px 15px;
        }

        .help-card strong { font-size: 12px; }
        .help-card p {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
            margin: 8px 0 12px;
        }

        .help-card a {
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
        }

        .logout-link { color: #dc5260; }

        .main {
            margin-left: 255px;
            min-height: 100vh;
            padding: 0 32px 35px;
        }

        .topbar {
            height: 78px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            margin-bottom: 29px;
        }

        .breadcrumb {
            color: var(--muted);
            font-size: 12px;
        }

        .breadcrumb strong { color: var(--text); }

        .top-right {
            display: flex;
            align-items: center;
            gap: 17px;
        }

        .icon-button {
            width: 39px;
            height: 39px;
            display: grid;
            place-items: center;
            background: white;
            border: 1px solid var(--border);
            border-radius: 11px;
            color: #607086;
        }

        .user-mini {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            font-weight: 800;
        }

        .avatar.small { width: 40px; height: 40px; }
        .avatar.large { width: 58px; height: 58px; font-size: 23px; }

        .user-mini strong { display: block; font-size: 12px; }
        .user-mini span { display: block; font-size: 10px; color: var(--muted); margin-top: 4px; }

        .welcome {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .welcome h1 {
            margin: 0 0 8px;
            font-size: 25px;
            letter-spacing: -.8px;
        }

        .welcome p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .primary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            border: 0;
            border-radius: 10px;
            padding: 12px 16px;
            background: var(--primary);
            color: white;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
            white-space: nowrap;
        }

        .primary-btn:hover { background: var(--primary-dark); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            padding: 21px 19px;
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            min-width: 0;
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .stat-label { color: var(--muted); font-size: 11px; font-weight: 600; }

        .stat-icon {
            width: 37px;
            height: 37px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            font-size: 15px;
        }

        .teal { background: #e3f7f6; color: #078b89; }
        .blue { background: #eaf1ff; color: #4777e7; }
        .purple { background: #f1eaff; color: #9061dc; }
        .orange { background: #fff1e3; color: #e28a35; }

        .stat-number {
            margin: 15px 0 7px;
            font-size: 29px;
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .stat-note { color: var(--muted); font-size: 10px; }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(280px, .9fr);
            gap: 22px;
            align-items: start;
        }

        .column { display: flex; flex-direction: column; gap: 22px; }

        .panel {
            min-width: 0;
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 21px 22px;
            border-bottom: 1px solid #edf1f5;
        }

        .panel-header h2 { margin: 0; font-size: 14px; font-weight: 800; }

        .text-link {
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .panel-body { padding: 21px 22px; }

        .table-wrap { width: 100%; overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            color: #8290a4;
            background: #f8fafc;
            font-size: 10px;
            font-weight: 700;
            padding: 13px 12px;
            white-space: nowrap;
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid #edf1f5;
            font-size: 11px;
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: 0; }
        td strong { font-size: 11px; font-weight: 700; }
        td small { display: inline-block; color: var(--muted); margin-top: 5px; font-size: 10px; }

        .status-label {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pending { background: #fff4db; color: #a76b00; }
        .status-success { background: #e1f8eb; color: #18834d; }
        .status-danger { background: #ffe8e8; color: #c43e4b; }

        .empty-state {
            text-align: center;
            padding: 29px 15px;
        }

        .empty-state > i {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: #edf8f9;
            color: var(--primary);
            display: grid;
            place-items: center;
            margin: 0 auto 14px;
            font-size: 21px;
        }

        .empty-state strong { display: block; font-size: 13px; }
        .empty-state p {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
            margin: 8px 0 14px;
        }

        .report-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 0;
            border-bottom: 1px solid #edf1f5;
        }

        .report-item:first-child { padding-top: 0; }
        .report-item:last-child { border-bottom: 0; padding-bottom: 0; }

        .report-icon {
            width: 39px;
            height: 42px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: #fff0f0;
            color: #e25a67;
        }

        .report-info { min-width: 0; flex: 1; }
        .report-info strong { display: block; font-size: 11px; overflow-wrap: anywhere; }
        .report-info span { display: block; color: var(--muted); font-size: 10px; margin-top: 5px; }
        .report-info a { color: var(--primary); font-size: 10px; font-weight: 700; }

        .consult-item {
            padding: 13px 0;
            border-bottom: 1px solid #edf1f5;
        }

        .consult-item:first-child { padding-top: 0; }
        .consult-item:last-child { border-bottom: 0; padding-bottom: 0; }

        .consult-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .consult-top strong { font-size: 11px; }
        .consult-item p { color: var(--muted); font-size: 10px; line-height: 1.7; margin: 7px 0 0; }

        .profile-card { padding: 22px; }

        .profile-heading {
            display: flex;
            align-items: center;
            gap: 13px;
            padding-bottom: 20px;
            border-bottom: 1px solid #edf1f5;
        }

        .profile-heading h3 { font-size: 14px; margin: 0 0 6px; }
        .profile-heading p { font-size: 10px; color: var(--muted); margin: 0; overflow-wrap: anywhere; }

        .profile-details { padding-top: 8px; }

        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f3f6;
            font-size: 11px;
        }

        .detail-row:last-child { border-bottom: 0; }
        .detail-row span { color: var(--muted); }
        .detail-row strong { text-align: right; overflow-wrap: anywhere; }

        .profile-button {
            display: flex;
            justify-content: center;
            width: 100%;
            margin-top: 15px;
            border: 1px solid #b9e3e5;
            color: var(--primary);
            background: #f4fbfb;
            padding: 12px;
            border-radius: 9px;
            font-size: 11px;
            font-weight: 700;
        }

        .notice {
            padding: 12px 15px;
            border-radius: 10px;
            background: #fff7e6;
            color: #875d11;
            font-size: 11px;
            line-height: 1.7;
            margin-bottom: 18px;
        }

        .footer {
            text-align: center;
            color: #98a4b4;
            font-size: 10px;
            padding: 30px 0 5px;
        }

        .mobile-menu {
            display: none;
            border: 0;
            background: transparent;
            color: var(--text);
            font-size: 20px;
            padding: 8px;
            cursor: pointer;
        }

        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .content-grid { grid-template-columns: minmax(0, 1fr); }
        }

        @media (max-width: 760px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
                box-shadow: none;
            }

            .sidebar.open {
                transform: translateX(0);
                box-shadow: 10px 0 30px rgba(0,0,0,.12);
            }

            .main { margin-left: 0; padding: 0 16px 25px; }
            .topbar { height: 68px; margin-bottom: 22px; }
            .mobile-menu { display: inline-block; }
            .breadcrumb { display: none; }
            .top-right { gap: 10px; }
            .user-mini .user-text { display: none; }
            .welcome { align-items: flex-start; flex-direction: column; }
            .welcome h1 { font-size: 22px; }
            .stats-grid { gap: 12px; }
            .stat-card { padding: 16px 13px; }
            .stat-number { font-size: 25px; }
            .stat-label { font-size: 10px; }
            .panel-header { padding: 17px 15px; }
            .panel-body { padding: 16px 15px; }
            .profile-card { padding: 17px; }
        }

        @media (max-width: 390px) {
            .stats-grid { grid-template-columns: 1fr; }
            .stat-card { padding: 17px; }
        }
    </style>
</head>

<body>
<div class="layout">

    <aside class="sidebar" id="sidebar">
        <a href="patient-dashboard.php" class="brand">
            <span class="brand-icon"><i class="fa-solid fa-heart-pulse"></i></span>
            <span>MediConnect</span>
        </a>

        <div class="nav-label">PATIENT MENU</div>

        <nav class="nav-menu">
            <a href="patient-dashboard.php" class="nav-link active">
                <i class="fa-solid fa-border-all"></i> Dashboard
            </a>
            <a href="doctors.php" class="nav-link">
                <i class="fa-solid fa-user-doctor"></i> Find Doctors
            </a>
            <a href="appointment.php" class="nav-link">
                <i class="fa-regular fa-calendar-check"></i> Appointments
            </a>
            <a href="medical-reports.php" class="nav-link">
                <i class="fa-solid fa-file-medical"></i> Medical Reports
            </a>
            <a href="consultation-history.php" class="nav-link">
                <i class="fa-solid fa-clock-rotate-left"></i> Consultation History
            </a>
        </nav>

        <div class="nav-label" style="margin-top:22px;">ACCOUNT</div>

        <nav class="nav-menu">
            <a href="patient-profile.php" class="nav-link">
                <i class="fa-regular fa-user"></i> My Profile
            </a>
            <a href="change-password.php" class="nav-link">
                <i class="fa-solid fa-lock"></i> Change Password
            </a>
            <a href="help-support.html" class="nav-link">
                <i class="fa-regular fa-circle-question"></i> Help &amp; Support
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="help-card">
                <strong><i class="fa-solid fa-headset"></i> Need assistance?</strong>
                <p>Need help with your appointments or medical reports?</p>
                <a href="help-support.html">Contact Support <i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <a href="patient-dashboard.php?logout=1" class="nav-link logout-link"
               onclick="return confirm('Are you sure you want to log out?');">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
            </a>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button type="button" class="mobile-menu" id="mobileMenu" aria-label="Open menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="breadcrumb">
                    Patient Portal &nbsp; / &nbsp; <strong>Dashboard</strong>
                </div>
            </div>

            <div class="top-right">
                <a href="appointment.php" class="icon-button" aria-label="Appointments">
                    <i class="fa-regular fa-calendar-check"></i>
                </a>

                <a href="patient-profile.php" class="user-mini">
                    <div class="avatar small"><?= h($initial) ?></div>
                    <div class="user-text">
                        <strong><?= h($patientName) ?></strong>
                        <span>Patient</span>
                    </div>
                </a>
            </div>
        </header>

        <section class="welcome">
            <div>
                <h1>Welcome back, <?= h($patientName) ?>! 👋</h1>
                <p>Here is an overview of your appointments and health records.</p>
            </div>

            <a href="doctors.php" class="primary-btn">
                <i class="fa-solid fa-plus"></i> Book an Appointment
            </a>
        </section>

        <?php if (!empty($pageErrors)): ?>
            <div class="notice">
                <strong>Some information could not be loaded.</strong><br>
                <?= h(implode(" ", array_unique($pageErrors))) ?>
                If this continues, check the PHP error log and database tables.
            </div>
        <?php endif; ?>

        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Upcoming Appointments</span>
                    <span class="stat-icon teal"><i class="fa-regular fa-calendar-check"></i></span>
                </div>
                <div class="stat-number"><?= (int) $appointmentCounts["upcoming"] ?></div>
                <div class="stat-note">Pending or approved future appointments</div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Total Consultations</span>
                    <span class="stat-icon blue"><i class="fa-solid fa-user-doctor"></i></span>
                </div>
                <div class="stat-number"><?= (int) $consultationCount ?></div>
                <div class="stat-note">Records in consultation history</div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Medical Reports</span>
                    <span class="stat-icon purple"><i class="fa-solid fa-file-medical"></i></span>
                </div>
                <div class="stat-number"><?= (int) $reportCount ?></div>
                <div class="stat-note">Reports saved to your account</div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Pending Requests</span>
                    <span class="stat-icon orange"><i class="fa-regular fa-hourglass-half"></i></span>
                </div>
                <div class="stat-number"><?= (int) $appointmentCounts["pending"] ?></div>
                <div class="stat-note">Appointments awaiting a decision</div>
            </article>
        </section>

        <section class="content-grid">
            <div class="column">

                <!-- Upcoming appointments -->
                <section class="panel">
                    <div class="panel-header">
                        <h2><i class="fa-regular fa-calendar-check" style="color:var(--primary);margin-right:8px;"></i>Upcoming Appointments</h2>
                        <a href="appointment.php" class="text-link">View All <i class="fa-solid fa-arrow-right"></i></a>
                    </div>

                    <?php if (empty($patientAppointments)): ?>
                        <div class="empty-state">
                            <i class="fa-regular fa-calendar-xmark"></i>
                            <strong>No appointments yet</strong>
                            <p>When you book an appointment, its details and status will appear here.</p>
                            <a href="doctors.php" class="primary-btn">
                                <i class="fa-solid fa-user-doctor"></i> Find a Doctor
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>DOCTOR</th>
                                        <th>DATE &amp; TIME</th>
                                        <th>STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($patientAppointments as $apt): ?>
                                    <tr>
                                        <td>
                                            <strong><?= h($apt["doctor_name"]) ?></strong>
                                            <br>
                                            <small><?= h($apt["doctor_specialty"]) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= h(displayDate($apt["appointment_date"])) ?></strong>
                                            <br>
                                            <small><?= h(displayTime($apt["appointment_time"])) ?></small>
                                        </td>
                                       <td>
    <span class="status-label <?= h(statusClass($apt["status"])) ?>">
        <?= h($apt["status"]) ?>
    </span>

    <?php if (
        strtolower(trim($apt["status"])) === "confirmed"
    ): ?>
        <br>
        <a
            href="video-consultations.php?appointment_id=<?= (int) $apt["id"] ?>"
            class="text-link"
            style="display:inline-block;margin-top:9px;"
        >
            <i class="fa-solid fa-video"></i>
            Join Video Consultation
        </a>
    <?php endif; ?>
</td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Recent medical reports -->
                <section class="panel">
                    <div class="panel-header">
                        <h2><i class="fa-solid fa-file-medical" style="color:var(--primary);margin-right:8px;"></i>Recent Medical Reports</h2>
                        <a href="medical-reports.php" class="text-link">View All <i class="fa-solid fa-arrow-right"></i></a>
                    </div>

                    <div class="panel-body">
                        <?php if (empty($recentReports)): ?>
                            <div class="empty-state">
                                <i class="fa-regular fa-file-lines"></i>
                                <strong>No medical reports yet</strong>
                                <p>Your uploaded medical reports will appear here.</p>
                                <a href="medical-reports.php" class="text-link">Go to Medical Reports</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentReports as $report): ?>
                                <div class="report-item">
                                    <div class="report-icon"><i class="fa-solid fa-file-medical"></i></div>
                                    <div class="report-info">
                                        <strong><?= h($report["report_title"]) ?></strong>
                                        <span>
                                            <?= h($report["report_type"] ?: "Medical Report") ?>
                                            &bull; <?= h(displayDate($report["report_date"])) ?>
                                        </span>
                                        <?php if (!empty($report["doctor_name"])): ?>
                                            <span>Doctor: <?= h($report["doctor_name"]) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Recent consultation history -->
                <section class="panel">
                    <div class="panel-header">
                        <h2><i class="fa-solid fa-clock-rotate-left" style="color:var(--primary);margin-right:8px;"></i>Consultation History</h2>
                        <a href="consultation-history.php" class="text-link">View All <i class="fa-solid fa-arrow-right"></i></a>
                    </div>

                    <div class="panel-body">
                        <?php if (empty($recentConsultations)): ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-stethoscope"></i>
                                <strong>No consultation history yet</strong>
                                <p>Completed consultations will appear here when a history record is saved.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentConsultations as $consultation): ?>
                                <div class="consult-item">
                                    <div class="consult-top">
                                        <strong><?= h($consultation["doctor_name"]) ?></strong>
                                        <span class="status-label <?= h(statusClass($consultation["status"])) ?>">
                                            <?= h($consultation["status"]) ?>
                                        </span>
                                    </div>
                                    <p>
                                        <?= h(displayDate($consultation["consultation_date"])) ?>
                                        &bull; <?= h($consultation["consultation_type"]) ?>
                                    </p>
                                    <?php if (!empty($consultation["diagnosis"])): ?>
                                        <p><strong>Diagnosis:</strong> <?= h($consultation["diagnosis"]) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

            </div>

            <!-- Patient profile summary -->
            <div class="column">
                <section class="panel">
                    <div class="panel-header">
                        <h2><i class="fa-regular fa-user" style="color:var(--primary);margin-right:8px;"></i>My Profile</h2>
                        <a href="patient-profile.php" class="text-link">Edit</a>
                    </div>

                    <div class="profile-card">
                        <div class="profile-heading">
                            <div class="avatar large"><?= h($initial) ?></div>
                            <div>
                                <h3><?= h($patientName) ?></h3>
                                <p>Patient ID: <?= h($patientID) ?></p>
                            </div>
                        </div>

                        <div class="profile-details">
                            <div class="detail-row">
                                <span><i class="fa-regular fa-envelope"></i> Email</span>
                                <strong><?= h($patientEmail ?: "Not provided") ?></strong>
                            </div>
                            <div class="detail-row">
                                <span><i class="fa-solid fa-phone"></i> Phone</span>
                                <strong><?= h($patientPhone ?: "Not provided") ?></strong>
                            </div>
                            <div class="detail-row">
                                <span><i class="fa-solid fa-droplet"></i> Blood Group</span>
                                <strong><?= h($patientBloodGroup ?: "Not provided") ?></strong>
                            </div>
                        </div>

                        <a href="patient-profile.php" class="profile-button">
                            <i class="fa-regular fa-pen-to-square" style="margin-right:8px;"></i> Update Profile
                        </a>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h2><i class="fa-solid fa-heart-pulse" style="color:var(--primary);margin-right:8px;"></i>Quick Actions</h2>
                    </div>
                    <div class="panel-body">
                        <a href="doctors.php" class="report-item">
                            <div class="stat-icon teal"><i class="fa-solid fa-user-doctor"></i></div>
                            <div class="report-info">
                                <strong>Find a Doctor</strong>
                                <span>Explore available specialists</span>
                            </div>
                            <i class="fa-solid fa-chevron-right" style="font-size:11px;color:#9aa7b7;"></i>
                        </a>

                        <a href="appointment.php" class="report-item">
                            <div class="stat-icon blue"><i class="fa-regular fa-calendar-check"></i></div>
                            <div class="report-info">
                                <strong>Book Appointment</strong>
                                <span>Request an online consultation</span>
                            </div>
                            <i class="fa-solid fa-chevron-right" style="font-size:11px;color:#9aa7b7;"></i>
                        </a>

                        <a href="medical-reports.php" class="report-item">
                            <div class="stat-icon purple"><i class="fa-solid fa-file-medical"></i></div>
                            <div class="report-info">
                                <strong>Medical Reports</strong>
                                <span>View your health documents</span>
                            </div>
                            <i class="fa-solid fa-chevron-right" style="font-size:11px;color:#9aa7b7;"></i>
                        </a>
                    </div>
                </section>
            </div>
        </section>

        <footer class="footer">
            &copy; <?= date("Y") ?> MediConnect. Your Health, Our Priority.
        </footer>
    </main>
</div>

<script>
    const mobileMenu = document.getElementById("mobileMenu");
    const sidebar = document.getElementById("sidebar");

    mobileMenu.addEventListener("click", function () {
        sidebar.classList.toggle("open");
    });

    document.addEventListener("click", function (event) {
        if (
            window.innerWidth <= 760 &&
            !sidebar.contains(event.target) &&
            !mobileMenu.contains(event.target)
        ) {           sidebar.classList.remove("open");
        }
    });
</script>
</body>
</html>