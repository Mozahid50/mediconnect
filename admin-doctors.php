<?php
session_start();
require_once 'config.php';

// config.php must provide a PDO connection in $pdo.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Database connection not found. Please check config.php.');
}

$message = '';
$error = '';

if (empty($_SESSION['doctor_csrf'])) {
    $_SESSION['doctor_csrf'] = bin2hex(random_bytes(32));
}

// Show one-time messages
if (!empty($_SESSION['doctor_message'])) {
    $message = $_SESSION['doctor_message'];
    unset($_SESSION['doctor_message']);
}

if (!empty($_SESSION['doctor_error'])) {
    $error = $_SESSION['doctor_error'];
    unset($_SESSION['doctor_error']);
}

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectToDoctors(): void {
    header('Location: admin-doctors.php');
    exit;
}

function uploadDoctorPhoto(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Photo upload failed. Please try again.');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Photo must be 2 MB or smaller.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);

    if ($imageInfo === false) {
        throw new RuntimeException('Please select a valid image.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG and WEBP photos are allowed.');
    }

    $folder = __DIR__ . '/uploads/doctors/';

    if (!is_dir($folder) &&
        !mkdir($folder, 0755, true) &&
        !is_dir($folder)) {
        throw new RuntimeException('Could not create the photo upload folder.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $folder . $filename)) {
        throw new RuntimeException('Could not save the uploaded photo.');
    }

    return 'uploads/doctors/' . $filename;
}

function deleteDoctorPhoto(?string $path): void {
    if (!$path || strpos($path, 'uploads/doctors/') !== 0) {
        return;
    }

    $fullPath = __DIR__ . '/' . $path;
    $uploadRoot = realpath(__DIR__ . '/uploads/doctors');

    if ($uploadRoot && is_file($fullPath)) {
        $realFile = realpath($fullPath);

        if ($realFile && strpos($realFile, $uploadRoot . DIRECTORY_SEPARATOR) === 0) {
            @unlink($realFile);
        }
    }
}

// Handle Add, Edit and Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['doctor_csrf'], $token)) {
        $_SESSION['doctor_error'] = 'Invalid request. Please try again.';
        redirectToDoctors();
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $id = trim((string)($_POST['doctor_id'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $specialty = trim((string)($_POST['specialty'] ?? ''));
            $qualification = trim((string)($_POST['qualification'] ?? ''));
            $experience = filter_var(
                $_POST['experience'] ?? null,
                FILTER_VALIDATE_INT
            );
            $fee = $_POST['fee'] ?? '';
            $phone = trim((string)($_POST['phone'] ?? ''));
            $status = $_POST['status'] ?? 'Active';

            if ($name === '' || $specialty === '' ||
                $qualification === '' || $phone === '') {
                throw new RuntimeException('Please fill in all required fields.');
            }

            if (mb_strlen($name) > 150 ||
                mb_strlen($specialty) > 100 ||
                mb_strlen($qualification) > 255 ||
                mb_strlen($phone) > 30) {
                throw new RuntimeException('One or more fields are too long.');
            }

            if ($experience === false || $experience < 0) {
                throw new RuntimeException('Enter a valid experience in years.');
            }

            if (!is_numeric($fee) || (float)$fee < 0 ||
                !is_finite((float)$fee) || (float)$fee > 99999999.99) {
                throw new RuntimeException('Enter a valid consultation fee.');
            }

            if (!in_array($status, ['Active', 'Pending', 'Inactive'], true)) {
                throw new RuntimeException('Please select a valid status.');
            }

            $existing = null;

            if ($id !== '') {
                if (!ctype_digit($id) || (int)$id < 1) {
                    throw new RuntimeException('Invalid doctor ID.');
                }

                $stmt = $pdo->prepare(
                    'SELECT id, photo FROM doctors WHERE id = ?'
                );
                $stmt->execute([(int)$id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    throw new RuntimeException('Doctor not found.');
                }
            }

            $newPhoto = null;

            try {
                $newPhoto = uploadDoctorPhoto($_FILES['doctor_photo'] ?? []);

                $photo = $newPhoto
                    ?? ($existing['photo'] ?? null);

                if ($id !== '') {
                    $stmt = $pdo->prepare(
                        'UPDATE doctors
                         SET name = ?, specialty = ?, qualification = ?,
                             experience = ?, fee = ?, phone = ?,
                             status = ?, photo = ?
                         WHERE id = ?'
                    );

                    $stmt->execute([
                        $name,
                        $specialty,
                        $qualification,
                        $experience,
                        (float)$fee,
                        $phone,
                        $status,
                        $photo,
                        (int)$id
                    ]);

                    if ($newPhoto && !empty($existing['photo'])) {
                        deleteDoctorPhoto($existing['photo']);
                    }

                    $_SESSION['doctor_message'] = 'Doctor updated successfully.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO doctors
                         (name, specialty, qualification, experience,
                          fee, phone, status, photo)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );

                    $stmt->execute([
                        $name,
                        $specialty,
                        $qualification,
                        $experience,
                        (float)$fee,
                        $phone,
                        $status,
                        $photo
                    ]);

                    $_SESSION['doctor_message'] = 'Doctor added successfully.';
                }
            } catch (Throwable $e) {
                if ($newPhoto) {
                    deleteDoctorPhoto($newPhoto);
                }

                throw $e;
            }

            redirectToDoctors();
        }

        if ($action === 'delete') {
            $id = filter_var(
                $_POST['doctor_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid doctor ID.');
            }

            $stmt = $pdo->prepare('SELECT photo FROM doctors WHERE id = ?');
            $stmt->execute([$id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doctor) {
                throw new RuntimeException('Doctor not found.');
            }

            $stmt = $pdo->prepare('DELETE FROM doctors WHERE id = ?');
            $stmt->execute([$id]);

            deleteDoctorPhoto($doctor['photo'] ?? null);

            $_SESSION['doctor_message'] = 'Doctor deleted successfully.';
            redirectToDoctors();
        }

        throw new RuntimeException('Unknown action.');
    } catch (Throwable $e) {
        if ($e instanceof RuntimeException) {
            $_SESSION['doctor_error'] = $e->getMessage();
        } else {
            error_log('Doctor management error: ' . $e->getMessage());
            $_SESSION['doctor_error'] =
                'Something went wrong. Check the database connection and table columns.';
        }

        redirectToDoctors();
    }
}

// Load doctor list and search
$search = trim((string)($_GET['search'] ?? ''));

try {
    if ($search !== '') {
        $stmt = $pdo->prepare(
            'SELECT * FROM doctors
             WHERE name LIKE ? OR specialty LIKE ?
                OR qualification LIKE ? OR phone LIKE ? OR status LIKE ?
             ORDER BY id DESC'
        );

        $term = '%' . $search . '%';
        $stmt->execute([$term, $term, $term, $term, $term]);
    } else {
        $stmt = $pdo->query('SELECT * FROM doctors ORDER BY id DESC');
    }

    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Doctor list error: ' . $e->getMessage());
    $doctors = [];
    $error = 'Could not load doctors. Please check your doctors table.';
}

$editDoctor = null;

if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM doctors WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editDoctor = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Management | MediConnect</title>
    <link rel="stylesheet" href="admin-doctors.css">
    <style>
        .notice {
            padding: 13px 16px;
            margin-bottom: 18px;
            border-radius: 8px;
        }
        .success-notice { background: #e8f8ef; color: #17683b; }
        .error-notice { background: #fff0f0; color: #a52222; }
        .doctor-thumb {
            width: 48px; height: 48px; object-fit: cover;
            border-radius: 9px; vertical-align: middle;
            margin-right: 8px;
        }
        .actions { white-space: nowrap; }
        .actions a { margin-right: 7px; }
        .edit-link {
            display: inline-block; padding: 7px 10px;
            border-radius: 6px; background: #e6f5f4;
            color: #087f7b; text-decoration: none;
        }
        .delete-btn {
            padding: 7px 10px; border: 0; border-radius: 6px;
            background: #ffe9e9; color: #a52222; cursor: pointer;
        }
        .form-grid input, .form-grid select { max-width: 100%; }
        .current-photo { width: 100px; height: 100px; object-fit: cover; border-radius: 10px; }
    </style>
</head>
<body>

<header class="topbar">
    <a href="admin-panel.html" class="back-link">← Admin Dashboard</a>
    <h1>Doctor Management</h1>
    <p>Add, edit, search and manage doctor profiles.</p>
</header>

<main class="container">

    <?php if ($message): ?>
        <div class="notice success-notice"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice error-notice"><?= h($error) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2><?= $editDoctor ? 'Edit Doctor' : 'Add New Doctor' ?></h2>

        <form method="POST" enctype="multipart/form-data" action="admin-doctors.php">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['doctor_csrf']) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="doctor_id" value="<?= h($editDoctor['id'] ?? '') ?>">

            <div class="form-grid">
                <div>
                    <label for="name">Doctor Name</label>
                    <input id="name" name="name" required maxlength="150"
                           value="<?= h($editDoctor['name'] ?? '') ?>"
                           placeholder="Dr. John Smith">
                </div>

                <div>
                    <label for="specialty">Specialty</label>
                    <input id="specialty" name="specialty" required maxlength="100"
                           value="<?= h($editDoctor['specialty'] ?? '') ?>"
                           placeholder="Cardiologist">
                </div>

                <div>
                    <label for="qualification">Qualification</label>
                    <input id="qualification" name="qualification" required maxlength="255"
                           value="<?= h($editDoctor['qualification'] ?? '') ?>"
                           placeholder="MBBS, FCPS">
                </div>

                <div>
                    <label for="doctor_photo">Doctor Photo (JPG, PNG, WEBP; max 2 MB)</label>
                    <input type="file" id="doctor_photo" name="doctor_photo"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                    <?php if (!empty($editDoctor['photo'])): ?>
                        <p>Current photo:</p>
                        <img class="current-photo"
                             src="<?= h($editDoctor['photo']) ?>" alt="Current doctor photo">
                    <?php endif; ?>
                </div>

                <div>
                    <label for="experience">Experience (years)</label>
                    <input id="experience" name="experience" type="number"
                           min="0" max="100" required
                           value="<?= h($editDoctor['experience'] ?? '') ?>"
                           placeholder="5">
                </div>

                <div>
                    <label for="fee">Consultation Fee (৳)</label>
                    <input id="fee" name="fee" type="number" min="0"
                           step="0.01" required
                           value="<?= h($editDoctor['fee'] ?? '') ?>"
                           placeholder="800">
                </div>

                <div>
                    <label for="phone">Phone Number</label>
                    <input id="phone" name="phone" type="tel" maxlength="30"
                           required value="<?= h($editDoctor['phone'] ?? '') ?>"
                           placeholder="01XXXXXXXXX">
                </div>

                <div>
                    <label for="status">Account Status</label>
                    <?php $currentStatus = $editDoctor['status'] ?? 'Active'; ?>
                    <select id="status" name="status" required>
                        <option value="Active" <?= $currentStatus === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Pending" <?= $currentStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Inactive" <?= $currentStatus === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="primary-btn">
                    <?= $editDoctor ? 'Save Changes' : 'Add Doctor' ?>
                </button>

                <?php if ($editDoctor): ?>
                    <a href="admin-doctors.php" class="cancel-btn">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="list-heading">
            <div>
                <h2>Registered Doctors</h2>
                <p><?= count($doctors) ?> doctor<?= count($doctors) === 1 ? '' : 's' ?></p>
            </div>

            <form method="GET" action="admin-doctors.php">
                <input type="search" name="search" value="<?= h($search) ?>"
                       placeholder="Search doctors...">
                <button type="submit">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="admin-doctors.php">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Experience</th>
                        <th>Fee</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($doctors as $doctor): ?>
                    <tr>
                        <td>
                            <?php if (!empty($doctor['photo'])): ?>
                                <img class="doctor-thumb"
                                     src="<?= h($doctor['photo']) ?>"
                                     alt="Doctor photo">
                            <?php endif; ?>
                            <strong><?= h($doctor['name']) ?></strong><br>
                            <small><?= h($doctor['qualification']) ?></small>
                        </td>
                        <td><?= h($doctor['specialty']) ?></td>
                        <td><?= h($doctor['experience']) ?> years</td>
                        <td>৳<?= h($doctor['fee']) ?></td>
                        <td><?= h($doctor['phone']) ?></td>
                        <td>
                            <span class="status <?= h(strtolower($doctor['status'])) ?>">
                                <?= h($doctor['status']) ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a class="edit-link"
                               href="admin-doctors.php?edit=<?= (int)$doctor['id'] ?>">
                                Edit
                            </a>

                            <form method="POST" action="admin-doctors.php"
                                  style="display:inline"
                                  onsubmit="return confirm('Delete this doctor?');">
                                <input type="hidden" name="csrf_token"
                                       value="<?= h($_SESSION['doctor_csrf']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="doctor_id"
                                       value="<?= (int)$doctor['id'] ?>">
                                <button class="delete-btn" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!$doctors): ?>
            <p style="padding:20px;text-align:center">No doctors found.</p>
        <?php endif; ?>
    </section>

</main>
</body>
</html>