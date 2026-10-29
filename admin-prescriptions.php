<?php
require_once __DIR__ . '/config.php';

$prescriptions = [];
$error = '';

try {
    $sql = "
        SELECT
            pr.id,
            pr.patient_id,
            pr.doctor_id,
            pr.diagnosis,
            pr.medicines,
            pr.dosage,
            pr.frequency,
            pr.duration,
            pr.instructions,
            pr.follow_up_date,
            pr.created_at,
            p.name AS patient_name,
            d.name AS doctor_name
        FROM prescriptions pr
        LEFT JOIN patients p
            ON p.patient_id = pr.patient_id
        LEFT JOIN doctors d
            ON d.id = pr.doctor_id
        ORDER BY pr.created_at DESC, pr.id DESC
    ";

    $stmt = $pdo->query($sql);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Could not load prescriptions. Please check the database table structure.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Management | MediConnect</title>
    <link rel="stylesheet" href="admin-panel.css">
<link rel="stylesheet" href="admin-prescriptions.css">
    <style>
        .prescription-content {
            padding: 30px;
            max-width: 1400px;
            margin: auto;
        }

        .prescription-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .prescription-heading h1 {
            margin: 0 0 8px;
        }

        .prescription-heading p {
            margin: 0;
            color: #64748b;
        }

        .prescription-search {
            width: 100%;
            max-width: 360px;
            padding: 12px 14px;
            border: 1px solid #dbe3ee;
            border-radius: 9px;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .prescription-message {
            background: #fff7ed;
            color: #9a3412;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .prescription-count {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 14px;
        }

        .prescription-table {
            width: 100%;
            border-collapse: collapse;
        }

        .prescription-table th,
        .prescription-table td {
            padding: 13px;
            border-bottom: 1px solid #e8edf4;
            text-align: left;
            vertical-align: top;
        }

        .prescription-table th {
            background: #f8fafc;
            white-space: nowrap;
        }

        .prescription-table td {
            overflow-wrap: anywhere;
        }

        .prescription-empty {
            text-align: center;
            padding: 35px 15px;
            color: #64748b;
        }

        .prescription-details {
            max-width: 240px;
            white-space: pre-wrap;
        }

        @media (max-width: 700px) {
            .prescription-content {
                padding: 16px;
            }

            .prescription-table {
                min-width: 1000px;
            }

            .prescription-table-wrap {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

<div class="prescription-content">
    <div class="prescription-heading">
        <div>
            <h1>Digital Prescription Management</h1>
            <p>View prescriptions saved in the MediConnect database.</p>
        </div>

        <a href="admin-panel.php" class="outline-btn">
            &larr; Back to Admin Panel
        </a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="prescription-message">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php else: ?>

        <input
            type="search"
            id="prescriptionSearch"
            class="prescription-search"
            placeholder="Search patient, doctor or prescription ID..."
            aria-label="Search prescriptions"
        >

        <div class="prescription-count">
            Total prescriptions: <?= count($prescriptions) ?>
        </div>

        <div class="section-card prescription-table-wrap">
            <table class="prescription-table" id="prescriptionTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th>Medicine</th>
                        <th>Dosage / Frequency</th>
                        <th>Duration</th>
                        <th>Follow-up</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$prescriptions): ?>
                    <tr>
                        <td colspan="9" class="prescription-empty">
                            No prescriptions found. Prescriptions will appear
                            here after they are saved in the database.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($prescriptions as $pr): ?>
                        <tr>
                            <td>
                                MC-RX-<?= (int) $pr['id'] ?>
                            </td>
                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $pr['patient_name'] ?? 'Unknown patient',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                                <br>
                                <small>
                                    <?= htmlspecialchars(
                                        $pr['patient_id'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </small>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    $pr['doctor_name'] ?? 'Unknown doctor',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td class="prescription-details">
                                <?= htmlspecialchars(
                                    $pr['diagnosis'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td class="prescription-details">
                                <?= htmlspecialchars(
                                    $pr['medicines'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td class="prescription-details">
                                <?= htmlspecialchars(
                                    trim(
                                        ($pr['dosage'] ?? '') . ' / ' .
                                        ($pr['frequency'] ?? ''),
                                        ' /'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    $pr['duration'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    $pr['follow_up_date'] ?? 'Not set',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    $pr['created_at'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('prescriptionSearch');

if (searchInput) {
    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();

        document.querySelectorAll('#prescriptionTable tbody tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(query)
                ? ''
                : 'none';
        });
    });
}
</script>

</body>
</html>