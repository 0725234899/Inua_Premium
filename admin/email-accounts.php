<?php
session_start();
include '../includes/functions.php';

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

$conn = db_connect();
$conn->exec("CREATE TABLE IF NOT EXISTS email_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_email VARCHAR(255) NOT NULL,
    app_password VARCHAR(255) NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$messages = [];
$errors = [];
$emailId = 0;
$senderEmail = '';
$appPassword = '';
$storedPassword = '';

function fetchEmailAccounts($conn) {
    $stmt = $conn->query("SELECT id, sender_email, app_password, updated_at FROM email_accounts ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchEmailAccountById($conn, $id) {
    $stmt = $conn->prepare("SELECT id, sender_email, app_password, updated_at FROM email_accounts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$accounts = fetchEmailAccounts($conn);

if (isset($_GET['edit'])) {
    $emailId = (int)$_GET['edit'];
    if ($emailId > 0) {
        $record = fetchEmailAccountById($conn, $emailId);
        if ($record) {
            $senderEmail = $record['sender_email'];
            $storedPassword = $record['app_password'];
        }
    }
}

if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM email_accounts");
    $stmt->execute();
    $messages[] = 'All email credentials have been cleared.';
    $accounts = fetchEmailAccounts($conn);
    $emailId = 0;
    $senderEmail = '';
    $storedPassword = '';
    $appPassword = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailId = (int)($_POST['email_id'] ?? 0);
    $senderEmail = trim($_POST['sender_email'] ?? '');
    $appPassword = trim($_POST['app_password'] ?? '');

    if ($senderEmail === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid sender email address.';
    }

    if ($emailId === 0 && $appPassword === '' && empty($accounts)) {
        $errors[] = 'Please enter the app password for the new email account.';
    }

    if ($appPassword !== '' && trim($appPassword) === '') {
        $errors[] = 'The app password cannot be blank.';
    }

    if (empty($errors)) {
        if ($emailId > 0) {
            $existing = fetchEmailAccountById($conn, $emailId);
            if (!$existing) {
                $errors[] = 'Email account not found.';
            }
        } else {
            $stmt = $conn->prepare("SELECT id, app_password FROM email_accounts WHERE sender_email = ? LIMIT 1");
            $stmt->execute([$senderEmail]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $emailId = (int)$existing['id'];
                $storedPassword = $existing['app_password'];
            }
        }
    }

    if (empty($errors)) {
        $passwordToSave = $appPassword !== '' ? $appPassword : $storedPassword;
        if ($passwordToSave === '') {
            $errors[] = 'App password is required to save this email account.';
        }
    }

    if (empty($errors)) {
        if ($emailId > 0) {
            $stmt = $conn->prepare("UPDATE email_accounts SET sender_email = ?, app_password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$senderEmail, $passwordToSave, $emailId]);
            $messages[] = 'Email account updated successfully.';
        } else {
            $stmt = $conn->prepare("INSERT INTO email_accounts (sender_email, app_password) VALUES (?, ?)");
            $stmt->execute([$senderEmail, $passwordToSave]);
            $messages[] = 'Email account added successfully.';
        }

        $accounts = fetchEmailAccounts($conn);
        if ($emailId > 0) {
            $record = fetchEmailAccountById($conn, $emailId);
            if ($record) {
                $senderEmail = $record['sender_email'];
                $storedPassword = $record['app_password'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Setup - Manager Dashboard</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f7f9fc; font-family: Arial, sans-serif; }
        .page-wrap { max-width: 1000px; margin: 30px auto; padding: 20px; }
        .card { border: 0; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
        .card-header { background: #0d6efd; color: #fff; }
        .form-text { color: #6c757d; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="page-wrap">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Email Setup</h4>
                <a href="admin.php" class="btn btn-light btn-sm">Back to Dashboard</a>
            </div>
            <div class="card-body">
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
                <?php endforeach; ?>
                <?php foreach ($messages as $message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
                <?php endforeach; ?>

                <p class="text-muted">Use this form to add or update an email account. You can paste the app password with spaces or without spaces; it will be handled automatically. Existing sender credentials are updated when you save changes.</p>

                <form method="post" action="email-accounts.php" class="row g-3">
                    <input type="hidden" name="email_id" value="<?php echo (int)$emailId; ?>">
                    <div class="col-md-6">
                        <label for="sender_email" class="form-label">Sender Email</label>
                        <input type="email" id="sender_email" name="sender_email" class="form-control" value="<?php echo htmlspecialchars($senderEmail, ENT_QUOTES); ?>" required>
                        <div class="form-text">Enter the sender email used for outgoing notifications.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="app_password" class="form-label">App Password</label>
                        <input type="password" id="app_password" name="app_password" class="form-control" placeholder="e.g. mdaj xpca xdok dxqq" autocomplete="new-password">
                        <div class="form-text">Enter the app password. Example: <strong>mdaj xpca xdok dxqq</strong> or <strong>mdajxpcaxdokdxqq</strong>. Leave blank to keep the current stored password.</div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?php echo $emailId > 0 ? 'Update Email Settings' : 'Save Email Settings'; ?></button>
                        <?php if ($emailId > 0): ?>
                            <a href="email-accounts.php" class="btn btn-secondary ms-2">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Configured Email Accounts</h5>
            </div>
            <div class="card-body">
                <?php if (empty($accounts)): ?>
                    <p class="text-muted mb-0">No email account has been configured yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>App Password</th>
                                    <th>Last Updated</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $account): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($account['sender_email'], ENT_QUOTES); ?></td>
                                        <td>••••••••••••••••</td>
                                        <td><?php echo htmlspecialchars($account['updated_at'], ENT_QUOTES); ?></td>
                                        <td class="text-end">
                                            <a href="email-accounts.php?edit=<?php echo (int)$account['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                            <a href="email-accounts.php?delete=1" class="btn btn-sm btn-danger ms-1" onclick="return confirm('Clear all stored email credentials?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
