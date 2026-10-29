<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$patients = [];
$totalPatients = 0;
$registeredPatients = 0;
$totalAppointments = 0;
$errorMessage = '';

/**
 * Escape values before displaying them in HTML.
 */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Calculate age from date of birth.
 */
function calculateAge(?string $dob): string
{
    if (empty($dob) || $dob === '0000-00-00') {
        return 'Not provided';
    }

    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        return (string)$birthDate->diff($today)->y;
    } catch (Throwable $exception) {
        return 'Not provided';
    }
}

try {
    // Statistics
    $totalPatients = (int)$pdo
        ->query("SELECT COUNT(*) FROM patients")
        ->fetchColumn();

    $registeredPatients = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM patients
            WHERE patient_id IS NOT NULL
              AND TRIM(patient_id) <> ''
        ")
        ->fetchColumn();

    $totalAppointments = (int)$pdo
        ->query("SELECT COUNT(*) FROM appointments")
        ->fetchColumn();

    /*
     * Fetch patient records and their appointment history.
     *
     * appointments.patient_id stores the patient registration ID,
     * for example MC-P001.
     */
    $sql = "
        SELECT
            p.id AS database_id,
            p.patient_id,
            p.name,
            p.email,
            p.phone,
            p.dob,
            p.gender,
            p.blood_group,
            p.address,
            p.emergency_contact,
            p.created_at,

            a.id AS appointment_id,
            a.appointment_date,
            a.appointment_time,
            a.reason,
            a.status,

            d.name AS doctor_name,
            d.speciality AS doctor_speciality

        FROM patients p

        LEFT JOIN appointments a
            ON a.patient_id = p.patient_id

        LEFT JOIN doctors d
            ON d.id = a.doctor_id

        ORDER BY p.id DESC, a.appointment_date DESC, a.id DESC
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group multiple appointments under the same patient.
    $patientMap = [];

    foreach ($rows as $row) {
        $databaseId = (int)$row['database_id'];

        if (!isset($patientMap[$databaseId])) {
            $patientMap[$databaseId] = [
                'id' => $databaseId,
                'patient_id' => $row['patient_id'] ?? '',
                'name' => $row['name'] ?? '',
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? '',
                'dob' => $row['dob'] ?? '',
                'age' => calculateAge($row['dob'] ?? null),
                'gender' => $row['gender'] ?? '',
                'blood_group' => $row['blood_group'] ?? '',
                'address' => $row['address'] ?? '',
                'emergency_contact' => $row['emergency_contact'] ?? '',
                'created_at' => $row['created_at'] ?? '',
                'appointments' => []
            ];
        }

        // A patient may not have any appointments yet.
        if (!empty($row['appointment_id'])) {
            $patientMap[$databaseId]['appointments'][] = [
                'id' => (int)$row['appointment_id'],
                'date' => $row['appointment_date'] ?? '',
                'time' => $row['appointment_time'] ?? '',
                'reason' => $row['reason'] ?? '',
                'status' => $row['status'] ?? 'Pending',
                'doctor' => $row['doctor_name'] ?? 'Not assigned',
                'speciality' => $row['doctor_speciality'] ?? ''
            ];
        }
    }

    $patients = array_values($patientMap);

} catch (Throwable $exception) {
    // Log the technical error on the server, not to the browser.
    error_log('MediConnect Patient Management error: ' . $exception->getMessage());
    $errorMessage = 'Unable to load patient records. Please check your database connection and table structure.';
}

$patientJson = json_encode(
    $patients,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP |
    JSON_INVALID_UTF8_SUBSTITUTE
);

if ($patientJson === false) {
    $patientJson = '[]';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Management | MediConnect</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: #f5f7fb;
    color: #1e293b;
}

button,
input {
    font-family: inherit;
}

button {
    cursor: pointer;
}

