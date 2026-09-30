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
    sender_email VARCHAR(255) DEFAULT NULL,
    sender_app_password VARCHAR(255) DEFAULT NULL,
    admin_email VARCHAR(255) DEFAULT NULL,
    admin_app_password VARCHAR(255) DEFAULT NULL,
    admin_totp_secret VARCHAR(128) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$senderEmailColumn = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'sender_email'")->fetch(PDO::FETCH_ASSOC);
if ($senderEmailColumn && $senderEmailColumn['Null'] === 'NO') {
    $conn->exec("ALTER TABLE email_accounts MODIFY COLUMN sender_email VARCHAR(255) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'sender_app_password'");
if ($columnCheck->rowCount() === 0) {
// Make column nullable by default to avoid insert failures in strict SQL modes when no default is provided
$conn->exec("ALTER TABLE email_accounts ADD COLUMN sender_app_password VARCHAR(255) DEFAULT NULL AFTER sender_email");
}
$senderPasswordColumn = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'sender_app_password'")->fetch(PDO::FETCH_ASSOC);
if ($senderPasswordColumn && $senderPasswordColumn['Null'] === 'NO') {
    $conn->exec("ALTER TABLE email_accounts MODIFY COLUMN sender_app_password VARCHAR(255) DEFAULT NULL");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'admin_app_password'");
if ($columnCheck->rowCount() === 0) {
    $conn->exec("ALTER TABLE email_accounts ADD COLUMN admin_app_password VARCHAR(255) DEFAULT NULL AFTER admin_email");
}
$columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'admin_totp_secret'");
if ($columnCheck->rowCount() === 0) {
    $conn->exec("ALTER TABLE email_accounts ADD COLUMN admin_totp_secret VARCHAR(128) DEFAULT NULL AFTER admin_app_password");
}

// Ensure legacy 'app_password' column (if present) is nullable to avoid strict-mode insert errors
try {
    $legacyCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'app_password'");
    if ($legacyCheck && $legacyCheck->rowCount() > 0) {
        // Make it nullable with a sensible length. Use MODIFY to preserve column name.
        $conn->exec("ALTER TABLE email_accounts MODIFY COLUMN app_password VARCHAR(255) DEFAULT NULL");
    }
} catch (Exception $e) {
    // Ignore — best-effort migration without blocking page load
}

$messages = [];
$errors = [];
$emailId = 0;
$senderEmail = '';
$adminEmail = '';
$adminTotpSecret = '';
$senderAppPassword = '';
$adminAppPassword = '';
$storedSenderPassword = '';
$storedAdminPassword = '';
$storedTotpSecret = '';

function fetchEmailAccounts($conn) {
    $stmt = $conn->query("SELECT id, sender_email, sender_app_password, admin_email, admin_app_password, (admin_totp_secret IS NOT NULL AND admin_totp_secret <> '') AS has_admin_totp, updated_at FROM email_accounts ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchEmailAccountById($conn, $id) {
    $stmt = $conn->prepare("SELECT id, sender_email, sender_app_password, admin_email, admin_app_password, admin_totp_secret, updated_at FROM email_accounts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$accounts = fetchEmailAccounts($conn);
$emailId = (int) ($_GET['edit'] ?? ($accounts[0]['id'] ?? 0));

if ($emailId > 0) {
    $record = fetchEmailAccountById($conn, $emailId);
    if ($record) {
        $senderEmail = $record['sender_email'] ?? '';
        $adminEmail = $record['admin_email'] ?? '';
        $storedSenderPassword = $record['sender_app_password'] ?? '';
        $storedAdminPassword = $record['admin_app_password'] ?? '';
        $storedTotpSecret = $record['admin_totp_secret'] ?? '';
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
    $action = $_POST['action'] ?? '';
    $emailId = (int) ($_POST['email_id'] ?? 0);
    $existing = $emailId > 0 ? fetchEmailAccountById($conn, $emailId) : null;
    if ($emailId > 0 && !$existing) {
        $errors[] = 'Email account not found.';
    }

    if ($action === 'save_sender') {
        $senderEmail = trim($_POST['sender_email'] ?? '');
        $senderAppPassword = trim($_POST['sender_app_password'] ?? '');
        $storedSenderPassword = $existing['sender_app_password'] ?? '';
        $passwordToSave = $senderAppPassword !== '' ? $senderAppPassword : $storedSenderPassword;

        if ($senderEmail === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid sender email address.';
        }
        if ($passwordToSave === '') {
            $errors[] = 'Enter the sender app password to save sender credentials.';
        }

        if (empty($errors)) {
            try {
                if ($existing) {
                    $stmt = $conn->prepare("UPDATE email_accounts SET sender_email = ?, sender_app_password = ?, app_password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$senderEmail, $passwordToSave, $passwordToSave, $emailId]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO email_accounts (sender_email, sender_app_password, app_password) VALUES (?, ?, ?)");
                    $stmt->execute([$senderEmail, $passwordToSave, $passwordToSave]);
                    $emailId = (int) $conn->lastInsertId();
                }
                $messages[] = 'Sender email credentials saved independently.';
            } catch (PDOException $e) {
                $errors[] = 'Database error saving sender credentials: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'save_admin') {
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminAppPassword = trim($_POST['admin_app_password'] ?? '');
        $adminTotpSecret = strtoupper(preg_replace('/\s+/', '', trim($_POST['admin_totp_secret'] ?? '')));
        $clearTotpSecret = isset($_POST['clear_totp_secret']);
        $storedAdminPassword = $existing['admin_app_password'] ?? '';
        $storedTotpSecret = $existing['admin_totp_secret'] ?? '';
        $passwordToSave = $adminAppPassword !== '' ? $adminAppPassword : $storedAdminPassword;
        $totpSecretToSave = $clearTotpSecret ? null : ($adminTotpSecret !== '' ? $adminTotpSecret : $storedTotpSecret);

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid admin email address.';
        }
        if ($passwordToSave === '') {
            $errors[] = 'Enter the admin app password to save admin credentials.';
        }
        if ($adminTotpSecret !== '' && !preg_match('/^[A-Z2-7]+=*$/', $adminTotpSecret)) {
            $errors[] = 'The Google Authenticator secret must be a Base32 setup key (letters A-Z and digits 2-7), not a six-digit code.';
        }
        if ($totpSecretToSave !== null && $totpSecretToSave !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid admin email is required when a Google Authenticator secret is configured.';
        }

        if (empty($errors)) {
            try {
                if ($existing) {
                    $stmt = $conn->prepare("UPDATE email_accounts SET admin_email = ?, admin_app_password = ?, admin_totp_secret = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$adminEmail, $passwordToSave, $totpSecretToSave, $emailId]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO email_accounts (sender_email, admin_email, admin_app_password, admin_totp_secret) VALUES (NULL, ?, ?, ?)");
                    $stmt->execute([$adminEmail, $passwordToSave, $totpSecretToSave]);
                    $emailId = (int) $conn->lastInsertId();
                }
                $messages[] = 'Admin email and authenticator credentials saved independently.';
            } catch (PDOException $e) {
                $errors[] = 'Database error saving admin credentials: ' . $e->getMessage();
            }
        }
    } else {
        $errors[] = 'Choose which email credentials to save.';
    }

    $accounts = fetchEmailAccounts($conn);
    if ($emailId > 0) {
        $record = fetchEmailAccountById($conn, $emailId);
        if ($record) {
            $senderEmail = $record['sender_email'] ?? '';
            $adminEmail = $record['admin_email'] ?? '';
            $storedSenderPassword = $record['sender_app_password'] ?? '';
            $storedAdminPassword = $record['admin_app_password'] ?? '';
            $storedTotpSecret = $record['admin_totp_secret'] ?? '';
        }
    }
    if (!empty($errors) && $action === 'save_sender') {
        $senderEmail = trim($_POST['sender_email'] ?? '');
    }
    if (!empty($errors) && $action === 'save_admin') {
        $adminEmail = trim($_POST['admin_email'] ?? '');
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">Email Setup</h3>
            <a href="admin.php" class="btn btn-outline-primary btn-sm">Back to Dashboard</a>
        </div>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
        <?php endforeach; ?>
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
        <?php endforeach; ?>

        <div class="card mb-4">
            <div class="card-header"><h4 class="mb-0">Sender Email Credentials</h4></div>
            <div class="card-body">
                <p class="text-muted">Configure the email account used to send system notifications. Saving this form does not change admin credentials.</p>
                <form method="post" action="email-accounts.php" class="row g-3">
                    <input type="hidden" name="email_id" value="<?php echo (int) $emailId; ?>">
                    <div class="col-md-6">
                        <label for="sender_email" class="form-label">Sender Email</label>
                        <input type="email" id="sender_email" name="sender_email" class="form-control" value="<?php echo htmlspecialchars($senderEmail, ENT_QUOTES); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="sender_app_password" class="form-label">Sender App Password</label>
                        <input type="password" id="sender_app_password" name="sender_app_password" class="form-control" placeholder="<?php echo $storedSenderPassword !== '' ? 'Configured; leave blank to keep it' : 'Enter sender app password'; ?>" autocomplete="new-password" <?php echo $storedSenderPassword === '' ? 'required' : ''; ?>>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="action" value="save_sender" class="btn btn-primary">Save Sender Credentials</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h4 class="mb-0">Admin Email Credentials</h4></div>
            <div class="card-body">
                <p class="text-muted">Configure the admin mailbox and its Google Authenticator setup key independently of the sender account.</p>
                <form method="post" action="email-accounts.php" class="row g-3">
                    <input type="hidden" name="email_id" value="<?php echo (int) $emailId; ?>">
                    <div class="col-md-6">
                        <label for="admin_email" class="form-label">Admin Email</label>
                        <input type="email" id="admin_email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($adminEmail, ENT_QUOTES); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="admin_app_password" class="form-label">Admin App Password</label>
                        <input type="password" id="admin_app_password" name="admin_app_password" class="form-control" placeholder="<?php echo $storedAdminPassword !== '' ? 'Configured; leave blank to keep it' : 'Enter admin app password'; ?>" autocomplete="new-password" <?php echo $storedAdminPassword === '' ? 'required' : ''; ?>>
                    </div>
                    <div class="col-12">
                        <label for="admin_totp_secret" class="form-label">Admin Google Authenticator Setup Key</label>
                        <input type="password" id="admin_totp_secret" name="admin_totp_secret" class="form-control" placeholder="<?php echo $storedTotpSecret !== '' ? 'Configured; leave blank to keep it' : 'Enter the Base32 setup key'; ?>" autocomplete="new-password">
                        <div class="form-text">Enter the Base32 setup key registered for the admin email, not the rotating six-digit code.</div>
                        <?php if ($storedTotpSecret !== ''): ?>
                            <div class="form-text text-success">Authenticator setup key is configured.</div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="clear_totp_secret" name="clear_totp_secret" value="1">
                                <label class="form-check-label" for="clear_totp_secret">Remove the stored authenticator key</label>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="action" value="save_admin" class="btn btn-primary">Save Admin Credentials</button>
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
                                    <th>Sender Email</th>
                                    <th>Admin Email</th>
                                    <th>Sender Password</th>
                                    <th>Admin Password</th>
                                    <th>Authenticator</th>
                                    <th>Last Updated</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $account): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($account['sender_email'] ?? '', ENT_QUOTES); ?></td>
                                        <td><?php echo htmlspecialchars($account['admin_email'] ?? '', ENT_QUOTES); ?></td>
                                        <td><?php echo !empty($account['sender_app_password']) ? '••••••••••••••••' : ''; ?></td>
                                        <td><?php echo !empty($account['admin_app_password']) ? '••••••••••••••••' : '' ; ?></td>
                                        <td><?php echo !empty($account['has_admin_totp']) ? 'Configured' : 'Not configured'; ?></td>
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
