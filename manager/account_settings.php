<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
include __DIR__ . '/../includes/functions.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function sendBillingVerificationEmail($adminEmail, $code, $userName = 'User') {
    $credentials = getEmailAccount();
    if (empty($credentials['sender_email']) || empty($credentials['sender_app_password'])) {
        throw new Exception('Admin email settings are not configured.');
    }

    $mail = new PHPMailer(true);
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth = true;
    $mail->Username = $credentials['sender_email'];
    $mail->Password = $credentials['sender_app_password'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($credentials['sender_email'], 'Inua Premium Services');
    $mail->addAddress($adminEmail, 'System Admin');
    $mail->isHTML(true);
    $mail->Subject = 'System billing verification code';
    $mail->Body = '<p>Hello Admin,</p>'
        . '<p>A user named <strong>' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</strong> has requested a system billing verification code.</p>'
        . '<p><strong>Verification code:</strong> ' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>Please share this code with the user so they can complete the system payment request.</p>'
        . '<p>Regards,<br>Inua Premium Services</p>';
    $mail->AltBody = "Hello Admin,\n\nA user named {$userName} has requested a system billing verification code.\n\nVerification code: {$code}\n\nPlease share this code with the user so they can complete the system payment request.\n\nRegards,\nInua Premium Services";
    $mail->send();
}

$adminAccount = getEmailAccount();
$adminEmail = trim((string) ($adminAccount['admin_email'] ?? $adminAccount['sender_email'] ?? ''));
if ($adminEmail === '') {
    $adminEmail = trim((string) ($adminAccount['sender_email'] ?? ''));
}
if ($adminEmail === '') {
    $adminEmail = 'admin@inua-premium.local';
}