.admin-layout {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 250px;
    min-height: 100vh;
    background: #fff;
    border-right: 1px solid #e5eaf1;
    padding: 28px 18px;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.logo {
    color: #1677e8;
    font-size: 23px;
    margin: 0 10px 35px;
}

.logo span {
    color: #172b4d;
}

.menu-title {
    color: #94a3b8;
    font-size: 11px;
    font-weight: 700;
    margin: 0 12px 14px;
    letter-spacing: 1px;
}

.sidebar nav {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.sidebar nav a,
.logout {
    text-decoration: none;
    padding: 13px 12px;
    border-radius: 8px;
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
    transition: .2s ease;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: #eaf3ff;
    color: #1677e8;
}

.logout {
    margin-top: auto;
    color: #dc3545;
}

.logout:hover {
    background: #fff0f0;
}

/* Main content */
.main-content {
    flex: 1;
    min-width: 0;
    padding: 30px;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 28px;
}

.topbar h1 {
    font-size: 26px;
    margin-bottom: 8px;
    color: #172b4d;
}

.topbar p,
.subtitle {
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.6;
}

.admin-profile {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    padding: 12px;
    border: 1px solid #e5eaf1;
    border-radius: 10px;
    font-size: 12px;
    flex-shrink: 0;
}

.admin-profile p {
    margin-top: 4px;
}

.avatar {
    width: 38px;
    height: 38px;
    background: #eaf3ff;
    color: #1677e8;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 700;
}

/* Statistics */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: #fff;
    border: 1px solid #e5eaf1;
    border-radius: 12px;
    padding: 22px;
    transition: transform .2s ease, box-shadow .2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
}

.stat-card p {
    color: #8491a5;
    font-size: 12px;
    margin-bottom: 10px;
}

.stat-card h2 {
    font-size: 27px;
    color: #172b4d;
}

/* Patient table */
.content-card {
    background: #fff;
    border: 1px solid #e5eaf1;
    border-radius: 12px;
    padding: 24px;
    min-width: 0;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 22px;
}

.section-heading h2 {
    font-size: 19px;
    margin-bottom: 7px;
}

.refresh-btn {
    background: #fff;
    border: 1px solid #dce5ef;
    padding: 10px 14px;
    border-radius: 7px;
    color: #334155;
    font-size: 12px;
    white-space: nowrap;
    transition: .2s ease;
}

.refresh-btn:hover {
    background: #eaf3ff;
    border-color: #1677e8;
    color: #1677e8;
}

.search-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}

.search-row input {
    width: 100%;
    max-width: 420px;
    padding: 13px 14px;
    border: 1px solid #dce5ef;
    border-radius: 8px;
    outline: none;
    font-size: 13px;
    background: #fff;
    color: #1e293b;
}

.search-row input:focus {
    border-color: #1677e8;
    box-shadow: 0 0 0 3px rgba(22, 119, 232, .08);
}

#resultCount {
    font-size: 12px;
    color: #94a3b8;
    white-space: nowrap;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
    text-align: left;
}

thead {
    background: #f7f9fc;
}

th {
    color: #8491a5;
    font-size: 11px;
    padding: 15px 12px;
    white-space: nowrap;
    font-weight: 600;
}

td {
    font-size: 12px;
    padding: 16px 12px;
    border-bottom: 1px solid #edf1f6;
    color: #526176;
    vertical-align: middle;
}

tbody tr:hover {
    background: #fbfdff;
}

.patient-name {
    font-weight: 600;
    color: #243b5a;
    margin-bottom: 5px;
    white-space: nowrap;
}

.patient-id {
    font-size: 10px;
    color: #94a3b8;
}

.count-badge {
    display: inline-block;
    background: #edf4ff;
    color: #1677e8;
    border-radius: 20px;
    padding: 6px 9px;
    font-size: 11px;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 6px;
}

.action-btn {
    padding: 8px 10px;
    border: none;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    transition: .2s ease;
}

.view-btn {
    background: #eaf3ff;
    color: #1677e8;
}

.view-btn:hover {
    background: #d7e8ff;
}

.empty-state {
    text-align: center;
    padding: 35px !important;
    color: #94a3b8;
}

.error-message {
    padding: 14px 16px;
    margin-bottom: 20px;
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #b91c1c;
    border-radius: 9px;
    font-size: 13px;
    line-height: 1.6;
}

