<?php
session_start();
include '../includes/functions.php';

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

$messages = [];
$emailSettings = getEmailAccount();
$senderEmail = $emailSettings['sender_email'] ?? '';
$appPassword = $emailSettings['app_password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senderEmail = trim($_POST['sender_email'] ?? '');
    $appPasswordInput = trim($_POST['app_password'] ?? '');

    if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        $messages[] = ['type' => 'danger', 'text' => 'Please enter a valid sender email address.'];
    }

    if ($appPasswordInput === '' && empty($appPassword)) {
        $messages[] = ['type' => 'danger', 'text' => 'Please enter a 16-character app password.'];
    }

    if ($appPasswordInput !== '' && strlen($appPasswordInput) !== 16) {
        $messages[] = ['type' => 'danger', 'text' => 'The app password must be exactly 16 characters.'];
    }

    if (empty($messages)) {
        $passwordToSave = $appPasswordInput !== '' ? $appPasswordInput : $appPassword;
        if (saveEmailAccount($senderEmail, $passwordToSave)) {
            $messages[] = ['type' => 'success', 'text' => 'Email settings saved successfully.'];
            $emailSettings = getEmailAccount();
            $senderEmail = $emailSettings['sender_email'] ?? '';
            $appPassword = $emailSettings['app_password'] ?? $emailSettings['sender_app_password'] ?? '';
        } else {
            $messages[] = ['type' => 'danger', 'text' => 'Unable to save email settings. Please try again.'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Setup - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f4f6fb; }
        .page-header { margin: 1.5rem 0; }
        .form-text { font-size: 0.88rem; color: #6c757d; }
        .card { border-radius: 0.75rem; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .sidebar { min-width: 250px; }
        .main { margin-left: 270px; padding: 20px; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    <main class="main">
        <section class="section">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center page-header">
                    <div>
                        <h1>Email Setup</h1>
                        <p class="text-muted">Configure the sender email and 16-character app password used by outgoing notifications.</p>
                    </div>
                    <a href="admin.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>

                <?php foreach ($messages as $message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message['type']); ?>" role="alert">
                        <?php echo htmlspecialchars($message['text']); ?>
                    </div>
                <?php endforeach; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="post" novalidate>
                            <div class="mb-3">
                                <label for="sender_email" class="form-label">Sender Email</label>
                                <input type="email" id="sender_email" name="sender_email" class="form-control" value="<?php echo htmlspecialchars($senderEmail); ?>" required>
                                <div class="form-text">This email is used as the sender for outgoing notification emails.</div>
                            </div>
                            <div class="mb-3">
                                <label for="app_password" class="form-label">App Password</label>
                                <input type="password" id="app_password" name="app_password" class="form-control" placeholder="Enter 16-character app password" maxlength="16" minlength="16" autocomplete="new-password">
                                <div class="form-text">Enter a 16-character app password for the sender email. Leave blank to keep the current stored password.</div>
                                <?php if (!empty($appPassword)): ?>
                                    <div class="form-text">Existing password is stored. Leave this blank to retain the current value.</div>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Email Setup</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Existing configuration</h5>
                        <p class="card-text">Current sender email: <strong><?php echo htmlspecialchars($senderEmail ?: 'Not configured'); ?></strong></p>
                        <p class="card-text">Password status: <strong><?php echo !empty($appPassword) ? 'Stored' : 'Not set'; ?></strong></p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
