<?php
session_start();
require_once __DIR__ . '/config.php';

if (
    ($_SESSION['user_role'] ?? '') !== 'patient' ||
    empty($_SESSION['patient_db_id'])
) {
    header('Location: login.php?redirect=medical-reports.php');
    exit;
}

$patientDbId = (int) $_SESSION['patient_db_id'];
$message = '';
$messageType = 'success';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Fetch registered doctors
|--------------------------------------------------------------------------
| This assumes doctors table has: id, name, speciality.
*/
$availableDoctors = [];

try {
    $doctorStmt = $pdo->query("
    SELECT *
    FROM doctors
    ORDER BY id ASC
");

$availableDoctors = $doctorStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Medical reports doctor list error: ' . $e->getMessage());
    $message = 'Could not load the doctor list. Please try again.';
    $messageType = 'error';
}

/*
|--------------------------------------------------------------------------
| Secure report file access
|--------------------------------------------------------------------------
*/
if (isset($_GET['file'])) {
    $reportId = filter_input(INPUT_GET, 'file', FILTER_VALIDATE_INT);

    if (!$reportId || $reportId < 1) {
        http_response_code(400);
        exit('Invalid report ID.');
    }

    $stmt = $pdo->prepare("
        SELECT id, report_title, file_path
        FROM medical_reports
        WHERE id = ? AND `Patient_id` = ?
        LIMIT 1
    ");

    $stmt->execute([$reportId, $patientDbId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report || empty($report['file_path'])) {
        http_response_code(404);
        exit('Report not found.');
    }

    $uploadRoot = realpath(__DIR__ . '/uploads/medical_reports');
    $filePath = realpath(__DIR__ . '/' . $report['file_path']);

    if (
        !$uploadRoot ||
        !$filePath ||
        !str_starts_with(
            $filePath,
            $uploadRoot . DIRECTORY_SEPARATOR
        ) ||
        !is_file($filePath)
    ) {
        http_response_code(404);
        exit('Report file not found.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
    $allowed = ['application/pdf', 'image/jpeg', 'image/png'];

    if (!in_array($mime, $allowed, true)) {
        http_response_code(403);
        exit('Unsupported file type.');
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $disposition = isset($_GET['download']) ? 'attachment' : 'inline';

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header(
        'Content-Disposition: ' . $disposition .
        '; filename="medical-report-' . (int)$report['id'] .
        '.' . $extension . '"'
    );
    header('Content-Length: ' . filesize($filePath));

    readfile($filePath);
    exit;
}

/*
|--------------------------------------------------------------------------
| Upload patient report
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['upload_report'])
) {
    $title = trim($_POST['report_title'] ?? '');
    $type = trim($_POST['report_type'] ?? '');
    $doctorId = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $date = trim($_POST['report_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $allowedTypes = ['Lab Test', 'Prescription', 'Imaging', 'Other'];
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

    $selectedDoctor = null;

    if ($doctorId && $doctorId > 0) {
        $doctorCheck = $pdo->prepare("
            SELECT id, name
            FROM doctors
            WHERE id = ?
            LIMIT 1
        ");
        $doctorCheck->execute([$doctorId]);
        $selectedDoctor = $doctorCheck->fetch(PDO::FETCH_ASSOC);
    }

    if ($title === '' || mb_strlen($title) > 150) {
        $message = 'Enter a report name (maximum 150 characters).';
        $messageType = 'error';

    } elseif (!in_array($type, $allowedTypes, true)) {
        $message = 'Please select a valid report type.';
        $messageType = 'error';

    } elseif (!$doctorId || !$selectedDoctor) {
        $message = 'Please select a valid registered doctor.';
        $messageType = 'error';

    } elseif ($date !== '' && (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
        !checkdate(
            (int)substr($date, 5, 2),
            (int)substr($date, 8, 2),
            (int)substr($date, 0, 4)
        ) ||
        $date > date('Y-m-d')
    )) {
        $message = 'Please enter a valid report date.';
        $messageType = 'error';

    } elseif (mb_strlen($notes) > 5000) {
        $message = 'Notes must not exceed 5000 characters.';
        $messageType = 'error';

    } elseif (
        !isset($_FILES['report_file']) ||
        $_FILES['report_file']['error'] !== UPLOAD_ERR_OK
    ) {
        $message = 'Please choose a report file.';
        $messageType = 'error';

    } else {
        $file = $_FILES['report_file'];
        $extension = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        if ($file['size'] > 5 * 1024 * 1024) {
            $message = 'File size must not exceed 5 MB.';
            $messageType = 'error';

        } elseif (!in_array($extension, $allowedExtensions, true)) {
            $message = 'Only PDF, JPG, JPEG and PNG files are allowed.';
            $messageType = 'error';

        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);

            $mimeMap = [
                'pdf'  => 'application/pdf',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png'
            ];

            if (
                !isset($mimeMap[$extension]) ||
                $mimeMap[$extension] !== $mime
            ) {
                $message = 'Invalid file content. Choose a genuine PDF or image.';
                $messageType = 'error';

            } else {
                $directory = __DIR__ . '/uploads/medical_reports';

                if (
                    !is_dir($directory) &&
                    !mkdir($directory, 0750, true) &&
                    !is_dir($directory)
                ) {
                    $message = 'Could not create the upload folder.';
                    $messageType = 'error';

                } else {
                    $filename = bin2hex(random_bytes(24)) . '.' . $extension;
                    $destination = $directory . '/' . $filename;
                    $relativePath = 'uploads/medical_reports/' . $filename;

                    if (
                        !move_uploaded_file(
                            $file['tmp_name'],
                            $destination
                        )
                    ) {
                        $message = 'File upload failed. Check folder permissions.';
                        $messageType = 'error';

                    } else {
                        try {
                            /*
                             * Save selected doctor's actual database ID.
                             * doctor_name is retained for existing UI/history.
                             */
                            $stmt = $pdo->prepare("
                                INSERT INTO medical_reports
                                (
                                    `Patient_id`,
                                    doctor_id,
                                    report_title,
                                    report_type,
                                    report_date,
                                    doctor_name,
                                    file_path,
                                    notes
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");

                            $stmt->execute([
                                $patientDbId,
                                (int)$selectedDoctor['id'],
                                $title,
                                $type,
                                $date !== '' ? $date : null,
                                $selectedDoctor['name'],
                                $relativePath,
                                $notes !== '' ? $notes : null
                            ]);

                            header(
                                'Location: medical-reports.php?uploaded=1'
                            );
                            exit;

                        } catch (Throwable $e) {
                            if (is_file($destination)) {
                                unlink($destination);
                            }

                            error_log(
                                'Medical report save error: ' .
                                $e->getMessage()
                            );

                            $message = 'Could not save the report. Check the database setup.';
                            $messageType = 'error';
                        }
                    }
                }
            }
        }
    }
}

if (isset($_GET['uploaded'])) {
    $message = 'Medical report uploaded successfully.';
    $messageType = 'success';
}

/*
|--------------------------------------------------------------------------
| Patient information
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT name, patient_id, email
    FROM patients
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$patientDbId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Report statistics
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_reports,
        COALESCE(SUM(report_type = 'Lab Test'), 0) AS lab_reports,
        COALESCE(SUM(report_type = 'Prescription'), 0) AS prescriptions
    FROM medical_reports
    WHERE `Patient_id` = ?
");
$stmt->execute([$patientDbId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| This patient's report history
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        id,
        report_title,
        report_type,
        report_date,
        doctor_name,
        doctor_id,
        file_path,
        created_at
    FROM medical_reports
    WHERE `Patient_id` = ?
    ORDER BY COALESCE(report_date, DATE(created_at)) DESC, id DESC
");
$stmt->execute([$patientDbId]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

function reportIcon($path) {
    $extension = strtolower(
        pathinfo((string)$path, PATHINFO_EXTENSION)
    );

    if ($extension === 'pdf') {
        return ['pdf', 'fa-file-pdf'];
    }

    if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        return ['image', 'fa-file-image'];
    }

    return ['', 'fa-file-lines'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medical Reports | MediConnect</title>
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
.nav-right a{color:#607184;font-size:13px;font-weight:600}
.profile-chip{display:flex;align-items:center;gap:10px;padding-left:20px;border-left:1px solid #e5ebf0}
.avatar{width:38px;height:38px;border-radius:50%;background:#e2f5f3;color:#078f91;display:flex;align-items:center;justify-content:center}
.profile-chip strong{display:block;font-size:12px}
.profile-chip small{display:block;font-size:10px;color:#8a97a6}
.layout{display:grid;grid-template-columns:245px minmax(0,1fr);min-height:calc(100vh - 76px)}
.sidebar{padding:30px 17px;background:#fff;border-right:1px solid #e8edf2}
.sidebar-label{padding:0 14px;margin-bottom:12px;color:#9aa5b2;font-size:10px;font-weight:800;letter-spacing:1.2px}
.sidebar-nav{display:grid;gap:6px}
.sidebar-nav a{display:flex;align-items:center;gap:13px;padding:12px 14px;border-radius:8px;color:#64758a;font-size:13px;font-weight:600}
.sidebar-nav a i{width:18px}
.sidebar-nav a:hover,.sidebar-nav a.active{background:#eaf8f6;color:#078f91}
.sidebar-help{margin-top:40px;padding:17px;background:#f0f9f8;border-radius:12px}
.sidebar-help i{color:#078f91;font-size:20px;margin-bottom:10px}
.sidebar-help h4{font-size:13px;margin-bottom:6px}
.sidebar-help p{color:#718395;font-size:11px;margin-bottom:12px}
.sidebar-help a{color:#078f91;font-size:11px;font-weight:800}
.main-content{width:100%;max-width:1500px;min-width:0;margin:0 auto;padding:35px}
.page-heading{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:28px}
.page-heading h1{color:#183751;font-size:27px;margin-bottom:5px}
.page-heading p{color:#8491a0;font-size:13px}
.primary-btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:12px 17px;border:none;border-radius:8px;background:#079e99;color:#fff;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap}
.primary-btn:hover{background:#087d79}
.stats-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-bottom:25px}
.stat-card{padding:21px;background:#fff;border:1px solid #e7edf2;border-radius:12px}
.stat-top{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:15px}
.stat-top span{color:#8190a0;font-size:12px}
.stat-icon{display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:10px;background:#e2f7f4;color:#078f91;font-size:18px}
.stat-card h2{color:#193750;font-size:27px}
.stat-card p{margin-top:5px;color:#91a0ad;font-size:10px}
.panel{min-width:0;margin-bottom:23px;padding:24px;background:#fff;border:1px solid #e7edf2;border-radius:12px}
.panel-heading{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px}
.panel-heading h2{color:#193750;font-size:17px}
.panel-heading p{color:#8491a0;font-size:11px;margin-top:4px}
.upload-box{padding:28px 20px;text-align:center;border:2px dashed #b8dcd9;border-radius:11px;background:#f8fcfc}
.upload-icon{display:flex;align-items:center;justify-content:center;width:58px;height:58px;margin:0 auto 13px;border-radius:15px;background:#e2f7f4;color:#078f91;font-size:24px}
.upload-box h3{color:#234459;font-size:15px;margin-bottom:7px}
.upload-box>p{color:#8190a0;font-size:11px;margin-bottom:17px}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;margin-bottom:16px;text-align:left}
.form-grid label{display:block;color:#52677a;font-size:11px;font-weight:700;margin-bottom:6px}
.form-grid input,.form-grid select,.form-grid textarea{width:100%;padding:11px;border:1px solid #dce7ec;border-radius:7px;background:#fff;font-size:12px;color:#26394e}
.form-grid textarea{min-height:70px;resize:vertical}
.form-grid .full{grid-column:1/-1}
#file-name{margin-top:8px;overflow-wrap:anywhere}
.notice{padding:12px 14px;margin-bottom:18px;border-radius:8px;font-size:12px}
.notice.success{background:#eaf8f1;color:#176b4d;border:1px solid #ccebdc}
.notice.error{background:#fff0ee;color:#a13f36;border:1px solid #f1d0cb}
.filters{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.search-box{display:flex;align-items:center;gap:10px;flex:1;min-width:0;padding:11px 13px;border:1px solid #e0e8ed;border-radius:8px}
.search-box i{color:#91a0ad}
.search-box input{width:100%;min-width:0;border:none;outline:none;color:#26394e;font-size:12px}
.filters select{min-width:145px;padding:11px;border:1px solid #e0e8ed;border-radius:8px;background:#fff;color:#607184;font-size:11px;outline:none}
.table-wrap{width:100%;overflow-x:auto}
table{width:100%;border-collapse:collapse;text-align:left;white-space:nowrap}
th{padding:13px 12px;background:#f7f9fb;color:#8190a0;font-size:10px;font-weight:700}
td{padding:15px 12px;border-bottom:1px solid #edf1f4;color:#52677a;font-size:11px}
tbody tr:last-child td{border-bottom:none}
.report-name{display:flex;align-items:center;gap:10px;color:#30475c;font-weight:700}
.file-icon{display:flex;align-items:center;justify-content:center;width:37px;height:37px;border-radius:8px;background:#eaf1ff;color:#547ce2;flex-shrink:0}
.file-icon.pdf{background:#fff0ee;color:#dd6258}
.file-icon.image{background:#f1eaff;color:#8862d5}
.type-badge{display:inline-block;padding:5px 9px;border-radius:20px;background:#edf6ff;color:#547ce2;font-size:9px;font-weight:700}
.actions{display:flex;gap:8px}
.action-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 10px;border:1px solid #dce7ec;border-radius:6px;background:#fff;color:#078f91;font-size:10px;font-weight:700;cursor:pointer}
.action-btn:hover{background:#eaf8f6}
.empty-state{padding:28px;text-align:center;color:#8491a0;font-size:12px}
.privacy-note{display:flex;align-items:flex-start;gap:13px;padding:17px;border:1px solid #dcefe9;border-radius:10px;background:#f0f9f6}
.privacy-note i{color:#078f91;font-size:19px;margin-top:2px}
.privacy-note h3{color:#234459;font-size:12px;margin-bottom:5px}
.privacy-note p{color:#718395;font-size:11px}
.footer{padding:20px;background:#102c43;color:#d6e1e9;text-align:center;font-size:11px}
@media(max-width:1150px){.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.layout{grid-template-columns:220px minmax(0,1fr)}}
@media(max-width:800px){.layout{grid-template-columns:1fr}.sidebar{padding:15px;border-right:none;border-bottom:1px solid #e8edf2;overflow-x:auto}.sidebar-label,.sidebar-help{display:none}.sidebar-nav{display:flex;width:max-content;gap:7px}.sidebar-nav a{white-space:nowrap;padding:10px 12px}.main-content{padding:24px 18px}.nav-right>a{display:none}}
@media(max-width:520px){.navbar{padding:14px 17px}.brand{font-size:21px}.profile-chip{padding-left:12px}.profile-chip>div:last-child{display:none}.page-heading{align-items:flex-start;flex-direction:column}.page-heading h1{font-size:23px}.stats-grid{gap:11px}.stat-card{padding:15px}.stat-card h2{font-size:23px}.stat-top{align-items:flex-start;flex-direction:column-reverse}.panel{padding:16px}.filters{align-items:stretch;flex-direction:column}.filters select{width:100%}.upload-box{padding:22px 13px}.form-grid{grid-template-columns:1fr}.form-grid .full{grid-column:auto}}
</style>
</head>
<body>

<header class="navbar">
    <a href="index.php" class="brand">Medi<span>Connect</span></a>
    <div class="nav-right">
        <a href="index.php">Home</a>
        <a href="doctors.php">Find Doctors</a>
        <div class="profile-chip">
            <div class="avatar"><i class="fa-solid fa-user"></i></div>
            <div>
                <strong><?= h($patient['name']) ?></strong>
                <small>Patient ID: <?= h($patient['patient_id']) ?></small>
            </div>
        </div>
    </div>
</header>

<div class="layout">
<aside class="sidebar">
    <p class="sidebar-label">PATIENT MENU</p>
    <nav class="sidebar-nav">
        <a href="patient-dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
        <a href="appointment.php"><i class="fa-solid fa-calendar-check"></i>Book Appointment</a>
        <a href="doctors.php"><i class="fa-solid fa-user-doctor"></i>Find Doctors</a>
        <a href="medical-reports.php" class="active"><i class="fa-solid fa-file-medical"></i>Medical Reports</a>
        <a href="consultation-history.php"><i class="fa-solid fa-clock-rotate-left"></i>Consultation History</a>
        <a href="patient-profile.php"><i class="fa-solid fa-user-gear"></i>My Profile</a>
        <a href="patient-dashboard.php?logout=1"><i class="fa-solid fa-right-from-bracket"></i>Sign Out</a>
    </nav>
    <div class="sidebar-help">
        <i class="fa-solid fa-headset"></i>
        <h4>Need Assistance?</h4>
        <p>Need help accessing your medical reports?</p>
        <a href="contact.html">CONTACT SUPPORT →</a>
    </div>
</aside>

<main class="main-content">
    <section class="page-heading">
        <div>
            <h1>Medical Reports</h1>
            <p>View and manage your medical documents in one place.</p>
        </div>
        <a href="#upload-section" class="primary-btn"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Report</a>
    </section>

    <?php if ($message !== ''): ?>
        <div class="notice <?= h($messageType) ?>"><?= h($message) ?></div>
    <?php endif; ?>

    <section class="stats-grid">
        <article class="stat-card">
            <div class="stat-top"><span>Total Reports</span><div class="stat-icon"><i class="fa-solid fa-file-medical"></i></div></div>
            <h2><?= (int)$stats['total_reports'] ?></h2><p>Your saved medical documents</p>
        </article>
        <article class="stat-card">
            <div class="stat-top"><span>Lab Reports</span><div class="stat-icon"><i class="fa-solid fa-flask"></i></div></div>
            <h2><?= (int)$stats['lab_reports'] ?></h2><p>Lab test documents</p>
        </article>
        <article class="stat-card">
            <div class="stat-top"><span>Prescriptions</span><div class="stat-icon"><i class="fa-solid fa-prescription"></i></div></div>
            <h2><?= (int)$stats['prescriptions'] ?></h2><p>Saved prescriptions</p>
        </article>
    </section>

    <section class="panel" id="upload-section">
        <div class="panel-heading">
            <div>
                <h2>Upload Medical Report</h2>
                <p>Select a medical document and the doctor who should receive it.</p>
            </div>
        </div>

        <form class="upload-box" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
            <input type="hidden" name="upload_report" value="1">
            <div class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
            <h3>Upload Your Medical Document</h3>
            <p>PDF, JPG, JPEG or PNG. Maximum file size: 5 MB.</p>

            <div class="form-grid">
                <div>
                    <label for="report-title">Report Name *</label>
                    <input id="report-title" name="report_title" maxlength="150" required placeholder="e.g. Blood Test Report">
                </div>

                <div>
                    <label for="report-type">Report Type *</label>
                    <select id="report-type" name="report_type" required>
                        <option value="">Select type</option>
                        <option>Lab Test</option>
                        <option>Prescription</option>
                        <option>Imaging</option>
                        <option>Other</option>
                    </select>
                </div>

                <div>
                    <label for="report-date">Report Date</label>
                    <input type="date" id="report-date" name="report_date" max="<?= date('Y-m-d') ?>">
                </div>

                <div>
                    <label for="doctor_id">Select Doctor *</label>
                    <select name="doctor_id" id="doctor_id" required>
    <option value="">Choose Doctor</option>

    <?php foreach ($availableDoctors as $doctor): ?>
        <option value="<?= (int)$doctor['id'] ?>">
            <?= htmlspecialchars(
                $doctor['name']
                ?? $doctor['full_name']
                ?? $doctor['doctor_name']
                ?? 'Doctor #' . $doctor['id'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </option>
    <?php endforeach; ?>
</select>
                    <?php if (!$availableDoctors): ?>
                        <small style="display:block;color:#a13f36;margin-top:6px">
                            No doctors are available. Please contact support.
                        </small>
                    <?php endif; ?>
                </div>

                <div class="full">
                    <label for="notes">Notes (optional)</label>
                    <textarea id="notes" name="notes" maxlength="5000" placeholder="Add a note about this report"></textarea>
                </div>

                <div class="full">
                    <label for="report-file">Choose File *</label>
                    <input type="file" id="report-file" name="report_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                    <p id="file-name">No file selected</p>
                </div>
            </div>

            <button class="primary-btn" type="submit" <?= !$availableDoctors ? 'disabled' : '' ?>>
                <i class="fa-solid fa-upload"></i> Upload Report
            </button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>All Medical Reports</h2>
                <p>Search and filter your report records.</p>
            </div>
        </div>

        <div class="filters">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="search-input" placeholder="Search report name...">
            </div>
            <select id="type-filter">
                <option value="all">All Report Types</option>
                <option>Lab Test</option>
                <option>Prescription</option>
                <option>Imaging</option>
                <option>Other</option>
            </select>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>REPORT NAME</th>
                        <th>DATE</th>
                        <th>REPORT TYPE</th>
                        <th>DOCTOR</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="report-list">
                <?php foreach ($reports as $report): ?>
                    <?php [$iconClass, $iconName] = reportIcon($report['file_path']); ?>
                    <tr
                        data-name="<?= h(mb_strtolower($report['report_title'])) ?>"
                        data-type="<?= h($report['report_type'] ?: 'Other') ?>"
                    >
                        <td>
                            <div class="report-name">
                                <div class="file-icon <?= h($iconClass) ?>">
                                    <i class="fa-solid <?= h($iconName) ?>"></i>
                                </div>
                                <?= h($report['report_title']) ?>
                            </div>
                        </td>
                        <td>
                            <?= $report['report_date']
                                ? h(date('d M Y', strtotime($report['report_date'])))
                                : '—' ?>
                        </td>
                        <td>
                            <span class="type-badge">
                                <?= h($report['report_type'] ?: 'Other') ?>
                            </span>
                        </td>
                        <td><?= h($report['doctor_name'] ?: '—') ?></td>
                        <td>
                            <div class="actions">
                                <?php if ($report['file_path']): ?>
                                    <a class="action-btn" target="_blank" rel="noopener"
                                       href="medical-reports.php?file=<?= (int)$report['id'] ?>">
                                        <i class="fa-solid fa-eye"></i> View
                                    </a>
                                    <a class="action-btn"
                                       href="medical-reports.php?file=<?= (int)$report['id'] ?>&amp;download=1">
                                        <i class="fa-solid fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    <span>File unavailable</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="empty-state" id="empty-state"
             style="<?= count($reports) ? 'display:none' : '' ?>">
            <?= count($reports)
                ? 'No matching reports found.'
                : 'No medical reports yet. Upload your first report above.' ?>
        </div>
    </section>

    <div class="privacy-note">
        <i class="fa-solid fa-shield-halved"></i>
        <div>
            <h3>Your Medical Information Matters</h3>
            <p>Only the logged-in patient can access their own report records through this page. A selected doctor is saved with each uploaded report.</p>
        </div>
    </div>
</main>
</div>

<footer class="footer">&copy; 2026 Medi<span>Connect</span>. All rights reserved.</footer>

<script>
const fileInput = document.getElementById('report-file');
const fileName = document.getElementById('file-name');

fileInput.addEventListener('change', function () {
    const file = this.files[0];

    if (!file) {
        fileName.textContent = 'No file selected';
        return;
    }

    const allowed = ['application/pdf', 'image/jpeg', 'image/png'];

    if (!allowed.includes(file.type)) {
        alert('Please select a PDF, JPG, JPEG or PNG file.');
        this.value = '';
        fileName.textContent = 'No file selected';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert('File size must not exceed 5 MB.');
        this.value = '';
        fileName.textContent = 'No file selected';
        return;
    }

    fileName.textContent = 'Selected: ' + file.name;
});

const searchInput = document.getElementById('search-input');
const typeFilter = document.getElementById('type-filter');
const rows = document.querySelectorAll('#report-list tr');
const emptyState = document.getElementById('empty-state');

function filterReports() {
    const query = searchInput.value.toLowerCase().trim();
    const selectedType = typeFilter.value;
    let count = 0;

    rows.forEach(row => {
        const matchesName = row.dataset.name.includes(query);
        const matchesType =
            selectedType === 'all' ||
            row.dataset.type === selectedType;

        row.style.display = matchesName && matchesType ? '' : 'none';

        if (matchesName && matchesType) {
            count++;
        }
    });

    emptyState.style.display = count === 0 ? 'block' : 'none';
    emptyState.textContent = rows.length === 0
        ? 'No medical reports yet. Upload your first report above.'
        : 'No matching reports found.';
}

searchInput.addEventListener('input', filterReports);
typeFilter.addEventListener('change', filterReports);
</script>
</body>
</html>