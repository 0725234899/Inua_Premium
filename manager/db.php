<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$managerCurrentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$managerBillingExemptPages = ['index.php', 'callback.php'];
if (!empty($_SESSION['email']) && !in_array($managerCurrentPage, $managerBillingExemptPages, true)) {
    $billingPdo = new PDO('mysql:host=localhost;dbname=microfinance', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $billingLookup = $billingPdo->prepare('SELECT start_date, billing_cycle, expires_at FROM manager_billing_access WHERE user_email = ? LIMIT 1');
    try {
        $billingLookup->execute([strtolower(trim((string) $_SESSION['email']))]);
        $billingRecord = $billingLookup->fetch();
    } catch (PDOException $e) {
        $billingRecord = false;
    }

    $calculateExpiry = static function ($startDate, $billingCycle) {
        $monthsByCycle = ['Monthly' => 1, 'Quarterly' => 3, 'Annually' => 12];
        if (!isset($monthsByCycle[$billingCycle])) {
            return null;
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $startDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$start || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $start->format('Y-m-d') !== $startDate || $start > new DateTimeImmutable('today')) {
            return null;
        }

        $targetMonth = $start->modify('first day of this month')->modify('+' . $monthsByCycle[$billingCycle] . ' months');
        $targetDay = min((int) $start->format('d'), (int) $targetMonth->format('t'));
        $maturityDate = $targetMonth->setDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('m'), $targetDay);
        return $maturityDate->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    };

    $billingExpiry = $billingRecord
        ? $calculateExpiry($billingRecord['start_date'], $billingRecord['billing_cycle'])
        : null;
    if ($billingRecord && $billingExpiry !== null && $billingExpiry !== $billingRecord['expires_at']) {
        $billingUpdate = $billingPdo->prepare('UPDATE manager_billing_access SET expires_at = ? WHERE user_email = ?');
        $billingUpdate->execute([$billingExpiry, strtolower(trim((string) $_SESSION['email']))]);
    }

    if ($billingExpiry === null || strtotime($billingExpiry) <= time()) {
        header('Location: index.php');
        exit();
    }
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "microfinance";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
