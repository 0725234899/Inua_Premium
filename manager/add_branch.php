<?php
// Include the database connection
include '../includes/functions.php';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $minLoanAmount = $_POST['min_loan_amount'];
    $maxLoanAmount = $_POST['max_loan_amount'];
    $minInterestRate = $_POST['min_interest_rate'];
    $maxInterestRate = $_POST['max_interest_rate'];
    $branchCapital = $_POST['branch_capital'];
    $status = $_POST['status'];

    // Validate and sanitize input
    $name = filter_var($name, FILTER_SANITIZE_STRING);
    $phone = filter_var($phone, FILTER_SANITIZE_STRING);
    $minLoanAmount = filter_var($minLoanAmount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $maxLoanAmount = filter_var($maxLoanAmount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $minInterestRate = filter_var($minInterestRate, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $maxInterestRate = filter_var($maxInterestRate, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $branchCapital = filter_var($branchCapital, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $status = filter_var($status, FILTER_SANITIZE_STRING);

    // Insert data into the database
    $db = db_connect(); // Make sure this function connects to your database
    $query = 'INSERT INTO branches (name, phone, min_loan_amount, max_loan_amount, min_interest_rate, max_interest_rate, branch_capital, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $db->prepare($query);

    if ($stmt) {
        $stmt->bind_param('ssdddddds', $name, $phone, $minLoanAmount, $maxLoanAmount, $minInterestRate, $maxInterestRate, $branchCapital, $status);
        if ($stmt->execute()) {
            header('Location: branches.php'); // Redirect to the branches list
            exit;
        } else {
            echo 'Error: ' . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    } else {
        echo 'Error: ' . htmlspecialchars($db->error);
    }

    $db->close();
}
?>
