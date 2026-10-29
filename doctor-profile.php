<?php
require_once 'config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection failed. Please check config.php.');
}

$doctorId = filter_input(INPUT_GET, 'doctor', FILTER_VALIDATE_INT);
$doctor = null;

if ($doctorId && $doctorId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, name, specialty, qualification, experience,
               fee, phone, photo
        FROM doctors
        WHERE id = :id
          AND LOWER(TRIM(status)) = 'active'
        LIMIT 1
    ");

    $stmt->execute(['id' => $doctorId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function profilePhoto(?string $photo): string {
    if (
        $photo &&
        preg_match('#^https://#i', $photo)
    ) {
        return $photo;
    }

    if (
        $photo &&
        strpos($photo, 'uploads/doctors/') === 0 &&
        !str_contains($photo, '..') &&
        !str_contains($photo, '\\')
    ) {
        return $photo;
    }

    return 'https://placehold.co/600x700/e5f1f4/31566b?text=Doctor+Photo';
}

$phone = preg_replace('/\D+/', '', (string)($doctor['phone'] ?? ''));

if (strlen($phone) === 11 && str_starts_with($phone, '01')) {
    $phone = '88' . $phone;
} elseif (strlen($phone) === 10 && str_starts_with($phone, '1')) {
    $phone = '880' . $phone;
}

$whatsappUrl = $phone
    ? 'https://wa.me/' . $phone . '?text=' .
        rawurlencode('Hello ' . ($doctor['name'] ?? 'Doctor') .
        ', I would like to know about your consultation.')
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $doctor ? h($doctor['name']) . ' | Doctor Profile' : 'Doctor Not Found' ?> | MediConnect</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:Inter,sans-serif;background:#f5f8fc;color:#536779;line-height:1.7}
        a{text-decoration:none}
        .navbar{background:#fff;padding:17px 7%;display:flex;justify-content:space-between;align-items:center;gap:20px;box-shadow:0 3px 18px #1536500c}
        .brand{font-size:25px;font-weight:800;color:#153650}
        .brand span,.footer span{color:#079e99}
        .nav-links{display:flex;gap:24px;align-items:center;flex-wrap:wrap}
        .nav-links a{font-size:13px;font-weight:600;color:#536779}
        .nav-links a:hover{color:#079e99}
        .nav-links .nav-btn{background:#079e99;color:white;padding:10px 17px;border-radius:8px}
        .breadcrumb{max-width:1120px;margin:25px auto;padding:0 20px;font-size:12px;color:#81909e}
        .breadcrumb a{color:#078f91}
        .container{max-width:1120px;padding:0 20px 65px;margin:auto}
        .profile-header{display:grid;grid-template-columns:270px minmax(0,1fr);gap:30px;background:white;border:1px solid #e5edf3;border-radius:16px;padding:30px;box-shadow:0 8px 30px #15365008}
        .doctor-image{width:100%;height:310px;border-radius:12px;overflow:hidden;background:#e5f1f4}
        .doctor-image img{width:100%;height:100%;object-fit:cover;object-position:center 25%}
        .verified{display:inline-flex;gap:7px;align-items:center;background:#e5f8f1;color:#16845f;border-radius:20px;padding:6px 11px;font-size:10px;font-weight:800;margin-bottom:13px}
        h1{font-size:30px;line-height:1.35;color:#153650;margin-bottom:5px;overflow-wrap:anywhere}
        .specialty{font-weight:700;color:#078f91;margin-bottom:7px}
        .qualification{font-size:13px;color:#81909e;margin-bottom:20px}
        .stats{display:flex;gap:20px;flex-wrap:wrap;margin:20px 0 25px}
        .stat{display:flex;gap:10px;align-items:center}
        .stat-icon{width:42px;height:42px;display:flex;align-items:center;justify-content:center;background:#e7f8f6;color:#078f91;border-radius:10px;flex-shrink:0}
        .stat strong{display:block;color:#20374e;font-size:13px}
        .stat small{display:block;color:#81909e;font-size:10px}
        .actions{display:flex;gap:10px;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 16px;border-radius:8px;font-size:12px;font-weight:700;transition:.2s}
        .primary{background:#079e99;color:white;border:1px solid #079e99}
        .primary:hover{background:#087e7b}
        .outline{background:white;border:1px solid #079e99;color:#078f91}
        .outline:hover{background:#e8f8f6}
        .whatsapp{background:#25d366;color:white}
        .whatsapp:hover{background:#1da851}
        .layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:24px;align-items:start;margin-top:24px}
        .main-column{display:grid;gap:20px}
        .card,.booking-card{background:white;border:1px solid #e5edf3;border-radius:14px;padding:25px}
        .card h2,.booking-card h2{font-size:18px;color:#183750;margin-bottom:15px}
        .card p{font-size:13px;line-height:1.9}
        .detail-list{display:grid;gap:17px}
        .detail-row{display:flex;gap:12px;font-size:13px}
        .detail-row>i{color:#079e99;width:18px;margin-top:5px;flex-shrink:0}
        .detail-row strong{display:block;color:#263c52;margin-bottom:3px}
        .booking-card{position:sticky;top:20px}
        .subtext{font-size:12px;color:#81909e;margin-bottom:14px}
        .option{display:flex;gap:12px;padding:14px 0;border-bottom:1px solid #edf1f4;font-size:12px}
        .option>i{color:#079e99;font-size:17px;margin-top:3px}
        .option strong{display:block;color:#263c52}
        .option small{color:#81909e}
        .booking-card .btn{width:100%;margin-top:12px}
        .note{font-size:10px;color:#81909e;line-height:1.8;margin-top:17px}
        .error{background:white;border:1px solid #f0d5d5;border-radius:14px;padding:40px 20px;text-align:center}
        .error h1{font-size:25px;color:#a33b3b;margin-bottom:10px}
        .error p{font-size:13px;margin-bottom:20px}
        .footer{background:#102c43;color:#d6e1e9;padding:26px 7%;text-align:center;font-size:12px}
        @media(max-width:850px){.layout{grid-template-columns:1fr}.booking-card{position:static}.profile-header{grid-template-columns:220px 1fr;padding:22px}.doctor-image{height:270px}}
        @media(max-width:600px){.navbar{padding:16px 5%;flex-wrap:wrap}.nav-links{width:100%;gap:15px}.profile-header{grid-template-columns:1fr;padding:18px}.doctor-image{height:280px;max-width:330px;margin:auto}h1{font-size:25px}.stats{gap:16px}.card,.booking-card{padding:20px}.actions .btn{flex:1 1 140px}}
    </style>
</head>
<body>

<header class="navbar">
    <a href="index.html" class="brand">Medi<span>Connect</span></a>
    <nav class="nav-links">
        <a href="index.html">Home</a>
        <a href="doctors.php">Find Doctors</a>
        <a href="appointment.html">Appointments</a>
        <a href="login.php" class="nav-btn">Sign In</a>
    </nav>
</header>

<div class="breadcrumb">
    <a href="index.html">Home</a> &nbsp; / &nbsp;
    <a href="doctors.php">Find Doctors</a> &nbsp; / &nbsp; Doctor Profile
</div>

<main class="container">

<?php if (!$doctor): ?>

    <section class="error">
        <h1><i class="fa-solid fa-user-doctor"></i> Doctor Not Found</h1>
        <p>This doctor may not exist or may no longer be active.</p>
        <a href="doctors.php" class="btn primary">
            <i class="fa-solid fa-arrow-left"></i> Back to Find Doctors
        </a>
    </section>

<?php else: ?>

    <section class="profile-header">
        <div class="doctor-image">
            <img
                src="<?= h(profilePhoto($doctor['photo'] ?? null)) ?>"
                alt="Photo of <?= h($doctor['name']) ?>"
                onerror="this.onerror=null;this.src='https://placehold.co/600x700/e5f1f4/31566b?text=Doctor+Photo';">
        </div>

        <div>
            <span class="verified">
                <i class="fa-solid fa-circle-check"></i> ACTIVE DOCTOR
            </span>

            <h1><?= h($doctor['name']) ?></h1>
            <p class="specialty"><?= h($doctor['specialty']) ?></p>
            <p class="qualification">
                <?= h($doctor['qualification'] ?: 'Qualification not provided') ?>
            </p>

            <div class="stats">
                <div class="stat">
                    <div class="stat-icon"><i class="fa-solid fa-briefcase-medical"></i></div>
                    <div>
                        <strong><?= (int)$doctor['experience'] ?> years</strong>
                        <small>Experience</small>
                    </div>
                </div>

                <div class="stat">
                    <div class="stat-icon"><i class="fa-solid fa-video"></i></div>
                    <div>
                        <strong>Online</strong>
                        <small>Consultation option</small>
                    </div>
                </div>

                <div class="stat">
                    <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                    <div>
                        <strong>৳<?= number_format((float)$doctor['fee'], 2) ?></strong>
                        <small>Consultation fee</small>
                    </div>
                </div>
            </div>

            <div class="actions">
                <a class="btn primary" href="appointment.php?doctor_id=<?= (int)$doctor['id'] ?>">
                    <i class="fa-solid fa-calendar-check"></i> Book Appointment
                </a>

                <?php if ($whatsappUrl): ?>
                    <a class="btn whatsapp" href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fa-brands fa-whatsapp"></i> WhatsApp Contact
                    </a>
                <?php endif; ?>

                <a class="btn outline" href="doctors.php">
                    <i class="fa-solid fa-arrow-left"></i> All Doctors
                </a>
            </div>

            <p class="note">
                Doctor information is provided by the MediConnect administrator.
                Please verify credentials before consultation.
            </p>
        </div>
    </section>

    <div class="layout">
        <div class="main-column">

            <section class="card">
                <h2>About the Doctor</h2>
                <p>
                    <?= h($doctor['name']) ?> is listed on MediConnect as a
                    <?= h($doctor['specialty']) ?> doctor.
                    Contact the clinic for further information about professional
                    background and available consultation services.
                </p>
            </section>

            <section class="card">
                <h2>Education & Qualifications</h2>
                <div class="detail-list">
                    <div class="detail-row">
                        <i class="fa-solid fa-graduation-cap"></i>
                        <div>
                            <strong>Medical Qualification</strong>
                            <span><?= h($doctor['qualification'] ?: 'Not provided') ?></span>
                        </div>
                    </div>

                    <div class="detail-row">
                        <i class="fa-solid fa-user-doctor"></i>
                        <div>
                            <strong>Specialization</strong>
                            <span><?= h($doctor['specialty']) ?></span>
                        </div>
                    </div>

                    <div class="detail-row">
                        <i class="fa-solid fa-briefcase-medical"></i>
                        <div>
                            <strong>Clinical Experience</strong>
                            <span><?= (int)$doctor['experience'] ?> years</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <h2>Consultation Information</h2>
                <div class="detail-list">
                    <div class="detail-row">
                        <i class="fa-solid fa-video"></i>
                        <div>
                            <strong>Online Consultation</strong>
                            <span>Contact the doctor to confirm availability and consultation details.</span>
                        </div>
                    </div>

                    <div class="detail-row">
                        <i class="fa-solid fa-file-medical"></i>
                        <div>
                            <strong>Medical Reports</strong>
                            <span>Medical report sharing should take place through protected patient accounts.</span>
                        </div>
                    </div>

                    <div class="detail-row">
                        <i class="fa-solid fa-shield-halved"></i>
                        <div>
                            <strong>Patient Privacy</strong>
                            <span>Share personal medical information only through appropriate secure channels.</span>
                        </div>
                    </div>
                </div>
            </section>

        </div>

        <aside class="booking-card">
            <h2>Book a Consultation</h2>
            <p class="subtext">Choose an option to continue.</p>

            <div class="option">
                <i class="fa-solid fa-calendar-days"></i>
                <div><strong>Appointment Scheduling</strong><small>Choose a date and time</small></div>
            </div>

            <div class="option">
                <i class="fa-solid fa-video"></i>
                <div><strong>Online Consultation</strong><small>Confirm availability with the doctor</small></div>
            </div>

            <div class="option">
                <i class="fa-solid fa-file-medical"></i>
                <div><strong>Medical Reports</strong><small>Secure patient access</small></div>
            </div>

            <a class="btn primary" href="appointment.php?doctor_id=<?= (int)$doctor['id'] ?>">
                <i class="fa-solid fa-calendar-check"></i> Book Appointment
            </a>

            <?php if ($whatsappUrl): ?>
                <a class="btn whatsapp" href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa-brands fa-whatsapp"></i> Contact Doctor
                </a>
            <?php endif; ?>

            <p class="note">
                WhatsApp opens a chat with the doctor's phone number supplied by the administrator.
                Real-time video calls inside MediConnect require separate integration.
            </p>
        </aside>
    </div>

<?php endif; ?>

</main>

<footer class="footer">
    &copy; 2026 Medi<span>Connect</span>. All rights reserved.
</footer>

</body>
</html>