<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config.php';

$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);

function sendResponse(
    bool $success,
    string $message,
    int $statusCode = 200
): void {
    global $isAjax;

    http_response_code($statusCode);

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            [
                'success' => $success,
                'message' => $message
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        exit;
    }

    $title = $success
        ? 'Message Submitted Successfully'
        : 'Unable to Submit Message';

    $color = $success ? '#0d9488' : '#dc2626';

    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>' . $safeTitle . ' | MediConnect</title>
        <style>
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 20px;
                background: #f0fdfa;
                font-family: Arial, sans-serif;
            }
            .card {
                width: 100%;
                max-width: 520px;
                padding: 35px;
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 35px rgba(0,0,0,.08);
                text-align: center;
            }
            h1 { color: ' . $color . '; }
            p { color: #475569; line-height: 1.7; }
            a {
                display: inline-block;
                margin-top: 18px;
                padding: 12px 20px;
                background: #0d9488;
                color: white;
                text-decoration: none;
                border-radius: 8px;
            }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>' . $safeTitle . '</h1>
            <p>' . $safeMessage . '</p>
            <a href="index.php#contact">Back to MediConnect</a>
        </div>
    </body>
    </html>';

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        sendResponse(false, 'Invalid request method.', 405);
    }

    header('Location: index.php#contact');
    exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if (
    $name === '' ||
    mb_strlen($name) > 100
) {
    sendResponse(
        false,
        'Please enter a valid name (maximum 100 characters).',
        422
    );
}

if (
    $email === '' ||
    mb_strlen($email) > 150 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    sendResponse(
        false,
        'Please enter a valid email address.',
        422
    );
}

if (
    $subject === '' ||
    mb_strlen($subject) > 150
) {
    sendResponse(
        false,
        'Please enter a subject (maximum 150 characters).',
        422
    );
}

if (
    $message === '' ||
    mb_strlen($message) > 5000
) {
    sendResponse(
        false,
        'Please enter a message (maximum 5000 characters).',
        422
    );
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO contact_messages
            (name, email, subject, message)
         VALUES
            (:name, :email, :subject, :message)'
    );

    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':subject' => $subject,
        ':message' => $message
    ]);

    sendResponse(
        true,
        'Thank you for contacting MediConnect! Your message has been submitted successfully. Our team can review it shortly.'
    );

} catch (PDOException $e) {
    error_log(
        'MediConnect contact form database error: ' .
        $e->getMessage()
    );

    sendResponse(
        false,
        'We could not save your message right now. Please try again later.',
        500
    );
}