<?php
session_start();
require_once 'config.php';

if (!isset($pdo)) {
    die('Database connection failed. Check config.php.');
}

$message = '';
$error = '';

/* CSRF protection */
if (empty($_SESSION['appointment_csrf'])) {
    $_SESSION['appointment_csrf'] = bin2hex(random_bytes(32));
}

/* Update appointment status */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['appointment_csrf'], $token)) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';

        $allowedStatuses = ['Pending', 'Approved', 'Rejected', 'Completed'];

        if ($appointmentId < 1 || !in_array($newStatus, $allowedStatuses, true)) {
            $error = 'Invalid appointment or status.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE appointments
                     SET status = ?
                     WHERE id = ?"
                );
                $stmt->execute([$newStatus, $appointmentId]);

                if ($stmt->rowCount() > 0) {
                    $message = 'Appointment status updated successfully.';
                } else {
                    $check = $pdo->prepare(
                        "SELECT id, status FROM appointments WHERE id = ?"
                    );
                    $check->execute([$appointmentId]);
                    $existing = $check->fetch(PDO::FETCH_ASSOC);

                    if ($existing && $existing['status'] === $newStatus) {
                        $message = 'Appointment already has this status.';
                    } else {
                        $error = 'Appointment not found.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Could not update appointment status.';
            }
        }
    }
}

/* Search and filter */
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$validFilters = ['Pending', 'Approved', 'Rejected', 'Completed'];

$sql = "
    SELECT
        a.id,
        a.patient_id,
        a.doctor_id,
        a.appointment_date,
        a.appointment_time,
        a.reason,
        a.status,
        a.created_at,
        p.patient_id AS public_patient_id,
        p.name AS patient_name,
        p.phone AS patient_phone,
        d.name AS doctor_name,
        d.specialty AS doctor_specialty,
        d.fee AS doctor_fee
    FROM appointments a
    INNER JOIN patients p ON p.id = a.patient_id
    INNER JOIN doctors d ON d.id = a.doctor_id
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (
        p.name LIKE ?
        OR p.patient_id LIKE ?
        OR d.name LIKE ?
        OR d.specialty LIKE ?
    )";

    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}

