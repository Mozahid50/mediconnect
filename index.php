<?php
session_start();

/*
|--------------------------------------------------------------------------
| MediConnect Home Page
|--------------------------------------------------------------------------
| Service cards open their pages directly.
| Protected PHP pages must validate the user's session and permissions.
|--------------------------------------------------------------------------
*/

$dashboardUrl = "patient-dashboard.php";
$prescriptionUrl = "digital-prescription.php";
$reportsUrl = "medical-reports.php";
$videoUrl = "video-consultations.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MediConnect | Online Healthcare</title>
    <meta name="description" content="Access online healthcare, appointments, video consultations, medical reports and digital prescriptions with MediConnect.">

     <link rel="stylesheet" href="style.css?v=contact-fix-2026">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <style>
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 100px;
        }

        .service-card a,
        .dashboard-btn {
            cursor: pointer;
        }

        .patient-access-note {
            margin-top: 12px;
            font-size: 12px;
            line-height: 1.6;
            opacity: 0.85;
        }

        .patient-access-note i {
            margin-right: 5px;
        }
    body > section.contact-section#contact {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 70px 20px !important;
        float: none !important;
        clear: both !important;
        box-sizing: border-box !important;
    }

    body > section.contact-section#contact > .contact-container {
        display: block !important;
        width: 100% !important;
        max-width: 1120px !important;
        margin: 0 auto !important;
    }

    body > section.contact-section#contact .contact-heading {
        display: block !important;
        width: 100% !important;
        max-width: 650px !important;
        margin: 0 auto 40px !important;
        text-align: center !important;
    }

    body > section.contact-section#contact .contact-grid {
        display: grid !important;
        grid-template-columns: minmax(0, 0.88fr) minmax(0, 1.12fr) !important;
        align-items: stretch !important;
        gap: 26px !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
    }

    body > section.contact-section#contact .contact-info,
    body > section.contact-section#contact .contact-form-card {
        display: block !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        margin: 0 !important;
        float: none !important;
    }

    @media (max-width: 800px) {
        body > section.contact-section#contact .contact-grid {
            grid-template-columns: minmax(0, 1fr) !important;
            max-width: 640px !important;
            margin: 0 auto !important;
        }
    }
    </style>
</head>

<body>

<!-- NAVBAR -->
<header class="navbar">

    <a href="index.php" class="logo">
        <i class="fa-solid fa-heart-pulse"></i>
        <span>
            Medi<strong>Connect</strong>
            <small>YOUR HEALTH, OUR PRIORITY</small>
        </span>
    </a>

    <nav class="nav-links">
        <a href="index.php">Home</a>
        <a href="#services">Services</a>
        <a href="#find-doctors">Find Doctors</a>
        <a href="#how-it-works">How It Works</a>
        <a href="#contact">Contact</a>
        <a href="<?php echo htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8'); ?>">
            Dashboard
        </a>
    </nav>

    <div class="nav-buttons">
        <a href="login.php" class="login-btn">Login</a>
        <a href="register.php" class="register-btn">Register</a>
        <a href="admin-login.php" class="register-btn">Admin</a>
    </div>

</header>


<!-- HERO SECTION -->
<section class="hero">

    <div class="hero-overlay"></div>

    <div class="hero-content">

        <div class="tag">
            <i class="fa-solid fa-heart-pulse"></i>
            YOUR HEALTH, OUR PRIORITY
        </div>

        <h1>
            Quality Healthcare,<br>
            Right From <span>Home.</span>
        </h1>

        <p>
            Experience convenient online healthcare with trusted doctors.
            Book appointments, consult doctors from home, and access your
            medical reports in one secure place.
        </p>

        <div class="hero-buttons">
            <a href="#find-doctors" class="primary-btn">
                Find a Doctor
                <i class="fa-solid fa-arrow-right"></i>
            </a>

            <a href="#services" class="secondary-btn">
                Explore Services
                <i class="fa-solid fa-circle-play"></i>
            </a>
        </div>

        <div class="hero-trust">
            <div class="trust-icon">
                <i class="fa-solid fa-shield-heart"></i>
            </div>

            <div>
                <strong>Your Health Information Matters</strong>
                <p>Convenient healthcare with privacy in mind.</p>
            </div>
        </div>

    </div>

    <div class="hero-cards">

        <div class="floating-card">
            <div class="float-icon video-icon">
                <i class="fa-solid fa-video"></i>
            </div>

            <div>
                <strong>Video Consultation</strong>
                <p>Consult from your home</p>
            </div>
        </div>

        <div class="floating-card report-card">
            <div class="float-icon report-icon">
                <i class="fa-solid fa-file-medical"></i>
            </div>

            <div>
                <strong>Medical Reports</strong>
                <p>Access your health records</p>
            </div>
        </div>

    </div>

