<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

// Fetch total overdue amount using same calculation as overdue_repayments.php (sum per-borrower amounts)
$sql_total_overdue = "SELECT 
                    borrowers.full_name AS borrower_name, 
                    borrowers.mobile AS phone_number, 
                    GREATEST(
                        COALESCE(SUM(CASE 
                            WHEN repayments.repayment_date < CURDATE() THEN COALESCE(repayments.amount, 0) 
                            ELSE 0 
                        END), 0) 
                        - COALESCE(SUM(COALESCE(repayments.paid, 0)), 0), 
                        0
                    ) AS total_overdue
                FROM 
                    borrowers
                LEFT JOIN 
                    loan_applications ON borrowers.id = loan_applications.borrower
                LEFT JOIN 
                    repayments ON loan_applications.id = repayments.loan_id
                WHERE 
                    1=1
                    AND loan_applications.loan_status = 'approved'
                GROUP BY 
                    borrowers.full_name, borrowers.mobile
                HAVING 
                    total_overdue > 0";
$stmt_total_overdue = $conn->prepare($sql_total_overdue);
$stmt_total_overdue->execute();
$result_total_overdue = $stmt_total_overdue->get_result();

// Calculate total overdue amount
$total_overdue_amount = 0;
while ($row = $result_total_overdue->fetch_assoc()) {
    $total_overdue_amount += $row['total_overdue'];
}

// Fetch total paid amount for approved loans
$sql_total_paid = "SELECT CEIL(SUM(paid)) AS total_paid 
                   FROM repayments 
                   INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                   WHERE loan_applications.loan_status = 'approved'";
$stmt_total_paid = $conn->prepare($sql_total_paid);
$stmt_total_paid->execute();
$total_paid_amount = $stmt_total_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;

// Calculate total arrears (overdue repayments)
$total_arrears = $total_overdue_amount;

// Fetch total interest and combined fee totals for approved loans
$sql_total_interest = "SELECT CEIL(COALESCE(SUM(total_amount - principal), 0)) AS total_interest
                       FROM loan_applications
                       WHERE loan_status = 'approved'";
$stmt_total_interest = $conn->prepare($sql_total_interest);
$stmt_total_interest->execute();
$total_interest_amount = $stmt_total_interest->get_result()->fetch_assoc()['total_interest'] ?? 0;

$sql_total_penalties = "SELECT COALESCE(SUM(GREATEST(0, (
                            COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = l.id), 0)
                            - (l.principal + (l.principal * 0.06 * l.loan_duration))
                        ))), 0) AS total_penalties
                        FROM loan_applications l
                        WHERE l.loan_status IN ('approved', 'rolled_over')
                           OR LOWER(TRIM(COALESCE(l.loan_status, ''))) LIKE '%roll%'";
$stmt_total_penalties = $conn->prepare($sql_total_penalties);
$stmt_total_penalties->execute();
$total_penalty_amount = $stmt_total_penalties->get_result()->fetch_assoc()['total_penalties'] ?? 0;

$sql_total_fees = "SELECT CEIL(COALESCE(SUM(processing_fee + registration_fee), 0)) AS total_fees
                   FROM loan_applications
                   WHERE loan_status = 'approved'";
$stmt_total_fees = $conn->prepare($sql_total_fees);
$stmt_total_fees->execute();
$total_fee_amount = $stmt_total_fees->get_result()->fetch_assoc()['total_fees'] ?? 0;

$sql_total_principal = "SELECT CEIL(SUM(loan_applications.principal)) AS total_principal 
                        FROM loan_applications 
                        WHERE loan_status = 'approved'";
$stmt_total_principal = $conn->prepare($sql_total_principal);
$stmt_total_principal->execute();
$total_disbursed_principal = $stmt_total_principal->get_result()->fetch_assoc()['total_principal'] ?? 0;

$sql_total_loans = "SELECT CEIL(SUM(loan_applications.total_amount)) AS total_loans 
                    FROM loan_applications 
                    WHERE loan_status = 'approved'";
$stmt_total_loans = $conn->prepare($sql_total_loans);
$stmt_total_loans->execute();
$total_loan_amount = $stmt_total_loans->get_result()->fetch_assoc()['total_loans'] ?? 0;

// Calculate Performing Book
$performing_book = max(0, $total_loan_amount - $total_arrears - $total_paid_amount);

// Calculate Loan Book
$loan_book = $performing_book + $total_arrears;

// Calculate Portfolio at Risk (PAR)
$par = ($loan_book > 0) ? ($total_arrears / $loan_book) * 100 : 0;

