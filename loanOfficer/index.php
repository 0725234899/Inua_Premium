<?php
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure session variable is set
if (!isset($_SESSION['email'])) {
    die("Access denied. No session found.");
}

include_once('db.php');

$email = $_SESSION['email'];

// Fetch total overdue amount (Filtered by Loan Officer)
$sql_total_overdue = "SELECT CEIL(SUM(amount - paid)) AS total_overdue 
                      FROM repayments 
                      INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                      INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                      WHERE repayment_date < CURDATE() 
                        AND loan_applications.loan_status = 'approved'
                        AND borrowers.loan_officer = ?
                        AND (amount - paid) > 0";
$stmt_total_overdue = $conn->prepare($sql_total_overdue);
$stmt_total_overdue->bind_param("s", $email);
$stmt_total_overdue->execute();
$total_overdue_amount = $stmt_total_overdue->get_result()->fetch_assoc()['total_overdue'] ?? 0;

// Fetch total paid amount (Filtered by Loan Officer)
$sql_total_paid = "SELECT CEIL(SUM(paid)) AS total_paid 
                   FROM repayments 
                   INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                   INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                   WHERE loan_applications.loan_status = 'approved'
                     AND borrowers.loan_officer = ?";
$stmt_total_paid = $conn->prepare($sql_total_paid);
$stmt_total_paid->bind_param("s", $email);
$stmt_total_paid->execute();
$total_paid_amount = $stmt_total_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;

// Fetch total disbursed loans (Filtered by Loan Officer)
$sql_total_loans = "SELECT CEIL(SUM(loan_applications.total_amount)) AS total_loans 
                    FROM loan_applications 
                    INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                    WHERE loan_applications.loan_status = 'approved' 
                      AND borrowers.loan_officer = ?";
$stmt_total_loans = $conn->prepare($sql_total_loans);
$stmt_total_loans->bind_param("s", $email);
$stmt_total_loans->execute();
$total_loan_amount = $stmt_total_loans->get_result()->fetch_assoc()['total_loans'] ?? 0;

// Calculate Performing Book
$performing_book = max(0, $total_loan_amount - $total_overdue_amount - $total_paid_amount);

// Calculate Loan Book
$loan_book = $performing_book + $total_overdue_amount;

// Calculate Portfolio at Risk (PAR)
$par = ($total_loan_amount > 0) ? ($total_overdue_amount / $total_loan_amount) * 100 : 0;

// Fetch total due amount for today (Filtered by Loan Officer)
$sql_due_loans = "SELECT CEIL(SUM(amount - paid)) AS total_due_loans 
                  FROM repayments 
                  INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                  INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                  WHERE repayment_date = CURDATE() 
                    AND loan_applications.loan_status = 'approved' 
                    AND borrowers.loan_officer = ?
                    AND (amount - paid) > 0";
$stmt_due_loans = $conn->prepare($sql_due_loans);
$stmt_due_loans->bind_param("s", $email);
$stmt_due_loans->execute();
$total_due_loans = $stmt_due_loans->get_result()->fetch_assoc()['total_due_loans'] ?? 0;

// Fetch total number of clients for the loan officer
$sql_total_clients = "SELECT COUNT(DISTINCT borrowers.id) AS total_clients 
                      FROM borrowers 
                      WHERE loan_officer = ?";
$stmt_total_clients = $conn->prepare($sql_total_clients);
$stmt_total_clients->bind_param("s", $email);
$stmt_total_clients->execute();
$total_clients = $stmt_total_clients->get_result()->fetch_assoc()['total_clients'] ?? 0;

// Fetch total number of clients in arrears (distinct clients with overdue repayments)
$sql_clients_in_arrears = "SELECT COUNT(DISTINCT borrowers.id) AS clients_in_arrears 
                           FROM borrowers
                           INNER JOIN loan_applications ON borrowers.id = loan_applications.borrower
                           INNER JOIN repayments ON loan_applications.id = repayments.loan_id
                           WHERE repayment_date < CURDATE() 
                             AND (amount - paid) > 0
                             AND borrowers.loan_officer = ?";
$stmt_clients_in_arrears = $conn->prepare($sql_clients_in_arrears);
$stmt_clients_in_arrears->bind_param("s", $email);
$stmt_clients_in_arrears->execute();
$clients_in_arrears = $stmt_clients_in_arrears->get_result()->fetch_assoc()['clients_in_arrears'] ?? 0;

// Fetch names of clients in arrears and their arrears amounts filtered by loan officer
$sql_clients_in_arrears_details = "SELECT 
    borrowers.full_name AS client_name,
    SUM(repayments.amount - repayments.paid) AS arrears_amount