</section>


<!-- SERVICES -->
<section class="section services" id="services">

    <div class="section-heading">
        <span class="section-label">OUR SERVICES</span>
        <h2>Healthcare Made Simpler</h2>
        <p>
            Access essential healthcare services from one convenient platform.
        </p>
    </div>

    <div class="service-grid">

       <!-- VIDEO CONSULTATION -->
<div class="service-card">
    <div class="service-icon blue">
        <i class="fa-solid fa-video"></i>
    </div>

    <h3>Video Consultation</h3>

    <p>
        Connect with your doctor online and discuss your health concerns.
    </p>

    <a href="video-consultations.php">
        Open Video Consultation
        <i class="fa-solid fa-arrow-right"></i>
    </a>
</div>

        <!-- ONLINE APPOINTMENT -->
        <div class="service-card">
            <div class="service-icon green">
                <i class="fa-solid fa-calendar-check"></i>
            </div>

            <h3>Online Appointment</h3>

            <p>
                Request appointments with doctors at your preferred time.
            </p>

            <a href="appointment.php">
                Book Appointment
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>


        <!-- MEDICAL REPORTS -->
        <div class="service-card">
            <div class="service-icon purple">
                <i class="fa-solid fa-file-medical"></i>
            </div>

            <h3>Medical Reports</h3>

            <p>
                View your medical reports and keep your health information organized.
            </p>

            <a href="medical-reports.php">
             View Medical Reports
            <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>


        <!-- DIGITAL PRESCRIPTION -->
        <div class="service-card">
            <div class="service-icon orange">
                <i class="fa-solid fa-prescription"></i>
            </div>

            <h3>Digital Prescription</h3>

            <p>
                Access digital prescriptions provided by your doctor.
            </p>

            <a href="digital-prescription.php">
    View Digital Prescription
    <i class="fa-solid fa-arrow-right"></i>
</a>
        </div>

    </div>

</section>


<!-- FIND DOCTOR BANNER -->
<section class="doctor-section" id="find-doctors">

    <div class="doctor-banner">

        <div class="doctor-banner-content">
            <span class="section-label">FIND YOUR DOCTOR</span>

            <h2>
                The Right Doctor<br>
                for Your Healthcare
            </h2>

            <p>
                Find a suitable doctor and take the next step toward
                better healthcare with MediConnect.
            </p>

            <a href="doctors.php" class="primary-btn">
                Find a Doctor
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="doctor-banner-icon">
            <i class="fa-solid fa-user-doctor"></i>

            <span>
                <i class="fa-solid fa-plus"></i>
            </span>
        </div>

    </div>

</section>


<!-- HOW IT WORKS -->
<section class="section how-it-works" id="how-it-works">

    <div class="section-heading">
        <span class="section-label">HOW IT WORKS</span>
        <h2>Healthcare in Four Simple Steps</h2>

        <p>
            Get started with MediConnect through a simple and convenient process.
        </p>
    </div>

    <div class="steps-grid">

        <div class="step-card">
            <span class="step-number">01</span>

            <div class="step-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>

            <h3>Create Your Account</h3>

            <p>
                Register for an account to access your personal healthcare services.
            </p>
        </div>

        <div class="step-card">
            <span class="step-number">02</span>

            <div class="step-icon">
                <i class="fa-solid fa-user-doctor"></i>
            </div>

            <h3>Choose a Doctor</h3>

            <p>
                Explore available doctors and select the one suitable for you.
            </p>
        </div>

        <div class="step-card">
            <span class="step-number">03</span>

            <div class="step-icon">
                <i class="fa-solid fa-calendar-check"></i>
            </div>

            <h3>Book an Appointment</h3>

            <p>
                Submit your appointment request through the online platform.
            </p>
        </div>

        <div class="step-card">
            <span class="step-number">04</span>

            <div class="step-icon">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>

            <h3>Receive Healthcare</h3>

            <p>
                Consult your doctor and access available medical records online.
            </p>
        </div>

    </div>

</section>


