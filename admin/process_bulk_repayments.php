<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['bulk_file']) && $_FILES['bulk_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['bulk_file']['tmp_name'];
        $handle = fopen($file, "r");

        if ($handle !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $borrower = $data[0];
                $loan_product = $data[1];
                $amount = $data[2];
                $repayment_date = $data[3];

                $sql = "INSERT INTO repayments (borrower, loan_product, amount, repayment_date) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssds", $borrower, $loan_product, $amount, $repayment_date);
                $stmt->execute();
            }
            fclose($handle);
            echo "Bulk repayments uploaded successfully";
        } else {
            echo "Error opening file";
        }
    } else {
        echo "Error uploading file";
    }
    $conn->close();
}
?>
