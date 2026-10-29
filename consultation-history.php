<?php
session_start();
require_once 'config.php';

if (
    !isset($_SESSION['user_role'], $_SESSION['patient_db_id']) ||
    $_SESSION['user_role'] !== 'patient'
) {
    header('Location: login.php');
    exit;
}

$patientDbId = (int) $_SESSION['patient_db_id'];

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* Get logged-in patient */
$stmt = $pdo->prepare(
    'SELECT name, patient_id, email
     FROM patients
     WHERE id = ?
     LIMIT 1'
);
$stmt->execute([$patientDbId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/* Get this patient's consultation records only */
$stmt = $pdo->prepare(
    'SELECT id, doctor_name, consultation_date,
            consultation_type, status, diagnosis, notes
     FROM consultation_history
     WHERE patient_id = ?
     ORDER BY consultation_date DESC, id DESC'
);
$stmt->execute([$patientDbId]);
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalConsultations = count($consultations);
$completedCount = 0;
$upcomingCount = 0;

foreach ($consultations as $consultation) {
    if (strcasecmp($consultation['status'], 'Completed') === 0) {
        $completedCount++;
    }

    if (
        strtotime($consultation['consultation_date']) > time() &&
        in_array(
            strtolower($consultation['status']),
            ['scheduled', 'confirmed', 'upcoming'],
            true
        )
    ) {
        $upcomingCount++;
    }
}

function statusClass($status) {
    switch (strtolower(trim((string)$status))) {
        case 'completed':
            return 'completed';
        case 'scheduled':
        case 'confirmed':
        case 'upcoming':
            return 'scheduled';
        case 'cancelled':
        case 'canceled':
            return 'cancelled';
        default:
            return 'other';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consultation History | MediConnect</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,sans-serif;background:#f5f8fc;color:#26394e;line-height:1.6}
a{text-decoration:none}
.navbar{min-height:76px;padding:15px 5%;background:#fff;display:flex;justify-content:space-between;align-items:center;gap:20px;border-bottom:1px solid #e8edf2}
.brand{font-size:24px;font-weight:800;color:#153751;white-space:nowrap}
.brand span,.footer span{color:#0ba59e}
.nav-right{display:flex;align-items:center;gap:22px}
.nav-right>a{color:#607184;font-size:13px;font-weight:600}
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
.main{width:100%;max-width:1500px;min-width:0;margin:0 auto;padding:35px}
.page-heading{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:28px}
.page-heading h1{color:#183751;font-size:27px;margin-bottom:5px}
.page-heading p{color:#8491a0;font-size:13px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:11px 16px;border:none;border-radius:8px;background:#079e99;color:#fff;font-size:12px;font-weight:700;cursor:pointer}
.btn:hover{background:#087d79}
.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-bottom:25px}
.stat{padding:21px;background:#fff;border:1px solid #e7edf2;border-radius:12px}
.stat-top{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:15px}
.stat-top span{color:#8190a0;font-size:12px}
.stat-icon{display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:10px;background:#e2f7f4;color:#078f91;font-size:18px}
.stat h2{color:#193750;font-size:27px}
.stat p{margin-top:5px;color:#91a0ad;font-size:10px}
.panel{margin-bottom:23px;padding:24px;background:#fff;border:1px solid #e7edf2;border-radius:12px}
.panel-head{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px}
.panel-head h2{color:#193750;font-size:17px}
.panel-head p{color:#8491a0;font-size:11px;margin-top:4px}
.filters{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.search{display:flex;align-items:center;gap:10px;flex:1;min-width:0;padding:11px 13px;border:1px solid #e0e8ed;border-radius:8px}
.search i{color:#91a0ad}
.search input{width:100%;min-width:0;border:none;outline:none;color:#26394e;font-size:12px}
.filters select{min-width:155px;padding:11px;border:1px solid #e0e8ed;border-radius:8px;background:#fff;color:#607184;font-size:11px}
.table-wrap{width:100%;overflow-x:auto}
table{width:100%;border-collapse:collapse;text-align:left;white-space:nowrap}
th{padding:13px 12px;background:#f7f9fb;color:#8190a0;font-size:10px;font-weight:700}
td{padding:15px 12px;border-bottom:1px solid #edf1f4;color:#52677a;font-size:11px}
tbody tr:last-child td{border-bottom:none}
.doctor{display:flex;align-items:center;gap:10px;color:#30475c;font-weight:700}
.doctor-icon{display:flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:9px;background:#e5f5f3;color:#078f91;flex-shrink:0}
.type-badge{display:inline-block;padding:5px 9px;border-radius:20px;background:#edf6ff;color:#547ce2;font-size:9px;font-weight:700}
.status{display:inline-block;padding:5px 9px;border-radius:20px;font-size:9px;font-weight:700}
.status.completed{background:#e8f8ef;color:#238557}
.status.scheduled{background:#eaf1ff;color:#547ce2}
.status.cancelled{background:#fff0ee;color:#c35249}
.status.other{background:#f0f2f5;color:#67788a}
.view-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 10px;border:1px solid #dce7ec;border-radius:6px;background:#fff;color:#078f91;font-size:10px;font-weight:700;cursor:pointer}
.view-btn:hover{background:#eaf8f6}
.empty{padding:35px 15px;text-align:center;color:#8491a0;font-size:12px}
.empty i{font-size:30px;color:#a8c9c7;margin-bottom:12px}
.empty h3{color:#52677a;font-size:15px;margin-bottom:5px}
.details{margin-top:20px;padding:18px;background:#f7fafc;border:1px solid #e7edf2;border-radius:10px;display:none}
.details h3{font-size:15px;color:#193750;margin-bottom:12px}
.details p{font-size:12px;color:#607184;margin-top:7px;white-space:normal;overflow-wrap:anywhere}
.details strong{color:#30475c}
.privacy{display:flex;align-items:flex-start;gap:13px;padding:17px;border:1px solid #dcefe9;border-radius:10px;background:#f0f9f6}
.privacy i{color:#078f91;font-size:19px;margin-top:2px}
.privacy h3{color:#234459;font-size:12px;margin-bottom:5px}
.privacy p{color:#718395;font-size:11px}
.footer{padding:20px;background:#102c43;color:#d6e1e9;text-align:center;font-size:11px}
@media(max-width:1150px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.layout{grid-template-columns:220px minmax(0,1fr)}}
@media(max-width:800px){.layout{grid-template-columns:1fr}.sidebar{padding:15px;border-right:none;border-bottom:1px solid #e8edf2;overflow-x:auto}.sidebar-label,.sidebar-help{display:none}.sidebar-nav{display:flex;width:max-content;gap:7px}.sidebar-nav a{white-space:nowrap;padding:10px 12px}.main{padding:24px 18px}.nav-right>a{display:none}}
@media(max-width:520px){.navbar{padding:14px 17px}.brand{font-size:21px}.profile-chip{padding-left:12px}.profile-chip>div:last-child{display:none}.page-heading{align-items:flex-start;flex-direction:column}.page-heading h1{font-size:23px}.stats{gap:11px}.stat{padding:15px}.stat h2{font-size:23px}.stat-top{align-items:flex-start;flex-direction:column-reverse}.panel{padding:16px}.filters{align-items:stretch;flex-direction:column}.filters select{width:100%}}
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
        <a href="appointment.html"><i class="fa-solid fa-calendar-check"></i>Book Appointment</a>
        <a href="doctors.html"><i class="fa-solid fa-user-doctor"></i>Find Doctors</a>
        <a href="medical-reports.php"><i class="fa-solid fa-file-medical"></i>Medical Reports</a>
        <a href="consultation-history.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i>Consultation History</a>
        <a href="patient-profile.php"><i class="fa-solid fa-user-gear"></i>My Profile</a>
        <a href="patient-dashboard.php?logout=1"><i class="fa-solid fa-right-from-bracket"></i>Sign Out</a>
    </nav>
    <div class="sidebar-help">
        <i class="fa-solid fa-headset"></i>
        <h4>Need Assistance?</h4>
        <p>Need help with your consultation records?</p>
        <a href="contact.html">CONTACT SUPPORT →</a>
    </div>
</aside>

<main class="main">
    <section class="page-heading">
        <div>
            <h1>Consultation History</h1>
            <p>Review your previous and upcoming doctor consultations.</p>
        </div>
        <a href="appointment.html" class="btn"><i class="fa-solid fa-calendar-plus"></i> Book Appointment</a>
    </section>

    <section class="stats">
        <article class="stat">
            <div class="stat-top"><span>Total Consultations</span><div class="stat-icon"><i class="fa-solid fa-stethoscope"></i></div></div>
            <h2><?= $totalConsultations ?></h2><p>Your consultation records</p>
        </article>
        <article class="stat">
            <div class="stat-top"><span>Completed</span><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div></div>
            <h2><?= $completedCount ?></h2><p>Completed consultations</p>
        </article>
        <article class="stat">
            <div class="stat-top"><span>Upcoming</span><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div></div>
            <h2><?= $upcomingCount ?></h2><p>Scheduled future consultations</p>
        </article>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h2>Your Consultation Records</h2><p>Search by doctor, diagnosis or consultation type.</p></div>
        </div>

        <div class="filters">
            <div class="search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="search" placeholder="Search consultations...">
            </div>
            <select id="status-filter">
                <option value="all">All Statuses</option>
                <option value="completed">Completed</option>
                <option value="scheduled">Scheduled</option>
                <option value="cancelled">Cancelled</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>DOCTOR</th>
                        <th>DATE & TIME</th>
                        <th>CONSULTATION TYPE</th>
                        <th>STATUS</th>
                        <th>DETAILS</th>
                    </tr>
                </thead>
                <tbody id="consultation-list">
                <?php foreach ($consultations as $item): ?>
                    <?php
                    $dateTimestamp = strtotime($item['consultation_date']);
                    $formattedDate = $dateTimestamp ? date('d M Y, h:i A', $dateTimestamp) : '—';
                    $cls = statusClass($item['status']);
                    $searchText = mb_strtolower(
                        $item['doctor_name'] . ' ' .
                        $item['consultation_type'] . ' ' .
                        ($item['diagnosis'] ?? '') . ' ' .
                        ($item['notes'] ?? '')
                    );
                    ?>
                    <tr
                        data-search="<?= h($searchText) ?>"
                        data-status="<?= h($cls) ?>"
                        data-doctor="<?= h($item['doctor_name']) ?>"
                        data-date="<?= h($formattedDate) ?>"
                        data-type="<?= h($item['consultation_type']) ?>"
                        data-diagnosis="<?= h($item['diagnosis'] ?? '') ?>"
                        data-notes="<?= h($item['notes'] ?? '') ?>"
                        data-status-label="<?= h($item['status']) ?>"
                    >
                        <td>
                            <div class="doctor">
                                <div class="doctor-icon"><i class="fa-solid fa-user-doctor"></i></div>
                                <?= h($item['doctor_name']) ?>
                            </div>
                        </td>
                        <td><?= h($formattedDate) ?></td>
                        <td><span class="type-badge"><?= h($item['consultation_type']) ?></span></td>
                        <td><span class="status <?= h($cls) ?>"><?= h($item['status']) ?></span></td>
                        <td><button type="button" class="view-btn" onclick="showDetails(this)"><i class="fa-solid fa-eye"></i> View</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="empty" id="empty-state" style="<?= $totalConsultations ? 'display:none' : '' ?>">
            <i class="fa-solid fa-calendar-xmark"></i>
            <h3>No Consultation History Yet</h3>
            <p>Your consultation records will appear here when they are saved to your account.</p>
        </div>

        <div class="details" id="details-panel">
            <h3>Consultation Details</h3>
            <p><strong>Doctor:</strong> <span id="detail-doctor"></span></p>
            <p><strong>Date:</strong> <span id="detail-date"></span></p>
            <p><strong>Type:</strong> <span id="detail-type"></span></p>
            <p><strong>Status:</strong> <span id="detail-status"></span></p>
            <p><strong>Diagnosis:</strong> <span id="detail-diagnosis"></span></p>
            <p><strong>Notes:</strong> <span id="detail-notes"></span></p>
        </div>
    </section>

    <div class="privacy">
        <i class="fa-solid fa-shield-halved"></i>
        <div><h3>Your Health Records Are Private</h3><p>This page displays consultation records associated with your logged-in patient account. Only authorized access should be given to medical information.</p></div>
    </div>
</main>
</div>

<footer class="footer">&copy; 2026 Medi<span>Connect</span>. All rights reserved.</footer>

<script>
const searchBox = document.getElementById('search');
const statusFilter = document.getElementById('status-filter');
const rows = document.querySelectorAll('#consultation-list tr');
const emptyState = document.getElementById('empty-state');

function filterConsultations() {
    const query = searchBox.value.toLowerCase().trim();
    const status = statusFilter.value;
    let visible = 0;

    rows.forEach(row => {
        const matchesQuery = row.dataset.search.includes(query);
        const matchesStatus = status === 'all' || row.dataset.status === status;
        const show = matchesQuery && matchesStatus;

        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    emptyState.style.display = visible === 0 ? 'block' : 'none';
    emptyState.innerHTML = rows.length === 0
        ? '<i class="fa-solid fa-calendar-xmark"></i><h3>No Consultation History Yet</h3><p>Your consultation records will appear here when they are saved to your account.</p>'
        : '<i class="fa-solid fa-magnifying-glass"></i><h3>No Matching Records</h3><p>Try changing your search or status filter.</p>';
}

function showDetails(button) {
    const row = button.closest('tr');
    document.getElementById('detail-doctor').textContent = row.dataset.doctor || '—';
    document.getElementById('detail-date').textContent = row.dataset.date || '—';
    document.getElementById('detail-type').textContent = row.dataset.type || '—';
    document.getElementById('detail-status').textContent = row.dataset.statusLabel || '—';
    document.getElementById('detail-diagnosis').textContent = row.dataset.diagnosis || 'Not provided';
    document.getElementById('detail-notes').textContent = row.dataset.notes || 'No additional notes';

    const panel = document.getElementById('details-panel');
    panel.style.display = 'block';
    panel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
}

searchBox.addEventListener('input', filterConsultations);
statusFilter.addEventListener('change', filterConsultations);
</script>
</body>
</html>