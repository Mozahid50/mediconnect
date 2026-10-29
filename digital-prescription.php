<?php
session_start();
require_once 'config.php';

/*
 * MediConnect Digital Prescription
 * Uses existing patients, doctors and prescriptions tables.
 */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection is unavailable. Please check config.php.');
}

/*
 * Support common patient session formats.
 * Keep these names consistent with your actual login handler.
 */
$sessionPatientDbId = $_SESSION['patient_db_id'] ?? null;
$sessionPatientCode = $_SESSION['patient_id'] ?? null;

if (
    ($_SESSION['user_role'] ?? '') !== 'patient' ||
    (!$sessionPatientDbId && !$sessionPatientCode)
) {
    header('Location: login.php?redirect=digital-prescription.php');
    exit;
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatDate($value): string
{
    if (!$value || $value === '0000-00-00') {
        return 'Not specified';
    }

    $time = strtotime($value);
    return $time ? date('d M Y', $time) : 'Not specified';
}

function patientAge($dob): string
{
    if (!$dob || $dob === '0000-00-00') {
        return 'Not specified';
    }

    try {
        return (new DateTime($dob))->diff(new DateTime())->y . ' years';
    } catch (Throwable $e) {
        return 'Not specified';
    }
}

$patient = null;
$prescriptions = [];
$pageError = '';

try {
    /*
     * Resolve the patient from the authenticated session.
     * Never accept a patient ID from a URL or form as proof of identity.
     */
    if ($sessionPatientDbId) {
        $stmt = $pdo->prepare(
            'SELECT id, patient_id, name, dob, gender
             FROM patients
             WHERE id = :db_id
             LIMIT 1'
        );
        $stmt->execute(['db_id' => $sessionPatientDbId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$patient && $sessionPatientCode) {
        $stmt = $pdo->prepare(
            'SELECT id, patient_id, name, dob, gender
             FROM patients
             WHERE patient_id = :patient_code
             LIMIT 1'
        );
        $stmt->execute(['patient_code' => $sessionPatientCode]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$patient) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php?error=patient_session');
        exit;
    }

    /*
     * Accept either patient identifier format in the existing
     * prescriptions table:
     * - patients.patient_id (example: MC-P001)
     * - patients.id (numeric database ID)
     *
     * The WHERE clause is tied to the authenticated patient record.
     */
    $stmt = $pdo->prepare(
        "SELECT
            rx.id,
            rx.patient_id AS prescription_patient_id,
            rx.diagnosis,
            rx.medicines,
            rx.dosage,
            rx.frequency,
            rx.duration,
            rx.instructions,
            rx.follow_up_date,
            rx.created_at,
           d.name AS doctor_name,
NULL AS doctor_speciality,
NULL AS doctor_qualification,
NULL AS doctor_phone
         FROM prescriptions rx
         LEFT JOIN doctors d ON d.id = rx.doctor_id
         WHERE
            CAST(rx.patient_id AS CHAR) = :patient_code
            OR CAST(rx.patient_id AS CHAR) = :patient_db_id
         ORDER BY rx.created_at DESC, rx.id DESC"
    );

    $stmt->execute([
        'patient_code' => (string)$patient['patient_id'],
        'patient_db_id' => (string)$patient['id']
    ]);

    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log('MediConnect prescription page: ' . $ex->getMessage());

    $pageError = 'Database Error: ' . $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#087f8c">
    <title>My Prescriptions | MediConnect</title>

    <link rel="stylesheet" href="digital-prescription.css">

    <style>
        :root {
            --mc-primary: #087f8c;
            --mc-primary-dark: #066570;
            --mc-ink: #183344;
            --mc-muted: #6b7d8b;
            --mc-border: #e2eaf0;
            --mc-bg: #f3f7fa;
            --mc-white: #fff;
            --mc-success: #168568;
            --mc-shadow: 0 12px 34px rgba(22, 52, 72, .07);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--mc-bg);
            color: var(--mc-ink);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
        }

        .header {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 22px;
            padding: 16px max(5%, calc((100% - 1200px)/2));
            background: rgba(255,255,255,.97);
            border-bottom: 1px solid var(--mc-border);
        }

        .logo {
            color: var(--mc-primary);
            font-weight: 800;
            font-size: 21px;
            text-decoration: none;
            white-space: nowrap;
        }

        .logo span { margin-right: 6px; }

        .header nav {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .header nav a {
            color: #5c7180;
            text-decoration: none;
            font-size: 13px;
            font-weight: 650;
            padding: 10px 12px;
            border-radius: 9px;
        }

        .header nav a:hover,
        .header nav a.active {
            background: #e8f6f7;
            color: var(--mc-primary-dark);
        }

        .container {
            width: min(1100px, 92%);
            margin: 36px auto 64px;
        }

        .welcome-panel {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 26px;
        }

        .eyebrow {
            margin: 0 0 9px;
            color: var(--mc-primary);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.8px;
        }

        .welcome-panel h1 {
            margin: 0;
            font-size: clamp(27px, 4vw, 36px);
            letter-spacing: -1px;
        }

        .welcome-panel p.description {
            color: var(--mc-muted);
            line-height: 1.7;
            margin: 10px 0 0;
            font-size: 14px;
        }

        .patient-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #fff;
            border: 1px solid var(--mc-border);
            border-radius: 14px;
        }

        .avatar {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            background: #e2f5f5;
            color: var(--mc-primary);
            border-radius: 13px;
            font-size: 20px;
            font-weight: 800;
        }

        .patient-chip strong,
        .patient-chip small { display: block; }

        .patient-chip strong { font-size: 14px; }
        .patient-chip small {
            margin-top: 4px;
            color: var(--mc-muted);
            font-size: 12px;
        }

        .summary-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 26px;
        }

        .summary-card {
            background: #fff;
            padding: 20px;
            border: 1px solid var(--mc-border);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(22,52,72,.025);
        }

        .summary-label {
            color: var(--mc-muted);
            font-size: 12px;
            font-weight: 650;
        }

        .summary-value {
            margin-top: 9px;
            font-size: 27px;
            font-weight: 800;
            color: var(--mc-ink);
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 30px 0 16px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: 20px;
        }

        .section-heading span {
            color: var(--mc-muted);
            font-size: 12px;
        }

        .prescription-list {
            display: grid;
            gap: 24px;
        }

        .prescription-card {
            overflow: hidden;
            background: var(--mc-white);
            border: 1px solid var(--mc-border);
            border-radius: 17px;
            box-shadow: var(--mc-shadow);
        }

        .rx-topbar {
            height: 5px;
            background: linear-gradient(90deg, #087f8c, #5bc5c4);
        }

        .rx-inner { padding: clamp(18px, 4vw, 34px); }

        .rx-brand {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            padding-bottom: 23px;
            border-bottom: 1px solid var(--mc-border);
        }

        .brand-lockup {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .clinic-logo {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: var(--mc-primary);
            color: white;
            font-size: 27px;
            font-weight: 800;
        }

        .brand-lockup h2 { margin: 0; font-size: 20px; }
        .brand-lockup p {
            margin: 5px 0 0;
            color: var(--mc-muted);
            font-size: 12px;
        }

        .record-status {
            padding: 8px 10px;
            border-radius: 30px;
            color: var(--mc-success);
            background: #e8f7f0;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .rx-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            padding: 22px 0;
        }

        .rx-title h2 {
            margin: 0;
            font-size: 17px;
            letter-spacing: 1px;
        }

        .rx-id {
            font-size: 12px;
            color: var(--mc-muted);
            overflow-wrap: anywhere;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 20px;
        }

        .info-panel {
            min-width: 0;
            padding: 18px;
            border: 1px solid var(--mc-border);
            border-radius: 12px;
            background: #fbfdfe;
        }

        .info-panel h3,
        .rx-section h3 {
            margin: 0 0 13px;
            font-size: 13px;
            font-weight: 800;
        }

        .doctor-name {
            font-size: 16px;
            font-weight: 800;
        }

        .doctor-meta {
            margin-top: 6px;
            color: var(--mc-muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .info-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 6px 0;
            font-size: 12px;
        }

        .info-line span:first-child { color: var(--mc-muted); }
        .info-line span:last-child {
            text-align: right;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .rx-section { margin-top: 20px; }

        .rx-section h3 {
            padding-bottom: 11px;
            border-bottom: 1px solid var(--mc-border);
        }

        .diagnosis-box,
        .advice-box {
            padding: 15px;
            border-radius: 10px;
            background: #f7fafc;
            color: #3c5362;
            line-height: 1.8;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--mc-border);
            border-radius: 10px;
        }

        .medicine-table {
            width: 100%;
            min-width: 520px;
            border-collapse: collapse;
            font-size: 12px;
        }

        .medicine-table th {
            padding: 13px 12px;
            background: #edf6f7;
            color: #315766;
            text-align: left;
            font-size: 11px;
        }

        .medicine-table td {
            padding: 14px 12px;
            vertical-align: top;
            border-top: 1px solid var(--mc-border);
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            line-height: 1.7;
        }

        .followup-box {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding: 15px 17px;
            background: #eaf7f7;
            border: 1px solid #d6eeee;
            border-radius: 11px;
        }

        .followup-box span {
            color: var(--mc-primary-dark);
            font-size: 11px;
            font-weight: 850;
            letter-spacing: 1px;
        }

        .followup-box strong { font-size: 13px; }

        .rx-footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
            padding-top: 17px;
            border-top: 1px solid var(--mc-border);
            color: var(--mc-muted);
            font-size: 10px;
            line-height: 1.7;
        }

        .signature-note {
            margin-top: 18px;
            color: var(--mc-muted);
            font-size: 10px;
        }

        .empty-state,
        .error-state {
            padding: 38px 22px;
            text-align: center;
            background: #fff;
            border: 1px solid var(--mc-border);
            border-radius: 16px;
            box-shadow: var(--mc-shadow);
        }

        .empty-icon {
            display: grid;
            place-items: center;
            width: 58px;
            height: 58px;
            margin: 0 auto 16px;
            border-radius: 18px;
            background: #e8f6f7;
            color: var(--mc-primary);
            font-size: 28px;
        }

        .empty-state h2,
        .error-state h2 { margin: 0 0 9px; }

        .empty-state p,
        .error-state p {
            max-width: 480px;
            margin: 0 auto;
            color: var(--mc-muted);
            line-height: 1.7;
            font-size: 13px;
        }

        .error-state { color: #a52828; }

        .bottom-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 26px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 43px;
            padding: 11px 16px;
            border: 0;
            border-radius: 10px;
            font: inherit;
            font-size: 12px;
            font-weight: 750;
            text-decoration: none;
            cursor: pointer;
        }

        .primary-action {
            color: #fff;
            background: var(--mc-primary);
        }

        .primary-action:hover { background: var(--mc-primary-dark); }

        .secondary-action {
            color: var(--mc-ink);
            background: #fff;
            border: 1px solid var(--mc-border);
        }

        .no-print { display: initial; }

        @media (max-width: 700px) {
            .header {
                align-items: flex-start;
                flex-direction: column;
                padding: 15px 4%;
            }

            .header nav { gap: 2px; }
            .header nav a { padding: 8px; font-size: 12px; }
            .container { margin-top: 25px; }
            .summary-row { grid-template-columns: 1fr; gap: 10px; }
            .summary-card { padding: 15px; }
            .summary-value { font-size: 23px; }
            .info-grid { grid-template-columns: 1fr; }
            .record-status { font-size: 10px; }
        }

        @media print {
            body { background: #fff !important; }
            .header, .welcome-panel, .summary-row, .section-heading,
            .bottom-actions, .no-print, .empty-state, .error-state {
                display: none !important;
            }

            .container {
                width: 100% !important;
                margin: 0 !important;
            }

            .prescription-list { display: block; }
            .prescription-card {
                border: 0;
                border-radius: 0;
                box-shadow: none;
                margin: 0 0 18px;
                break-after: page;
            }

            .prescription-card:last-child { break-after: auto; }
            .rx-inner { padding: 0; }
            .rx-topbar { background: #087f8c !important; }
            .info-panel, .diagnosis-box, .advice-box, .followup-box {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            @page { size: A4; margin: 13mm; }
        }
        .rx-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
    padding: 16px 24px;
    background: #f8fbfc;
    border-bottom: 1px solid var(--mc-border);
}

@media (max-width: 500px) {
    .rx-actions .action-btn {
        flex: 1 1 100%;
    }
}

@media print {
    .rx-actions {
        display: none !important;
    }
}
    </style>
</head>

<body>

<header class="header">
    <a href="index.php" class="logo"><span>✚</span>MediConnect</a>

    <nav>
        <a href="patient-dashboard.php">Dashboard</a>
        <a href="appointment.php">Appointments</a>
        <a href="medical-reports.php">Medical Reports</a>
        <a href="digital-prescription.php" class="active">Prescriptions</a>
    </nav>
</header>

<main class="container">

    <?php if (!$pageError && $patient): ?>
        <section class="welcome-panel">
            <div>
                <p class="eyebrow">YOUR PERSONAL HEALTH RECORDS</p>
                <h1>My Prescriptions</h1>
                <p class="description">
                    Secure access to prescriptions issued to your patient account.
                </p>
            </div>

            <div class="patient-chip">
                <div class="avatar">✚</div>
                <div>
                    <strong><?= e($patient['name']) ?></strong>
                    <small>Patient ID: <?= e($patient['patient_id']) ?></small>
                </div>
            </div>
        </section>

        <section class="summary-row">
            <div class="summary-card">
                <div class="summary-label">Total Prescriptions</div>
                <div class="summary-value"><?= count($prescriptions) ?></div>
            </div>

            <div class="summary-card">
                <div class="summary-label">Patient Record</div>
                <div class="summary-value" style="font-size:20px;">Verified</div>
            </div>

            <div class="summary-card">
                <div class="summary-label">Latest Prescription</div>
                <div class="summary-value" style="font-size:17px;">
                    <?= !empty($prescriptions)
                        ? e(formatDate($prescriptions[0]['created_at']))
                        : 'No records' ?>
                </div>
            </div>
        </section>

        <div class="section-heading">
            <h2>Prescription History</h2>
            <span><?= count($prescriptions) ?> record(s)</span>
        </div>
    <?php endif; ?>

    <?php if ($pageError): ?>
        <section class="error-state">
            <div class="empty-icon">!</div>
            <h2>Unable to Load Prescriptions</h2>
            <p><?= e($pageError) ?></p>
        </section>

    <?php elseif (empty($prescriptions)): ?>
        <section class="empty-state">
            <div class="empty-icon">℞</div>
            <h2>No Prescriptions Available</h2>
            <p>
                Your prescription history will appear here when your doctor
                saves a prescription for your patient account.
            </p>
        </section>

    <?php else: ?>
        <div class="prescription-list">
            <?php foreach ($prescriptions as $rx): ?>
                <article class="prescription-card">
                    <div class="rx-topbar"></div>

                    <div class="rx-inner">
                        <div class="rx-actions no-print">
    <button type="button"
            class="action-btn secondary-action"
            onclick="printPrescription(this)">
        <i class="fa-solid fa-print"></i> Print Prescription
    </button>

    <button type="button"
            class="action-btn primary-action"
            onclick="downloadPrescription(this)">
        <i class="fa-solid fa-file-pdf"></i> Download PDF
    </button>
</div>
                        <div class="rx-brand">
                            <div class="brand-lockup">
                                <div class="clinic-logo">✚</div>
                                <div>
                                    <h2>MediConnect</h2>
                                    <p>Digital Healthcare &amp; Consultation</p>
                                </div>
                            </div>
                            <span class="record-status">✓ DIGITAL RECORD</span>
                        </div>

                        <div class="rx-title">
                            <h2>MEDICAL PRESCRIPTION</h2>
                            <span class="rx-id">
                                Prescription ID: MC-RX-<?= e($rx['id']) ?>
                            </span>
                        </div>

                        <div class="info-grid">
                            <section class="info-panel">
                                <h3>ISSUED BY</h3>
                                <div class="doctor-name">
                                    <?= e($rx['doctor_name'] ?: 'Doctor information unavailable') ?>
                                </div>
                                <div class="doctor-meta">
                                    <?= e($rx['doctor_qualification'] ?? '') ?><br>
                                    <?= e($rx['doctor_speciality'] ?? '') ?>
                                    <?php if (!empty($rx['doctor_phone'])): ?>
                                        <br>Phone: <?= e($rx['doctor_phone']) ?>
                                    <?php endif; ?>
                                </div>
                            </section>

                            <section class="info-panel">
                                <h3>PATIENT DETAILS</h3>
                                <div class="info-line">
                                    <span>Full Name</span>
                                    <span><?= e($patient['name']) ?></span>
                                </div>
                                <div class="info-line">
                                    <span>Patient ID</span>
                                    <span><?= e($patient['patient_id']) ?></span>
                                </div>
                                <div class="info-line">
                                    <span>Age</span>
                                    <span><?= e(patientAge($patient['dob'] ?? null)) ?></span>
                                </div>
                                <div class="info-line">
                                    <span>Gender</span>
                                    <span><?= e($patient['gender'] ?: 'Not specified') ?></span>
                                </div>
                                <div class="info-line">
                                    <span>Issued On</span>
                                    <span><?= e(formatDate($rx['created_at'])) ?></span>
                                </div>
                            </section>
                        </div>

                        <section class="rx-section">
                            <h3>Diagnosis / Clinical Notes</h3>
                            <div class="diagnosis-box">
                                <?= nl2br(e($rx['diagnosis'] ?: 'Not specified')) ?>
                            </div>
                        </section>

                        <section class="rx-section">
                            <h3>℞ Prescribed Medicines</h3>
                            <div class="table-wrap">
                                <table class="medicine-table">
                                    <thead>
                                        <tr>
                                            <th>Medicine</th>
                                            <th>Dosage</th>
                                            <th>Frequency</th>
                                            <th>Duration</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?= nl2br(e($rx['medicines'])) ?></td>
                                            <td><?= nl2br(e($rx['dosage'])) ?></td>
                                            <td><?= nl2br(e($rx['frequency'])) ?></td>
                                            <td><?= nl2br(e($rx['duration'])) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="rx-section">
                            <h3>Doctor's Advice</h3>
                            <div class="advice-box">
                                <?= nl2br(e($rx['instructions'] ?: 'Follow your doctor’s instructions.')) ?>
                            </div>
                        </section>

                        <div class="followup-box">
                            <span>FOLLOW-UP APPOINTMENT</span>
                            <strong>
                                <?= !empty($rx['follow_up_date'])
                                    ? e(formatDate($rx['follow_up_date']))
                                    : 'As advised by your doctor' ?>
                            </strong>
                        </div>

                        <div class="signature-note">
                            This record displays information saved by your healthcare provider.
                            Contact your doctor if any prescription details appear incorrect.
                        </div>

                        <footer class="rx-footer">
                            <span>MediConnect · Healthcare at Your Convenience</span>
                            <span>Prescription record #<?= e($rx['id']) ?></span>
                        </footer>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="bottom-actions">
        <a href="dashboard-router.php" class="action-btn secondary-action">
            ← Back to Dashboard
        </a>

        <?php if (!empty($prescriptions) && !$pageError): ?>
            <button class="action-btn primary-action no-print"
                    type="button" onclick="window.print()">
                Print / Save as PDF
            </button>
        <?php endif; ?>
    </div>

</main>

</body>
</html>