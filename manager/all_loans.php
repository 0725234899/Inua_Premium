<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Applications</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-family: 'Open Sans', sans-serif;
            margin: 0;
        }

        .header {
            background-color: #e84545;
            color: #ffffff;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header .logo h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
        }

        .header .navmenu ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
        }

        .header .navmenu ul li {
            margin-right: 20px;
        }

        .header .navmenu ul li a {
            color: #ffffff;
            text-decoration: none;
        }

        .header .navmenu ul li a.active,
        .header .navmenu ul li a:hover {
            color: #e84545;
        }

        .sidebar {
            background-color: #ffffff;
            color: #3a3939;
            padding: 20px;
            width: 250px;
            position: fixed;
            height: 100%;
            overflow: auto;
        }

        .sidebar .nav-item .nav-link {
            color: #3a3939;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }

        .sidebar .nav-item .nav-link.active,
        .sidebar .nav-item .nav-link:hover {
            color: #e84545;
        }

        .main {
            margin-left: 270px;
            padding: 20px;
        }

        .container {
            margin-top: 20px;
        }

        table {
            width: 100%;
            margin-bottom: 1rem;
            background-color: #fff;
        }

        table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
        }

        table tbody tr {
            border-bottom: 1px solid #dee2e6;
        }

        table tbody td {
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <?php 
    include '../includes/functions.php';
    include 'includes/header.php'; 
    ?>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    <?php
    include 'db.php';

    // Function to update loan status
    function updateLoanStatus($loan_id, $status) {
        global $conn;
        $sql = "UPDATE loan_applications SET loan_status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $loan_id);
        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }

    // Check for approve or deny actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $loan_id = $_POST['loan_id'];
        $action = $_POST['action'];
        $status = ($action === 'approve') ? 'approved' : 'denied';

        if (updateLoanStatus($loan_id, $status)) {
            echo "<div class='alert alert-success'>Loan $action successfully.</div>";
        } else {
            echo "<div class='alert alert-danger'>Failed to $action the loan.</div>";
        }
    }

    // Function to get loans
    function getLoans() {
        global $conn;
        $loans = array();

        $sql = "SELECT 
                    l.id AS loan_id, 
                    b.full_name AS borrower_name, 
                    l.principal, 
                    l.loan_duration AS duration, 
                    l.number_of_repayments AS repayments_count, 
                    l.total_amount, 
                    l.loan_status AS status
                FROM loan_applications l 
                INNER JOIN borrowers b ON l.borrower = b.id";

        $result = $conn->query($sql);

        if ($result === FALSE) {
            echo "Error: " . $conn->error;
            return $loans;
        }

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $loans[] = $row;
            }
        } else {
            echo "No records found.";
        }

        return $loans;
    }
    
    $loans = getLoans();
    ?>
    <main class="main">
        <section class="section">
            <div class="container">
                <h1>Loan Applications</h1>
                <a href="generate_pdf.php" class="btn btn-primary">Export Report as PDF</a>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Borrower's Name</th>
                            <th>Principal</th>
                            <!-- Duration column hidden -->
                            <!-- Number of Repayments column hidden -->
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td><?= htmlspecialchars($loan['borrower_name']); ?></td>
                                <td><?= number_format($loan['principal'], 2); ?> KES</td>
                                <!-- Duration column hidden -->
                                <!-- Number of Repayments column hidden -->
                                <td><?= number_format($loan['total_amount'], 2); ?> KES</td>
                                <td><?= htmlspecialchars($loan['status']); ?></td>
                                <td>
                                    <!-- Approve Button -->
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="loan_id" value="<?= $loan['loan_id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                    </form>
                                    <!-- Deny Button -->
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="loan_id" value="<?= $loan['loan_id']; ?>">
                                        <input type="hidden" name="action" value="deny">
                                        <button type="submit" class="btn btn-danger btn-sm">Deny</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>

</html>