// Fetch total performing loans
$sql_total_performing = "SELECT CEIL(SUM(amount - paid)) AS total_performing 
                         FROM repayments 
                         INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                         WHERE loan_applications.loan_status = 'approved' 
                         AND (amount - paid) = 0";
$stmt_total_performing = $conn->prepare($sql_total_performing);
$stmt_total_performing->execute();
$total_performing_loans = $stmt_total_performing->get_result()->fetch_assoc()['total_performing'] ?? 0;

// Fetch total number of clients with outstanding loan balance > 0
$sql_total_clients = "SELECT COUNT(*) AS total_clients 
                      FROM (
                          SELECT borrowers.id
                          FROM borrowers
                          LEFT JOIN loan_applications ON borrowers.id = loan_applications.borrower
                          LEFT JOIN repayments ON loan_applications.id = repayments.loan_id
                          WHERE loan_applications.loan_status = 'approved'
                          GROUP BY borrowers.id
                          HAVING SUM(COALESCE(repayments.amount - repayments.paid, 0)) > 0
                      ) AS clients_with_balance";
$stmt_total_clients = $conn->prepare($sql_total_clients);
$stmt_total_clients->execute();
$total_clients = $stmt_total_clients->get_result()->fetch_assoc()['total_clients'] ?? 0;

// Fetch total number of clients in arrears (with total overdue amount > 0)
$sql_clients_in_arrears = "SELECT COUNT(*) AS clients_in_arrears 
                           FROM (
                               SELECT borrowers.id,
                                   GREATEST(
                                       COALESCE(SUM(CASE 
                                           WHEN repayments.repayment_date < CURDATE() THEN COALESCE(repayments.amount, 0) 
                                           ELSE 0 
                                       END), 0) 
                                       - COALESCE(SUM(COALESCE(repayments.paid, 0)), 0), 
                                       0
                                   ) AS total_overdue
                               FROM borrowers
                               LEFT JOIN loan_applications ON borrowers.id = loan_applications.borrower
                               LEFT JOIN repayments ON loan_applications.id = repayments.loan_id
                               WHERE loan_applications.loan_status = 'approved'
                               GROUP BY borrowers.id
                               HAVING total_overdue > 0
                           ) AS arrears_summary";
$stmt_clients_in_arrears = $conn->prepare($sql_clients_in_arrears);
$stmt_clients_in_arrears->execute();
$clients_in_arrears = $stmt_clients_in_arrears->get_result()->fetch_assoc()['clients_in_arrears'] ?? 0;

// Fetch total due loans
$sql_due_loans = "SELECT CEIL(SUM(amount - paid)) AS total_due_loans 
                  FROM repayments 
                  INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                  WHERE repayment_date = CURDATE() 
                  AND loan_applications.loan_status = 'approved' 
                  AND (amount - paid) > 0";
$stmt_due_loans = $conn->prepare($sql_due_loans);
$stmt_due_loans->execute();
$total_due_loans = $stmt_due_loans->get_result()->fetch_assoc()['total_due_loans'] ?? 0;

// Query for upcoming repayments
$sql_due = "SELECT 
                borrowers.full_name,
                loan_applications.loan_status,  
                loan_applications.loan_product,
                loan_applications.total_amount, 
                SUM(repayments.amount - repayments.paid) AS total_amount_due, 
                DATE_FORMAT(MIN(repayments.repayment_date), '%d/%m/%Y') AS next_due_date 
            FROM repayments 
            INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
            INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
            WHERE repayments.repayment_date >= CURDATE() 
            AND loan_applications.loan_status = 'approved'
            AND (repayments.amount - repayments.paid) > 0
            GROUP BY repayments.loan_id, borrowers.full_name, loan_applications.loan_product, loan_applications.total_amount";

$stmt_due = $conn->prepare($sql_due);
$stmt_due->execute();
$result_due = $stmt_due->get_result();

// Query for overdue repayments
$sql_overdue = "SELECT borrowers.full_name, 
                       loan_applications.loan_product, 
                       loan_applications.loan_status, 
                       SUM(repayments.amount - repayments.paid) AS total_amount, 
                       DATE_FORMAT(MIN(repayments.repayment_date), '%d/%m/%Y') AS earliest_due_date 
                FROM repayments 
                INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id 
                INNER JOIN borrowers ON loan_applications.borrower = borrowers.id 
                WHERE repayments.repayment_date < CURDATE() 
                  AND loan_applications.loan_status = 'approved' 
                  AND (repayments.amount - repayments.paid) > 0
                GROUP BY repayments.loan_id, 
                         borrowers.full_name, 
                         loan_applications.loan_product";

