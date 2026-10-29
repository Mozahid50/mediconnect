<?php
session_start();
require_once 'config.php';

if (!isset($pdo)) {
    die('Database connection not found. Check config.php');
}

$error = '';
$success = '';

$selectedDoctor = isset($_GET['doctor_id'])
    ? (int) $_GET['doctor_id']
    : (int) ($_POST['doctor_id'] ?? 0);

$doctors = $pdo->query(
    "SELECT id, name, specialty, qualification, fee, photo
     FROM doctors
     WHERE status = 'Active'
     ORDER BY name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$doctorIds = array_column($doctors, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_SESSION['patient_db_id']) ||
        ($_SESSION['user_role'] ?? '') !== 'patient'
    ) {
        header('Location: login.php');
        exit;
    }

    $doctorId = (int) ($_POST['doctor_id'] ?? 0);
    $date = trim($_POST['appointment_date'] ?? '');
    $time = trim($_POST['appointment_time'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (!in_array($doctorId, $doctorIds)) {
        $error = 'Please select a valid active doctor.';
    } elseif (!$date || !$time) {
        $error = 'Please select an appointment date and time.';
    } elseif ($date < date('Y-m-d')) {
        $error = 'Past dates cannot be selected.';
    } elseif (strlen($reason) > 1000) {
        $error = 'Reason must be within 1000 characters.';
    } else {
        try {
            $check = $pdo->prepare(
                "SELECT id FROM doctors
                 WHERE id = ? AND status = 'Active'"
            );
            $check->execute([$doctorId]);

            if (!$check->fetch()) {
                $error = 'This doctor is no longer available.';
            } else {
                /*
                 * The appointments table structure may differ
                 * in your database. If an SQL error appears,
                 * send me the exact error and we will match it.
                 */
                $stmt = $pdo->prepare(
                    "INSERT INTO appointments
                    (patient_id, doctor_id, appointment_date,
                     appointment_time, reason, status)
                    VALUES (?, ?, ?, ?, ?, 'Pending')"
                );

                $stmt->execute([
    $_SESSION['patient_db_id'],
    $doctorId,
    $date,
    $time,
    $reason
]);

$newAppointmentId = (int) $pdo->lastInsertId();

$success = 'Appointment request submitted successfully!';
$selectedDoctor = $doctorId;
            }
        } catch (PDOException $e) {
            $error = 'Could not save the appointment. Your appointments table may need to be adjusted to match this form.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Appointment | MediConnect</title>
<style>
:root {
    --primary: #087f8c;
    --primary-dark: #066773;
    --primary-light: #e8f8f8;
    --text: #173b43;
    --muted: #718096;
    --border: #e2eaf0;
    --white: #ffffff;
    --background: #f3f8fb;
    --shadow: 0 18px 50px rgba(20, 65, 80, 0.08);
}

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family: Inter, "Segoe UI", Arial, sans-serif;
    color: var(--text);
    background:
        radial-gradient(ellipse at top left, #e0f5f5 0, transparent 35%),
        var(--background);
    line-height: 1.6;
}

/* Header */

header {
    min-height: 76px;
    padding: 16px 7%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: rgba(255, 255, 255, 0.94);
    border-bottom: 1px solid rgba(226, 234, 240, 0.9);
    box-shadow: 0 3px 18px rgba(20, 65, 80, 0.035);
}

.brand {
    color: var(--primary);
    font-size: 25px;
    font-weight: 800;
    letter-spacing: -0.8px;
}

header a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--primary);
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    padding: 10px 16px;
    border: 1px solid #cce9e9;
    border-radius: 10px;
    transition: 0.25s ease;
}

header a:hover {
    background: var(--primary-light);
    transform: translateY(-1px);
}

/* Main layout */

main {
    width: min(900px, calc(100% - 36px));
    margin: 48px auto 70px;
}

.intro {
    max-width: 650px;
    margin: 0 auto 32px;
    text-align: center;
}

.intro h1 {
    margin: 0 0 12px;
    color: #153c44;
    font-size: clamp(29px, 4vw, 40px);
    font-weight: 800;
    letter-spacing: -1.2px;
    line-height: 1.2;
}

.intro p {
    margin: 0;
    color: var(--muted);
    font-size: 16px;
}

/* Booking panel */

.panel {
    padding: 38px 40px;
    background: var(--white);
    border: 1px solid rgba(226, 234, 240, 0.95);
    border-radius: 22px;
    box-shadow: var(--shadow);
}

label {
    display: block;
    margin: 22px 0 9px;
    color: #254751;
    font-size: 14px;
    font-weight: 700;
}

input,
select,
textarea {
    display: block;
    width: 100%;
    min-height: 51px;
    padding: 13px 15px;
    color: #24444c;
    background: #fbfdfe;
    border: 1px solid #dce6ed;
    border-radius: 11px;
    outline: none;
    font-family: inherit;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}

select {
    cursor: pointer;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

input:hover,
select:hover,
textarea:hover {
    border-color: #b4d7dc;
}

input:focus,
select:focus,
textarea:focus {
    background: #fff;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(8, 127, 140, 0.10);
}

input::placeholder,
textarea::placeholder {
    color: #9aaab6;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.grid label {
    margin-top: 24px;
}

/* Selected doctor information */

.doctor-info {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-top: 18px;
    padding: 19px;
    background: linear-gradient(135deg, #f0fbfb, #f8fcfd);
    border: 1px solid #d5eeee;
    border-radius: 15px;
    transition: 0.25s ease;
}

.doctor-info[hidden] {
    display: none;
}

.doctor-info img {
    flex-shrink: 0;
    width: 78px;
    height: 78px;
    object-fit: cover;
    border: 3px solid white;
    border-radius: 15px;
    background: #dceff0;
    box-shadow: 0 3px 12px rgba(20, 65, 80, 0.10);
}

.doctor-info strong {
    display: block;
    color: #173e46;
    font-size: 17px;
    font-weight: 800;
}

.doctor-info p {
    margin: 4px 0 0;
    color: var(--muted);
    font-size: 13px;
}

/* Helper text */

.hint {
    margin: 17px 0 0;
    padding: 12px 14px;
    color: #607782;
    background: #f5f9fb;
    border-left: 3px solid #7bc8cc;
    border-radius: 7px;
    font-size: 12px;
}

/* Messages */

.message {
    margin-bottom: 20px;
    padding: 15px 17px;
    border: 1px solid transparent;
    border-radius: 11px;
    font-size: 14px;
}

.message p {
    margin-bottom: 0;
}

.message a {
    color: inherit;
    font-weight: 700;
}

.error {
    color: #a52b3c;
    background: #fff3f4;
    border-color: #ffd9de;
}

.success {
    color: #187044;
    background: #effbf4;
    border-color: #c9efd7;
}

/* Submit button */

button {
    width: 100%;
    min-height: 54px;
    margin-top: 25px;
    padding: 15px 22px;
    color: white;
    background: linear-gradient(135deg, #098b97, #076c79);
    border: 0;
    border-radius: 11px;
    box-shadow: 0 8px 18px rgba(8, 127, 140, 0.20);
    font-family: inherit;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s, filter 0.2s;
}

button:hover {
    filter: brightness(1.04);
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(8, 127, 140, 0.25);
}

button:active {
    transform: translateY(0);
}

button:focus-visible,
header a:focus-visible {
    outline: 3px solid #9ddfe2;
    outline-offset: 3px;
}

/* Responsive design */

@media (max-width: 640px) {
    header {
        min-height: 68px;
        padding: 13px 18px;
    }

    .brand {
        font-size: 21px;
    }

    header a {
        padding: 9px 11px;
        font-size: 12px;
    }

    main {
        width: calc(100% - 26px);
        margin: 30px auto 45px;
    }

    .intro {
        margin-bottom: 23px;
    }

    .intro h1 {
        font-size: 29px;
    }

    .intro p {
        font-size: 14px;
    }

    .panel {
        padding: 23px 19px;
        border-radius: 17px;
    }

    .grid {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .grid label {
        margin-top: 20px;
    }

    .doctor-info {
        padding: 14px;
        gap: 13px;
    }

    .doctor-info img {
        width: 65px;
        height: 65px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        transition: none !important;
    }
}
</style>
</head>
<body>
<header>
    <div class="brand">MediConnect</div>
    <a href="doctors.php">← Find Doctors</a>
</header>

<main>
    <div class="intro">
        <h1>Book an Appointment</h1>
        <p>Choose your doctor and preferred consultation time.</p>
    </div>

    <section class="panel">
        <?php if ($error): ?>
            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success">
                <?= htmlspecialchars($success) ?>
                <p>
                    <a href="patient-dashboard.php">Go to Patient Dashboard</a>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!$doctors): ?>
            <p>No active doctors are available right now.</p>
            <a href="doctors.php">Back to Find Doctors</a>
        <?php else: ?>
        <form method="POST" action="appointment.php">
            <label for="doctor_id">Select Doctor *</label>
            <select name="doctor_id" id="doctor_id" required>
                <option value="">Choose a doctor</option>
                <?php foreach ($doctors as $doctor): ?>
                    <option
                        value="<?= (int)$doctor['id'] ?>"
                        <?= $selectedDoctor === (int)$doctor['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($doctor['name']) ?>
                        — <?= htmlspecialchars($doctor['specialty']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div id="doctorInfo" class="doctor-info" hidden>
                <img id="doctorPhoto" src="" alt="Doctor photo">
                <div>
                    <strong id="doctorName"></strong>
                    <p id="doctorSpecialty"></p>
                    <p id="doctorFee"></p>
                </div>
            </div>

            <div class="grid">
                <div>
                    <label for="appointment_date">Appointment Date *</label>
                    <input
                        type="date"
                        name="appointment_date"
                        id="appointment_date"
                        min="<?= date('Y-m-d') ?>"
                        required>
                </div>
                <div>
                    <label for="appointment_time">Appointment Time *</label>
                    <input
                        type="time"
                        name="appointment_time"
                        id="appointment_time"
                        required>
                </div>
            </div>

            <label for="reason">Reason for Consultation</label>
            <textarea
                name="reason"
                id="reason"
                rows="4"
                maxlength="1000"
                placeholder="Briefly describe your health concern..."></textarea>

            <p class="hint">
                Your request will be submitted with Pending status until reviewed.
            </p>

            <button type="submit">Submit Appointment Request</button>
        </form>
        <?php endif; ?>
    </section>
</main>

<script>
const doctors = <?= json_encode(
    $doctors,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const select = document.getElementById('doctor_id');
const info = document.getElementById('doctorInfo');

function updateDoctorInfo() {
    const doctor = doctors.find(d => String(d.id) === select.value);

    if (!doctor) {
        info.hidden = true;
        return;
    }

    document.getElementById('doctorName').textContent = doctor.name;
    document.getElementById('doctorSpecialty').textContent =
        doctor.specialty + (doctor.qualification ? ' · ' + doctor.qualification : '');
    document.getElementById('doctorFee').textContent =
        'Consultation fee: ৳' + Number(doctor.fee || 0).toFixed(2);

    const photo = document.getElementById('doctorPhoto');
    photo.src = doctor.photo || 'images/doctor-placeholder.png';
    photo.onerror = function() {
        this.onerror = null;
        this.removeAttribute('src');
        this.style.visibility = 'hidden';
    };
    photo.style.visibility = 'visible';

    info.hidden = false;
}

select.addEventListener('change', updateDoctorInfo);
updateDoctorInfo();
</script>
</body>
</html>