$billingMessage = '';
$billingMessageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billingContact = trim((string) ($_POST['billing_contact'] ?? ''));
    $billingEmail = trim((string) ($_POST['billing_email'] ?? $adminEmail));
    $paymentMethod = trim((string) ($_POST['payment_method'] ?? ''));
    $billingCycle = trim((string) ($_POST['billing_cycle'] ?? ''));
    $authCode = trim((string) ($_POST['auth_code'] ?? ''));
    $action = $_POST['action'] ?? '';

    if ($action === 'request_code') {
        if ($adminEmail === '') {
            $billingMessage = 'The admin email is not configured yet. Add an admin email in the email settings before requesting a verification code.';
            $billingMessageType = 'danger';
        } else {
            $generatedCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['billing_auth_code'] = $generatedCode;
            $_SESSION['billing_auth_code_expiry'] = time() + 600;

            try {
                $userName = $_SESSION['name'] ?? $_SESSION['email'] ?? 'System User';
                sendBillingVerificationEmail($adminEmail, $generatedCode, $userName);
                $billingMessage = 'A verification code has been sent to the admin email address: ' . htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') . '. Use it to complete the payment request.';
                $billingMessageType = 'success';
            } catch (Exception $e) {
                $billingMessage = 'Unable to send the verification code: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                $billingMessageType = 'danger';
            }
        }
    }

    if ($action === 'submit_payment') {
        if ($billingContact === '' || $billingEmail === '' || $paymentMethod === '' || $billingCycle === '' || $authCode === '') {
            $billingMessage = 'Please complete all billing fields and enter the admin authenticator code before submitting the payment request.';
            $billingMessageType = 'danger';
        } elseif (!isset($_SESSION['billing_auth_code']) || !isset($_SESSION['billing_auth_code_expiry']) || time() > (int) $_SESSION['billing_auth_code_expiry']) {
            $billingMessage = 'The verification code has expired. Please request a new code from the admin.';
            $billingMessageType = 'danger';
        } elseif (!isset($_SESSION['billing_auth_code']) || trim((string) $_SESSION['billing_auth_code']) !== trim((string) $authCode)) {
            $billingMessage = 'The authenticator code entered is invalid. Please use the exact code sent by the admin.';
            $billingMessageType = 'danger';
        } else {
            unset($_SESSION['billing_auth_code'], $_SESSION['billing_auth_code_expiry']);
            $billingMessage = 'Your system payment request has been submitted for review. The admin verification code was confirmed successfully.';
            $billingMessageType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Billing</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --ink: #172331;
            --muted: #687582;
            --line: #dbe3e8;
            --paper: #ffffff;
            --canvas: #f2f5f6;
            --teal: #147d78;
            --gold: #c7973e;
            --danger: #c75151;
            --dark: #12212d;
        }

        body {
            background: var(--canvas);
            color: var(--ink);
            font-family: "Trebuchet MS", Arial, sans-serif;
            margin: 0;
        }

        .report-shell {
            max-width: 1120px;
            margin: 34px auto 60px;
            padding: 0 22px;
        }

        .report-heading {
            background: var(--dark);
            border-top: 4px solid var(--gold);
            color: white;
            padding: 30px 34px 27px;
            position: relative;
        }

        .report-heading h1 {
            font-family: Georgia, serif;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: normal;
            letter-spacing: .02em;
            margin: 0 0 8px;
        }

        .report-heading p {
            color: #c3d0d6;
            margin: 0;
        }

        .report-meta {
            color: #e5c579;
            font-size: .8rem;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .report-actions {
            position: absolute;
            right: 34px;
            bottom: 27px;
        }

        .report-actions a,
        .report-actions button {
            border: 1px solid #82939c;
            color: white;
            background: transparent;
            padding: 8px 14px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .report-actions a:hover,
        .report-actions button:hover {
            background: var(--teal);
            border-color: var(--teal);
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin: 18px 0;
        }

        .metric {
            background: var(--paper);
            border: 1px solid var(--line);
            border-left: 4px solid var(--teal);
            padding: 18px 20px;
        }

        .metric:nth-child(2) { border-left-color: var(--gold); }
        .metric:nth-child(3) { border-left-color: #5b7180; }
        .metric:nth-child(4) { border-left-color: #a95d55; }

        .metric-label {
            color: var(--muted);
            font-size: .74rem;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .metric-value {
            display: block;
            font-family: Georgia, serif;
            font-size: 1.45rem;
            margin-top: 8px;
        }

        .payment-panel {
            background: var(--paper);
            border: 1px solid var(--line);
        }

        .panel-heading {
            border-bottom: 1px solid var(--line);
            padding: 18px 22px;
        }

        .panel-heading h2 {
            font-family: Georgia, serif;
            font-size: 1.25rem;
            margin: 0;
        }

        .payment-body {
            padding: 26px 22px 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            color: var(--ink);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            border: 1px solid #d4dfe6;
            border-radius: 10px;
            background: #fbfcfd;
            color: var(--ink);
            padding: 12px 14px;
            width: 100%;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--teal);
            box-shadow: 0 0 0 0.2rem rgba(20, 125, 120, 0.12);
        }

        .helper-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 6px;
            color: var(--muted);
            font-size: 0.82rem;
        }

        .inline-button {
            border: none;
            border-radius: 10px;
            background: var(--gold);
            color: #fff;
            padding: 12px 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .primary-button {
            border: none;
            border-radius: 10px;
            background: var(--teal);
            color: white;
            padding: 12px 20px;
            font-weight: 700;
            cursor: pointer;
        }

        .secondary-button {
            border: 1px solid var(--line);
            background: white;
            color: var(--ink);
            border-radius: 10px;
            padding: 12px 18px;
            font-weight: 600;
            cursor: pointer;
        }

        .button-row {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 12px 14px;
            font-size: 0.95rem;
        }

        .alert-success {
            background: #eafaf4;
            border: 1px solid #cfead8;
            color: #1d684f;
        }

        .alert-danger {
            background: #fff1f1;
            border: 1px solid #f3d4d4;
            color: #8f3030;
        }

        .report-footer {
            color: var(--muted);
            font-size: .78rem;
            margin-top: 14px;
        }

        @media (max-width: 820px) {
            .report-heading { padding: 24px; }
            .report-actions { margin-top: 20px; position: static; }
            .metric-grid { grid-template-columns: repeat(2, 1fr); }
            .form-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 520px) {
            .report-shell { padding: 0 12px; }
            .metric-grid { grid-template-columns: 1fr; }
            .report-heading h1 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="report-shell" id="mainContent">
    <section class="report-heading">
        <div class="report-meta">Billing portal · System payment</div>
        <h1>System Billing</h1>
        <p>Complete the payment details below to settle your system billing securely.</p>
        <div class="report-actions">
            <a href="index.php">Back to Dashboard</a>
        </div>
    </section>

    <section class="metric-grid" aria-label="Billing summary">
        <div class="metric"><span class="metric-label">Billing status</span><strong class="metric-value">Pending</strong></div>
        <div class="metric"><span class="metric-label">Admin contact</span><strong class="metric-value"><?php echo htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <div class="metric"><span class="metric-label">Payment method</span><strong class="metric-value">Flexible</strong></div>
        <div class="metric"><span class="metric-label">Cycle</span><strong class="metric-value">Monthly / Quarterly</strong></div>
    </section>

    <section class="payment-panel">
        <div class="panel-heading d-flex justify-content-between align-items-center gap-3">
            <h2>Billing form</h2>
            <span>Verify with admin code before payment</span>
        </div>

        <div class="payment-body">
            <?php if ($billingMessage !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($billingMessageType, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo $billingMessage; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="account_settings.php">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="billing_contact">Billing Contact</label>
                        <input type="text" id="billing_contact" name="billing_contact" value="<?php echo htmlspecialchars($_POST['billing_contact'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter billing contact name" required>
                    </div>

                    <div class="form-group">
                        <label for="billing_email">Billing Email</label>
                        <input type="email" id="billing_email" name="billing_email" value="<?php echo htmlspecialchars($_POST['billing_email'] ?? $adminEmail, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">Select payment method</option>
                            <option value="Bank Transfer" <?php echo (($_POST['payment_method'] ?? '') === 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="Credit Card" <?php echo (($_POST['payment_method'] ?? '') === 'Credit Card') ? 'selected' : ''; ?>>Credit Card</option>
                            <option value="Mobile Money" <?php echo (($_POST['payment_method'] ?? '') === 'Mobile Money') ? 'selected' : ''; ?>>Mobile Money</option>
                            <option value="Cash" <?php echo (($_POST['payment_method'] ?? '') === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="billing_cycle">Billing Cycle</label>
                        <select id="billing_cycle" name="billing_cycle" required>
                            <option value="">Select billing cycle</option>
                            <option value="Monthly" <?php echo (($_POST['billing_cycle'] ?? '') === 'Monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="Quarterly" <?php echo (($_POST['billing_cycle'] ?? '') === 'Quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="Annually" <?php echo (($_POST['billing_cycle'] ?? '') === 'Annually') ? 'selected' : ''; ?>>Annually</option>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label for="auth_code">Admin Authenticator Code</label>
                        <input type="text" id="auth_code" name="auth_code" value="<?php echo htmlspecialchars($_POST['auth_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter the code sent by the admin" required>
                        <div class="helper-row">
                            <span>Code is generated by the admin email authenticator.</span>
                            <button type="submit" name="action" value="request_code" class="secondary-button">Request code from admin</button>
                        </div>
                    </div>
                </div>

                <div class="button-row">
                    <button type="submit" name="action" value="submit_payment" class="primary-button">Submit payment request</button>
                </div>
            </form>
        </div>
    </section>

    <div class="report-footer">Source: system billing template · Payments are verified using the admin authenticator code.</div>
</main>
</body>
</html>
