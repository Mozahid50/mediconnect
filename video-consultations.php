<?php
session_start();
require_once __DIR__ . "/config.php";

/* Allow authenticated patients and doctors only */
if (
    empty($_SESSION["user_role"]) ||
    !in_array($_SESSION["user_role"], ["patient", "doctor"], true)
) {
    header("Location: login.php?redirect=video-consultations.php");
    exit;
}

$role = $_SESSION["user_role"];
$appointmentId = filter_input(
    INPUT_GET,
    "appointment_id",
    FILTER_VALIDATE_INT
);

if (!$appointmentId) {
    if ($role === "patient") {
        $stmt = $pdo->prepare("
            SELECT id
            FROM appointments
            WHERE patient_id = ?
              AND status = 'Confirmed'
            ORDER BY appointment_date DESC, appointment_time DESC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION["patient_db_id"] ?? 0]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM appointments
            WHERE doctor_id = ?
              AND status = 'Confirmed'
            ORDER BY appointment_date DESC, appointment_time DESC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION["doctor_id"] ?? 0]);
    }

    $appointmentId = (int) ($stmt->fetchColumn() ?: 0);
}

if ($appointmentId < 1) {
    http_response_code(200);
    exit("No confirmed appointment is available for video consultation.");
}

/* Verify that the appointment belongs to this logged-in user */
if ($role === "patient") {
    $stmt = $pdo->prepare("
        SELECT id, patient_id, doctor_id, status
        FROM appointments
        WHERE id = ? AND patient_id = ?
        LIMIT 1
    ");
    $stmt->execute([
        $appointmentId,
        $_SESSION["patient_db_id"] ?? 0
    ]);
} else {
    $stmt = $pdo->prepare("
        SELECT id, patient_id, doctor_id, status
        FROM appointments
        WHERE id = ? AND doctor_id = ?
        LIMIT 1
    ");
    $stmt->execute([
        $appointmentId,
        $_SESSION["doctor_id"] ?? 0
    ]);
}

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    http_response_code(403);
    exit("You are not authorized to access this appointment.");
}

if ($appointment["status"] !== "Confirmed") {
    http_response_code(403);
    exit("This appointment is not confirmed for video consultation.");
}

/*
 * Generate the same room name for both appointment participants.
 * This is a room identifier, NOT an access-control mechanism.
 */
$roomName = "MediConnectAppointment" . $appointmentId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Video Consultation | MediConnect</title>

    <link rel="stylesheet" href="video-consultation.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .jitsi-wrapper {
            width: 100%;
            min-height: 520px;
            background: #101828;
            border-radius: 16px;
            overflow: hidden;
        }

        #jitsiMeeting {
            width: 100%;
            height: 520px;
        }

        .jitsi-message {
            margin-top: 14px;
            padding: 14px;
            border-radius: 10px;
            background: #eef6ff;
            color: #164e82;
            line-height: 1.6;
        }

        .jitsi-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .jitsi-actions button,
        .jitsi-actions a {
            border: 0;
            border-radius: 8px;
            padding: 12px 18px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
        }

        #joinMeetingBtn {
            background: #087f8c;
            color: white;
        }

        #leaveMeetingBtn {
            background: #dc3545;
            color: white;
        }

        #leaveMeetingBtn:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        @media (max-width: 600px) {
            .jitsi-wrapper,
            #jitsiMeeting {
                min-height: 420px;
                height: 420px;
            }
        }
    </style>
</head>

<body>

<header class="navbar">
    <a href="index.php" class="logo">
        <i class="fa-solid fa-heart-pulse"></i>
        MediConnect
    </a>

    <a href="dashboard-router.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Dashboard
    </a>
</header>

<main class="container">

    <section class="heading">
        <span>ONLINE HEALTHCARE</span>
        <h1>Video Consultation</h1>
        <p>Connect with your doctor from anywhere.</p>
    </section>

    <section class="call-layout">

        <div class="video-section">

            <div class="jitsi-wrapper">
                <div id="jitsiMeeting"></div>
            </div>

            <div class="jitsi-actions">
                <button id="joinMeetingBtn" type="button">
                    <i class="fa-solid fa-video"></i>
                    Join Video Consultation
                </button>

                <button id="leaveMeetingBtn" type="button" disabled>
                    <i class="fa-solid fa-phone-slash"></i>
                    Leave Call
                </button>
            </div>

            <div id="callStatus" class="jitsi-message" role="status">
                Ready to connect. Press Join Video Consultation.
            </div>

        </div>

        <aside class="info-card">
            <div class="info-icon">
                <i class="fa-solid fa-shield-heart"></i>
            </div>

            <h2>Online Consultation</h2>

            <p>
                Allow camera and microphone access when your browser
                asks for permission.
            </p>

            <div class="info-row">
                <i class="fa-solid fa-video"></i>
                <span>Live video and audio</span>
            </div>

            <div class="info-row">
                <i class="fa-solid fa-microphone"></i>
                <span>Microphone controls inside the meeting</span>
            </div>

            <div class="info-row">
                <i class="fa-solid fa-user-doctor"></i>
                <span>
                    <?= $role === "doctor" ? "Doctor" : "Patient" ?> account verified
                </span>
            </div>

            <a href="appointment.php" class="appointment-link">
                <i class="fa-solid fa-calendar-check"></i>
                Book Appointment
            </a>

            <a href="consultation-history.php" class="history-link">
                Consultation History
            </a>
        </aside>

    </section>

</main>

<script>
"use strict";

const meetingConfig = {
    roomName: <?= json_encode(
        $roomName,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>,
    displayName: <?= json_encode(
        $role === "doctor" ? "MediConnect Doctor" : "MediConnect Patient",
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>
};
</script>

<script src="https://meet.jit.si/external_api.js"></script>
<script src="video-consultation.js?v=14"></script>

</body>
</html>
I