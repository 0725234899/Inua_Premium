<?php
use AfricasTalking\SDK\AfricasTalking;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/vendor/autoload.php';

function calculateManagerBillingExpiry($startDate, $billingCycle) {
    $monthsByCycle = ['Monthly' => 1, 'Quarterly' => 3, 'Annually' => 12];
    if (!isset($monthsByCycle[$billingCycle])) {
        return null;
    }

    $start = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $startDate);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$start || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $start->format('Y-m-d') !== $startDate) {
        return null;
    }

    if ($start > new DateTimeImmutable('today')) {
        return null;
    }

    $targetMonth = $start->modify('first day of this month')->modify('+' . $monthsByCycle[$billingCycle] . ' months');
    $targetDay = min((int) $start->format('d'), (int) $targetMonth->format('t'));
    $maturityDate = $targetMonth->setDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('m'), $targetDay);

    return $maturityDate->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
}

function enforceManagerBillingAccess(PDO $connection) {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($scriptName, '/manager/') === false) {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['email']) || in_array(basename($scriptName), ['index.php', 'callback.php'], true)) {
        return;
    }

    $billingRecord = false;
    try {
        $stmt = $connection->prepare('SELECT start_date, billing_cycle, expires_at FROM manager_billing_access WHERE user_email = ? LIMIT 1');
        $stmt->execute([strtolower(trim((string) $_SESSION['email']))]);
        $billingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $billingRecord = false;
    }

    $expiresAt = $billingRecord
        ? calculateManagerBillingExpiry($billingRecord['start_date'], $billingRecord['billing_cycle'])
        : null;
    if ($billingRecord && $expiresAt !== null && $expiresAt !== $billingRecord['expires_at']) {
        $updateExpiry = $connection->prepare('UPDATE manager_billing_access SET expires_at = ? WHERE user_email = ?');
        $updateExpiry->execute([$expiresAt, strtolower(trim((string) $_SESSION['email']))]);
    }

    if ($expiresAt === null || strtotime($expiresAt) <= time()) {
        if (preg_match('#^(.*?/manager)(?:/|$)#', $scriptName, $matches)) {
            $billingGateUrl = $matches[1] . '/index.php';
        } else {
            $billingGateUrl = 'index.php';
        }
        header('Location: ' . $billingGateUrl);
        exit();
    }
}

