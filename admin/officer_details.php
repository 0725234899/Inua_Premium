<?php
include_once("../includes/functions.php");
include_once("db.php"); // Ensure database connection is included

// Get loan officer ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid loan officer ID.");
}

$officer_id = intval($_GET['id']); // Ensure ID is an integer

// Fetch loan officer details using INNER JOIN to get role name
$sql_officer = "SELECT u.*, r.name AS role_name 
                FROM users u 
                INNER JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ?";
$stmt = $conn->prepare($sql_officer);
$stmt->bind_param("i", $officer_id);
$stmt->execute();
$result_officer = $stmt->get_result();
$officer = $result_officer->fetch_assoc();

if (!$officer) {
    die("Loan officer not found.");
}

// Fetch clients assigned to this officer using INNER JOIN
$sql_clients = "SELECT b.id, b.full_name, b.email, b.mobile 
                FROM borrowers b 
                INNER JOIN users u ON b.loan_officer = u.email 
                WHERE u.id = ?";
$stmt_clients = $conn->prepare($sql_clients);
$stmt_clients->bind_param("i", $officer_id);
$stmt_clients->execute();
$result_clients = $stmt_clients->get_result();

// Calculate total loan amount using INNER JOIN
$sql_total_loan = "SELECT SUM(r.amount) AS total_loan_amount 
                   FROM repayments r 
                   INNER JOIN loan_applications l ON r.loan_id = l.id
                   INNER JOIN borrowers b ON l.borrower = b.id 
                   INNER JOIN users u ON b.loan_officer = u.email
                   WHERE b.loan_officer = ?";
$stmt_total_loan = $conn->prepare($sql_total_loan);
$stmt_total_loan->bind_param("i", $officer_id);
$stmt_total_loan->execute();
$total_loan_amount = $stmt_total_loan->get_result()->fetch_assoc()['total_loan_amount'] ?? 0;

// Calculate overdue loan amount using INNER JOIN
$sql_overdue_loan = "SELECT SUM(r.amount) AS overdue_loan_amount 
                     FROM repayments r 
                     INNER JOIN loan_applications l ON r.loan_id = l.id
                     INNER JOIN borrowers b ON l.borrower = b.id 
                     INNER JOIN users u ON b.loan_officer = u.email
                     WHERE u.id = ? 
                     AND r.repayment_date < CURDATE() 
                     AND r.paid < r.amount";
$stmt_overdue_loan = $conn->prepare($sql_overdue_loan);
$stmt_overdue_loan->bind_param("i", $officer_id);
$stmt_overdue_loan->execute();
$overdue_loan_amount = $stmt_overdue_loan->get_result()->fetch_assoc()['overdue_loan_amount'] ?? 0;

// Calculate Portfolio at Risk (PAR)
$par = ($total_loan_amount > 0) ? ($overdue_loan_amount / $total_loan_amount) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Loan Officer Details</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #ffffff;
            font-family: 'Open Sans', sans-serif;
        }
        .container {
            margin-top: 30px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .table-container {
            overflow-x: auto;
        }
        body {
            background-color: #ffffff;
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
        .header .navmenu ul li a.active, .header .navmenu ul li a:hover {
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
        .sidebar .nav-item .nav-link.active, .sidebar .nav-item .nav-link:hover {
            color: #e84545;
        }
        .main {
            margin-left: 270px;
            padding: 20px;
        }
        .table-container {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <?php include("includes/header.php"); ?>
    <div class="container">
        <h1 class="mb-4">Loan Officer Details</h1>
        <div class="row">
            <div class="col-md-6">
                <div class="card p-3">
                    <h3>Officer Information</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($officer['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($officer['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($officer['phone']); ?></p>
                    <p><strong>Role:</strong> <?php echo htmlspecialchars($officer['role_name']); ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-3">
                    <h3>Portfolio at Risk (PAR)</h3>
                    <p><strong>Total Loan Amount:</strong> KSh <?php echo number_format($total_loan_amount, 2); ?></p>
                    <p><strong>Overdue Loan Amount:</strong> KSh <?php echo number_format($overdue_loan_amount, 2); ?></p>
                    <p><strong>PAR:</strong> <?php echo number_format($par, 2); ?>%</p>
                </div>
            </div>
        </div>

        <h2 class="mt-4">Assigned Clients</h2>
        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_clients->num_rows > 0): ?>
                        <?php while ($client = $result_clients->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($client['id']); ?></td>
                                <td><a href="client_details.php?id=<?php echo htmlspecialchars($client['id']); ?>"><?php echo htmlspecialchars($client['full_name']); ?></a></td>

                                <td><?php echo htmlspecialchars($client['email']); ?></td>
                                <td><?php echo htmlspecialchars($client['mobile']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No clients assigned to this officer.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="view_loanOfficers.php" class="btn btn-secondary mt-3">Back to Loan Officers</a>
    </div>
</body>
</html>
