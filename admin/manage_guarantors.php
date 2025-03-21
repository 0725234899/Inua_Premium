<?php
include 'db.php';

// Get the loan ID from the URL
$loan_id = isset($_GET['loan_id']) ? $_GET['loan_id'] : null;

if (!$loan_id) {
    echo "Invalid loan ID.";
    exit;
}

// Function to get guarantors for a specific loan
function getGuarantors($loan_id) {
    global $conn;
    $guarantors = array();

    $sql = "SELECT 
                g.id, 
                g.full_name, 
                g.email, 
                g.phone 
            FROM guarantors g
            INNER JOIN loan_guarantors lg ON g.id = lg.guarantor_id
            WHERE lg.loan_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $guarantors[] = $row;
        }
    } else {
        echo "No guarantors found.";
    }

    return $guarantors;
}

// Function to add a guarantor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    $sql = "INSERT INTO guarantors (full_name, email, phone) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $full_name, $email, $phone);
    
    if ($stmt->execute()) {
        $guarantor_id = $stmt->insert_id;
        $sql = "INSERT INTO loan_guarantors (loan_id, guarantor_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $loan_id, $guarantor_id);
        $stmt->execute();

        echo "<div class='alert alert-success'>Guarantor added successfully.</div>";
    } else {
        echo "<div class='alert alert-danger'>Failed to add guarantor.</div>";
    }
}

$guarantors = getGuarantors($loan_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Guarantors</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Manage Guarantors for Loan ID: <?php echo $loan_id; ?></h1>

        <h3>Add a New Guarantor</h3>
        <form method="POST">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" name="phone" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary mt-2">Add Guarantor</button>
        </form>

        <h3 class="mt-5">Current Guarantors</h3>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (count($guarantors) > 0) {
                    foreach ($guarantors as $guarantor) {
                        echo "<tr>
                            <td>{$guarantor['id']}</td>
                            <td>{$guarantor['full_name']}</td>
                            <td>{$guarantor['email']}</td>
                            <td>{$guarantor['phone']}</td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>No guarantors found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
