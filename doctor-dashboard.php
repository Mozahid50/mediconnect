<?php
session_start();
require_once __DIR__ . '/config.php';

if (
    ($_SESSION['user_role'] ?? '') !== 'doctor' ||
    empty($_SESSION['doctor_id'])
) {
    header("Location: login.php?redirect=doctor-dashboard.php");
    exit;
}

$prescriptionCount = 0;

try {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) 
         FROM prescriptions 
         WHERE doctor_id = :doctor_id"
    );

    $stmt->execute([
        'doctor_id' => $_SESSION['doctor_id']
    ]);

    $prescriptionCount = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log('Prescription count error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Doctor Dashboard | MediConnect</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="doctor-dashboard.css">
</head>

<body>
<div class="app">

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-icon">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>
            <div>
                <div class="brand-name">Medi<span>Connect</span></div>
                <div class="brand-subtitle">HEALTHCARE MANAGEMENT</div>
            </div>
        </div>

        <div class="menu-label">WORKSPACE</div>

        <nav class="nav-list">
            <button class="nav-item active" data-page="dashboard"
                    onclick="openPage('dashboard', this)">
                <i class="fa-solid fa-grip"></i> Dashboard
            </button>

            <button class="nav-item" data-page="patients"
                    onclick="openPage('patients', this)">
                <i class="fa-solid fa-users"></i> My Patients
            </button>

            <button class="nav-item" data-page="appointments"
                    onclick="openPage('appointments', this)">
                <i class="fa-regular fa-calendar-check"></i> Appointments
            </button>

            <button class="nav-item" data-page="reports"
                    onclick="openPage('reports', this)">
                <i class="fa-solid fa-file-medical"></i> Medical Reports
            </button>

            <button class="nav-item" data-page="prescriptions"
                    onclick="openPage('prescriptions', this)">
                <i class="fa-solid fa-prescription"></i> Digital Prescriptions
            </button>

            <button class="nav-item" data-page="video"
                    onclick="openPage('video', this)">
                <i class="fa-solid fa-video"></i> Video Consultations
            </button>
        </nav>

        <div class="sidebar-bottom">
            <div class="help-card">
                <i class="fa-solid fa-shield-heart"></i>
                <h4>Patient care comes first</h4>
                <p>Manage appointments and patient records in one place.</p>
            </div>

            <button class="logout-btn" onclick="logoutDoctor()">
                <i class="fa-solid fa-right-from-bracket"></i>
                Sign Out
            </button>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" onclick="toggleSidebar()">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>
                    <div class="page-heading" id="topTitle">Dashboard</div>
                    <div class="page-subheading" id="topSubtitle">
                        Your healthcare workspace at a glance
                    </div>
                </div>
            </div>

            <div class="topbar-right">
                <div class="today-date">
                    <i class="fa-regular fa-calendar"></i>
                    <span id="todayDate"></span>
                </div>

                <button class="notification-btn"
                        onclick="openPage('appointments')"
                        title="Pending appointments">
                    <i class="fa-regular fa-bell"></i>
                    <span class="notification-dot" id="notificationDot"></span>
                </button>

                <div class="doctor-avatar">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
            </div>
        </header>

        <div class="content">

            <!-- DASHBOARD -->
            <section class="page active" id="page-dashboard">
                <div class="welcome-banner">
                    <div class="welcome-copy">
                        <div class="welcome-label">MEDICONNECT · DOCTOR PORTAL</div>
                        <h1>Welcome, Doctor</h1>
                        <p>
                            Manage appointments, care for your patients and
                            organize medical records—all in one place.
                        </p>
                    </div>

                    <div class="welcome-illustration">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                </div>

                <div class="section-heading">
                    <div>
                        <h2>Overview</h2>
                        <p>A quick summary of your practice</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon icon-teal">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="stat-value" id="statPatients">0</div>
                        <div class="stat-title">Total unique patients</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-blue">
                            <i class="fa-regular fa-calendar-check"></i>
                        </div>
                        <div class="stat-value" id="statAppointments">0</div>
                        <div class="stat-title">Total appointments</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-purple">
                            <i class="fa-solid fa-file-medical"></i>
                        </div>
                        <div class="stat-value" id="statReports">0</div>
                        <div class="stat-title">Medical reports</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-orange">
                            <i class="fa-solid fa-prescription"></i>
                        </div>
                       <div class="stat-value" id="statPrescriptions">
    <?= $prescriptionCount ?>
</div>
                        <div class="stat-title">Digital prescriptions</div>
                    </div>
                </div>

                <div class="section-heading">
                    <div>
                        <h2>Practice Overview</h2>
                        <p>Recent bookings and quick actions</p>
                    </div>
                    <button class="text-link" onclick="openPage('appointments')">
                        View all <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>

                <div class="dashboard-grid">
                    <div class="panel">
                        <div class="panel-header">
                            <div>
                                <h3>Recent Appointments</h3>
                                <p>Latest bookings assigned to you</p>
                            </div>
                        </div>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody id="recentAppointments"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-header">
                            <div>
                                <h3>Quick Actions</h3>
                                <p>Frequently used tools</p>
                            </div>
                        </div>

                        <div class="panel-body quick-actions">
                            <button class="quick-action" onclick="openPage('appointments')">
                                <i class="fa-regular fa-calendar-check"></i>
                                <strong>Appointments</strong>
                                <span>Manage patient bookings</span>
                            </button>

                            <button class="quick-action"
                                    onclick="openPage('reports');showForm('reportForm')">
                                <i class="fa-solid fa-file-circle-plus"></i>
                                <strong>New Report</strong>
                                <span>Create medical reports</span>
                            </button>

                            <button class="quick-action"
                                    onclick="openPage('prescriptions');showForm('prescriptionForm')">
                                <i class="fa-solid fa-prescription"></i>
                                <strong>Prescription</strong>
                                <span>Prepare prescriptions</span>
                            </button>

                            <button class="quick-action" onclick="openPage('video')">
                                <i class="fa-solid fa-video"></i>
                                <strong>Video Visits</strong>
                                <span>View online appointments</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PATIENTS -->
            <section class="page" id="page-patients">
                <div class="toolbar">
                    <div>
                        <h2>My Patients</h2>
                        <p>Patients associated with your appointments and records.</p>
                    </div>

                    <label class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input id="patientSearch" placeholder="Search patients..."
                               oninput="renderPatients()">
                    </label>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Patient Directory</h3>
                            <p id="patientCountLabel">Your patient records</p>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Patient ID</th>
                                <th>Phone</th>
                                <th>Appointments</th>
                                <th>Last Visit</th>
                            </tr>
                            </thead>
                            <tbody id="patientsTable"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- APPOINTMENTS -->
            <section class="page" id="page-appointments">
                <div class="toolbar">
                    <div>
                        <h2>Appointments</h2>
                        <p>Review bookings and update their status.</p>
                    </div>

                    <div class="toolbar-actions">
                        <label class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input id="appointmentSearch" placeholder="Search patient..."
                                   oninput="renderAppointments()">
                        </label>

                        <select class="filter-select" id="appointmentFilter"
                                onchange="renderAppointments()">
                            <option value="all">All statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Appointment Management</h3>
                            
                            <p>Accept or cancel pending appointment requests.</p>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Visit Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody id="appointmentsTable"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- MEDICAL REPORTS -->
            <section class="page" id="page-reports">
                <div class="toolbar">
                    <div>
                        <h2>Medical Reports</h2>
                        <p>Create and manage patient reports.</p>
                    </div>

                    <div class="toolbar-actions">
                        <label class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input id="reportSearch" placeholder="Search reports..."
                                   oninput="renderReports()">
                        </label>

                        <button class="btn btn-primary" onclick="toggleForm('reportForm')">
                            <i class="fa-solid fa-plus"></i> Create Report
                        </button>
                    </div>
                </div>

                <div class="panel form-panel" id="reportForm">
                    <div class="panel-header">
                        <div>
                            <h3>Create Medical Report</h3>
                            <p>Enter the patient and clinical details.</p>
                        </div>
                        <button class="btn btn-outline"
                                onclick="toggleForm('reportForm',false)">Close</button>
                    </div>

                    <div class="panel-body">
                        <form id="createReportForm">
                            <div class="form-grid">
                                <div class="field">
                                    <label for="reportPatientName">Patient Name *</label>
                                    <input id="reportPatientName" required>
                                </div>

                                <div class="field">
                                    <label for="reportPatientId">Patient ID</label>
                                    <input id="reportPatientId" placeholder="e.g. MC-P001">
                                </div>

                                <div class="field">
                                    <label for="reportTitle">Report Title *</label>
                                    <input id="reportTitle" required>
                                </div>

                                <div class="field">
                                    <label for="reportCategory">Category *</label>
                                    <select id="reportCategory" required>
                                        <option value="">Select category</option>
                                        <option>General Examination</option>
                                        <option>Blood Test</option>
                                        <option>Imaging / Radiology</option>
                                        <option>Cardiology</option>
                                        <option>Pathology</option>
                                        <option>Follow-up</option>
                                        <option>Other</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="reportDate">Report Date *</label>
                                    <input type="date" id="reportDate" required>
                                </div>

                                <div class="field">
                                    <label for="reportStatus">Status</label>
                                    <select id="reportStatus">
                                        <option value="Available">Available</option>
                                        <option value="Draft">Draft</option>
                                    </select>
                                </div>

                                <div class="field full">
                                    <label for="reportFindings">Clinical Findings / Notes *</label>
                                    <textarea id="reportFindings" required></textarea>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-outline"
                                        onclick="toggleForm('reportForm',false)">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Report Records</h3>
                            <p>Your saved medical reports.</p>
                        </div>
                    </div>
                    <div class="panel-body" id="reportsList"></div>
                </div>
            </section>

            <!-- DIGITAL PRESCRIPTIONS -->
            <section class="page" id="page-prescriptions">
                <div class="toolbar">
                    <div>
                        <h2>Digital Prescriptions</h2>
                        <p>Prepare treatment and medication instructions.</p>
                    </div>

                    <div class="toolbar-actions">
                        <label class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input id="prescriptionSearch" placeholder="Search prescriptions..."
                                   oninput="renderPrescriptions()">
                        </label>

                        <button class="btn btn-primary"
                                onclick="toggleForm('prescriptionForm')">
                            <i class="fa-solid fa-plus"></i> New Prescription
                        </button>
                    </div>
                </div>

                <div class="panel form-panel" id="prescriptionForm">
                    <div class="panel-header">
                        <div>
                            <h3>Create Digital Prescription</h3>
                            <p>Complete the prescription before saving.</p>
                        </div>
                        <button class="btn btn-outline"
                                onclick="toggleForm('prescriptionForm',false)">Close</button>
                    </div>

                    <div class="panel-body">
                        <form id="createPrescriptionForm">
                            <div class="form-grid">
                                <div class="field">
                                    <label for="presPatientName">Patient Name *</label>
                                    <input id="presPatientName" required>
                                </div>

                                <div class="field">
                                    <label for="presPatientId">Patient ID</label>
                                    <input id="presPatientId" placeholder="e.g. MC-P001">
                                </div>

                                <div class="field full">
                                    <label for="presDiagnosis">Diagnosis *</label>
                                    <input id="presDiagnosis" required>
                                </div>

                                <div class="field full">
                                    <label for="presMedicine">Medicine Name *</label>
                                    <textarea id="presMedicine" required></textarea>
                                </div>

                                <div class="field">
                                    <label for="presDosage">Dosage / Frequency *</label>
                                    <input id="presDosage" required>
                                </div>

                                <div class="field">
                                    <label for="presDuration">Duration *</label>
                                    <input id="presDuration" required>
                                </div>

                                <div class="field full">
                                    <label for="presInstructions">Additional Instructions</label>
                                    <textarea id="presInstructions"></textarea>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-outline"
                                        onclick="toggleForm('prescriptionForm',false)">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Prescription
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Prescription Records</h3>
                            <p>Your saved digital prescriptions.</p>
                        </div>
                    </div>
                    <div class="panel-body" id="prescriptionsList"></div>
                </div>
            </section>

            <!-- VIDEO CONSULTATIONS -->
            <section class="page" id="page-video">
                <div class="toolbar">
                    <div>
                        <h2>Video Consultations</h2>
                        <p>Review online consultation appointments.</p>
                    </div>

                    <label class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input id="videoSearch" placeholder="Search patient..."
                               oninput="renderVideoAppointments()">
                    </label>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Online Consultation Schedule</h3>
                            <p>Video appointments assigned to you.</p>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Consultation</th>
                            </tr>
                            </thead>
                            <tbody id="videoTable"></tbody>
                        </table>
                    </div>
                </div>

                <p class="note">
                    A real video call requires integration with a video-conferencing service.
                </p>
            </section>

            <div class="footer-note">
                © <span id="year"></span> MediConnect · Doctor Workspace
            </div>
        </div>
    </main>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script src="doctor-dashboard.js?v=5"></script>

</body>
</html>