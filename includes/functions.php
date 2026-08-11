<?php
use AfricasTalking\SDK\AfricasTalking;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/vendor/autoload.php';

if (!function_exists('db_connect')) {
    // Database connection
    function db_connect() {
    $host = 'localhost';
    $db = 'microfinance';
    $user = 'root';
    $pass = '';
    try {
        return new PDO("mysql:host=$host;dbname=$db", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
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

// User management functions
function ensureUserSalaryColumnExists() {
    $conn = db_connect();
    $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'basic_salary'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $conn->exec("ALTER TABLE users ADD COLUMN basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00");
    }
}

function add_user($name, $email, $password, $role, $area = null, $phone = null, $basic_salary = 0.00) {
    ensureUserSalaryColumnExists();
    $conn = db_connect();
    $email = strtolower(trim((string) $email));

    $checkStmt = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        error_log('Duplicate staff email attempted: ' . $email);
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role_id, area, phone, basic_salary) VALUES (?, ?, ?, ?, ?, ?, ?)");
    try {
        return $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $area, $phone, $basic_salary]);
    } catch (PDOException $e) {
        error_log('Failed to add staff user: ' . $e->getMessage());
        return false;
    }
}

function login($email, $password, $role) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND role_id = :role");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':role', $role);
    $stmt->execute();

    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $email;
        $_SESSION['username'] = $user['name'];
        return '1,' . $role; // Success
    } else {
        return '0'; // Failure
    }
}

function update_user($id, $name, $email, $password) {
    $conn = db_connect();
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
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

function normalizeEmailAppPassword($password) {
    return preg_replace('/\s+/', '', trim((string) $password));
}

function ensureEmailAccountTable() {
    $conn = db_connect();
    $conn->exec("CREATE TABLE IF NOT EXISTS email_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_email VARCHAR(255) NOT NULL,
        sender_app_password VARCHAR(255) DEFAULT NULL,
        admin_email VARCHAR(255) DEFAULT NULL,
        admin_app_password VARCHAR(255) DEFAULT NULL,
        app_password VARCHAR(255) DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'sender_app_password'");
    if ($columnCheck->rowCount() === 0) {
        $conn->exec("ALTER TABLE email_accounts ADD COLUMN sender_app_password VARCHAR(255) DEFAULT NULL AFTER sender_email");
        $conn->exec("UPDATE email_accounts SET sender_app_password = app_password WHERE sender_app_password IS NULL");
    }

    $columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'admin_email'");
    if ($columnCheck->rowCount() === 0) {
        $conn->exec("ALTER TABLE email_accounts ADD COLUMN admin_email VARCHAR(255) DEFAULT NULL AFTER sender_app_password");
    }

    $columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'admin_app_password'");
    if ($columnCheck->rowCount() === 0) {
        $conn->exec("ALTER TABLE email_accounts ADD COLUMN admin_app_password VARCHAR(255) DEFAULT NULL AFTER admin_email");
    }

    $columnCheck = $conn->query("SHOW COLUMNS FROM email_accounts LIKE 'app_password'");
    if ($columnCheck->rowCount() === 0) {
        $conn->exec("ALTER TABLE email_accounts ADD COLUMN app_password VARCHAR(255) DEFAULT NULL AFTER admin_app_password");
        $conn->exec("UPDATE email_accounts SET app_password = sender_app_password WHERE app_password IS NULL");
    }
}

function getEmailAccount() {
    $conn = db_connect();
    ensureEmailAccountTable();
    $stmt = $conn->query("SELECT * FROM email_accounts ORDER BY id ASC LIMIT 1");
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
    $username = 'inua'; // use 'sandbox' for development in the test environment
    $apiKey   = 'atsk_e901eb4e7f48e5ebcc5a327516cba2e0213b991c7485d6c64d6f5a05de75a49dba270f32'; // use your sandbox app API key for development in the test environment
    $AT = new AfricasTalking($username,$apiKey);
    
    // Get one of the services
    $sms      = $AT->sms();
    
    // Use the service
    $result   = $sms->send([
        'to'      => $phone,
        'message' => $text
    ]);
}
function savelogs() {
    // Implement log saving functionality
}
?>