/* Patient details modal */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .55);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 18px;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: #fff;
    width: 100%;
    max-width: 680px;
    max-height: 88vh;
    overflow-y: auto;
    padding: 28px;
    border-radius: 14px;
    position: relative;
    box-shadow: 0 20px 60px rgba(15, 23, 42, .2);
}

.close-btn {
    position: absolute;
    right: 18px;
    top: 15px;
    border: none;
    background: #f1f5f9;
    color: #334155;
    border-radius: 7px;
    width: 34px;
    height: 34px;
    font-size: 22px;
}

.close-btn:hover {
    background: #e2e8f0;
}

.modal-content h2 {
    margin-bottom: 8px;
    font-size: 21px;
    color: #172b4d;
    padding-right: 38px;
}

.modal-subtitle {
    color: #94a3b8;
    font-size: 12px;
    margin-bottom: 22px;
    line-height: 1.6;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.detail-item {
    background: #f7f9fc;
    padding: 14px;
    border-radius: 8px;
    overflow-wrap: anywhere;
}

.detail-item span {
    display: block;
    color: #94a3b8;
    font-size: 11px;
    margin-bottom: 7px;
}

.detail-item strong {
    font-size: 13px;
    color: #243b5a;
    line-height: 1.6;
}

.history-heading {
    font-size: 16px;
    margin: 25px 0 13px;
    color: #172b4d;
}

.history-item {
    border: 1px solid #e5eaf1;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 10px;
}

.history-item strong {
    font-size: 13px;
    color: #243b5a;
}

.history-item p {
    font-size: 12px;
    color: #64748b;
    margin-top: 7px;
    line-height: 1.8;
    overflow-wrap: anywhere;
}

.no-history {
    color: #94a3b8;
    background: #f7f9fc;
    padding: 16px;
    border-radius: 8px;
    font-size: 12px;
}

@media (max-width: 900px) {
    .admin-layout {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        min-height: auto;
        padding: 18px;
    }

    .logo {
        margin-bottom: 20px;
    }

    .sidebar nav {
        flex-direction: row;
        flex-wrap: wrap;
    }

    .logout {
        margin-top: 12px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .main-content {
        padding: 20px;
    }
}

@media (max-width: 520px) {
    .main-content {
        padding: 14px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .content-card {
        padding: 14px;
    }

    .section-heading {
        align-items: flex-start;
    }

    .search-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .search-row input {
        max-width: 100%;
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }

    .modal-content {
        padding: 22px 16px;
    }
}
</style>
</head>

<body>

<div class="admin-layout">

    <aside class="sidebar">
        <h2 class="logo">Medi<span>Connect</span></h2>

        <p class="menu-title">MAIN MENU</p>

        <nav>
            <a href="admin-panel.php">Dashboard</a>
            <a href="admin-doctors.php">Doctor Management</a>
            <a href="admin-patients.php" class="active">Patient Management</a>
            <a href="admin-appointment.php">Appointment Management</a>
            <a href="index.php">Visit Website</a>
        </nav>

        <a href="login.php" class="logout">Logout</a>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Patient Management</h1>
                <p>Manage and monitor registered patients.</p>
            </div>

            <div class="admin-profile">
                <div class="avatar">A</div>
                <div>
                    <strong>Administrator</strong>
                    <p>Admin Panel</p>
                </div>
            </div>
        </header>

        <?php if ($errorMessage !== ''): ?>
            <div class="error-message">
                <?= e($errorMessage) ?>
                <br>
                Make sure the database tables and column names match your project.
            </div>
        <?php endif; ?>

        <section class="stats-grid">
            <div class="stat-card">
                <p>Total Patients</p>
                <h2><?= number_format($totalPatients) ?></h2>
            </div>

            <div class="stat-card">
                <p>Registered Patients</p>
                <h2><?= number_format($registeredPatients) ?></h2>
            </div>

            <div class="stat-card">
                <p>Total Appointments</p>
                <h2><?= number_format($totalAppointments) ?></h2>
            </div>
        </section>

        <section class="content-card">

            <div class="section-heading">
                <div>
                    <h2>All Patients</h2>
                    <p class="subtitle">View registered patient records and appointment history.</p>
                </div>

                <button type="button" class="refresh-btn" onclick="refreshPatients()">
                    ↻ Refresh
                </button>
            </div>

            <div class="search-row">
                <input
                    type="text"
                    id="searchPatient"
                    placeholder="Search by patient ID, name, phone or email..."
                    autocomplete="off"
                    aria-label="Search patients"
                >
                <span id="resultCount"><?= count($patients) ?> patients</span>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Age</th>
                            <th>Appointments</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody id="patientTable">
                    <?php if (empty($patients)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                No registered patients found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($patients as $patient): ?>
                            <tr
                                class="patient-row"
                                data-search="<?= e(strtolower(
                                    ($patient['patient_id'] ?? '') . ' ' .
                                    ($patient['name'] ?? '') . ' ' .
                                    ($patient['phone'] ?? '') . ' ' .
                                    ($patient['email'] ?? '')
                                )) ?>"
                            >
                                <td>
                                    <div class="patient-name">
                                        <?= e($patient['name'] ?: 'Unnamed Patient') ?>
                                    </div>
                                    <div class="patient-id">
                                        <?= e($patient['patient_id'] ?: 'No Patient ID') ?>
                                    </div>
                                </td>

                                <td><?= e($patient['phone'] ?: 'Not provided') ?></td>

                                <td><?= e($patient['email'] ?: 'Not provided') ?></td>

                                <td><?= e($patient['age']) ?></td>

                                <td>
                                    <span class="count-badge">
                                        <?= count($patient['appointments']) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="action-buttons">
                                        <button
                                            type="button"
                                            class="action-btn view-btn"
                                            data-patient-id="<?= (int)$patient['id'] ?>"
                                        >
                                            View Details
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </section>

    </main>
</div>

<!-- Patient details modal -->
<div class="modal" id="patientModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-content">

        <button type="button" class="close-btn" id="closeModalBtn" aria-label="Close patient details">
            &times;
        </button>

        <h2 id="modalTitle">Patient Details</h2>
        <p class="modal-subtitle">Patient information and appointment history.</p>

        <div id="patientDetails"></div>

        <h3 class="history-heading">Appointment History</h3>
        <div id="appointmentHistory"></div>

    </div>
</div>

<script>
'use strict';

// Data is rendered by PHP from MySQL.
// No patient records are read from localStorage.
const patients = <?= $patientJson ?>;

const patientTable = document.getElementById('patientTable');
const searchInput = document.getElementById('searchPatient');
const resultCount = document.getElementById('resultCount');
const patientModal = document.getElementById('patientModal');
const patientDetails = document.getElementById('patientDetails');
const appointmentHistory = document.getElementById('appointmentHistory');

function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[character]);
}

