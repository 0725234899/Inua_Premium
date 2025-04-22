<?php
include_once("../includes/functions.php");
include_once("db.php"); // Ensure database connection is included

// Get client ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid client ID.");
}

$client_id = intval($_GET['id']); // Ensure ID is an integer

// Fetch client details, including passport photo and loan officer name
$sql_client = "SELECT b.*, u.name AS loan_officer_name 
               FROM borrowers b 
               INNER JOIN users u ON b.loan_officer = u.email 
               WHERE b.id = ?";
$stmt = $conn->prepare($sql_client);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result_client = $stmt->get_result();
$client = $result_client->fetch_assoc();

if (!$client) {
    die("Client not found.");
}

// Fetch loan details for this client
$sql_loans = "SELECT 
    l.id, 
    l.total_amount, 
    l.loan_status, 
    DATE_FORMAT(l.loan_release_date, '%d/%m/%Y') AS loan_release_date, 
    SUM(r.paid) AS total_paid
FROM 
    loan_applications l 
LEFT JOIN 
    repayments r ON l.id = r.loan_id
WHERE 
    l.borrower = ?";
$stmt_loans = $conn->prepare($sql_loans);
$stmt_loans->bind_param("i", $client_id);
$stmt_loans->execute();

$result_loans = $stmt_loans->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Client Details</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .text-left{
            text-align: left;
        }
        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e84545;
        }
        .table-container {
            overflow-x: auto;
        }
        .dashboard-metrics {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .metric {
            background-color: #ffffff;
            border: 1px solid #212529;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            flex: 1;
            margin: 10px;
            min-width: 250px;
        }
        .chart-container {
            width: 80%;
            margin: auto;
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
        .container h1 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        .table thead th {
            background-color: #f5f5f5;
        }
        .overdue {
            background-color: #f8d7da;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
            }
            .main {
                margin-left: 0;
            }
        }
      

    .profile-img:hover {
        transform: scale(1.5); /* Zoom in by 1.5 times */
        cursor: pointer;
    }
    </style>
</head>
<body>
    <?php include("includes/header.php"); ?>
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Passport Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="img-fluid" alt="Passport Photo">
            </div>
        </div>
    </div>
</div>

    <div class="container mt-4">
        <h1>Client Details</h1>
        <div class="row">
            <div class="col-md-4">
                <div class="card p-3">
                    
                    <div class="text-left">
                    <h3></h3>
                      
                    </div>
                    <?php
    $image = "../loanOfficer/" . $client['passport_photo'];
    echo "<img src='$image' alt='Passport Photo' class='profile-img mb-2' onclick='openModal(\"$image\")'>";
?>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($client['full_name']); ?></p>
                    <p><strong>Id Number:</strong> <?php echo htmlspecialchars($client['unique_number']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($client['mobile']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                    <p><strong>Loan Officer:</strong> <?php echo htmlspecialchars($client['loan_officer_name']); ?></p>
                </div>
            </div>

            <div class="col-md-8">
                <h2>Loan Details</h2>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Loan ID</th>
                                <th>Amount (KSh)</th>
                                <th>Loan Balance</th>
                                <th>Application Date</th>
                               
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_loans->num_rows > 0): ?>
                                <?php while ($loan = $result_loans->fetch_assoc()): ?>
                                    <tr>
                                    <?php 
                                        $total_amount = $loan['total_amount'] ?? 0;
                                        $total_paid = $loan['total_paid'] ?? 0;
                                        $outstanding_loans = $total_amount - $total_paid;
                                   $loanid = $loan['id'];
                                  ?>
                                        <td>
    <a href="<?php echo 'repayment_details.php?loanId=' . urlencode($loanid); ?>">
        <?php echo htmlspecialchars($loan['id']); ?>
    </a>
</td>

                                        <td><?php echo number_format(ceil($loan['total_amount'])); ?></td>
                                        <td><?php echo number_format(ceil($outstanding_loans)); ?></td>
                                        <td><?php echo htmlspecialchars($loan['loan_release_date']); ?></td>
                                       
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">No loan records found for this client.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <a href="view_loanOfficers.php" class="btn btn-secondary mt-3">Back to Loan Officers</a>
    </div>
    <script>
    function openModal(imageSrc) {
        document.getElementById("modalImage").src = imageSrc; // Set modal image source
        var myModal = new bootstrap.Modal(document.getElementById("imageModal")); // Initialize Bootstrap modal
        myModal.show(); // Show modal
    }
</script>
</body>
</html>