FROM 
    borrowers
INNER JOIN 
    loan_applications ON borrowers.id = loan_applications.borrower
INNER JOIN 
    repayments ON loan_applications.id = repayments.loan_id
WHERE 
    repayments.repayment_date < CURDATE()
    AND (repayments.amount - repayments.paid) > 0
    AND borrowers.loan_officer = ?
GROUP BY 
    borrowers.full_name";

$stmt_clients_in_arrears_details = $conn->prepare($sql_clients_in_arrears_details);
$stmt_clients_in_arrears_details->bind_param("s", $email);
$stmt_clients_in_arrears_details->execute();
$result_clients_in_arrears_details = $stmt_clients_in_arrears_details->get_result();

// Fetch names of clients with due repayments today and their due amounts filtered by loan officer
$sql_clients_due_today = "SELECT 
    borrowers.full_name AS client_name,
    SUM(repayments.amount - repayments.paid) AS due_amount
FROM 
    borrowers
INNER JOIN 
    loan_applications ON borrowers.id = loan_applications.borrower
INNER JOIN 
    repayments ON loan_applications.id = repayments.loan_id
WHERE 
    repayments.repayment_date = CURDATE()
    AND (repayments.amount - repayments.paid) > 0
    AND borrowers.loan_officer = ?
GROUP BY 
    borrowers.full_name";

$stmt_clients_due_today = $conn->prepare($sql_clients_due_today);
$stmt_clients_due_today->bind_param("s", $email);
$stmt_clients_due_today->execute();
$result_clients_due_today = $stmt_clients_due_today->get_result();

// Fetch recent repayments
$sql_recent_repayments = "SELECT 
    borrowers.full_name AS client_name,
    repayments.paid AS repaid_amount,
    DATE_FORMAT(repayments.repayment_date, '%d/%m/%Y') AS repayment_date
FROM 
    borrowers
INNER JOIN 
    loan_applications ON borrowers.id = loan_applications.borrower
INNER JOIN 
    repayments ON loan_applications.id = repayments.loan_id
WHERE 
    repayments.paid > 0
    AND borrowers.loan_officer = ?
ORDER BY repayments.repayment_date DESC
LIMIT 15";