if (!function_exists('db_connect')) {
    // Database connection
    function db_connect() {
    $host = 'localhost';
    $db = 'microfinance';
    $user = 'root';
    $pass = '';
    try {
        $connection = new PDO("mysql:host=$host;dbname=$db", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        enforceManagerBillingAccess($connection);
        return $connection;
    } catch (PDOException $e) {
        die('Connection failed: ' . $e->getMessage());
    }
}
}
function getBranches() {
    $conn = db_connect();
    $stmt = $conn->query("SELECT * FROM branches");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ensureCompanyTableExists() {
    $conn = db_connect();
    $conn->exec("CREATE TABLE IF NOT EXISTS companies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        registration_number VARCHAR(255) DEFAULT NULL,
        country VARCHAR(255) DEFAULT NULL,
        timezone VARCHAR(255) DEFAULT NULL,
        currency VARCHAR(50) DEFAULT NULL,
        address VARCHAR(255) DEFAULT NULL,
        city VARCHAR(255) DEFAULT NULL,
        province VARCHAR(255) DEFAULT NULL,
        zipcode VARCHAR(50) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        logo VARCHAR(255) DEFAULT NULL,
        status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = ['registration_number', 'country', 'timezone', 'currency', 'address', 'city', 'province', 'zipcode', 'phone', 'email', 'logo'];
    foreach ($columns as $column) {
        $check = $conn->prepare("SHOW COLUMNS FROM companies LIKE ?");
        $check->execute([$column]);
        if (!$check->fetch()) {
            $conn->exec("ALTER TABLE companies ADD COLUMN `{$column}` VARCHAR(255) DEFAULT NULL");
        }
    }

    $check = $conn->prepare("SHOW COLUMNS FROM companies LIKE 'name'");
    $check->execute();
    if (!$check->fetch()) {
        $conn->exec("ALTER TABLE companies ADD COLUMN `name` VARCHAR(255) NOT NULL DEFAULT ''");
    }
}

function getCompanies() {
    ensureCompanyTableExists();
    $conn = db_connect();
    $stmt = $conn->query("SELECT * FROM companies ORDER BY id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCompanyById($id) {
    ensureCompanyTableExists();
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function saveCompany($company) {
    ensureCompanyTableExists();
    $conn = db_connect();

    $companyName = trim((string) ($company['name'] ?? $company['company_name'] ?? ''));
    $registrationNumber = trim((string) ($company['registration_number'] ?? ''));
    $country = trim((string) ($company['country'] ?? ''));
    $timezone = trim((string) ($company['timezone'] ?? ''));
    $currency = trim((string) ($company['currency'] ?? ''));
    $address = trim((string) ($company['address'] ?? ''));
    $city = trim((string) ($company['city'] ?? ''));
    $province = trim((string) ($company['province'] ?? ''));
    $zipcode = trim((string) ($company['zipcode'] ?? ''));
    $phone = trim((string) ($company['phone'] ?? ''));
    $email = trim((string) ($company['email'] ?? ''));
    $logo = trim((string) ($company['logo'] ?? ''));
    $status = in_array(($company['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $company['status'] : 'Active';
    $companyId = isset($company['id']) && $company['id'] !== '' ? (int) $company['id'] : null;

    if ($companyName === '') {
        return false;
    }

    if ($companyId) {
        $stmt = $conn->prepare("
            UPDATE companies SET
                name = ?, registration_number = ?, country = ?, timezone = ?, currency = ?, address = ?, city = ?, province = ?, zipcode = ?, phone = ?, email = ?, logo = ?, status = ?
            WHERE id = ?
        ");
        return $stmt->execute([$companyName, $registrationNumber, $country, $timezone, $currency, $address, $city, $province, $zipcode, $phone, $email, $logo, $status, $companyId]);
    }

    $stmt = $conn->prepare("
        INSERT INTO companies (name, registration_number, country, timezone, currency, address, city, province, zipcode, phone, email, logo, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $result = $stmt->execute([$companyName, $registrationNumber, $country, $timezone, $currency, $address, $city, $province, $zipcode, $phone, $email, $logo, $status]);
    return $result ? $conn->lastInsertId() : false;
}

// User management functions
function ensureUserSalaryColumnExists() {
    $conn = db_connect();
    $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'basic_salary'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $conn->exec("ALTER TABLE users ADD COLUMN basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00");
    }
}

function ensureUserPasswordExpiryColumnsExist() {
    $conn = db_connect();
    foreach (['password_changed_at' => 'DATETIME NULL', 'password_expires_at' => 'DATETIME NULL'] as $column => $definition) {
        $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE ?");
        $stmt->execute([$column]);
        if (!$stmt->fetch()) {
            $conn->exec("ALTER TABLE users ADD COLUMN `{$column}` {$definition}");
        }
    }
}

function add_user($name, $email, $password, $role, $area = null, $phone = null, $basic_salary = 0.00) {
    ensureUserSalaryColumnExists();
    ensureUserPasswordExpiryColumnsExist();
    $conn = db_connect();
    $email = strtolower(trim((string) $email));

    $checkStmt = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        error_log('Duplicate staff email attempted: ' . $email);
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role_id, area, phone, basic_salary, password_changed_at, password_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY))");
    try {
        return $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $area, $phone, $basic_salary]);
    } catch (PDOException $e) {
        error_log('Failed to add staff user: ' . $e->getMessage());
        return false;
    }
}

function login($email, $password, $role) {
    ensureUserPasswordExpiryColumnsExist();
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND role_id = :role");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':role', $role);
    $stmt->execute();

    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        if (in_array((string) $role, ['2', '4'], true)) {
            if (empty($user['password_expires_at'])) {
                $expiryStmt = $pdo->prepare("UPDATE users SET password_changed_at = COALESCE(password_changed_at, NOW()), password_expires_at = DATE_ADD(COALESCE(password_changed_at, NOW()), INTERVAL 90 DAY) WHERE id = ?");
                $expiryStmt->execute([$user['id']]);
                $user['password_expires_at'] = date('Y-m-d H:i:s', strtotime('+90 days'));
            }

            if (strtotime($user['password_expires_at']) <= time()) {
                return '2,expired';
            }
        }

        $_SESSION['user'] = $email;
        $_SESSION['username'] = $user['name'];
        return '1,' . $role; // Success
    } else {
        return '0'; // Failure
    }
}

function update_user($id, $name, $email, $password) {
    ensureUserPasswordExpiryColumnsExist();
    $conn = db_connect();
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, password_changed_at = NOW(), password_expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY) WHERE id = ?");
    return $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $id]);
}

function delete_user($id) {
    $conn = db_connect();
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    return $stmt->execute([$id]);
}

// Loan management functions
function apply_loan($client_id, $amount, $term, $interest_rate) {
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO loans (client_id, amount, term, interest_rate, status) VALUES (?, ?, ?, ?, 'Pending')");
    return $stmt->execute([$client_id, $amount, $term, $interest_rate]);
}

function update_loan_status($loan_id, $status) {
    $conn = db_connect();
    $stmt = $conn->prepare("UPDATE loans SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $loan_id]);
}

function track_repayment($loan_id, $amount) {
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO repayments (loan_id, amount) VALUES (?, ?)");
    return $stmt->execute([$loan_id, $amount]);
}

function calculate_portfolio_at_risk($days_overdue = 30, $loanOfficer = null) {
    $conn = db_connect();
    $params = [$days_overdue];
    $loanOfficerCondition = "";

    if ($loanOfficer !== null) {
        $loanOfficerCondition = " AND loan_officer = ?";
        $params[] = $loanOfficer;
    }

    $stmt = $conn->prepare("
        SELECT SUM(amount) AS at_risk_amount
        FROM loans
        WHERE status = 'Disbursed' AND due_date < DATE_SUB(NOW(), INTERVAL ? DAY) $loanOfficerCondition
    ");
    $stmt->execute($params);
    $at_risk_amount = $stmt->fetchColumn();

    $stmt = $conn->prepare("
        SELECT SUM(amount) AS total_amount
        FROM loans
        WHERE status = 'Disbursed' $loanOfficerCondition
    ");
    $stmt->execute($loanOfficer !== null ? [$loanOfficer] : []);
    $total_amount = $stmt->fetchColumn();

    return $total_amount > 0 ? ($at_risk_amount / $total_amount) * 100 : 0;
}

function logout() {
    session_destroy();
}

// Loan Products management functions
function getLoanProducts() {
    $conn = db_connect();
    $stmt = $conn->query("SELECT * FROM loan_products");
    // Fetch all rows as an associative array
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addLoanProduct($name, $branch_access, $penalty_settings, $status) {
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO loan_products (name, branch_access, penalty_settings, status) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$name, $branch_access, $penalty_settings, $status]);
}

// Placeholder functions for demo purposes
function getTotalAreas() {
    // Fetch total areas from the database
    return 50; // Example value
}

function getTotalDisbursedLoans() {
    // Fetch total disbursed loans from the database
    return 1000000; // Example value
}

function getPortfolioAtRisk() {
    // Fetch portfolio at risk from the database
    return 20000; // Example value
}

function getDisbursedLoans() {
    $pdo = db_connect();
    $query = "SELECT loan_id, borrower_name, amount, disbursement_date, status FROM disbursed_loans";
    
    try {
        $stmt = $pdo->query($query);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Database query failed: " . $e->getMessage());
    }
}

function getOutstandingBalance() {
    $pdo = db_connect();
    $query = "SELECT loan_id, borrower_name, amount, due_date, status FROM outstanding_balance";
    
    try {
        $stmt = $pdo->query($query);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Database query failed: " . $e->getMessage());
    }
}

// Role and Navigation Management
function getUserRole($userId) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function getRoles() {
    $conn = db_connect();
    $stmt = $conn->query("SELECT * FROM roles");
    return $stmt->fetchAll();
}
function getAllRoles() {
    global $conn;
    $sql = "SELECT * FROM roles";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}
function getLoanOfficers() {
    include 'db.php';
    $sql = "SELECT * FROM users WHERE role_id = 2";
    $result = $conn->query($sql);
    $loanOfficers = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $loanOfficers[] = $row;
        }
    }
    return $loanOfficers;
}
function getStaff() {
    include 'db.php';
    $sql = "SELECT * FROM users";
    $result = $conn->query($sql);
    $loanOfficers = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $loanOfficers[] = $row;
        }
    }
    return $loanOfficers;
}
function getAllNavigationItems() {
    global $conn;
    $sql = "SELECT * FROM navigation_items";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getRole($id) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getAreas() {
    $conn = db_connect();
    $stmt = $conn->query("SELECT * FROM areas");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNavigationItems($roleId) {
    try {
        $conn = db_connect();
        $stmt = $conn->prepare("
            SELECT ni.id, ni.title, ni.url, ni.icon, ni.parent_id 
            FROM navigation_items ni
            INNER JOIN navigation_item_roles nir ON ni.id = nir.navigation_item_id
            WHERE nir.role_id = :role_id ORDER BY ni.id ASC
        ");

        // Execute the statement with the provided role ID
        $stmt->execute([':role_id' => $roleId]);

        // Fetch all results as an associative array
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return the result, ensuring it’s always an array
        return $result ?: [];

    } catch (PDOException $e) {
        // Log or handle the exception as needed
        error_log('Database query error: ' . $e->getMessage());
        return [];
    } finally {
        // Close the connection if it’s still open (if your db_connect function doesn't handle this automatically)
        $conn = null;
    }
}


function updateSettings($settings) {
    $conn = db_connect();
    $stmt = $conn->prepare("
        UPDATE settings SET
            company_name = ?, country = ?, timezone = ?, currency = ?, currency_in_words = ?, 
            date_format = ?, decimal_separator = ?, thousand_separator = ?, monthly_loan_repayment_cycle = ?, 
            yearly_loan_repayment_cycle = ?, days_in_a_month = ?, days_in_a_year = ?, 
            business_registration_number = ?, address = ?, city = ?, province = ?, zipcode = ?
    ");
    return $stmt->execute([
        $settings['company_name'], $settings['country'], $settings['timezone'], $settings['currency'], $settings['currency_in_words'],
        $settings['date_format'], $settings['decimal_separator'], $settings['thousand_separator'], $settings['monthly_loan_repayment_cycle'],
        $settings['yearly_loan_repayment_cycle'], $settings['days_in_a_month'], $settings['days_in_a_year'],
        $settings['business_registration_number'], $settings['address'], $settings['city'], $settings['province'], $settings['zipcode']
    ]);
}

function getSettings() {
    $conn = db_connect();
    $stmt = $conn->query("SELECT * FROM settings");
    return $stmt->fetch();
}

function ensureApiIntegrationSettingsTable(PDO $connection) {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $connection->exec("CREATE TABLE IF NOT EXISTS api_integration_settings (
        integration VARCHAR(32) PRIMARY KEY,
        settings_json LONGTEXT NOT NULL,
        updated_by VARCHAR(255) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $initialized = true;
}

function getApiIntegrationSettings($integration) {
    $integration = strtolower(trim((string) $integration));
    if (!in_array($integration, ['sms', 'mpesa'], true)) {
        throw new InvalidArgumentException('Unsupported API integration.');
    }

    $connection = db_connect();
    ensureApiIntegrationSettingsTable($connection);
    $stmt = $connection->prepare('SELECT settings_json FROM api_integration_settings WHERE integration = ? LIMIT 1');
    $stmt->execute([$integration]);
    $settingsJson = $stmt->fetchColumn();
    if ($settingsJson === false) {
        return [];
    }

    $settings = json_decode((string) $settingsJson, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($settings)) {
        throw new UnexpectedValueException('Stored API integration settings are invalid.');
    }

    return $settings;
}

function saveApiIntegrationSettings($integration, array $settings, $updatedBy = null) {
    $integration = strtolower(trim((string) $integration));
    if (!in_array($integration, ['sms', 'mpesa'], true)) {
        throw new InvalidArgumentException('Unsupported API integration.');
    }

    $connection = db_connect();
    ensureApiIntegrationSettingsTable($connection);
    $stmt = $connection->prepare("
        INSERT INTO api_integration_settings (integration, settings_json, updated_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE settings_json = VALUES(settings_json), updated_by = VALUES(updated_by)
    ");
    return $stmt->execute([
        $integration,
        json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $updatedBy
    ]);
}

function getMpesaApiConfig() {
    $settings = getApiIntegrationSettings('mpesa');
    $getValue = static function ($settingKey, $environmentKey, $default = '') use ($settings) {
        $value = trim((string) ($settings[$settingKey] ?? ''));
        if ($value !== '') {
            return $value;
        }

        $value = trim((string) ($_ENV[$environmentKey] ?? getenv($environmentKey) ?: ''));
        return $value !== '' ? $value : $default;
    };

    $environment = strtolower($getValue('environment', 'MPESA_ENV', 'sandbox'));
    if (in_array($environment, ['live', 'prod'], true)) {
        $environment = 'production';
    }
    if (!in_array($environment, ['sandbox', 'production'], true)) {
        throw new UnexpectedValueException('The configured M-Pesa environment is invalid.');
    }

    $shortcode = $getValue('shortcode', 'MPESA_SHORTCODE');
    $c2bResponseType = $getValue('c2b_response_type', 'MPESA_C2B_RESPONSE_TYPE', 'Completed');
    if (!in_array($c2bResponseType, ['Completed', 'Cancelled'], true)) {
        throw new UnexpectedValueException('The configured M-Pesa C2B response type is invalid.');
    }

    $b2cCommandId = $getValue('b2c_command_id', 'MPESA_B2C_COMMAND_ID', 'SalaryPayment');
    if (!in_array($b2cCommandId, ['SalaryPayment', 'BusinessPayment', 'PromotionPayment'], true)) {
        throw new UnexpectedValueException('The configured M-Pesa B2C command ID is invalid.');
    }

    return [
        'environment' => $environment,
        'base_url' => $environment === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke',
        'consumer_key' => $getValue('consumer_key', 'MPESA_CONSUMER_KEY'),
        'consumer_secret' => $getValue('consumer_secret', 'MPESA_CONSUMER_SECRET'),
        'shortcode' => $shortcode,
        'passkey' => $getValue('passkey', 'MPESA_PASSKEY'),
        'callback_url' => $getValue('callback_url', 'MPESA_CALLBACK_URL'),
        'account_reference' => $getValue('account_reference', 'MPESA_ACCOUNT_REFERENCE', 'Loan Repayment'),
        'transaction_description' => $getValue('transaction_description', 'MPESA_TRANSACTION_DESCRIPTION', 'Loan repayment for account'),
        'c2b_shortcode' => $getValue('c2b_shortcode', 'MPESA_C2B_SHORTCODE', $shortcode),
        'c2b_validation_url' => $getValue('c2b_validation_url', 'MPESA_C2B_VALIDATION_URL'),
        'c2b_confirmation_url' => $getValue('c2b_confirmation_url', 'MPESA_C2B_CONFIRMATION_URL'),
        'c2b_response_type' => $c2bResponseType,
        'b2c_shortcode' => $getValue('b2c_shortcode', 'MPESA_B2C_SHORTCODE', $shortcode),
        'b2c_initiator_name' => $getValue('b2c_initiator_name', 'MPESA_B2C_INITIATOR_NAME'),
        'b2c_security_credential' => $getValue('b2c_security_credential', 'MPESA_B2C_SECURITY_CREDENTIAL'),
        'b2c_result_url' => $getValue('b2c_result_url', 'MPESA_B2C_RESULT_URL'),
        'b2c_queue_timeout_url' => $getValue('b2c_queue_timeout_url', 'MPESA_B2C_QUEUE_TIMEOUT_URL'),
        'b2c_command_id' => $b2cCommandId
    ];
}

function normalizeEmailAppPassword($password) {
    return preg_replace('/\s+/', '', trim((string) $password));
}

function ensureEmailAccountTable() {
    $conn = db_connect();
    $conn->exec("CREATE TABLE IF NOT EXISTS email_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_email VARCHAR(255) DEFAULT NULL,
        sender_app_password VARCHAR(255) DEFAULT NULL,
        admin_email VARCHAR(255) DEFAULT NULL,
        admin_app_password VARCHAR(255) DEFAULT NULL,
        admin_totp_secret VARCHAR(128) DEFAULT NULL,
        app_password VARCHAR(255) DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $senderEmailColumn = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'sender_email'")->fetch(PDO::FETCH_ASSOC);
    if ($senderEmailColumn && $senderEmailColumn['Null'] === 'NO') {
        $conn->exec("ALTER TABLE email_accounts MODIFY COLUMN sender_email VARCHAR(255) DEFAULT NULL");
    }

    $columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'sender_app_password'");
    if ($columnCheck->rowCount() === 0) {
        $conn->exec("ALTER TABLE email_accounts ADD COLUMN sender_app_password VARCHAR(255) DEFAULT NULL AFTER sender_email");
        $conn->exec("UPDATE email_accounts SET sender_app_password = app_password WHERE sender_app_password IS NULL");
    }

    $senderPasswordColumn = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'sender_app_password'")->fetch(PDO::FETCH_ASSOC);
    if ($senderPasswordColumn && $senderPasswordColumn['Null'] === 'NO') {
        $conn->exec("ALTER TABLE email_accounts MODIFY COLUMN sender_app_password VARCHAR(255) DEFAULT NULL");
    }

    $columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'admin_email'");
    if ($columnCheck->rowCount() === 0) {
        $conn->exec("ALTER TABLE email_accounts ADD COLUMN admin_email VARCHAR(255) DEFAULT NULL AFTER sender_app_password");
    }

    $columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'admin_app_password'");
    if ($columnCheck->rowCount() === 0) {
        $conn->exec("ALTER TABLE email_accounts ADD COLUMN admin_app_password VARCHAR(255) DEFAULT NULL AFTER admin_email");
    }

    $columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'admin_totp_secret'");
    if ($columnCheck->rowCount() === 0) {
        $conn->exec("ALTER TABLE email_accounts ADD COLUMN admin_totp_secret VARCHAR(128) DEFAULT NULL AFTER admin_app_password");
    }

    $columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'app_password'");
    if ($columnCheck->rowCount() === 0) {
        $conn->exec("ALTER TABLE email_accounts ADD COLUMN app_password VARCHAR(255) DEFAULT NULL AFTER admin_app_password");
        $conn->exec("UPDATE email_accounts SET app_password = sender_app_password WHERE app_password IS NULL");
    }

    $legacyPasswordColumn = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'app_password'")->fetch(PDO::FETCH_ASSOC);
    if ($legacyPasswordColumn && $legacyPasswordColumn['Null'] === 'NO') {
        $conn->exec("ALTER TABLE email_accounts MODIFY COLUMN app_password VARCHAR(255) DEFAULT NULL");
    }
}

function getEmailAccount() {
    $conn = db_connect();
    ensureEmailAccountTable();
    $stmt = $conn->query("SELECT * FROM email_accounts WHERE TRIM(COALESCE(sender_email, '')) <> '' AND TRIM(COALESCE(sender_app_password, app_password, '')) <> '' ORDER BY id ASC LIMIT 1");
    $account = $stmt->fetch();
    if ($account) {
        if (isset($account['sender_app_password']) && $account['sender_app_password'] !== null && $account['sender_app_password'] !== '') {
            $account['sender_app_password'] = normalizeEmailAppPassword($account['sender_app_password']);
            if (!isset($account['app_password']) || $account['app_password'] === null || $account['app_password'] === '') {
                $account['app_password'] = $account['sender_app_password'];
            }
        }
        if (isset($account['app_password']) && $account['app_password'] !== null && $account['app_password'] !== '') {
            $account['app_password'] = normalizeEmailAppPassword($account['app_password']);
            if (!isset($account['sender_app_password']) || $account['sender_app_password'] === null || $account['sender_app_password'] === '') {
                $account['sender_app_password'] = $account['app_password'];
            }
        }
        if (isset($account['admin_app_password']) && $account['admin_app_password'] !== null && $account['admin_app_password'] !== '') {
            $account['admin_app_password'] = normalizeEmailAppPassword($account['admin_app_password']);
        }
    }
    return $account;
}

function ensurePayrollEmailSettingsTable() {
    $conn = db_connect();
    $conn->exec("CREATE TABLE IF NOT EXISTS payroll_email_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_email VARCHAR(255) NOT NULL,
        sender_app_password VARCHAR(255) NOT NULL,
        admin_email VARCHAR(255) DEFAULT NULL,
        admin_app_password VARCHAR(255) DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getAdminAuthenticatorAccount() {
    ensureEmailAccountTable();
    $conn = db_connect();
        $stmt = $conn->query("SELECT ea.id, ea.admin_email, ea.admin_totp_secret, u.phone AS admin_phone
                FROM email_accounts ea
                LEFT JOIN roles r ON LOWER(TRIM(r.name)) = 'admin'
                LEFT JOIN users u ON u.role_id = r.id AND LOWER(TRIM(u.email)) = LOWER(TRIM(ea.admin_email))
                WHERE TRIM(COALESCE(ea.admin_email, '')) <> ''
                    AND TRIM(COALESCE(ea.admin_totp_secret, '')) <> ''
                ORDER BY ea.id ASC
                LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function getPayrollEmailAccount() {
    ensurePayrollEmailSettingsTable();
    $conn = db_connect();
    $stmt = $conn->query("SELECT * FROM payroll_email_settings ORDER BY id ASC LIMIT 1");
    $account = $stmt->fetch();
    if ($account) {
        $account['sender_app_password'] = normalizeEmailAppPassword($account['sender_app_password'] ?? '');
        $account['admin_app_password'] = normalizeEmailAppPassword($account['admin_app_password'] ?? '');
    }
    return $account;
}

function getPayrollSmtpSettings($email) {
    $domain = strtolower((string) substr(strrchr((string) $email, '@'), 1));
    $settings = [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
    ];

    if (in_array($domain, ['yahoo.com', 'yahoo.co.uk', 'yahoo.co.in'], true)) {
        $settings = ['host' => 'smtp.mail.yahoo.com', 'port' => 587, 'encryption' => 'tls'];
    } elseif (in_array($domain, ['outlook.com', 'hotmail.com', 'live.com'], true)) {
        $settings = ['host' => 'smtp-mail.outlook.com', 'port' => 587, 'encryption' => 'tls'];
    } elseif (in_array($domain, ['icloud.com', 'me.com', 'mac.com'], true)) {
        $settings = ['host' => 'smtp.mail.me.com', 'port' => 587, 'encryption' => 'tls'];
    }

    return $settings;
}

function ensureAdminPHPMailerLoaded() {
    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        require_once __DIR__ . '/../admin/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/../admin/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../admin/PHPMailer/src/SMTP.php';
    }
}

function getConfiguredSenderEmail() {
    $account = getEmailAccount();
    return !empty($account['sender_email']) ? trim($account['sender_email']) : '';
}

function saveEmailAccount($senderEmail, $senderAppPassword, $adminEmail = null, $adminAppPassword = null) {
    $conn = db_connect();
    ensureEmailAccountTable();
    $normalizedSenderPassword = normalizeEmailAppPassword($senderAppPassword);
    $normalizedAdminPassword = $adminAppPassword !== null ? normalizeEmailAppPassword($adminAppPassword) : null;
    $existing = getEmailAccount();
    if ($existing) {
        $stmt = $conn->prepare("UPDATE email_accounts SET sender_email = ?, sender_app_password = ?, admin_email = ?, admin_app_password = ?, app_password = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$senderEmail, $normalizedSenderPassword, $adminEmail, $normalizedAdminPassword, $normalizedSenderPassword, $existing['id']]);
    }
    $stmt = $conn->prepare("INSERT INTO email_accounts (sender_email, sender_app_password, admin_email, admin_app_password, app_password) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$senderEmail, $normalizedSenderPassword, $adminEmail, $normalizedAdminPassword, $normalizedSenderPassword]);
}

function sendWelcomeEmailToStaff($name, $email, $roleId) {
    $account = getEmailAccount();
    $hasAdminCredentials = !empty($account['admin_email']) && !empty($account['admin_app_password']);
    $fromEmail = $hasAdminCredentials ? $account['admin_email'] : (!empty($account['sender_email']) ? $account['sender_email'] : '');
    $fromPassword = $hasAdminCredentials ? $account['admin_app_password'] : (!empty($account['sender_app_password']) ? $account['sender_app_password'] : ($account['app_password'] ?? ''));
    if ($fromEmail === '' || $fromPassword === '') {
        error_log('Staff welcome email not sent: missing from email or password.');
        return false;
    }

    ensureAdminPHPMailerLoaded();

    $role = getRole($roleId);
    $roleName = isset($role['name']) ? strtolower($role['name']) : '';
    $isLoanOfficer = strpos($roleName, 'loan') !== false || (int) $roleId === 2;
    $attachmentPath = $isLoanOfficer
        ? dirname(__DIR__) . '/admin/assets/performance_contract.pdf'
        : dirname(__DIR__) . '/admin/assets/company_profile.txt';
    $attachmentName = $isLoanOfficer ? 'performance_contract.pdf' : 'company_profile.txt';

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $fromEmail;
        $mail->Password = $fromPassword;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom($fromEmail, 'Inua Premium Services');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Inua Premium Services';
        $mail->Body = "<p>Dear {$name},</p>
            <p>Welcome to Inua Premium Services. We are a trusted financial partner focused on delivering responsible lending, transparent client service, and strong operational standards.</p>
            <p>As a member of our team, you are expected to support our clients professionally, maintain the confidentiality of borrower information, and follow the policies that govern loan processing, repayment collection, and client communication.</p>
            <p>Our standards require that you:
                <ul>
                    <li>treat every client with respect and fairness,</li>
                    <li>never share sensitive data outside approved channels,</li>
                    <li>follow approval workflows for disbursements and repayments,</li>
                    <li>report issues immediately to your supervisor,</li>
                    <li>and monitor your portfolio closely for any exceptions.</li>
                </ul>
            </p>
            <p>Always check your email regularly to track client repayments, review due amounts, and respond to any arrears notifications. Use the automated 7-day-to-due-date schedule and arrears list to stay ahead of overdue accounts and support timely collections.</p>
            <p>Please review the attached document carefully. It contains your performance contract, our key policies, and the standards expected of every staff member.</p>
            <p>If you have any questions, contact your manager or the operations team before taking any action.</p>
            <p>Regards,<br/>Inua Premium Services</p>";
        $mail->AltBody = 'Welcome to Inua Premium Services. Please review the attached performance contract for your role, policies, and expected standards, and check your email regularly for repayment and arrears updates.';

        if (file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath, $attachmentName);
        }

        return $mail->send();
    } catch (Exception $e) {
        error_log('Staff welcome email failed: ' . $mail->ErrorInfo);
        return false;
    }
}

function sendMessage($recipient, $message) {
    $text=$message;
    $phone=$recipient;
    $username = trim((string) ($_ENV['AFRICASTALKING_USERNAME'] ?? getenv('AFRICASTALKING_USERNAME') ?: ''));
    $apiKey = trim((string) ($_ENV['AFRICASTALKING_API_KEY'] ?? getenv('AFRICASTALKING_API_KEY') ?: ''));
    $smsSettings = getApiIntegrationSettings('sms');
    $provider = strtolower(trim((string) ($smsSettings['provider'] ?? 'africastalking')));
    $providerConfigs = $smsSettings['providers'] ?? [];
    if (!is_array($providerConfigs)) {
        throw new UnexpectedValueException('Stored SMS provider settings are invalid.');
    }

    if ($provider === 'celcom') {
        $celcomSettings = $providerConfigs['celcom'] ?? [];
        if (!is_array($celcomSettings)) {
            throw new UnexpectedValueException('Stored Celcom Africa settings are invalid.');
        }

        $partnerId = trim((string) ($celcomSettings['partner_id'] ?? ''));
        $celcomApiKey = trim((string) ($celcomSettings['api_key'] ?? ''));
        $shortcode = trim((string) ($celcomSettings['shortcode'] ?? ''));
        if ($partnerId === '' || $celcomApiKey === '' || $shortcode === '') {
            throw new RuntimeException('Celcom Africa SMS settings are incomplete.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required to send SMS through Celcom Africa.');
        }

        $recipients = is_array($phone) ? $phone : [$phone];
        $mobileNumbers = array_filter(array_map(static function ($number) {
            return trim((string) $number);
        }, $recipients), static function ($number) {
            return $number !== '';
        });
        $mobile = implode(',', $mobileNumbers);
        if ($mobile === '' || trim((string) $text) === '') {
            throw new InvalidArgumentException('A recipient and message are required to send SMS.');
        }

        $requestBody = json_encode([
            'apikey' => $celcomApiKey,
            'partnerID' => $partnerId,
            'message' => $text,
            'shortcode' => $shortcode,
            'mobile' => $mobile
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $curl = curl_init('https://isms.celcomafrica.com/api/services/sendsms/');
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize the Celcom Africa SMS request.');
        }

        $optionsSet = curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30
        ]);
        if (!$optionsSet) {
            curl_close($curl);
            throw new RuntimeException('Unable to configure the Celcom Africa SMS request.');
        }

        $responseBody = curl_exec($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = $responseBody === false ? curl_error($curl) : '';
        curl_close($curl);

        if ($responseBody === false) {
            error_log('Celcom Africa SMS request failed: ' . $curlError);
            throw new RuntimeException('Celcom Africa SMS request failed due to a network error.');
        }
        if ($httpStatus < 200 || $httpStatus >= 300) {
            error_log('Celcom Africa SMS request failed with HTTP status ' . $httpStatus . '.');
            throw new RuntimeException('Celcom Africa SMS request failed with HTTP status ' . $httpStatus . '.');
        }

        $responseData = json_decode($responseBody, true);
        if (!is_array($responseData) || !is_array($responseData['responses'] ?? null) || $responseData['responses'] === []) {
            error_log('Celcom Africa returned an unexpected SMS response.');
            throw new RuntimeException('Celcom Africa returned an unexpected SMS response.');
        }
        foreach ($responseData['responses'] as $providerResponse) {
            if (!is_array($providerResponse)) {
                throw new RuntimeException('Celcom Africa returned an invalid SMS response.');
            }
            $responseCode = $providerResponse['response-code'] ?? $providerResponse['respose-code'] ?? null;
            if (!is_numeric($responseCode) || (int) $responseCode !== 200) {
                $responseCode = is_scalar($responseCode) ? (string) $responseCode : 'unknown';
                error_log('Celcom Africa rejected an SMS request with response code ' . $responseCode . '.');
                throw new RuntimeException('Celcom Africa rejected the SMS request (response code ' . $responseCode . ').');
            }
        }

        return $responseData;
    }

    if ($provider !== 'africastalking') {
        throw new InvalidArgumentException('The configured SMS provider is not supported.');
    }

    $africasTalkingSettings = $providerConfigs['africastalking'] ?? [];
    if (!is_array($africasTalkingSettings)) {
        throw new UnexpectedValueException('Stored Africa\'s Talking settings are invalid.');
    }
    $smsSettings['username'] = $africasTalkingSettings['username'] ?? $smsSettings['username'] ?? null;
    $smsSettings['api_key'] = $africasTalkingSettings['api_key'] ?? $smsSettings['api_key'] ?? null;
    $smsSettings['sender_id'] = $africasTalkingSettings['sender_id'] ?? $smsSettings['sender_id'] ?? null;
    $username = trim((string) ($smsSettings['username'] ?? '')) ?: $username;
    $apiKey = trim((string) ($smsSettings['api_key'] ?? '')) ?: $apiKey;
    $senderId = trim((string) ($smsSettings['sender_id'] ?? ''));
    if ($username === '' || $apiKey === '') {
        throw new RuntimeException('Africa\'s Talking SMS credentials are not configured. Set them under Admin > SMS Settings.');
    }

    $recipients = is_array($phone) ? $phone : [$phone];
    $recipients = array_values(array_filter(array_map(static function ($number) {
        $number = preg_replace('/[\s().-]+/', '', trim((string) $number));
        if (strpos($number, '00') === 0) {
            $number = '+' . substr($number, 2);
        } elseif (preg_match('/^0[17]\d{8}$/', $number)) {
            $number = '+254' . substr($number, 1);
        } elseif (preg_match('/^254[17]\d{8}$/', $number)) {
            $number = '+' . $number;
        } elseif (preg_match('/^[17]\d{8}$/', $number)) {
            $number = '+254' . $number;
        }
        return $number;
    }, $recipients), static function ($number) {
        return $number !== '';
    }));
    if ($recipients === []) {
        throw new InvalidArgumentException('No valid SMS recipient phone numbers were provided.');
    }

    $AT = new AfricasTalking($username, $apiKey);
    
    // Get one of the services
    $sms      = $AT->sms();
    
    // Use the service
    $smsPayload = [
        'to'      => $recipients,
        'message' => $text
    ];
    if ($senderId !== '') {
        $smsPayload['from'] = $senderId;
    }

    try {
        $response = $sms->send($smsPayload);
    } catch (\GuzzleHttp\Exception\RequestException $exception) {
        $httpStatus = $exception->getResponse() ? $exception->getResponse()->getStatusCode() : null;
        if ($httpStatus === 401) {
            throw new RuntimeException('Africa\'s Talking rejected the SMS credentials (HTTP 401). Update the username and API key under Admin > SMS Settings.', 401, $exception);
        }
        if ($httpStatus !== null) {
            throw new RuntimeException('Africa\'s Talking rejected the SMS request (HTTP ' . $httpStatus . '). Check the provider account and SMS settings.', $httpStatus, $exception);
        }
        throw $exception;
    }

    if (!is_array($response) || ($response['status'] ?? '') !== 'success') {
        throw new RuntimeException('Africa\'s Talking returned an unsuccessful SMS response.');
    }

    $responseData = $response['data'] ?? null;
    if (is_string($responseData)) {
        $decodedResponseData = json_decode($responseData);
        if (json_last_error() === JSON_ERROR_NONE) {
            $responseData = $decodedResponseData;
        }
    }
    $getResponseField = static function ($data, $field) {
        if (is_object($data)) {
            foreach (get_object_vars($data) as $key => $value) {
                if (strcasecmp((string) $key, $field) === 0) {
                    return $value;
                }
            }
        } elseif (is_array($data)) {
            foreach ($data as $key => $value) {
                if (strcasecmp((string) $key, $field) === 0) {
                    return $value;
                }
            }
        }
        return null;
    };
    $smsMessageData = $getResponseField($responseData, 'SMSMessageData');
    if ($smsMessageData === null) {
        $smsMessageData = $responseData;
    }
    $providerRecipients = $getResponseField($smsMessageData, 'Recipients');
    if ($providerRecipients instanceof Traversable) {
        $providerRecipients = iterator_to_array($providerRecipients, false);
    } elseif (is_object($providerRecipients)) {
        $providerRecipients = [$providerRecipients];
    }

    if (!is_array($providerRecipients) || $providerRecipients === []) {
        $providerMessage = $getResponseField($smsMessageData, 'Message');
        $providerMessage = is_scalar($providerMessage) ? trim((string) $providerMessage) : '';
        if (preg_match('/Sent\s+to\s+(\d+)\s*\/\s*(\d+)/i', $providerMessage, $sentCounts) && $sentCounts[1] === $sentCounts[2] && (int) $sentCounts[2] > 0) {
            return $response;
        }
        $responseKeys = is_object($responseData)
            ? implode(',', array_keys(get_object_vars($responseData)))
            : (is_array($responseData) ? implode(',', array_keys($responseData)) : gettype($responseData));
        error_log('Africa\'s Talking SMS response contained no recipient results; provider message: ' . ($providerMessage !== '' ? substr($providerMessage, 0, 300) : 'none') . '; response fields: ' . $responseKeys);
        $failureDetail = $providerMessage !== ''
            ? ' Provider response: ' . substr($providerMessage, 0, 300)
            : ' The provider response did not contain a result message.';
        throw new RuntimeException('Africa\'s Talking returned no recipient delivery results.' . $failureDetail . ' Check that the manager phone number is valid and internationally formatted, and review the provider dashboard.');
    }

    foreach ($providerRecipients as $recipientResult) {
        $deliveryStatus = strtolower(trim((string) ($getResponseField($recipientResult, 'status') ?? '')));
        $statusCode = (int) ($getResponseField($recipientResult, 'statusCode') ?? 0);
        if ($deliveryStatus !== 'success' && !in_array($statusCode, [100, 101], true)) {
            $providerStatus = $deliveryStatus !== '' ? $deliveryStatus : ($statusCode > 0 ? (string) $statusCode : 'unknown');
            throw new RuntimeException('Africa\'s Talking did not accept delivery to one or more recipients (provider status: ' . $providerStatus . '). Check the phone number format and provider account.');
        }
    }

    return $response;
}
function savelogs() {
    // Implement log saving functionality
}
?>
