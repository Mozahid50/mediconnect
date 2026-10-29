<?php
require_once 'config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection failed. Please check config.php.');
}

// Search and specialty filters
$search = trim($_GET['search'] ?? '');
$specialtyFilter = trim($_GET['specialty'] ?? '');

try {
    // Get specialties from active doctors
    $specialtyQuery = $pdo->query("
        SELECT DISTINCT specialty
        FROM doctors
        WHERE LOWER(TRIM(status)) = 'active'
          AND specialty IS NOT NULL
          AND TRIM(specialty) != ''
        ORDER BY specialty ASC
    ");

    $specialties = $specialtyQuery->fetchAll(PDO::FETCH_COLUMN);

    // Fetch active doctors
    $sql = "
        SELECT id, name, specialty, qualification, experience,
               fee, phone, photo
        FROM doctors
        WHERE LOWER(TRIM(status)) = 'active'
    ";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (
            name LIKE :search_name
            OR specialty LIKE :search_specialty
            OR qualification LIKE :search_qualification
        )";

        $term = '%' . $search . '%';
        $params['search_name'] = $term;
        $params['search_specialty'] = $term;
        $params['search_qualification'] = $term;
    }

    if ($specialtyFilter !== '') {
        $sql .= " AND specialty = :specialty";
        $params['specialty'] = $specialtyFilter;
    }

    $sql .= " ORDER BY name ASC";

    $doctorQuery = $pdo->prepare($sql);
    $doctorQuery->execute($params);
    $doctors = $doctorQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Doctors page error: ' . $e->getMessage());
    $specialties = [];
    $doctors = [];
    $databaseError = true;
}

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function doctorPhoto(?string $photo): string {
    $fallback = 'https://placehold.co/600x450/e5f1f4/31566b?text=Doctor+Photo';

    if (!$photo) {
        return $fallback;
    }

    // Only permit local relative upload paths or HTTPS image URLs.
    if (preg_match('#^https://#i', $photo)) {
        return $photo;
    }

    if (
        strpos($photo, 'uploads/doctors/') === 0
        && !str_contains($photo, '..')
        && !str_contains($photo, '\\')
    ) {
        return $photo;
    }

    return $fallback;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Find Doctors | MediConnect</title>
    <meta name="description" content="Find doctors and explore consultation information on MediConnect.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --primary: #079e99;
            --primary-dark: #087e7b;
            --navy: #153650;
            --text: #536779;
            --muted: #81909e;
            --background: #f5f8fc;
            --border: #e5edf3;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        button, input, select {
            font: inherit;
        }

        .navbar {
            background: var(--white);
            padding: 17px 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            box-shadow: 0 3px 18px rgba(20, 50, 80, .05);
        }

        .brand {
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
            white-space: nowrap;
        }

        .brand span,
        .footer span {
            color: var(--primary);
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 25px;
        }

        .nav-links a {
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--primary);
        }

        .nav-links .nav-btn {
            background: var(--primary);
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
        }

        .nav-links .nav-btn:hover {
            background: var(--primary-dark);
            color: white;
        }

        .hero {
            padding: 65px 20px 90px;
            text-align: center;
            background: linear-gradient(135deg, #e6f8f6, #f1f8fc);
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-dark);
            background: #d8f3ef;
            padding: 7px 14px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .7px;
            margin-bottom: 17px;
        }

        .hero h1 {
            color: var(--navy);
            font-size: clamp(30px, 4vw, 43px);
            line-height: 1.25;
            margin-bottom: 14px;
            font-weight: 800;
        }

        .hero h1 span {
            color: var(--primary);
        }

        .hero p {
            max-width: 620px;
            margin: 0 auto;
            color: #6b7e8e;
            font-size: 14px;
        }

        .page-container {
            width: min(1160px, 92%);
            margin: -35px auto 70px;
            position: relative;
        }

        .search-panel {
            background: var(--white);
            padding: 23px;
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: 0 10px 35px rgba(25, 60, 85, .08);
            margin-bottom: 37px;
        }

        .search-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 240px auto;
            gap: 13px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap > i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #8a9aa8;
            font-size: 14px;
        }

        .search-form input,
        .search-form select {
            width: 100%;
            min-height: 49px;
            border: 1px solid #dfe8ee;
            border-radius: 9px;
            background: white;
            color: var(--navy);
            font-size: 13px;
            outline: none;
            transition: .2s;
        }

        .search-form input {
            padding: 12px 14px 12px 42px;
        }

        .search-form select {
            padding: 12px;
        }

        .search-form input:focus,
        .search-form select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(7, 158, 153, .1);
        }

        .search-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 49px;
            padding: 12px 22px;
            border: 0;
            border-radius: 9px;
            background: var(--primary);
            color: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            transition: .2s;
        }

        .search-btn:hover {
            background: var(--primary-dark);
        }

        .section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .section-heading h2 {
            color: var(--navy);
            font-size: 25px;
            margin-bottom: 4px;
        }

        .section-heading p {
            color: var(--muted);
            font-size: 13px;
        }

        .result-count {
            color: var(--primary-dark);
            background: #e1f6f3;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .doctor-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 23px;
        }

        .doctor-card {
            min-width: 0;
            overflow: hidden;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 5px 22px rgba(25, 60, 85, .035);
            transition: transform .25s, box-shadow .25s;
        }

        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 13px 30px rgba(25, 60, 85, .10);
        }

        .doctor-photo {
            position: relative;
            height: 225px;
            overflow: hidden;
            background: #e5f1f4;
        }

        .doctor-photo img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            object-position: center 25%;
            transition: transform .35s;
        }

        .doctor-card:hover .doctor-photo img {
            transform: scale(1.035);
        }

        .doctor-status {
            position: absolute;
            top: 14px;
            left: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e4f8ef;
            color: #16845f;
            border-radius: 20px;
            padding: 6px 10px;
            font-size: 10px;
            font-weight: 800;
        }

        .doctor-status i {
            font-size: 8px;
        }

        .doctor-info {
            padding: 21px;
        }

        .doctor-specialty {
            color: var(--primary-dark);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        .doctor-name {
            color: var(--navy);
            font-size: 19px;
            font-weight: 800;
            line-height: 1.4;
            margin-bottom: 7px;
            overflow-wrap: anywhere;
        }

        .doctor-qualification {
            min-height: 40px;
            color: #7b8a98;
            font-size: 12px;
            line-height: 1.65;
            margin-bottom: 17px;
        }

        .doctor-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding: 15px 0;
            border-top: 1px solid #edf1f4;
            border-bottom: 1px solid #edf1f4;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #718293;
            font-size: 11px;
        }

        .meta-item i {
            color: var(--primary);
            font-size: 13px;
        }

        .fee {
            color: var(--navy);
            font-size: 17px;
            font-weight: 800;
        }

        .fee small {
            display: block;
            color: #82909d;
            font-size: 10px;
            font-weight: 500;
            text-align: right;
        }

        .card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 17px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 42px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            text-align: center;
            transition: .2s;
        }

        .btn-outline {
            color: var(--primary-dark);
            border: 1px solid #b7e5e0;
            background: white;
        }

        .btn-outline:hover {
            background: #e9f8f6;
        }

        .btn-primary {
            color: white;
            border: 1px solid var(--primary);
            background: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .empty-state {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 55px 20px;
            text-align: center;
        }

        .empty-icon {
            width: 75px;
            height: 75px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #e4f7f4;
            color: var(--primary);
            font-size: 28px;
        }

        .empty-state h3 {
            color: var(--navy);
            font-size: 21px;
            margin-bottom: 9px;
        }

        .empty-state p {
            max-width: 450px;
            margin: 0 auto 20px;
            color: var(--muted);
            font-size: 13px;
        }

        .clear-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary);
            color: white;
            padding: 11px 17px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .footer {
            background: #102c43;
            color: #d6e1e9;
            padding: 28px 7%;
            text-align: center;
            font-size: 12px;
        }

        .footer p + p {
            margin-top: 5px;
            color: #a9bac8;
        }

        @media (max-width: 950px) {
            .doctor-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .search-form {
                grid-template-columns: minmax(0, 1fr) 220px;
            }

            .search-btn {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 650px) {
            .navbar {
                padding: 16px 5%;
                flex-wrap: wrap;
            }

            .brand {
                font-size: 23px;
            }

            .nav-links {
                width: 100%;
                justify-content: flex-start;
                gap: 16px;
            }

            .nav-links a {
                font-size: 12px;
            }

            .hero {
                padding: 48px 20px 72px;
            }

            .page-container {
                width: 92%;
                margin-top: -28px;
            }

            .search-panel {
                padding: 16px;
            }

            .search-form {
                grid-template-columns: 1fr;
            }

            .search-btn {
                grid-column: auto;
            }

            .section-heading h2 {
                font-size: 22px;
            }

            .doctor-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .doctor-photo {
                height: 245px;
            }

            .doctor-info {
                padding: 19px;
            }
        }
    </style>
</head>

<body>

<header class="navbar">
    <a href="index.html" class="brand">Medi<span>Connect</span></a>

    <nav class="nav-links">
        <a href="index.html">Home</a>
        <a href="doctors.php" class="active">Find Doctors</a>
        <a href="appointment.html">Appointments</a>
        <a href="login.php" class="nav-btn">Sign In</a>
    </nav>
</header>

<section class="hero">
    <div class="hero-tag">
        <i class="fa-solid fa-heart-pulse"></i>
        YOUR HEALTH, OUR PRIORITY
    </div>

    <h1>Find the Right <span>Doctor</span></h1>

    <p>
        Explore our available doctors, review their qualifications,
        and choose a consultation that fits your needs.
    </p>
</section>

<main class="page-container">

    <section class="search-panel">
        <form action="doctors.php" method="GET" class="search-form">

            <div class="input-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                    type="search"
                    name="search"
                    placeholder="Search by doctor name or qualification..."
                    value="<?= h($search) ?>"
                    aria-label="Search doctors">
            </div>

            <select name="specialty" aria-label="Filter by specialty">
                <option value="">All Specialties</option>

                <?php foreach ($specialties as $specialty): ?>
                    <option
                        value="<?= h($specialty) ?>"
                        <?= $specialtyFilter === $specialty ? 'selected' : '' ?>>
                        <?= h($specialty) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="search-btn">
                <i class="fa-solid fa-magnifying-glass"></i>
                Search Doctors
            </button>

        </form>
    </section>

    <section>
        <div class="section-heading">
            <div>
                <h2>Our Available Doctors</h2>
                <p>Choose a doctor to view their full profile.</p>
            </div>

            <div class="result-count">
                <i class="fa-solid fa-user-doctor"></i>
                <?= count($doctors) ?> Doctor<?= count($doctors) === 1 ? '' : 's' ?> Found
            </div>
        </div>

        <?php if (!empty($databaseError)): ?>

            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fa-solid fa-database"></i>
                </div>
                <h3>Unable to Load Doctors</h3>
                <p>
                    Please check your database connection and make sure
                    the doctors table exists.
                </p>
            </div>

        <?php elseif (count($doctors) > 0): ?>

            <div class="doctor-grid">

                <?php foreach ($doctors as $doctor): ?>

                    <article class="doctor-card">

                        <div class="doctor-photo">
                            <img
                                src="<?= h(doctorPhoto($doctor['photo'] ?? null)) ?>"
                                alt="Photo of <?= h($doctor['name']) ?>"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='https://placehold.co/600x450/e5f1f4/31566b?text=Doctor+Photo';">

                            <span class="doctor-status">
                                <i class="fa-solid fa-circle-check"></i>
                                ACTIVE DOCTOR
                            </span>
                        </div>

                        <div class="doctor-info">

                            <p class="doctor-specialty">
                                <?= h($doctor['specialty']) ?>
                            </p>

                            <h3 class="doctor-name">
                                <?= h($doctor['name']) ?>
                            </h3>

                            <p class="doctor-qualification">
                                <?= h($doctor['qualification'] ?: 'Qualification not provided') ?>
                            </p>

                            <div class="doctor-meta">

                                <div class="meta-item">
                                    <i class="fa-solid fa-briefcase-medical"></i>
                                    <span>
                                        <?= (int)$doctor['experience'] ?> years
                                        experience
                                    </span>
                                </div>

                                <div class="fee">
                                    ৳<?= number_format((float)$doctor['fee'], 2) ?>
                                    <small>Consultation fee</small>
                                </div>

                            </div>

                            <div class="card-actions">

                                <a
                                    class="btn btn-outline"
                                    href="Doctor-profile.php?doctor=<?= (int)$doctor['id'] ?>">
                                    <i class="fa-solid fa-user-doctor"></i>
                                    View Profile
                                </a>

                                <a
                                    class="btn btn-primary"
                                    href="appointment.php?doctor_id=<?= (int)$doctor['id'] ?>">
                                    <i class="fa-solid fa-calendar-check"></i>
                                    Book Appointment
                                </a>

                            </div>
                        </div>
                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>

                <h3>No Doctors Found</h3>

                <p>
                    <?php if ($search !== '' || $specialtyFilter !== ''): ?>
                        No active doctors match your search. Try another name
                        or select a different specialty.
                    <?php else: ?>
                        No active doctors have been added yet.
                        Please check back after the administrator adds doctors.
                    <?php endif; ?>
                </p>

                <?php if ($search !== '' || $specialtyFilter !== ''): ?>
                    <a href="doctors.php" class="clear-btn">
                        <i class="fa-solid fa-rotate-left"></i>
                        Clear Search
                    </a>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </section>

</main>

<footer class="footer">
    <p>&copy; 2026 Medi<span>Connect</span>. All rights reserved.</p>
    <p>Your Health, Our Priority.</p>
</footer>

</body>
</html>