$stmt_recent_repayments = $conn->prepare($sql_recent_repayments);
$stmt_recent_repayments->bind_param("s", $email);
$stmt_recent_repayments->execute();
$result_recent_repayments = $stmt_recent_repayments->get_result();
$recent_repayments = [];
while ($row = $result_recent_repayments->fetch_assoc()) {
    $recent_repayments[] = htmlspecialchars($row['client_name']) . " repaid KSH " . number_format($row['repaid_amount'], 2) . " on " . htmlspecialchars($row['repayment_date']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Microfinance</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #e3f2fd; /* Sky-blue background */
            margin: 0;
            padding: 0;
        }
        .dashboard-metrics {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end; /* Align metrics to the right edge */
            gap: 15px; /* Gap between metrics */
            margin-top: 20px;
            margin-right: 20px; /* Slightly touch the right edge */
        }
        .metric {
            background-color: #ffffff;
            border: 1px solid #90caf9;
            border-radius: 10px;
            padding: 10px; /* Reduced padding */
            text-align: center;
            width: 180px; /* Reduced width for uniformity */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease-in-out;
        }
        .metric.loan-book {
            width: 150px; /* Further reduced size for Loan Book metric */
        }
        .metric:hover {
            transform: scale(1.05);
        }
        .metric h2 {
            font-size: 18px; /* Reduced font size */
            font-weight: bold;
            color: #1976d2;
        }
        .metric p {
            font-size: 14px; /* Reduced font size */
            color: #424242;
        }
        .chart-container {
            width: 100%;
            max-width: 700px; /* Reduced max width */
            margin: 20px auto; /* Reduced margin */
            background-color: #ffffff;
            padding: 15px; /* Reduced padding */
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            padding: 10px 0;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px; /* Reduced font size */
            color: #000; /* Removed theme color */
        }
        @media (max-width: 768px) {
            .metric {
                flex: 1 1 100%; /* Stack metrics vertically on smaller screens */
            }
        }
        .marquee-box {
            background-color: #f8f9fa;
            padding: 8px; /* Reduced padding */
            font-weight: bold;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px; /* Slightly smaller font size */
        }

        .blinking {
            font-weight: bold;
            color: #28a745;
            animation: blink 10s infinite;
        }

        @keyframes blink {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0;
            }
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
<main class="main">
    <div class="header">
        <h1>Loan Officer Dashboard</h1>
    </div>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="text-center"></h1>
            <!-- Removed the blinking repayments section -->
        </div>

        <!-- Combined Marquee for Arrears and Clients Due Today -->
        <marquee behavior="scroll" direction="left" scrollamount="3" class="marquee-box mt-3">
            <?php while ($row = $result_clients_in_arrears_details->fetch_assoc()): ?>
                <span style="color: red;">
                    <?= htmlspecialchars($row['client_name']) . " (Arrears: KSH " . number_format($row['arrears_amount'], 2) . ")"; ?>
                </span> &nbsp;&nbsp;&nbsp;
            <?php endwhile; ?>
            <?php while ($row = $result_clients_due_today->fetch_assoc()): ?>
                <span style="color: green;">
                    <?= htmlspecialchars($row['client_name']) . " (Due Today: KSH " . number_format($row['due_amount'], 2) . ")"; ?>
                </span> &nbsp;&nbsp;&nbsp;
            <?php endwhile; ?>
        </marquee>

        <div class="dashboard-metrics">
            <!-- Metrics -->
            <a href="overdue_repayments.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_overdue_amount)); ?></h2>
                <p>Total Arrears</p>
            </div></a>
            <a href="approved-loans.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_loan_amount)); ?></h2>
                <p>Total Disbursed Loans</p>
            </div></a>
            <a href="performingBook.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($performing_book)); ?></h2>
                <p>Performing Book</p>
            </div></a>
            <div class="metric loan-book">
                <h2>KSH <?php echo number_format(ceil($loan_book)); ?></h2>
                <p>Loan Book</p>
            </div>
            <div class="metric">
                <h2><?php echo number_format($par, 2); ?>%</h2>
                <p>Portfolio At Risk</p>
            </div>
            <div class="metric">
                <h2><?php echo $total_clients; ?></h2>
                <p>Total Clients</p>
            </div>
            <div class="metric">
                <h2><?php echo $clients_in_arrears; ?></h2>
                <p>Clients in Arrears</p>
            </div>
            <a href="due_loans.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_due_loans)); ?></h2>
                <p>Due Loans</p>
            </div></a>
        </div>

        <div class="chart-container mt-5 d-flex flex-wrap justify-content-center">
            <!-- Pie Chart for PAR -->
            <div style="flex: 1; min-width: 300px; max-width: 400px;">
                <canvas id="parPieChart" style="width: 100%; height: 300px;"></canvas>
            </div>
            <!-- Bar Chart for Loan Metrics -->
            <div style="flex: 1; min-width: 300px; max-width: 400px;">
                <canvas id="loanChart" style="width: 100%; height: 300px;"></canvas>
            </div>
        </div>
    </div>
</main>

<footer class="text-center mt-5">
    <p><em>Powered by AntonTech</em></p>
</footer>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Add logo
        const logoPath = "/Inua_Premium_services/assets/img/logo.png";
        const img = new Image();
        img.src = logoPath;

        img.onload = function () {
            // Center the logo
            const logoWidth = 50;
            const logoHeight = 30;
            const pageWidth = doc.internal.pageSize.getWidth();
            const logoX = (pageWidth - logoWidth) / 2;
            doc.addImage(img, 'PNG', logoX, 10, logoWidth, logoHeight);

            // Add company name below the logo
            doc.setFontSize(14);
            doc.text('Inua Premium Services', 10, 50);

            // Add "Loan Officer Dashboard" title
            doc.setFontSize(18);
            doc.text('Loan Officer Dashboard', pageWidth / 2, 50, { align: 'center' });

            // Add borrower's name section with wrapping for long names
            doc.setFontSize(14);
            const borrowerName = '<?php echo htmlspecialchars($row["borrower_name"] ?? "N/A"); ?>';
            const maxWidth = doc.internal.pageSize.getWidth() - 40; // Leave margins
            doc.text('Borrower:', 10, 60);
            doc.text(borrowerName, 40, 60, { maxWidth: maxWidth, align: 'left' }); // Wrap text to fit within maxWidth

            // Add footer with sky blue text
            doc.setFontSize(10);
            doc.setFont('helvetica', 'italic');
            doc.setTextColor(135, 206, 235); // Sky blue color
            doc.text('Powered by AntonTech', pageWidth / 2, doc.internal.pageSize.getHeight() - 10, { align: 'center' });

            // Save the PDF
            doc.save('Loan_Officer_Dashboard.pdf');
        };

        img.onerror = function () {
            alert("Failed to load the logo. Please check the logo path.");
        };
    });
</script>
</body>
</html>