$stmt_overdue = $conn->prepare($sql_overdue);
$stmt_overdue->execute();
$result_overdue = $stmt_overdue->get_result();

// Start session before any HTML output so includes/header.php can safely manage auth headers.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        :root {
            --ink: #172331;
            --muted: #687582;
            --line: #dbe3e8;
            --paper: #ffffff;
            --canvas: #f2f5f6;
            --teal: #147d78;
            --gold: #c7973e;
        }

        body {
            background: var(--canvas);
            color: var(--ink);
            font-family: "Trebuchet MS", Arial, sans-serif;
        }

        .main {
            margin-left: 250px;
            padding: 34px 22px 60px;
            transition: margin-left 0.3s ease;
        }

        .main > .header {
            background: var(--ink);
            border-top: 4px solid var(--gold);
            color: white;
            margin: 0 auto;
            max-width: 1280px;
            padding: 26px 34px;
        }

        .main > .header h1 {
            color: white;
            font-family: Georgia, serif;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: normal;
            letter-spacing: .02em;
        }

        .sidebar-toggle-btn {
            background: transparent;
            border: 1px solid #82939c;
            border-radius: 0;
            color: white;
            margin-right: 14px;
            padding: 8px 12px;
        }

        .sidebar-toggle-btn:hover {
            background: var(--teal);
            border-color: var(--teal);
            color: white;
        }

        .main > .header .btn-primary {
            background: transparent;
            border: 1px solid #82939c;
            border-radius: 0;
            color: white;
            margin-right: 0 !important;
            padding: 8px 14px;
        }

        .main > .header .btn-primary:hover {
            background: var(--teal);
            border-color: var(--teal);
        }

        .dashboard-client-search {
            display: flex;
            gap: 8px;
            margin-left: auto;
            margin-right: 18px;
            max-width: 390px;
            width: 100%;
        }

        .dashboard-client-search input {
            border: 1px solid #82939c;
            border-radius: 0;
            min-width: 0;
        }

        .dashboard-client-search .btn {
            background: var(--teal);
            border: 1px solid var(--teal);
            border-radius: 0;
            color: white;
            white-space: nowrap;
        }

        .dashboard-client-search .btn:hover { background: #0f625e; }
        .client-search-error { color: #a95d55; display: none; font-size: .85rem; margin-top: 10px; }
        .client-summary { background: #f7faf9; border-left: 4px solid var(--teal); padding: 16px 18px; }
        .client-summary h3 { font-family: Georgia, serif; font-size: 1.35rem; margin: 0 0 8px; }
        .client-summary p { color: var(--muted); margin: 3px 0; }
        .client-loans-title { color: var(--ink); font-family: Georgia, serif; font-size: 1.2rem; margin: 22px 0 10px; }
        .client-loans-table { font-size: .9rem; }
        .client-loans-table th { white-space: nowrap; }

        .main > .container {
            margin: 18px auto 0;
            max-width: 1280px;
            padding: 0;
        }

        .dashboard-metrics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin: 0;
        }

        .dashboard-metrics a {
            color: inherit;
            text-decoration: none;
        }

        .metric {
            background: var(--paper);
            border: 1px solid var(--line);
            border-left: 4px solid var(--teal);
            border-radius: 0;
            box-shadow: none;
            padding: 18px 20px;
            text-align: left;
            transition: border-color 0.2s ease, transform 0.2s ease;
            width: auto;
        }

        .metric:nth-child(2), .metric:nth-child(6) { border-left-color: var(--gold); }
        .metric:nth-child(3), .metric:nth-child(7) { border-left-color: #5b7180; }
        .metric:nth-child(4), .metric:nth-child(8) { border-left-color: #a95d55; }
        .metric:hover { box-shadow: 0 3px 10px rgba(23, 35, 49, .08); transform: translateY(-2px); }
        .metric.loan-book { width: auto; }
        .metric h2 { color: var(--ink); font-family: Georgia, serif; font-size: 1.45rem; margin: 8px 0 0; }
        .metric p { color: var(--muted); font-size: .74rem; letter-spacing: .1em; margin: 0; text-transform: uppercase; }

        .chart-container {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 0;
            box-shadow: none;
            margin: 18px 0 !important;
            max-width: none;
            padding: 24px 18px;
            width: auto;
        }

        .main > .container > .container {
            margin: 18px 0 0;
            max-width: none;
            padding: 0;
        }

        .section-title {
            background: var(--ink);
            border-top: 4px solid var(--gold);
            color: white;
            font-family: Georgia, serif;
            font-size: 1.25rem;
            font-weight: normal;
            margin: 0;
            padding: 18px 22px;
        }

        .table-container {
            background: var(--paper);
            border: 1px solid var(--line);
            border-top: 0;
            overflow-x: auto;
            padding: 18px 22px 0;
        }

        #interestSearch { border-color: var(--line); border-radius: 0; color: var(--ink); }
        #interestSearch:focus { border-color: var(--teal); box-shadow: 0 0 0 .2rem rgba(20, 125, 120, .12); }
        .table { margin: 0; }
        .table thead th { background: #edf2f3; border-bottom: 2px solid var(--teal); color: #425460; font-size: .72rem; letter-spacing: .08em; padding: 14px 18px; text-transform: uppercase; white-space: nowrap; }
        .table tbody td { border-color: #e6ecef; padding: 15px 18px; vertical-align: middle; }
        .table tbody tr:nth-child(odd), .table tbody tr.cleared-loan, .table tbody tr.rolled-over-loan { background: transparent; }
        .table tbody tr:hover { background: #f7faf9; }
        .table a { color: var(--teal); font-weight: bold; text-decoration: none; }
        .table a:hover { color: var(--ink); text-decoration: underline; }
        .cleared-loan-badge, .rolled-over-badge { border-radius: 20px; display: inline-block; font-size: .72rem; font-weight: bold; letter-spacing: .05em; padding: 5px 10px; text-transform: uppercase; }
        .cleared-loan-badge { background: #edf0f2; color: #53636d; }
        .rolled-over-badge { background: #e6f3f1; color: #146b67; }

        .sidebar { transition: all 0.3s ease; }
        .sidebar.collapsed { display: none; }
        .main.sidebar-collapsed { margin-left: 0; }

        @media (max-width: 850px) {
            .main > .header { padding: 24px; }
            .dashboard-metrics { grid-template-columns: repeat(2, 1fr); }
            .dashboard-client-search { margin: 16px 0 0; max-width: none; order: 3; }
            .main > .header { flex-wrap: wrap; }
        }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 20px 12px 40px; }
            .main.sidebar-collapsed { margin-left: 0; }
        }

        @media (max-width: 520px) {
            .dashboard-metrics { grid-template-columns: 1fr; }
            .main > .header { padding: 20px; }
            .main > .header h1 { font-size: 1.8rem; }
            .table-container { padding-left: 12px; padding-right: 12px; }
            .dashboard-client-search { flex-direction: column; }
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="sidebar" id="sidebarWrapper">
    <?php include '../includes/sidebar.php'; ?>
</div>
<main class="main" id="mainContent">
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <button type="button" class="sidebar-toggle-btn" id="sidebarToggleMain" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="mb-0">Manager Dashboard</h1>
        </div>
        <form class="dashboard-client-search" id="clientSearchForm" novalidate>
            <input type="search" class="form-control" id="clientSearchInput" placeholder="Search client name or phone" aria-label="Search client name or phone">
            <button type="submit" class="btn" id="clientSearchButton"><i class="bi bi-search"></i> Search</button>
        </form>
        <a href="add_repayments.php" class="btn btn-primary" style="margin-right:20px;">Add Repayments</a>
    </div>
    <div class="container mt-4">
        <div class="dashboard-metrics">
            <!-- Metrics -->
            <a href="overdue_repayments.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_arrears)); ?></h2>
                <p>Total Overdue Amount</p>
            </div></a>
            <a href="approved-loans.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_disbursed_principal)); ?></h2>
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
            <a href="interest_breakdown.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_interest_amount)); ?></h2>
                <p>Total Interest</p>
            </div></a>
            <a href="interest_breakdown.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_interest_amount)); ?></h2>
                <p>Interest Breakdown</p>
            </div></a>
            <a href="penalty_breakdown.php"><div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_penalty_amount)); ?></h2>
                <p>Penalties</p>
            </div></a>
            <div class="metric">
                <h2>KSH <?php echo number_format(ceil($total_fee_amount)); ?></h2>
                <p>Processing + Registration Fees</p>
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
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="clientSearchModal" tabindex="-1" aria-labelledby="clientSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="clientSearchModalLabel">Client Information</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="clientSearchResults"></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleButton = document.getElementById('sidebarToggleMain');
        const sidebarWrapper = document.getElementById('sidebarWrapper');
        const mainContent = document.getElementById('mainContent');

        if (toggleButton && sidebarWrapper && mainContent) {
            toggleButton.addEventListener('click', function () {
                sidebarWrapper.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
            });
        }

        const clientSearchForm = document.getElementById('clientSearchForm');
        const clientSearchInput = document.getElementById('clientSearchInput');
        const clientSearchButton = document.getElementById('clientSearchButton');
        const clientSearchModal = document.getElementById('clientSearchModal');
        const clientSearchResults = document.getElementById('clientSearchResults');

        if (clientSearchForm) {
            clientSearchForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                const query = clientSearchInput.value.trim();
                if (!query) {
                    clientSearchResults.innerHTML = '<p class="client-search-error" style="display:block;">Enter a client name or phone number.</p>';
                    bootstrap.Modal.getOrCreateInstance(clientSearchModal).show();
                    return;
                }

                clientSearchButton.disabled = true;
                clientSearchButton.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Searching';
                try {
                    const response = await fetch('search_client_details.php?query=' + encodeURIComponent(query), { headers: { 'Accept': 'application/json' } });
                    const clients = await response.json();
                    if (!response.ok || clients.error) throw new Error(clients.error || 'Unable to search clients.');
                    clientSearchResults.innerHTML = renderClientSearchResults(clients);
                    bootstrap.Modal.getOrCreateInstance(clientSearchModal).show();
                } catch (error) {
                    clientSearchResults.innerHTML = '<p class="client-search-error" style="display:block;">' + escapeClientSearchText(error.message) + '</p>';
                    bootstrap.Modal.getOrCreateInstance(clientSearchModal).show();
                } finally {
                    clientSearchButton.disabled = false;
                    clientSearchButton.innerHTML = '<i class="bi bi-search"></i> Search';
                }
            });
        }

        function escapeClientSearchText(value) {
            const element = document.createElement('div');
            element.textContent = value;
            return element.innerHTML;
        }

        function renderClientSearchResults(clients) {
            if (!clients.length) return '<p class="client-search-error" style="display:block;">No client found with that name or phone number.</p>';
            return clients.map(client => {
                const loans = client.loans.length ? client.loans.map(loan => `
                    <tr>
                        <td><a href="repayment_details.php?loanId=${encodeURIComponent(loan.id)}">${escapeClientSearchText(loan.id)}</a></td>
                        <td>KSH ${Number(loan.principal || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                        <td>KSH ${Number(loan.total_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                        <td>${escapeClientSearchText(loan.loan_duration || '')}</td>
                        <td>KSH ${Number(loan.total_paid || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                        <td>KSH ${Number(loan.dues_arrears || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                        <td>${escapeClientSearchText(loan.loan_release_date || 'Not specified')}</td>
                        <td><span class="status ${loan.loan_status === 'Cleared' ? 'status-cleared' : 'status-not-cleared'}">${escapeClientSearchText(loan.loan_status || 'Not Cleared')}</span></td>
                    </tr>`).join('') : '<tr><td colspan="7" class="text-center">No loans found.</td></tr>';
                return `<div class="client-summary mb-3">
                    <h3>${escapeClientSearchText(client.full_name)}</h3>
                    <p><strong>Phone:</strong> ${escapeClientSearchText(client.mobile || 'Not specified')}</p>
                    <p><strong>Client number:</strong> ${escapeClientSearchText(client.unique_number || 'Not specified')}</p>
                    <p><strong>Loan officer:</strong> ${escapeClientSearchText(client.loan_officer_name || 'Not specified')}</p>
                </div>
                <h3 class="client-loans-title">Loan Terms and IDs</h3>
                <div class="table-responsive"><table class="table table-bordered client-loans-table">
                    <thead><tr><th>Loan ID</th><th>Principal</th><th>Total Amount</th><th>Duration</th><th>Total Paid</th><th>Dues/Arrears</th><th>Release Date</th><th>Status</th></tr></thead>
                    <tbody>${loans}</tbody>
                </table></div>`;
            }).join('<hr>');
        }
    });

    // Pie Chart for PAR
    new Chart(document.getElementById('parPieChart'), {
        type: 'pie',
        data: {
            labels: ['At Risk', 'Performing'],
            datasets: [{
                data: [<?php echo $par; ?>, <?php echo 100 - $par; ?>],
                backgroundColor: ['#ef5350', '#42a5f5']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                }
            }
        }
    });

    // Bar Chart for Loan Metrics
    new Chart(document.getElementById('loanChart'), {
        type: 'bar',
        data: {
            labels: ['Total Principal', 'Performing Book', 'Loan Book'],
            datasets: [{
                label: 'Loan Metrics',
                data: [<?php echo $total_loan_amount; ?>, <?php echo $performing_book; ?>, <?php echo $loan_book; ?>],
                backgroundColor: ['#42a5f5', '#66bb6a', '#ffca28']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { title: { display: true, text: 'Amount (KSH)' } },
                x: { title: { display: true, text: 'Metrics' } }
            }
        }
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