function displayValue(value) {
    if (value === null || value === undefined || String(value).trim() === '') {
        return 'Not provided';
    }

    return String(value);
}

function formatDate(value) {
    if (!value) return 'Not provided';

    const parts = String(value).split('-');

    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    return String(value);
}

function renderPatients() {
    const query = searchInput.value.trim().toLowerCase();
    const rows = Array.from(patientTable.querySelectorAll('.patient-row'));
    let visibleRows = 0;

    rows.forEach(row => {
        const searchableText = row.dataset.search || '';
        const matches = searchableText.includes(query);

        row.style.display = matches ? '' : 'none';

        if (matches) visibleRows++;
    });

    resultCount.textContent =
        `${visibleRows} ${visibleRows === 1 ? 'patient' : 'patients'}`;

    const emptyRow = document.getElementById('searchEmptyRow');

    if (visibleRows === 0 && rows.length > 0) {
        if (!emptyRow) {
            const tr = document.createElement('tr');
            tr.id = 'searchEmptyRow';

            const td = document.createElement('td');
            td.colSpan = 6;
            td.className = 'empty-state';
            td.textContent = 'No patients match your search.';

            tr.appendChild(td);
            patientTable.appendChild(tr);
        }
    } else if (emptyRow) {
        emptyRow.remove();
    }
}

