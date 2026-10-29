<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    /*
     * Temporary admin credentials.
     * Replace these with database-based authentication
     * before deploying the website publicly.
     */
    $adminUsername = 'admin';
    $adminPassword = 'Admin@123';

    if (
        hash_equals($adminUsername, $username) &&
        hash_equals($adminPassword, $password)
    ) {
        session_regenerate_id(true);

        $_SESSION['mediconnect_admin_logged_in'] = true;
        $_SESSION['mediconnect_admin_username'] = $adminUsername;

        header('Location: admin-panel.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | MediConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #e8f8f7, #eef3ff);
            color: #193247;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
            padding: 38px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(25, 65, 85, .12);
        }

        .logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo-icon {
            width: 65px;
            height: 65px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            border-radius: 18px;
            background: #e3f7f5;
            color: #079b91;
            font-size: 30px;
        }

        h1 {
            font-size: 25px;
            margin-bottom: 9px;
        }

        .logo p {
            color: #758493;
            font-size: 13px;
            margin-top: 8px;
        }

        .subtitle {
            text-align: center;
            color: #718096;
            font-size: 14px;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin: 17px 0 8px;
            font-size: 13px;
            font-weight: 700;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #8494a4;
        }

        input {
            width: 100%;
            padding: 14px 14px 14px 42px;
            border: 1px solid #dce5eb;
            border-radius: 10px;
            outline: none;
            font: inherit;
            font-size: 14px;
        }

        input:focus {
            border-color: #079b91;
            box-shadow: 0 0 0 3px rgba(7, 155, 145, .1);
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: #079b91;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        button:hover {
            background: #087f79;
        }

        .error {
            padding: 12px;
            margin-bottom: 16px;
            border-radius: 8px;
            background: #fff0f0;
            color: #c62828;
            font-size: 13px;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 22px;
            color: #078b84;
            font-size: 13px;
            text-decoration: none;
        }

        .back:hover {
            text-decoration: underline;
        }

        .notice {
            margin-top: 18px;
            color: #8995a2;
            font-size: 11px;
            line-height: 1.6;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logo">
        <div class="logo-icon">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>
        <h1>MediConnect</h1>
        <p>YOUR HEALTH, OUR PRIORITY</p>
    </div>

    <p class="subtitle">Sign in to access the administration panel.</p>

    <?php if ($error !== ''): ?>
        <div class="error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="admin-login.php">
        <label for="username">Admin Username</label>
        <div class="input-wrap">
            <i class="fa-solid fa-user-shield"></i>
            <input
                type="text"
                id="username"
                name="username"
                placeholder="Enter admin username"
                autocomplete="username"
                required
            >
        </div>

        <label for="password">Password</label>
        <div class="input-wrap">
            <i class="fa-solid fa-lock"></i>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter admin password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit">
            Login to Admin Panel
            <i class="fa-solid fa-arrow-right"></i>
        </button>
    </form>

    <a class="back" href="index.php">
        <i class="fa-solid fa-arrow-left"></i> Back to Home
    </a>

    <p class="notice">
        Temporary login setup for local development only.
        Replace it with secure database-based admin authentication before public deployment.
    </p>
</div>

</body>
</html>