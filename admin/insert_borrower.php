<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input data
    $loanOfficer = mysqli_real_escape_string($conn, $_POST['loanOfficer']);
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $business_name = mysqli_real_escape_string($conn, $_POST['business_name']);
    $id_number = mysqli_real_escape_string($conn, $_POST['id_number']); // Changed from unique_number
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $total_paid = mysqli_real_escape_string($conn, $_POST['total_paid']);
    $open_loans_balance = mysqli_real_escape_string($conn, $_POST['open_loans_balance']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // File Upload Handling
    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Function to handle file uploads
    function uploadFile($file_input, $target_dir) {
        $file_name = basename($_FILES[$file_input]['name']);
        $file_tmp = $_FILES[$file_input]['tmp_name'];
        $file_path = $target_dir . time() . "_" . $file_name;

        if (move_uploaded_file($file_tmp, $file_path)) {
            return $file_path;
        } else {
            return null;
        }
    }

    // Upload files
    $id_upload = uploadFile('id_upload', $upload_dir);
    $passport_photo = uploadFile('passport_photo', $upload_dir);

    // Insert borrower details into the database
    $sql = "INSERT INTO borrowers (full_name, business_name, unique_number, mobile, email, total_paid, open_loans_balance, status, loan_officer, id_upload,passport_photo)
            VALUES ('$full_name', '$business_name', '$id_number', '$mobile', '$email', '$total_paid', '$open_loans_balance', '$status', '$loanOfficer', '$id_upload', '$passport_photo')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>
                alert('New borrower added successfully!');
                location.replace('view_borrowers.php');
              </script>";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();
}
?>