if (in_array($statusFilter, $validFilters, true)) {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY a.created_at DESC, a.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts = [
        'Total' => 0,
        'Pending' => 0,
        'Approved' => 0,
        'Rejected' => 0,
        'Completed' => 0
    ];

    $countStmt = $pdo->query(
        "SELECT status, COUNT(*) AS total
         FROM appointments
         GROUP BY status"
    );

    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $row['status'];
        if (isset($counts[$key])) {
            $counts[$key] = (int) $row['total'];
        }
        $counts['Total'] += (int) $row['total'];
    }
} catch (PDOException $e) {
    die(
        'Could not load appointments. Please check that patients, doctors, ' .
        'and appointments tables contain the expected columns.'
    );
}

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointment Management | MediConnect Admin</title>
<style>
:root {
    --teal: #087f8c;
    --teal-dark: #066773;
    --ink: #183b43;
    --muted: #718096;
    --border: #e3ebf0;
    --bg: #f4f8fb;
    --white: #fff;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: Inter, "Segoe UI", Arial, sans-serif;
    background: var(--bg);
    color: var(--ink);
}
header {
    min-height: 76px;
    padding: 17px 5%;
    background: #fff;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
.brand {
    color: var(--teal);
    font-size: 23px;
    font-weight: 800;
}
.header-link {
    color: var(--teal);
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
}
main {
    max-width: 1450px;
    margin: 32px auto;
    padding: 0 24px;
}
.heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 25px;
}
.heading h1 {
    margin: 0 0 8px;
    font-size: 30px;
    letter-spacing: -.6px;
}
.heading p { margin: 0; color: var(--muted); }
.stats {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}
.stat {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px #183b4305;
}
.stat-label {
    color: var(--muted);
    font-size: 13px;
    font-weight: 600;
}
.stat-value {
    margin-top: 8px;
    font-size: 29px;
    font-weight: 800;
}
.panel {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 30px #183b4306;
}
.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 20px;
    border-bottom: 1px solid var(--border);
}
.toolbar h2 { margin: 0; font-size: 18px; }
.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
input, select {
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid #dbe5eb;
    border-radius: 9px;
    background: white;
    color: var(--ink);
    font: inherit;
    font-size: 13px;
}
input { width: 240px; }
input:focus, select:focus {
    outline: 2px solid #b8e5e8;
    border-color: var(--teal);
}
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 8px 12px;
    border: 0;
    border-radius: 8px;
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
}
.btn-primary { background: var(--teal); color: white; }
.btn-primary:hover { background: var(--teal-dark); }
.btn-light { background: #eef5f7; color: #38545d; }
.table-wrap { width: 100%; overflow-x: auto; }
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1120px;
}
th {
    padding: 15px 14px;
    text-align: left;
    background: #f7fafc;
    color: #667b87;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .45px;
    white-space: nowrap;
}
td {
    padding: 16px 14px;
    border-top: 1px solid #edf1f4;
    font-size: 13px;
    vertical-align: top;
}
tbody tr:hover { background: #fbfefe; }
.person { font-weight: 750; color: #24444c; }
.subtext {
    margin-top: 4px;
    color: var(--muted);
    font-size: 12px;
}
.reason {
    max-width: 180px;
    white-space: normal;
    overflow-wrap: anywhere;
    color: #617580;
}
.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}
.status-pending { color: #945900; background: #fff4d6; }
.status-approved { color: #08764c; background: #dff8eb; }
.status-rejected { color: #b42335; background: #ffeaed; }
.status-completed { color: #2458a6; background: #e8f0ff; }
.action-form { display: flex; flex-wrap: wrap; gap: 6px; }
.action-form select { width: 120px; min-height: 34px; padding: 6px; }
.action-form button { min-height: 34px; }
.message {
    margin-bottom: 18px;
    padding: 13px 16px;
    border-radius: 10px;
    font-size: 14px;
}
.message-success { background: #eafaf1; color: #176c43; }
.message-error { background: #fff0f1; color: #a51d31; }
.empty {
    padding: 55px 20px;
    text-align: center;
    color: var(--muted);
}
.empty strong {
    display: block;
    margin-bottom: 8px;
    color: var(--ink);
    font-size: 18px;
}
@media(max-width:1000px) {
    .stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .toolbar { align-items: flex-start; flex-direction: column; }
}
@media(max-width:600px) {
    header { padding: 15px 18px; }
    .brand { font-size: 20px; }
    main { margin: 24px auto; padding: 0 12px; }
    .heading h1 { font-size: 25px; }
    .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .stat { padding: 15px; }
    .stat-value { font-size: 25px; }
    .filters { width: 100%; }
    input { width: 100%; }
    .filters input, .filters select { flex: 1 1 100%; }
}
</style>
</head>
<body>
<header>
    <div class="brand">MediConnect <span style="color:#73858c;font-size:13px;font-weight:600">/ Admin</span></div>
    <a class="header-link" href="admin-panel.html">← Back to Admin Panel</a>
</header>

<main>
    <section class="heading">
        <div>
            <h1>Appointment Management</h1>
            <p>Review bookings and manage patient appointment requests.</p>
        </div>
    </section>

    <?php if ($message): ?>
        <div class="message message-success"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message message-error"><?= h($error) ?></div>
    <?php endif; ?>

    <section class="stats">
        <?php foreach ([
            'Total' => 'All Appointments',
            'Pending' => 'Pending',
            'Approved' => 'Approved',
            'Rejected' => 'Rejected',
            'Completed' => 'Completed'
        ] as $key => $label): ?>
            <div class="stat">
                <div class="stat-label"><?= h($label) ?></div>
                <div class="stat-value"><?= (int)$counts[$key] ?></div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="panel">
        <div class="toolbar">
            <h2>All Appointments</h2>
            <form class="filters" method="GET">
                <input
                    type="search"
                    name="search"
                    placeholder="Search patient or doctor..."
                    value="<?= h($search) ?>">

                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($validFilters as $filter): ?>
                        <option value="<?= h($filter) ?>"
                            <?= $statusFilter === $filter ? 'selected' : '' ?>>
                            <?= h($filter) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn btn-primary" type="submit">Search</button>
                <a class="btn btn-light" href="admin-appointment.php">Reset</a>
            </form>
        </div>

        <?php if (!$appointments): ?>
            <div class="empty">
                <strong>No appointments found</strong>
                Try changing your search or status filter.
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date &amp; Time</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($appointments as $a): ?>
                    <?php
                    $statusClass = strtolower($a['status']);
                    if (!in_array($statusClass, $validFilters, true)) {
                        $statusClass = 'Pending';
                    }
                    $statusClass = strtolower($statusClass);
                    ?>
                    <tr>
                        <td><strong>#<?= (int)$a['id'] ?></strong></td>
                        <td>
                            <div class="person"><?= h($a['patient_name']) ?></div>
                            <div class="subtext"><?= h($a['public_patient_id']) ?></div>
                            <div class="subtext"><?= h($a['patient_phone']) ?></div>
                        </td>
                        <td>
                            <div class="person"><?= h($a['doctor_name']) ?></div>
                            <div class="subtext"><?= h($a['doctor_specialty']) ?></div>
                        </td>
                        <td>
                            <div class="person"><?= h($a['appointment_date']) ?></div>
                            <div class="subtext"><?= h(substr((string)$a['appointment_time'], 0, 5)) ?></div>
                        </td>
                        <td>
                            <div class="reason">
                                <?= $a['reason'] !== null && $a['reason'] !== ''
                                    ? nl2br(h($a['reason']))
                                    : 'Not provided' ?>
                            </div>
                        </td>
                        <td>
                            <span class="status status-<?= h($statusClass) ?>">
                                <?= h($a['status']) ?>
                            </span>
                        </td>
                        <td>
                            <form class="action-form" method="POST">
                                <input type="hidden" name="csrf_token"
                                    value="<?= h($_SESSION['appointment_csrf']) ?>">
                                <input type="hidden" name="appointment_id"
                                    value="<?= (int)$a['id'] ?>">

                                <select name="status" aria-label="New status">
                                    <?php foreach ($validFilters as $option): ?>
                                        <option value="<?= h($option) ?>"
                                            <?= $a['status'] === $option ? 'selected' : '' ?>>
                                            <?= h($option) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button class="btn btn-primary" type="submit">
                                    Update
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>