<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $borrower = $_POST['borrower'];
    $loan_product = $_POST['loan_product'];
    $amount = $_POST['amount'];
    $repayment_date = $_POST['repayment_date'];

    $sql = "INSERT INTO repayments (borrower, loan_product, amount, repayment_date) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssds", $borrower, $loan_product, $amount, $repayment_date);

    if ($stmt->execute()) {
        echo "Repayment added successfully";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
