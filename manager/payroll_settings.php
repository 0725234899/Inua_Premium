<?php
session_start();
include '../includes/functions.php';

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('Location: ../login.php');
    exit();
}

$conn = db_connect();
ensurePayrollEmailSettingsTable();
$messages = [];
$errors = [];
$emailId = 0;
$senderEmail = '';
$adminEmail = '';
$senderAppPassword = '';
$adminAppPassword = '';
$storedSenderPassword = '';
$storedAdminPassword = '';

function fetchPayrollEmailAccounts($conn) {
    $stmt = $conn->query("SELECT id, sender_email, sender_app_password, admin_email, admin_app_password, updated_at FROM payroll_email_settings ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchPayrollEmailAccountById($conn, $id) {
    $stmt = $conn->prepare("SELECT id, sender_email, sender_app_password, admin_email, admin_app_password, updated_at FROM payroll_email_settings WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$accounts = fetchPayrollEmailAccounts($conn);

if (isset($_GET['edit'])) {
    $emailId = (int)$_GET['edit'];
    if ($emailId > 0) {
        $record = fetchPayrollEmailAccountById($conn, $emailId);
        if ($record) {
            $senderEmail = $record['sender_email'];
            $adminEmail = $record['admin_email'] ?? '';
            $storedSenderPassword = $record['sender_app_password'] ?? '';
            $storedAdminPassword = $record['admin_app_password'] ?? '';
        }
    }
}

if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM payroll_email_settings");
    $stmt->execute();
    $messages[] = 'All email credentials have been cleared.';
    $accounts = fetchPayrollEmailAccounts($conn);
    $emailId = 0;
    $senderEmail = '';
    $storedPassword = '';
    $appPassword = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailId = (int)($_POST['email_id'] ?? 0);
    $senderEmail = trim($_POST['sender_email'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $senderAppPassword = trim($_POST['sender_app_password'] ?? '');
    $adminAppPassword = trim($_POST['admin_app_password'] ?? '');

    if ($senderEmail === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid sender email address.';
    }

    if ($adminEmail !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid admin email address or leave it blank.';
    }

    if ($emailId === 0 && $senderAppPassword === '' && empty($accounts)) {
        $errors[] = 'Please enter the app password for the new sender email account.';
    }

    if ($senderAppPassword !== '' && trim($senderAppPassword) === '') {
        $errors[] = 'The sender app password cannot be blank.';
    }

    if ($adminEmail !== '' && $emailId === 0 && $adminAppPassword === '') {
        $errors[] = 'Please enter the admin app password when adding an admin email.';
    }

    if ($adminAppPassword !== '' && trim($adminAppPassword) === '') {
        $errors[] = 'The admin app password cannot be blank.';
    }

    if (empty($errors)) {
        if ($emailId > 0) {
            $existing = fetchPayrollEmailAccountById($conn, $emailId);
            if (!$existing) {
                $errors[] = 'Email account not found.';
            }
        } else {
            $stmt = $conn->prepare("SELECT id, sender_app_password, admin_email, admin_app_password FROM payroll_email_settings WHERE sender_email = ? LIMIT 1");
            $stmt->execute([$senderEmail]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $emailId = (int)$existing['id'];
                $storedSenderPassword = $existing['sender_app_password'] ?? '';
                $adminEmail = $existing['admin_email'] ?? $adminEmail;
                $storedAdminPassword = $existing['admin_app_password'] ?? '';
            }
        }
    }

    if (empty($errors)) {
        $passwordToSave = $senderAppPassword !== '' ? $senderAppPassword : $storedSenderPassword;
        $adminPasswordToSave = $adminAppPassword !== '' ? $adminAppPassword : $storedAdminPassword;
        if ($passwordToSave === '') {
            $errors[] = 'Sender app password is required to save this email account.';
        }
        if ($adminEmail !== '' && $adminPasswordToSave === '') {
            $errors[] = 'Admin app password is required when admin email is provided.';
        }
    }

    if (empty($errors)) {
        try {
            if ($emailId > 0) {
                $stmt = $conn->prepare("UPDATE payroll_email_settings SET sender_email = ?, sender_app_password = ?, admin_email = ?, admin_app_password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$senderEmail, $passwordToSave, $adminEmail, $adminPasswordToSave, $emailId]);
                $messages[] = 'Email account updated successfully.';
            } else {
                $stmt = $conn->prepare("INSERT INTO payroll_email_settings (sender_email, sender_app_password, admin_email, admin_app_password) VALUES (?, ?, ?, ?)");
                $stmt->execute([$senderEmail, $passwordToSave, $adminEmail, $adminPasswordToSave]);
                $messages[] = 'Email account added successfully.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error saving email account: ' . $e->getMessage();
        }

        $accounts = fetchPayrollEmailAccounts($conn);
        if ($emailId > 0) {
            $record = fetchPayrollEmailAccountById($conn, $emailId);
            if ($record) {
                $senderEmail = $record['sender_email'];
                $adminEmail = $record['admin_email'] ?? '';
                $storedSenderPassword = $record['sender_app_password'] ?? '';
                $storedAdminPassword = $record['admin_app_password'] ?? '';
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
    <title>Payroll Email Setup - Manager Dashboard</title>
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
                <h4 class="mb-0">Payroll Email Setup</h4>
                <a href="index.php" class="btn btn-light btn-sm">Back to Dashboard</a>
            </div>
            <div class="card-body">
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
                <?php endforeach; ?>
                <?php foreach ($messages as $message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
                <?php endforeach; ?>

                <p class="text-muted">Enter the manager email account credentials used to send payroll notifications to staff. App passwords are stored securely and never displayed.</p>

                <form method="post" action="payroll_settings.php" class="row g-3">
                    <input type="hidden" name="email_id" value="<?php echo (int)$emailId; ?>">
                    <div class="col-md-3">
                        <label for="sender_email" class="form-label">Manager Sender Email</label>
                        <input type="email" id="sender_email" name="sender_email" class="form-control" value="<?php echo htmlspecialchars($senderEmail, ENT_QUOTES); ?>" required>
                        <div class="form-text">Manager email used to send payroll notifications.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="sender_app_password" class="form-label">Manager App Password</label>
                        <input type="password" id="sender_app_password" name="sender_app_password" class="form-control" placeholder="e.g. mdaj xpca xdok dxqq" autocomplete="new-password">
                        <div class="form-text">Required for a new setup. Leave blank when editing to keep the stored password.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="admin_email" class="form-label">Manager Copy Email</label>
                        <input type="email" id="admin_email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($adminEmail, ENT_QUOTES); ?>" placeholder="Optional admin email">
                        <div class="form-text">Optional address to receive a copy of payroll emails.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="admin_app_password" class="form-label">Manager Copy App Password</label>
                        <input type="password" id="admin_app_password" name="admin_app_password" class="form-control" placeholder="Optional admin app password" autocomplete="new-password">
                        <div class="form-text">Required only when a copy email is configured.</div>
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
                <h5 class="mb-0">Configured Manager Payroll Email</h5>
            </div>
            <div class="card-body">
                <?php if (empty($accounts)): ?>
                    <p class="text-muted mb-0">No manager payroll email account has been configured yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Sender Email</th>
                                    <th>Admin Email</th>
                                    <th>Sender Password</th>
                                    <th>Admin Password</th>
                                    <th>Last Updated</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $account): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($account['sender_email'], ENT_QUOTES); ?></td>
                                        <td><?php echo htmlspecialchars($account['admin_email'] ?? '', ENT_QUOTES); ?></td>
                                        <td>••••••••••••••••</td>
                                        <td><?php echo !empty($account['admin_app_password']) ? '••••••••••••••••' : '' ; ?></td>
                                        <td><?php echo htmlspecialchars($account['updated_at'], ENT_QUOTES); ?></td>
                                        <td class="text-end">
                                            <a href="payroll_settings.php?edit=<?php echo (int)$account['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                            <a href="payroll_settings.php?delete=1" class="btn btn-sm btn-danger ms-1" onclick="return confirm('Clear all stored manager payroll email credentials?');">Delete</a>
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
