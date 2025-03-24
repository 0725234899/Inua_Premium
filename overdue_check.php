<?php
$pdo = new PDO('mysql:host=localhost;dbname=loan_system', 'root', '');

// Get overdue loans
$query = $pdo->query("SELECT * FROM loan_applications WHERE due_date < CURDATE() AND status = 'active'");
$overdueLoans = $query->fetchAll();

foreach ($overdueLoans as $loan) {
    $loanId = $loan['id'];
    $pdo->query("UPDATE loans SET status = 'overdue' WHERE id = $loanId");

    // Notify user (email/SMS)
    $userQuery = $pdo->query("SELECT * FROM users WHERE id = {$loan['user_id']}");
    $user = $userQuery->fetch();
    $phone = $user['phone_number'];
    $message = "Your loan is overdue. Please make a payment immediately.";
    sendSmsNotification($phone, $message); // Implement sendSmsNotification
}

function sendSmsNotification($phone, $message) {
    // Integrate with an SMS API
    echo "Sending SMS to $phone: $message\n";
}
?>