function openPatientModal(databaseId) {
    const patient = patients.find(item =>
        Number(item.id) === Number(databaseId)
    );

    if (!patient) {
        alert('Patient information could not be found.');
        return;
    }

    patientDetails.innerHTML = `
        <div class="detail-grid">
            <div class="detail-item">
                <span>Patient ID</span>
                <strong>${escapeHTML(displayValue(patient.patient_id))}</strong>
            </div>

            <div class="detail-item">
                <span>Full Name</span>
                <strong>${escapeHTML(displayValue(patient.name))}</strong>
            </div>

            <div class="detail-item">
                <span>Phone</span>
                <strong>${escapeHTML(displayValue(patient.phone))}</strong>
            </div>

            <div class="detail-item">
                <span>Email</span>
                <strong>${escapeHTML(displayValue(patient.email))}</strong>
            </div>

            <div class="detail-item">
                <span>Age</span>
                <strong>${escapeHTML(displayValue(patient.age))}</strong>
            </div>

            <div class="detail-item">
                <span>Date of Birth</span>
                <strong>${escapeHTML(formatDate(patient.dob))}</strong>
            </div>

            <div class="detail-item">
                <span>Gender</span>
                <strong>${escapeHTML(displayValue(patient.gender))}</strong>
            </div>

            <div class="detail-item">
                <span>Blood Group</span>
                <strong>${escapeHTML(displayValue(patient.blood_group))}</strong>
            </div>

            <div class="detail-item">
                <span>Address</span>
                <strong>${escapeHTML(displayValue(patient.address))}</strong>
            </div>

            <div class="detail-item">
                <span>Emergency Contact</span>
                <strong>${escapeHTML(displayValue(patient.emergency_contact))}</strong>
            </div>

            <div class="detail-item">
                <span>Registered On</span>
                <strong>${escapeHTML(formatDate(patient.created_at ? String(patient.created_at).slice(0, 10) : ''))}</strong>
            </div>

            <div class="detail-item">
                <span>Total Appointments</span>
                <strong>${Array.isArray(patient.appointments) ? patient.appointments.length : 0}</strong>
            </div>
        </div>
    `;

    const history = Array.isArray(patient.appointments)
        ? patient.appointments
        : [];

    if (history.length === 0) {
        appointmentHistory.innerHTML = `
            <div class="no-history">
                This patient has no appointments yet.
            </div>
        `;
    } else {
        appointmentHistory.innerHTML = history.map((appointment, index) => `
            <div class="history-item">
                <strong>Appointment ${index + 1}</strong>

                <p>
                    <strong>Doctor:</strong>
                    ${escapeHTML(displayValue(appointment.doctor))}
                    ${appointment.speciality
                        ? ` — ${escapeHTML(appointment.speciality)}`
                        : ''}
                    <br>

                    <strong>Date:</strong>
                    ${escapeHTML(formatDate(appointment.date))}
                    <br>

                    <strong>Time:</strong>
                    ${escapeHTML(displayValue(appointment.time))}
                    <br>

                    <strong>Reason:</strong>
                    ${escapeHTML(displayValue(appointment.reason))}
                    <br>

                    <strong>Status:</strong>
                    ${escapeHTML(displayValue(appointment.status))}
                </p>
            </div>
        `).join('');
    }

    patientModal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closePatientModal() {
    patientModal.classList.remove('show');
    document.body.style.overflow = '';
}

function refreshPatients() {
    // Reload from PHP/MySQL instead of localStorage.
    window.location.reload();
}

searchInput.addEventListener('input', renderPatients);

patientTable.addEventListener('click', function(event) {
    const button = event.target.closest('[data-patient-id]');

    if (!button) return;

    openPatientModal(button.dataset.patientId);
});

document.getElementById('closeModalBtn').addEventListener('click', closePatientModal);

patientModal.addEventListener('click', function(event) {
    if (event.target === patientModal) {
        closePatientModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePatientModal();
    }
});
</script>

</body>
</html>