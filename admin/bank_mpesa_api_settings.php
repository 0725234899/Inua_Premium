<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$settings = getApiIntegrationSettings('mpesa');
$config = getMpesaApiConfig();
$errors = [];
$messages = [];
$environment = $config['environment'];
$shortcode = $config['shortcode'];
$passkey = $config['passkey'];
$callbackUrl = $config['callback_url'];
$accountReference = $config['account_reference'];
$transactionDescription = $config['transaction_description'];
$c2bShortcode = $config['c2b_shortcode'];
$c2bValidationUrl = $config['c2b_validation_url'];
$c2bConfirmationUrl = $config['c2b_confirmation_url'];
$c2bResponseType = $config['c2b_response_type'];
$b2cShortcode = $config['b2c_shortcode'];
$b2cInitiatorName = $config['b2c_initiator_name'];
$b2cResultUrl = $config['b2c_result_url'];
$b2cQueueTimeoutUrl = $config['b2c_queue_timeout_url'];
$b2cCommandId = $config['b2c_command_id'];

$invalidPostValue = false;
$readPostValue = static function (string $key) use (&$invalidPostValue): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        $invalidPostValue = true;
        return '';
    }

    return trim($value);
};

$areValidUrls = static function (array $urls): bool {
    foreach ($urls as $url) {
        if ($url !== '') {
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (
                filter_var($url, FILTER_VALIDATE_URL) === false ||
                !in_array($scheme, ['http', 'https'], true)
            ) {
                return false;
            }
        }
    }

    return true;
};

