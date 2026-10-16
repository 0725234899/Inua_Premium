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

$settings = getApiIntegrationSettings('sms');
$message = '';
$messageType = '';
$providerSettings = $settings['providers'] ?? [];
if (!is_array($providerSettings)) {
    $providerSettings = [];
}

$africasTalkingSettings = array_merge(
    [
        'username' => (string) ($settings['username'] ?? 'inua'),
        'api_key' => (string) ($settings['api_key'] ?? ''),
        'sender_id' => (string) ($settings['sender_id'] ?? '')
    ],
    is_array($providerSettings['africastalking'] ?? null) ? $providerSettings['africastalking'] : []
);
$celcomSettings = is_array($providerSettings['celcom'] ?? null) ? $providerSettings['celcom'] : [];
$provider = strtolower(trim((string) ($settings['provider'] ?? 'africastalking')));
if (!in_array($provider, ['africastalking', 'celcom'], true)) {
    $provider = 'africastalking';
    $message = 'The saved SMS provider is invalid. Select a provider and save the settings again.';
    $messageType = 'danger';
}

$username = (string) ($africasTalkingSettings['username'] ?? 'inua');
$senderId = (string) ($africasTalkingSettings['sender_id'] ?? '');
$savedAfricaApiKey = trim((string) ($africasTalkingSettings['api_key'] ?? ''));
$celcomPartnerId = (string) ($celcomSettings['partner_id'] ?? '');
$celcomShortcode = (string) ($celcomSettings['shortcode'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (($_POST['action'] ?? '') === 'test_sms') {
        $testRecipient = trim((string) ($_POST['test_recipient'] ?? ''));
        if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            $message = 'Your session token is invalid. Refresh the page and try again.';
            $messageType = 'danger';
        } elseif ($testRecipient === '' || strlen($testRecipient) > 50) {
            $message = 'Enter a valid test recipient phone number, preferably in international format (for example, +2547XXXXXXXX).';
            $messageType = 'danger';
        } else {
            try {
                sendMessage($testRecipient, 'Test SMS from Inua Premium using the saved SMS settings.');
                $message = 'Test SMS accepted by the configured provider for ' . $testRecipient . '.';
                $messageType = 'success';
            } catch (Throwable $exception) {
                error_log('SMS settings test failed (' . get_class($exception) . ', code ' . $exception->getCode() . '): ' . $exception->getMessage());
                $message = 'Test SMS failed: ' . $exception->getMessage();
                $messageType = 'danger';
            }
        }
    } else {
    $submittedProvider = strtolower(trim((string) ($_POST['provider'] ?? '')));
    $submittedFields = $_POST[$submittedProvider] ?? [];
    if (!is_array($submittedFields)) {
        $submittedFields = [];
    }
    $submittedApiKey = trim((string) ($submittedFields['api_key'] ?? ''));

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $message = 'Your session token is invalid. Refresh the page and try again.';
        $messageType = 'danger';
    } elseif (!in_array($submittedProvider, ['africastalking', 'celcom'], true)) {
        $message = 'Select a supported SMS provider.';
        $messageType = 'danger';
    } else {
        $provider = $submittedProvider;
        $validationError = '';

        if ($provider === 'africastalking') {
            $username = trim((string) ($submittedFields['username'] ?? ''));
            $senderId = trim((string) ($submittedFields['sender_id'] ?? ''));
            if (
                $username === ''
                || strlen($username) > 255
                || strlen($senderId) > 100
                || strlen($submittedApiKey) > 4096
                || ($submittedApiKey === '' && $savedAfricaApiKey === '')
            ) {
                $validationError = 'Enter an Africa\'s Talking username and API key. If a key is already saved, leave the API key blank to keep it.';
            }
        } else {
            $celcomPartnerId = trim((string) ($submittedFields['partner_id'] ?? ''));
            $celcomShortcode = trim((string) ($submittedFields['shortcode'] ?? ''));
            $savedCelcomApiKey = trim((string) ($celcomSettings['api_key'] ?? ''));
            if (
                $celcomPartnerId === ''
                || strlen($celcomPartnerId) > 255
                || $celcomShortcode === ''
                || strlen($celcomShortcode) > 100
                || strlen($submittedApiKey) > 4096
                || ($submittedApiKey === '' && $savedCelcomApiKey === '')
            ) {
                $validationError = 'Enter a valid Celcom Africa Partner ID, API key, and shortcode/sender ID.';
            }
        }

        if ($validationError !== '') {
            $message = $validationError;
            $messageType = 'danger';
        } else {
            $updatedSettings = $settings;
            $updatedProviderSettings = $providerSettings;

            if ($provider === 'africastalking') {
                $savedApiKey = $savedAfricaApiKey;
                if ($submittedApiKey !== '') {
                    $savedApiKey = $submittedApiKey;
                }

                $updatedProviderSettings['africastalking'] = [
                    'username' => $username,
                    'sender_id' => $senderId
                ];
                if ($savedApiKey !== '') {
                    $updatedProviderSettings['africastalking']['api_key'] = $savedApiKey;
                    $updatedSettings['api_key'] = $savedApiKey;
                }
                $updatedSettings['username'] = $username;
                $updatedSettings['sender_id'] = $senderId;
            } else {
                $savedApiKey = $submittedApiKey !== ''
                    ? $submittedApiKey
                    : trim((string) ($celcomSettings['api_key'] ?? ''));
                $updatedProviderSettings['celcom'] = [
                    'partner_id' => $celcomPartnerId,
                    'api_key' => $savedApiKey,
                    'shortcode' => $celcomShortcode
                ];
            }

            $updatedSettings['provider'] = $provider;
            $updatedSettings['providers'] = $updatedProviderSettings;

            if (saveApiIntegrationSettings('sms', $updatedSettings, $_SESSION['email'])) {
                $settings = $updatedSettings;
                $providerSettings = $updatedProviderSettings;
                if ($provider === 'africastalking') {
                    $africasTalkingSettings = $updatedProviderSettings['africastalking'];
                    $savedAfricaApiKey = trim((string) ($africasTalkingSettings['api_key'] ?? ''));
                } else {
                    $celcomSettings = $updatedProviderSettings['celcom'];
                }
                $message = 'SMS settings saved. Credentials have not been tested; delivery status will be reported when the next SMS is sent.';
                $messageType = 'success';
            } else {
                $message = 'Unable to save SMS settings.';
                $messageType = 'danger';
            }
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
    <title>SMS Settings - Admin</title>
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
        <h3 class="mb-0">SMS Settings</h3>
        <a href="admin.php" class="btn btn-outline-primary btn-sm">Back to Dashboard</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>" role="alert"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="mb-0">SMS Gateway Credentials</h4>
        </div>
        <div class="card-body">
            <p class="text-muted">Choose the gateway used for outgoing SMS. Settings are saved in the database and automatically loaded whenever the application sends an SMS. Each provider has its own credentials. API keys are write-only; leave a key blank to keep its saved value. For Africa&apos;s Talking, the username and API key must belong to the same account and environment. Use the username <code>sandbox</code> with a sandbox API key, or your account username with its live API key.</p>
            <form method="post" action="sms_settings.php" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="save_settings">
                <div class="col-md-6">
                    <label for="provider" class="form-label">Bulk SMS Provider</label>
                    <select id="provider" name="provider" class="form-select" required>
                        <option value="africastalking" <?php echo $provider === 'africastalking' ? 'selected' : ''; ?>>Africa's Talking</option>
                        <option value="celcom" <?php echo $provider === 'celcom' ? 'selected' : ''; ?>>Celcom Africa</option>
                    </select>
                </div>
                <div class="col-12" data-provider-fields="africastalking" <?php echo $provider === 'africastalking' ? '' : 'hidden'; ?>>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="africastalking_username" class="form-label">Africa's Talking Username</label>
                            <input type="text" id="africastalking_username" name="africastalking[username]" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" data-required="true" <?php echo $provider === 'africastalking' ? 'required' : 'disabled'; ?>>
                        </div>
                        <div class="col-md-6">
                            <label for="africastalking_api_key" class="form-label">Africa's Talking API Key</label>
                            <input type="password" id="africastalking_api_key" name="africastalking[api_key]" class="form-control" maxlength="4096" autocomplete="new-password" placeholder="Leave blank to keep the current key" data-required="<?php echo $savedAfricaApiKey === '' ? 'true' : 'false'; ?>" <?php echo $provider === 'africastalking' && $savedAfricaApiKey === '' ? 'required' : ''; ?> <?php echo $provider === 'africastalking' ? '' : 'disabled'; ?>>
                            <div class="form-text">Required until a key is saved. The API key is never displayed. If SMS returns HTTP 401, generate a valid key for the selected username/environment and replace the saved key here.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="africastalking_sender_id" class="form-label">Africa's Talking Sender ID (optional)</label>
                            <input type="text" id="africastalking_sender_id" name="africastalking[sender_id]" class="form-control" maxlength="100" value="<?php echo htmlspecialchars($senderId, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $provider === 'africastalking' ? '' : 'disabled'; ?>>
                            <div class="form-text">When set, this sender ID is included with outgoing SMS messages.</div>
                        </div>
                    </div>
                </div>
                <div class="col-12" data-provider-fields="celcom" <?php echo $provider === 'celcom' ? '' : 'hidden'; ?>>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="celcom_partner_id" class="form-label">Celcom Africa Partner ID</label>
                            <input type="text" id="celcom_partner_id" name="celcom[partner_id]" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($celcomPartnerId, ENT_QUOTES, 'UTF-8'); ?>" data-required="true" <?php echo $provider === 'celcom' ? 'required' : 'disabled'; ?>>
                        </div>
                        <div class="col-md-6">
                            <label for="celcom_api_key" class="form-label">Celcom Africa API Key</label>
                            <input type="password" id="celcom_api_key" name="celcom[api_key]" class="form-control" maxlength="4096" autocomplete="new-password" placeholder="Leave blank to keep the current key" data-required="<?php echo trim((string) ($celcomSettings['api_key'] ?? '')) === '' ? 'true' : 'false'; ?>" <?php echo $provider === 'celcom' && trim((string) ($celcomSettings['api_key'] ?? '')) === '' ? 'required' : ''; ?> <?php echo $provider === 'celcom' ? '' : 'disabled'; ?>>
                            <div class="form-text">The API key is never displayed. Find it in your Celcom Africa dashboard.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="celcom_shortcode" class="form-label">Celcom Africa Shortcode / Sender ID</label>
                            <input type="text" id="celcom_shortcode" name="celcom[shortcode]" class="form-control" maxlength="100" value="<?php echo htmlspecialchars($celcomShortcode, ENT_QUOTES, 'UTF-8'); ?>" data-required="true" <?php echo $provider === 'celcom' ? 'required' : 'disabled'; ?>>
                            <div class="form-text">Use the registered sender ID or shortcode associated with the account.</div>
                        </div>
                        <div class="col-12">
                            <div class="form-text">Celcom Africa requires a Partner ID, API key, and registered shortcode. Get the API key and Partner ID from the Celcom Africa dashboard.</div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Save SMS Settings</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Test SMS Delivery</h4>
        </div>
        <div class="card-body">
            <p class="text-muted">Send a test using the provider credentials currently saved in the database. This does not submit a loan application.</p>
            <form method="post" action="sms_settings.php" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="test_sms">
                <div class="col-md-6">
                    <label for="test_recipient" class="form-label">Test recipient phone number</label>
                    <input type="tel" id="test_recipient" name="test_recipient" class="form-control" maxlength="50" placeholder="+2547XXXXXXXX" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-outline-primary">Send Test SMS</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    const providerSelect = document.getElementById('provider');
    const providerSections = document.querySelectorAll('[data-provider-fields]');

    function updateProviderFields() {
        providerSections.forEach((section) => {
            const isActive = section.dataset.providerFields === providerSelect.value;
            section.hidden = !isActive;
            section.querySelectorAll('input').forEach((input) => {
                input.disabled = !isActive;
                input.required = isActive && input.dataset.required === 'true';
            });
        });
    }

    providerSelect.addEventListener('change', updateProviderFields);
    updateProviderFields();
</script>
</body>
</html>