<!-- Contact Section -->
<section class="contact-section" id="contact">
    <div class="contact-container">

        <div class="contact-heading">
            <span class="contact-eyebrow">GET IN TOUCH</span>
            <h2>Contact Us</h2>
            <p>
                Have questions about our healthcare services?
                Our team is here to help you.
            </p>
        </div>

        <div class="contact-grid">

            <!-- Contact Information -->
            <div class="contact-info">
                <h3>Let's Connect</h3>
                <p class="contact-description">
                    We're here to make your healthcare experience
                    easier, safer, and more convenient.
                </p>

                <div class="contact-info-item">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <h4>Email Support</h4>
                        <p>Contact our team for assistance.</p>
                    </div>
                </div>

                <div class="contact-info-item">
                    <div class="contact-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div>
                        <h4>Phone Support</h4>
                        <p>We're ready to help with your questions.</p>
                    </div>
                </div>

                <div class="contact-info-item">
                    <div class="contact-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div>
                        <h4>Your Health Matters</h4>
                        <p>Your healthcare journey is important to us.</p>
                    </div>
                </div>
            </div>

            <form action="contact.php" method="POST" class="contact-form" id="contactForm">

    <div class="contact-field-row">
        <div class="contact-field">
            <label for="contact-name">Full Name</label>
            <input type="text" id="contact-name" name="name"
                   placeholder="Your full name" maxlength="100" required>
        </div>

        <div class="contact-field">
            <label for="contact-email">Email Address</label>
            <input type="email" id="contact-email" name="email"
                   placeholder="Your email address" maxlength="150" required>
        </div>
    </div>

    <div class="contact-field">
        <label for="contact-subject">Subject</label>
        <input type="text" id="contact-subject" name="subject"
               placeholder="How can we help?" maxlength="150" required>
    </div>

    <div class="contact-field">
        <label for="contact-message">Message</label>
        <textarea id="contact-message" name="message" rows="5"
                  placeholder="Write your message here..."
                  maxlength="5000" required></textarea>
    </div>

    <div id="contactResponse" role="status" aria-live="polite"
         style="display:none; padding:12px 15px; margin-bottom:15px; border-radius:8px;">
    </div>

    <button type="submit" class="contact-submit" id="contactSubmit">
        Send Message <i class="fas fa-paper-plane"></i>
    </button>
</form>

<script>
document.getElementById('contactForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    const form = this;
    const button = document.getElementById('contactSubmit');
    const response = document.getElementById('contactResponse');

    button.disabled = true;
    button.innerHTML = 'Sending... <i class="fas fa-spinner fa-spin"></i>';

    response.style.display = 'none';
    response.textContent = '';

    try {
        const result = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });

        const data = await result.json();

        response.style.display = 'block';
        response.textContent = data.message || 'An unexpected response was received.';
        response.style.background = data.success ? '#e8f8ef' : '#fff0f0';
        response.style.color = data.success ? '#167344' : '#b42318';

        if (result.ok && data.success) {
            form.reset();
        }
    } catch (error) {
        response.style.display = 'block';
        response.style.background = '#fff0f0';
        response.style.color = '#b42318';
        response.textContent = 'Unable to send your message. Please try again.';
    } finally {
        button.disabled = false;
        button.innerHTML = 'Send Message <i class="fas fa-paper-plane"></i>';
    }
});
</script>
            </div>

        </div>
    </div>
</section>
<!-- FOOTER -->
<footer class="footer">

    <div class="footer-main">

        <div class="footer-about">

            <a href="index.php" class="logo footer-logo">
                <i class="fa-solid fa-heart-pulse"></i>

                <span>
                    Medi<strong>Connect</strong>
                    <small>YOUR HEALTH, OUR PRIORITY</small>
                </span>
            </a>

            <p>
                Making healthcare more convenient through online appointments,
                doctor consultations, and digital health records.
            </p>

        </div>

        <div class="footer-links">
            <h3>Quick Links</h3>

            <a href="index.php">Home</a>
            <a href="#find-doctors">Find Doctors</a>
            <a href="appointment.php">Appointments</a>
            <a href="login.php">Patient Login</a>
        </div>

        <div class="footer-links">
            <h3>Patient Services</h3>

            <a href="<?php echo htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8'); ?>">
                Video Consultation
            </a>

            <a href="<?php echo htmlspecialchars($reportsUrl, ENT_QUOTES, 'UTF-8'); ?>">
                Medical Reports
            </a>

            <a href="<?php echo htmlspecialchars($prescriptionUrl, ENT_QUOTES, 'UTF-8'); ?>">
                Digital Prescription
            </a>

            <a href="<?php echo htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8'); ?>">
                Patient Dashboard
            </a>
        </div>

        <div class="footer-contact">
            <h3>Contact Information</h3>

            <p>
                <i class="fa-solid fa-envelope"></i>
                support@mediconnect.com
            </p>

            <p>
                <i class="fa-solid fa-heart-pulse"></i>
                Your Health, Our Priority
            </p>
        </div>

    </div>

    <div class="footer-bottom">
        <p>
            &copy; <?php echo date('Y'); ?> MediConnect. All rights reserved.
        </p>

        <p>Designed for convenient online healthcare.</p>
    </div>

</footer>

</body>
</html>