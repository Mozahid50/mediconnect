<?php
session_start();
require_once __DIR__ . '/config.php';

// Protect this page: admin login required.
if (
    empty($_SESSION['mediconnect_admin_logged_in']) ||
    $_SESSION['mediconnect_admin_logged_in'] !== true
) {
    header('Location: admin-login.php');
    exit;
}

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$error = '';
$messages = [];
$totalMessages = 0;
$newMessages = 0;
$search = trim($_GET['search'] ?? '');

try {
    // Mark a message as read.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';

        if ($messageId && $action === 'read') {
            $stmt = $pdo->prepare(
                "UPDATE contact_messages SET status = 'Read' WHERE id = ?"
            );
            $stmt->execute([$messageId]);
        } elseif ($messageId && $action === 'new') {
            $stmt = $pdo->prepare(
                "UPDATE contact_messages SET status = 'New' WHERE id = ?"
            );
            $stmt->execute([$messageId]);
        }

        header('Location: admin-messages.php');
        exit;
    }

    $totalMessages = (int)$pdo->query(
        "SELECT COUNT(*) FROM contact_messages"
    )->fetchColumn();

    $newMessages = (int)$pdo->query(
        "SELECT COUNT(*) FROM contact_messages WHERE status = 'New'"
    )->fetchColumn();

    if ($search !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, name, email, subject, message, status, created_at
             FROM contact_messages
             WHERE name LIKE :search
                OR email LIKE :search
                OR subject LIKE :search
                OR message LIKE :search
             ORDER BY created_at DESC, id DESC"
        );
        $stmt->execute(['search' => '%' . $search . '%']);
    } else {
        $stmt = $pdo->query(
            "SELECT id, name, email, subject, message, status, created_at
             FROM contact_messages
             ORDER BY created_at DESC, id DESC"
        );
    }

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Admin contact messages error: ' . $e->getMessage());
    $error = 'Could not load contact messages. Please check the database configuration.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages | MediConnect Admin</title>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 28px;
            font-family: Inter, Arial, sans-serif;
            background: #f4f8fb;
            color: #193247;
        }
        .container { max-width: 1200px; margin: auto; }
        .topbar {
            display: flex; align-items: center;
            justify-content: space-between; gap: 16px;
            flex-wrap: wrap; margin-bottom: 25px;
        }
        h1 { margin: 0 0 8px; font-size: 27px; }
        .muted { color: #758493; font-size: 14px; }
        a { color: #078b84; text-decoration: none; }
        .back {
            display: inline-block; padding: 11px 15px;
            border-radius: 9px; background: #079b91; color: white;
            font-weight: 700; font-size: 13px;
        }
        .stats {
            display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px; margin-bottom: 22px;
        }
        .stat, .panel {
            background: white; border: 1px solid #e7edf2;
            border-radius: 15px; padding: 22px;
            box-shadow: 0 5px 18px rgba(25, 65, 85, .04);
        }
        .stat strong { display: block; font-size: 29px; margin-top: 8px; }
        .searchbar {
            display: flex; gap: 10px; margin-bottom: 20px;
        }
        .searchbar input {
            min-width: 0; flex: 1; padding: 13px;
            border: 1px solid #dce5eb; border-radius: 9px;
            font: inherit; background: white;
        }
        button {
            border: 0; cursor: pointer; border-radius: 8px;
            padding: 9px 12px; font-weight: 700;
        }
        .search-btn { background: #079b91; color: white; padding: 0 18px; }
        .message {
            border: 1px solid #e7edf2; border-radius: 13px;
            background: white; padding: 20px; margin-bottom: 15px;
            overflow-wrap: anywhere;
        }
        .message-head {
            display: flex; align-items: flex-start;
            justify-content: space-between; gap: 12px; flex-wrap: wrap;
        }
        .message h3 { margin: 0 0 8px; font-size: 17px; }
        .meta { color: #758493; font-size: 13px; line-height: 1.8; }
        .body-text { white-space: pre-wrap; line-height: 1.7; margin: 18px 0; }
        .badge {
            display: inline-block; border-radius: 20px; padding: 5px 10px;
            background: #fff4d8; color: #8a6100; font-size: 12px;
            font-weight: 700;
        }
        .badge.read { background: #e0f7ec; color: #147747; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .read-btn { background: #e0f7ec; color: #147747; }
        .new-btn { background: #fff4d8; color: #8a6100; }
        .error, .empty {
            background: white; padding: 22px; border-radius: 12px;
            color: #667788; margin-top: 15px;
        }
        .error { color: #b42318; }
        @media(max-width: 600px) {
            body { padding: 15px; }
            .stats { grid-template-columns: 1fr; }
            h1 { font-size: 23px; }
        }
    </style>
</head>
<body>
<div class="container">
    <header class="topbar">
        <div>
            <h1><i class="fa-solid fa-envelope-open-text"></i> Contact Messages</h1>
            <div class="muted">Review messages submitted through the MediConnect website.</div>
        </div>
        <a class="back" href="admin-panel.php">
            <i class="fa-solid fa-arrow-left"></i> Admin Dashboard
        </a>
    </header>

    <section class="stats">
        <div class="stat">
            <span class="muted">Total Messages</span>
            <strong><?= $totalMessages ?></strong>
        </div>
        <div class="stat">
            <span class="muted">New Messages</span>
            <strong><?= $newMessages ?></strong>
        </div>
    </section>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php else: ?>
        <form class="searchbar" method="GET" action="admin-messages.php">
            <input type="search" name="search"
                   value="<?= h($search) ?>"
                   placeholder="Search name, email, subject or message...">
            <button class="search-btn" type="submit">
                <i class="fa-solid fa-search"></i> Search
            </button>
        </form>

        <p class="muted">
            Showing <?= count($messages) ?> message(s).
        </p>

        <?php if (!$messages): ?>
            <div class="empty">
                <i class="fa-regular fa-envelope"></i>
                No contact messages found.
            </div>
        <?php endif; ?>

        <?php foreach ($messages as $item): ?>
            <article class="message">
                <div class="message-head">
                    <div>
                        <h3><?= h($item['subject']) ?></h3>
                        <div class="meta">
                            <strong><?= h($item['name']) ?></strong><br>
                            <a href="mailto:<?= h($item['email']) ?>">
                                <?= h($item['email']) ?>
                            </a><br>
                            Received: <?= h($item['created_at']) ?>
                        </div>
                    </div>
                    <span class="badge <?= strtolower($item['status']) === 'read' ? 'read' : '' ?>">
                        <?= h($item['status']) ?>
                    </span>
                </div>

                <div class="body-text"><?= h($item['message']) ?></div>

                <div class="actions">
                    <?php if (strtolower($item['status']) === 'read'): ?>
                        <form method="POST">
                            <input type="hidden" name="message_id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="action" value="new">
                            <button class="new-btn" type="submit">Mark as New</button>
                        </form>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="message_id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="action" value="read">
                            <button class="read-btn" type="submit">
                                <i class="fa-solid fa-check"></i> Mark as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>