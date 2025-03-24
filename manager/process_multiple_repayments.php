<?php
include '../includes/db.php'; // Ensure this includes the database connection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $borrowers = $_POST['borrower'];
    $loan_products = $_POST['loan_product'];
    $amounts = $_POST['amount'];
    $repayment_dates = $_POST['repayment_date'];

    $stmt = $conn->prepare("INSERT INTO repayments (borrower, loan_product, amount, repayment_date) VALUES (?, ?, ?, ?)");

    foreach ($borrowers as $index => $borrower) {
        $loan_product = $loan_products[$index];
        $amount = $amounts[$index];
        $repayment_date = $repayment_dates[$index];
        $stmt->bind_param("ssds", $borrower, $loan_product, $amount, $repayment_date);
        $stmt->execute();
    }

    $stmt->close();
    $conn->close();

    echo "Repayments added successfully";
}
?>
