<?php
session_start();
require_once __DIR__ . '/config.php';

if (
    empty($_SESSION['mediconnect_admin_logged_in']) ||
    $_SESSION['mediconnect_admin_logged_in'] !== true
) {
    header('Location: admin-login.php');
    exit;
}

$errorMessage = '';
$successMessage = '';

$doctorCount = 0;
$patientCount = 0;
$appointmentCount = 0;
$pendingCount = 0;
$recentAppointments = [];
$contactMessageCount = 0;
$newContactMessageCount = 0;

/*
|--------------------------------------------------------------------------
| Automatically detect the homepage
|--------------------------------------------------------------------------
*/
$websitePage = is_file(__DIR__ . '/index.php')
    ? 'index.php'
    : 'index.php';

$loginPage = is_file(__DIR__ . '/login.php')
    ? 'login.php'
    : 'login.php';

/*
|--------------------------------------------------------------------------
| Confirm appointment
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['confirm_appointment'])
) {
    $appointmentId = filter_input(
        INPUT_POST,
        'appointment_id',
        FILTER_VALIDATE_INT
    );

    if ($appointmentId) {
        try {
            $stmt = $pdo->prepare("
                UPDATE appointments
                SET status = 'Confirmed'
                WHERE id = :id
                AND LOWER(TRIM(status)) = 'pending'
            ");

            $stmt->execute(['id' => $appointmentId]);

            if ($stmt->rowCount() > 0) {
                header('Location: admin-panel.php?updated=1');
                exit;
            }

            $errorMessage =
                'Appointment was already updated or not found.';

        } catch (PDOException $e) {
            error_log('Appointment confirmation error: ' . $e->getMessage());
            $errorMessage = 'Could not update appointment status.';
        }
    } else {
        $errorMessage = 'Invalid appointment ID.';
    }
}

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $successMessage = 'Appointment confirmed successfully.';
}

/*
|--------------------------------------------------------------------------
| Dashboard data from MySQL
|--------------------------------------------------------------------------
*/
try {
    $doctorCount = (int)$pdo
        ->query("SELECT COUNT(*) FROM doctors")
        ->fetchColumn();

    $patientCount = (int)$pdo
        ->query("SELECT COUNT(*) FROM patients")
        ->fetchColumn();

    $appointmentCount = (int)$pdo
        ->query("SELECT COUNT(*) FROM appointments")
        ->fetchColumn();

    $pendingCount = (int)$pdo
        ->query("
            SELECT COUNT(*)
            FROM appointments
            WHERE LOWER(TRIM(status)) = 'pending'
        ")
        ->fetchColumn();
    $contactMessageCount = (int)$pdo
    ->query("SELECT COUNT(*) FROM contact_messages")
    ->fetchColumn();

$newContactMessageCount = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM contact_messages
        WHERE LOWER(TRIM(status)) = 'new'
    ")
    ->fetchColumn();

    $stmt = $pdo->query("
        SELECT
            a.id AS appointment_id,
            a.patient_id,
            p.name AS patient_name,
            d.name AS doctor_name,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.created_at
        FROM appointments a
        LEFT JOIN patients p
            ON a.patient_id = p.patient_id
        LEFT JOIN doctors d
            ON a.doctor_id = d.id
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT 5
    ");

    $recentAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Admin dashboard database error: ' . $e->getMessage());

    $errorMessage =
        'Database error. Please check your database connection and table structure.';
}

function h($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function dateLabel($value): string
{
    if (empty($value)) {
        return 'N/A';
    }

    $timestamp = strtotime((string)$value);

    return $timestamp ? date('d M Y', $timestamp) : h($value);
}

function timeLabel($value): string
{
    if (empty($value)) {
        return 'N/A';
    }

    $timestamp = strtotime((string)$value);

    return $timestamp ? date('h:i A', $timestamp) : h($value);
}

function statusLabel($status): string
{
    $status = strtolower(trim((string)$status));

    if ($status === 'pending') {
        return 'pending';
    }

    if ($status === 'confirmed') {
        return 'confirmed';
    }

    return 'muted';
}

function statusText($status): string
{
    return ucwords(
        str_replace('_', ' ', (string)$status)
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | MediConnect</title>

    <link rel="stylesheet" href="admin-panel.css">

    <style>
        /* Settings modal */
        .settings-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(15, 23, 42, .55);
            align-items: center;
            justify-content: center;
            padding: 18px;
        }

        .settings-modal.show {
            display: flex;
        }

        .settings-box {
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            background: #fff;
            border-radius: 15px;
            padding: 28px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
        }

        .settings-box h2 {
            font-size: 22px;
            color: #172b4d;
            margin: 0 38px 8px 0;
        }

        .settings-description {
            color: #8491a5;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 22px;
        }

        .settings-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 35px;
            height: 35px;
            border: none;
            border-radius: 8px;
            background: #f1f5f9;
            color: #334155;
            font-size: 23px;
            cursor: pointer;
        }

        .settings-option {
            padding: 16px;
            border: 1px solid #e5eaf1;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .settings-option strong {
            display: block;
            font-size: 13px;
            color: #243b5a;
            margin-bottom: 6px;
        }

        .settings-option p {
            color: #8491a5;
            font-size: 12px;
            line-height: 1.6;
        }

        .settings-option select {
            width: 100%;
            padding: 11px;
            margin-top: 12px;
            border: 1px solid #dce5ef;
            border-radius: 7px;
            background: #fff;
            color: #334155;
        }

        .settings-button {
            display: inline-block;
            margin-top: 8px;
            padding: 11px 16px;
            background: #1677e8;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
        }

        .settings-button:hover {
            background: #0f62c4;
        }

        .settings-notice {
            margin-top: 12px;
            color: #13865f;
            font-size: 12px;
            line-height: 1.6;
        }

        .settings-modal a:focus-visible,
        .settings-modal button:focus-visible,
        .settings-modal select:focus-visible {
            outline: 2px solid #1677e8;
            outline-offset: 3px;
        }

        @media (max-width: 520px) {
            .settings-box {
                padding: 22px 17px;
            }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <!-- Sidebar -->
    <aside class="sidebar">

        <a href="admin-panel.php" class="brand">
            <span class="brand-icon">+</span>
            <span>Medi<span class="brand-light">Connect</span></span>
        </a>

        <p class="menu-label">MAIN MENU</p>

        <nav class="sidebar-nav">

            <a href="admin-panel.php" class="nav-item active">
                <span>▦</span>
                Dashboard
            </a>

            <a href="admin-doctors.php" class="nav-item">
                <span>⚕</span>
                Doctor Management
            </a>

            <a href="admin-patients.php" class="nav-item">
                <span>♙</span>
                Patient Management
            </a>

            <a href="admin-appointment.php" class="nav-item">
                <span>▣</span>
                Appointments
            </a>

            <a href="admin-video-consultations.html" class="nav-item">
                <span>▷</span>
                Video Consultations
            </a>

            <a href="admin-medical-reports.html" class="nav-item">
                <span>▤</span>
                Medical Reports
            </a>

            <a href="admin-prescriptions.php" class="nav-item">
                <span>▧</span>
                Digital Prescriptions
            </a>

            <a href="admin-messages.php" class="nav-item">
    <span>✉</span>
    Contact Messages

    <?php if ($newContactMessageCount > 0): ?>
        <span style="
            margin-left:auto;
            background:#e5484d;
            color:#fff;
            border-radius:20px;
            padding:3px 8px;
            font-size:11px;
            font-weight:700;
        ">
            <?= number_format($newContactMessageCount) ?>
        </span>
    <?php endif; ?>
</a>

        </nav>

        <p class="menu-label">PREFERENCES</p>

        <nav class="sidebar-nav">

            <a href="help-support.html" class="nav-item">
                <span>?</span>
                Help &amp; Support
            </a>

            <!-- Opens the settings modal on this page -->
            <a href="#settings" class="nav-item" id="settingsLink">
                <span>⚙</span>
                Settings
            </a>

            <!-- Opens whichever homepage file exists -->
            <a href="<?= h($websitePage) ?>" class="nav-item" target="_blank" rel="noopener">
                <span>⌂</span>
                View Website
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="admin-avatar">A</div>

            <div class="admin-info">
                <strong>Administrator</strong>
                <span>Admin Panel</span>
            </div>

            <a href="<?= h($loginPage) ?>" class="logout" title="Logout">↪</a>

        </div>

    </aside>

    <!-- Main Dashboard -->
    <main class="main-content">

        <header class="topbar">

            <div>
                <h1>Admin Dashboard</h1>
                <p>Welcome back! Here's what's happening with MediConnect.</p>
            </div>

            <div class="topbar-right">
                <span class="status-dot"></span>
                <span>System Overview</span>
                <div class="top-avatar">A</div>
            </div>

        </header>

        <?php if ($successMessage !== ''): ?>
            <div class="section-card" style="color:#13865f;">
                <?= h($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="section-card" style="color:#c0392b;">
                <?= h($errorMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <section class="stats-grid">

            <article class="stat-card">
                <div class="stat-top">
                    <span>Total Doctors</span>
                    <div class="stat-icon blue">⚕</div>
                </div>
                <h2><?= number_format($doctorCount) ?></h2>
                <p>Doctors registered in the system</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Total Patients</span>
                    <div class="stat-icon green">♙</div>
                </div>
                <h2><?= number_format($patientCount) ?></h2>
                <p>Registered patients</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Appointments</span>
                    <div class="stat-icon purple">▣</div>
                </div>
                <h2><?= number_format($appointmentCount) ?></h2>
                <p>Total appointments booked</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Pending Requests</span>
                    <div class="stat-icon orange">◷</div>
                </div>
                <h2><?= number_format($pendingCount) ?></h2>
                <p>Appointments awaiting confirmation</p>
            </article>

        </section>

        <!-- Quick Actions -->
        <section class="section-card">

            <div class="section-heading">
                <div>
                    <h2>Quick Actions</h2>
                    <p>Access important management tools quickly.</p>
                </div>
            </div>

            <div class="quick-actions">

                <a href="admin-doctors.php" class="action-btn">
                    <span>+</span>
                    Add / Manage Doctors
                </a>

                <a href="admin-patients.php" class="action-btn">
                    <span>♙</span>
                    View Patients
                </a>

                <a href="admin-appointment.php" class="action-btn">
                    <span>▣</span>
                    Manage Appointments
                </a>

                <a href="admin-medical-reports.html" class="action-btn">
                    <span>▤</span>
                    Medical Reports
                </a>

                <a href="admin-messages.php" class="action-btn">
    <span>✉</span>
    Contact Messages (<?= number_format($contactMessageCount) ?>)
</a>

            </div>

        </section>

        <!-- Recent Appointments -->
        <section class="section-card" id="appointments">

            <div class="section-heading">
                <div>
                    <h2>Recent Appointments</h2>
                    <p>Latest appointment activity from your database.</p>
                </div>

                <a href="admin-appointment.php" class="outline-btn">
                    View All
                </a>
            </div>

            <div class="table-wrap">

                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (!empty($recentAppointments)): ?>

                        <?php foreach ($recentAppointments as $appointment): ?>
                            <?php
                                $status = (string)($appointment['status'] ?? '');
                                $isPending = strtolower(trim($status)) === 'pending';
                            ?>

                            <tr>
                                <td>
                                    <?= h($appointment['patient_name'] ?: 'Patient not found') ?>
                                    <br>
                                    <span class="muted">
                                        <?= h($appointment['patient_id']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= h($appointment['doctor_name'] ?: 'Doctor not found') ?>
                                </td>

                                <td>
                                    <?= h(dateLabel($appointment['appointment_date'])) ?>
                                </td>

                                <td>
                                    <?= h(timeLabel($appointment['appointment_time'])) ?>
                                </td>

                                <td>
                                    <span class="badge <?= h(statusLabel($status)) ?>">
                                        <?= h(statusText($status ?: 'Unknown')) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($isPending): ?>
                                        <form method="POST"
                                              action="admin-panel.php"
                                              onsubmit="return confirm('Confirm this appointment?');">

                                            <input type="hidden"
                                                   name="appointment_id"
                                                   value="<?= (int)$appointment['appointment_id'] ?>">

                                            <button type="submit"
                                                    name="confirm_appointment"
                                                    value="1"
                                                    class="small-btn">
                                                Confirm
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">No action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:30px;">
                                No appointments found.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>

            </div>

        </section>

        <!-- Management Center -->
        <section class="section-card" id="management">

            <div class="section-heading">
                <div>
                    <h2>Management Center</h2>
                    <p>Manage the core services of MediConnect.</p>
                </div>
            </div>

            <div class="management-grid">

                <article class="section-card management-card">
                    <div class="section-heading">
                        <div class="management-icon blue">⚕</div>
                        <a href="admin-doctors.php" class="manage-link">Manage →</a>
                    </div>
                    <h3>Doctor Management</h3>
                    <p>Add doctors, update professional information, and manage doctor profiles.</p>
                    <a href="admin-doctors.php" class="manage-link">Open Doctor Management →</a>
                </article>

                <article class="section-card management-card">
                    <div class="section-heading">
                        <div class="management-icon green">♙</div>
                        <a href="admin-patients.php" class="manage-link">Manage →</a>
                    </div>
                    <h3>Patient Management</h3>
                    <p>View patient profiles and manage registered patient information.</p>
                    <a href="admin-patients.php" class="manage-link">Open Patient Management →</a>
                </article>

                <article class="section-card management-card">
                    <div class="section-heading">
                        <div class="management-icon purple">▣</div>
                        <a href="admin-appointment.php" class="manage-link">Manage →</a>
                    </div>
                    <h3>Appointment Management</h3>
                    <p>Review appointment requests and manage appointment statuses.</p>
                    <a href="admin-appointment.php" class="manage-link">Open Appointments →</a>
                </article>

                <article class="section-card management-card">
                    <div class="section-heading">
                        <div class="management-icon orange">▷</div>
                        <a href="admin-video-consultations.html" class="manage-link">Open →</a>
                    </div>
                    <h3>Video Consultations</h3>
                    <p>Access the online consultation management page.</p>
                    <a href="admin-video-consultations.html" class="manage-link">Open Consultations →</a>
                </article>

                <article class="section-card management-card">
                    <div class="section-heading">
                        <div class="management-icon blue">▤</div>
                        <a href="admin-medical-reports.html" class="manage-link">Open →</a>
                    </div>
                    <h3>Medical Reports</h3>
                    <p>Access medical report management for patient records.</p>
                    <a href="admin-medical-reports.html" class="manage-link">Open Medical Reports →</a>
                </article>

                <article class="section-card management-card">
                    <div class="section-heading">
                        <div class="management-icon green">▧</div>
                        <a href="admin-prescriptions.php" class="manage-link">Open →</a>
                    </div>
                    <h3>Digital Prescriptions</h3>
                    <p>Access the digital prescription management page.</p>
                    <a href="admin-prescriptions.php" class="manage-link">Open Prescriptions →</a>
                </article>

            </div>

        </section>

        <footer class="footer">
            <span>© <?= date('Y') ?> MediConnect. All rights reserved.</span>
            <span>Admin Dashboard</span>
        </footer>

    </main>
</div>

<!-- Settings Modal: no separate file required -->
<div class="settings-modal" id="settingsModal"
     role="dialog" aria-modal="true" aria-labelledby="settingsTitle">

    <div class="settings-box">

        <button type="button" class="settings-close"
                id="closeSettings" aria-label="Close settings">&times;</button>

        <h2 id="settingsTitle">Dashboard Settings</h2>

        <p class="settings-description">
            Manage your dashboard display preferences and access important pages.
        </p>

        <div class="settings-option">
            <strong>Dashboard Appearance</strong>
            <p>Choose the text size used on this dashboard. This preference is saved in this browser.</p>

            <select id="textSizeSetting">
                <option value="normal">Normal text</option>
                <option value="large">Large text</option>
            </select>
        </div>

        <div class="settings-option">
            <strong>Website Homepage</strong>
            <p>Open the public MediConnect website in a new browser tab.</p>
            <a class="settings-button"
               href="<?= h($websitePage) ?>"
               target="_blank"
               rel="noopener">Open Website</a>
        </div>

        <div class="settings-option">
            <strong>Dashboard Data</strong>
            <p>Reload the dashboard and fetch the latest statistics from MySQL.</p>
            <button type="button" class="settings-button"
                    id="refreshDashboard">Refresh Dashboard</button>
        </div>

        <p class="settings-notice" id="settingsNotice" aria-live="polite"></p>

    </div>
</div>

<script>
(function () {
    const settingsModal = document.getElementById('settingsModal');
    const settingsLink = document.getElementById('settingsLink');
    const closeSettings = document.getElementById('closeSettings');
    const textSizeSetting = document.getElementById('textSizeSetting');
    const settingsNotice = document.getElementById('settingsNotice');
    const refreshDashboard = document.getElementById('refreshDashboard');

    function openSettings(event) {
        if (event) event.preventDefault();

        settingsModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSettingsModal() {
        settingsModal.classList.remove('show');
        document.body.style.overflow = '';
    }

    settingsLink.addEventListener('click', openSettings);
    closeSettings.addEventListener('click', closeSettingsModal);

    settingsModal.addEventListener('click', function (event) {
        if (event.target === settingsModal) {
            closeSettingsModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && settingsModal.classList.contains('show')) {
            closeSettingsModal();
        }
    });

    // Load saved display preference.
    let savedTextSize = 'normal';

    try {
        savedTextSize = localStorage.getItem('mediConnectAdminTextSize') || 'normal';
    } catch (error) {
        // Continue with the default if browser storage is unavailable.
    }

    textSizeSetting.value = savedTextSize;

    function applyTextSize(value) {
        document.querySelector('.main-content').style.fontSize =
            value === 'large' ? '1.08em' : '';

        document.querySelectorAll('.main-content p, .main-content td, .main-content th')
            .forEach(function (element) {
                element.style.fontSize = value === 'large' ? '1.05em' : '';
            });
    }

    applyTextSize(savedTextSize);

    textSizeSetting.addEventListener('change', function () {
        const value = textSizeSetting.value;
        applyTextSize(value);

        try {
            localStorage.setItem('mediConnectAdminTextSize', value);
            settingsNotice.textContent = 'Display preference saved in this browser.';
        } catch (error) {
            settingsNotice.textContent = 'Preference applied for this page only.';
        }
    });

    refreshDashboard.addEventListener('click', function () {
        window.location.href = 'admin-panel.php';
    });
})();
</script>

</body>
</html>