$saveSettings = static function (array $changes, array $secrets, string $successMessage) use (&$settings, &$config, &$messages, &$errors): void {
    $updatedSettings = array_merge($settings, $changes);
    foreach ($secrets as $key => $value) {
        if ($value !== '') {
            $updatedSettings[$key] = $value;
        }
    }

    if (saveApiIntegrationSettings('mpesa', $updatedSettings, $_SESSION['email'])) {
        $settings = $updatedSettings;
        $config = getMpesaApiConfig();
        $messages[] = $successMessage;
    } else {
        $errors[] = 'Unable to save M-Pesa API settings.';
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $readPostValue('action');
    $csrfToken = $readPostValue('csrf_token');

    if ($invalidPostValue) {
        $errors[] = 'Invalid settings input. Refresh the page and try again.';
    } elseif (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $errors[] = 'Your session token is invalid. Refresh the page and try again.';
    } else {
        switch ($action) {
            case 'save_shared':
                $environment = strtolower($readPostValue('environment'));
                $consumerKey = $readPostValue('consumer_key');
                $consumerSecret = $readPostValue('consumer_secret');

                if (
                    $invalidPostValue ||
                    !in_array($environment, ['sandbox', 'production'], true) ||
                    strlen($consumerKey) > 255 ||
                    strlen($consumerSecret) > 255
                ) {
                    $errors[] = 'Check the M-Pesa environment and API credential values.';
                } else {
                    $saveSettings(
                        ['environment' => $environment],
                        [
                            'consumer_key' => $consumerKey,
                            'consumer_secret' => $consumerSecret
                        ],
                        'Shared Daraja API settings saved successfully.'
                    );
                }
                break;

            case 'save_express':
                $shortcode = $readPostValue('shortcode');
                $passkey = $readPostValue('passkey');
                $callbackUrl = $readPostValue('callback_url');
                $accountReference = $readPostValue('account_reference');
                $transactionDescription = $readPostValue('transaction_description');

                if (
                    $invalidPostValue ||
                    strlen($shortcode) > 32 ||
                    strlen($passkey) > 512 ||
                    strlen($callbackUrl) > 2048 ||
                    strlen($accountReference) > 255 ||
                    strlen($transactionDescription) > 255 ||
                    !$areValidUrls([$callbackUrl])
                ) {
                    $errors[] = 'Check the M-Pesa Express shortcode, callback URL, and transaction values.';
                } else {
                    $saveSettings(
                        [
                            'shortcode' => $shortcode,
                            'callback_url' => $callbackUrl,
                            'account_reference' => $accountReference,
                            'transaction_description' => $transactionDescription
                        ],
                        ['passkey' => $passkey],
                        'M-Pesa Express settings saved successfully.'
                    );
                }
                break;

            case 'save_c2b':
                $c2bShortcode = $readPostValue('c2b_shortcode');
                $c2bValidationUrl = $readPostValue('c2b_validation_url');
                $c2bConfirmationUrl = $readPostValue('c2b_confirmation_url');
                $c2bResponseType = $readPostValue('c2b_response_type');

                if (
                    $invalidPostValue ||
                    strlen($c2bShortcode) > 32 ||
                    strlen($c2bValidationUrl) > 2048 ||
                    strlen($c2bConfirmationUrl) > 2048 ||
                    !$areValidUrls([$c2bValidationUrl, $c2bConfirmationUrl]) ||
                    !in_array($c2bResponseType, ['Completed', 'Cancelled'], true)
                ) {
                    $errors[] = 'Check the C2B shortcode, callback URLs, and response type.';
                } else {
                    $saveSettings(
                        [
                            'c2b_shortcode' => $c2bShortcode,
                            'c2b_validation_url' => $c2bValidationUrl,
                            'c2b_confirmation_url' => $c2bConfirmationUrl,
                            'c2b_response_type' => $c2bResponseType
                        ],
                        [],
                        'C2B settings saved successfully.'
                    );
                }
                break;

            case 'save_b2c':
                $b2cShortcode = $readPostValue('b2c_shortcode');
                $b2cInitiatorName = $readPostValue('b2c_initiator_name');
                $b2cSecurityCredential = $readPostValue('b2c_security_credential');
                $b2cResultUrl = $readPostValue('b2c_result_url');
                $b2cQueueTimeoutUrl = $readPostValue('b2c_queue_timeout_url');
                $b2cCommandId = $readPostValue('b2c_command_id');

                if (
                    $invalidPostValue ||
                    strlen($b2cShortcode) > 32 ||
                    strlen($b2cInitiatorName) > 255 ||
                    strlen($b2cSecurityCredential) > 8192 ||
                    strlen($b2cResultUrl) > 2048 ||
                    strlen($b2cQueueTimeoutUrl) > 2048 ||
                    !$areValidUrls([$b2cResultUrl, $b2cQueueTimeoutUrl]) ||
                    ($b2cSecurityCredential !== '' && base64_decode($b2cSecurityCredential, true) === false) ||
                    !in_array($b2cCommandId, ['SalaryPayment', 'BusinessPayment', 'PromotionPayment'], true)
                ) {
                    $errors[] = 'Check the B2C shortcode, initiator, callback URLs, and command ID.';
                } else {
                    $saveSettings(
                        [
                            'b2c_shortcode' => $b2cShortcode,
                            'b2c_initiator_name' => $b2cInitiatorName,
                            'b2c_result_url' => $b2cResultUrl,
                            'b2c_queue_timeout_url' => $b2cQueueTimeoutUrl,
                            'b2c_command_id' => $b2cCommandId
                        ],
                        ['b2c_security_credential' => $b2cSecurityCredential],
                        'B2C settings saved successfully.'
                    );
                }
                break;

            default:
                $errors[] = 'Select a valid M-Pesa settings section.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa API Settings - Admin</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f7f9fc; font-family: Arial, sans-serif; }
        .page-wrap { max-width: 1000px; margin: 30px auto; padding: 20px; }
        .card { border: 0; border-radius: 12px; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08); }
        .card-header { background: #0d6efd; color: #fff; }
        .form-text { color: #6c757d; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="page-wrap">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">M-Pesa API Settings</h3>
            <a href="admin.php" class="btn btn-outline-primary btn-sm">Back to Dashboard</a>
        </div>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>

        <div class="card mb-4">
            <div class="card-header"><h4 class="mb-0">Shared Daraja API Credentials</h4></div>
            <div class="card-body">
                <p class="text-muted">These credentials and the selected environment are shared by M-Pesa Express, C2B, and B2C. Secret fields are write-only; leave them blank to keep the existing value.</p>
                <form method="post" action="bank_mpesa_api_settings.php" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="col-md-6">
                        <label for="environment" class="form-label">Environment</label>
                        <select id="environment" name="environment" class="form-select" required>
                            <option value="sandbox" <?php echo $environment === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option>
                            <option value="production" <?php echo $environment === 'production' ? 'selected' : ''; ?>>Production</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="consumer_key" class="form-label">Consumer Key</label>
                        <input type="password" id="consumer_key" name="consumer_key" class="form-control" autocomplete="new-password" placeholder="<?php echo $config['consumer_key'] !== '' ? 'Configured; leave blank to keep it' : 'Enter consumer key'; ?>">
                        <?php if ($config['consumer_key'] !== ''): ?><div class="form-text text-success">Consumer key is configured.</div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="consumer_secret" class="form-label">Consumer Secret</label>
                        <input type="password" id="consumer_secret" name="consumer_secret" class="form-control" autocomplete="new-password" placeholder="<?php echo $config['consumer_secret'] !== '' ? 'Configured; leave blank to keep it' : 'Enter consumer secret'; ?>">
                        <?php if ($config['consumer_secret'] !== ''): ?><div class="form-text text-success">Consumer secret is configured.</div><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="action" value="save_shared" class="btn btn-primary">Save Shared API Settings</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h4 class="mb-0">M-Pesa Express (STK Push)</h4></div>
            <div class="card-body">
                <p class="text-muted">Configure the Paybill shortcode, STK passkey, and callback used by Express requests. Blank secret fields keep the current value; blank callback values leave existing application defaults active.</p>
                <form method="post" action="bank_mpesa_api_settings.php" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="col-md-6">
                        <label for="shortcode" class="form-label">Business Shortcode</label>
                        <input type="text" id="shortcode" name="shortcode" class="form-control" maxlength="32" value="<?php echo htmlspecialchars($shortcode, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="passkey" class="form-label">STK Push Passkey</label>
                        <input type="password" id="passkey" name="passkey" class="form-control" autocomplete="new-password" placeholder="<?php echo $config['passkey'] !== '' ? 'Configured; leave blank to keep it' : 'Enter STK passkey'; ?>">
                        <?php if ($config['passkey'] !== ''): ?><div class="form-text text-success">STK passkey is configured.</div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="callback_url" class="form-label">STK Callback URL</label>
                        <input type="url" id="callback_url" name="callback_url" class="form-control" maxlength="2048" value="<?php echo htmlspecialchars($callbackUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="account_reference" class="form-label">Default Account Reference</label>
                        <input type="text" id="account_reference" name="account_reference" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($accountReference, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="transaction_description" class="form-label">Transaction Description</label>
                        <input type="text" id="transaction_description" name="transaction_description" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($transactionDescription, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="action" value="save_express" class="btn btn-primary">Save M-Pesa Express Settings</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h4 class="mb-0">C2B (Customer to Business)</h4></div>
            <div class="card-body">
                <p class="text-muted">Set the shortcode and public validation and confirmation endpoints used when registering C2B URLs. Leave callback fields blank to retain the existing application defaults. Daraja transaction simulation is available in Sandbox only.</p>
                <form method="post" action="bank_mpesa_api_settings.php" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="col-md-6">
                        <label for="c2b_shortcode" class="form-label">C2B Shortcode</label>
                        <input type="text" id="c2b_shortcode" name="c2b_shortcode" class="form-control" maxlength="32" value="<?php echo htmlspecialchars($c2bShortcode, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="c2b_response_type" class="form-label">Validation Response Type</label>
                        <select id="c2b_response_type" name="c2b_response_type" class="form-select" required>
                            <option value="Completed" <?php echo $c2bResponseType === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $c2bResponseType === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="c2b_validation_url" class="form-label">Validation URL</label>
                        <input type="url" id="c2b_validation_url" name="c2b_validation_url" class="form-control" maxlength="2048" value="<?php echo htmlspecialchars($c2bValidationUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="c2b_confirmation_url" class="form-label">Confirmation URL</label>
                        <input type="url" id="c2b_confirmation_url" name="c2b_confirmation_url" class="form-control" maxlength="2048" value="<?php echo htmlspecialchars($c2bConfirmationUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="action" value="save_c2b" class="btn btn-primary">Save C2B Settings</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h4 class="mb-0">B2C (Business to Customer)</h4></div>
            <div class="card-body">
                <p class="text-muted">Configure disbursement credentials and public callback URLs. Enter the base64-encoded RSA Security Credential generated with the Safaricom certificate for the selected environment, not the plain initiator password. Blank secret fields keep the existing value.</p>
                <form method="post" action="bank_mpesa_api_settings.php" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="col-md-6">
                        <label for="b2c_shortcode" class="form-label">B2C Shortcode</label>
                        <input type="text" id="b2c_shortcode" name="b2c_shortcode" class="form-control" maxlength="32" value="<?php echo htmlspecialchars($b2cShortcode, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="b2c_initiator_name" class="form-label">Initiator Name</label>
                        <input type="text" id="b2c_initiator_name" name="b2c_initiator_name" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($b2cInitiatorName, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="b2c_security_credential" class="form-label">Security Credential</label>
                        <input type="password" id="b2c_security_credential" name="b2c_security_credential" class="form-control" autocomplete="new-password" placeholder="<?php echo $config['b2c_security_credential'] !== '' ? 'Configured; leave blank to keep it' : 'Enter encrypted B2C credential'; ?>">
                        <?php if ($config['b2c_security_credential'] !== ''): ?><div class="form-text text-success">B2C security credential is configured.</div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="b2c_command_id" class="form-label">B2C Command ID</label>
                        <select id="b2c_command_id" name="b2c_command_id" class="form-select" required>
                            <option value="SalaryPayment" <?php echo $b2cCommandId === 'SalaryPayment' ? 'selected' : ''; ?>>Salary Payment</option>
                            <option value="BusinessPayment" <?php echo $b2cCommandId === 'BusinessPayment' ? 'selected' : ''; ?>>Business Payment</option>
                            <option value="PromotionPayment" <?php echo $b2cCommandId === 'PromotionPayment' ? 'selected' : ''; ?>>Promotion Payment</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="b2c_result_url" class="form-label">Result URL</label>
                        <input type="url" id="b2c_result_url" name="b2c_result_url" class="form-control" maxlength="2048" value="<?php echo htmlspecialchars($b2cResultUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="b2c_queue_timeout_url" class="form-label">Queue Timeout URL</label>
                        <input type="url" id="b2c_queue_timeout_url" name="b2c_queue_timeout_url" class="form-control" maxlength="2048" value="<?php echo htmlspecialchars($b2cQueueTimeoutUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="action" value="save_b2c" class="btn btn-primary">Save B2C Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
