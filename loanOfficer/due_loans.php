<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';
include_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

$email = (string) $_SESSION['email'];
$days = ['all', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$selected_day = (string) ($_GET['day'] ?? 'all');
if (!in_array($selected_day, $days, true)) {
    $selected_day = 'all';
}
$day_filter = ($selected_day !== 'all') ? 'AND DAYNAME(repayments.repayment_date) = ?' : '';

$sql_due_loans = "SELECT
                    borrowers.full_name AS borrower_name,
                    borrowers.mobile AS phone_number,
                    loan_applications.id AS loan_id,
                    loan_applications.total_amount AS total_disbursed,
                    SUM(repayments.paid) AS total_paid,
                    (loan_applications.total_amount - SUM(repayments.paid)) AS loan_balance,
                    repayments.amount - repayments.paid AS due_amount,
                    DATE_FORMAT(repayments.repayment_date, '%d/%m/%Y') AS due_date
                  FROM repayments
                  INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id
                  INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
                  WHERE (repayments.amount - repayments.paid) > 0
                    AND borrowers.loan_officer = ?
                    $day_filter
                  GROUP BY repayments.id
                  ORDER BY repayments.repayment_date ASC";

$stmt_due_loans = $conn->prepare($sql_due_loans);
if (!$stmt_due_loans) {
    throw new RuntimeException('Unable to prepare the due loans query: ' . $conn->error);
}
if ($selected_day !== 'all') {
    $stmt_due_loans->bind_param('ss', $email, $selected_day);
} else {
    $stmt_due_loans->bind_param('s', $email);
}
$stmt_due_loans->execute();
$result_due_loans = $stmt_due_loans->get_result();
$due_loan_rows = $result_due_loans->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Due Loans</title>
    <link href="../assets/img/logo.png" rel="icon">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #172331;
            --muted: #687582;
            --line: #dbe3e8;
            --paper: #fff;
            --canvas: #f2f5f6;
            --teal: #147d78;
            --gold: #c7973e;
        }

        body {
            background: var(--canvas);
            color: var(--ink);
            font-family: "Trebuchet MS", Arial, sans-serif;
            margin: 0;
            padding-top: 70px;
        }

        .site-letterhead {
            align-items: center;
            background: linear-gradient(90deg, #00c6ff, #0072ff);
            color: #fff;
            display: flex;
            height: 70px;
            justify-content: space-between;
            left: 0;
            padding: 0 24px;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1100;
        }

        .site-letterhead-brand {
            align-items: center;
            display: flex;
            font-size: 24px;
            font-weight: 700;
            min-width: 0;
        }

        .site-letterhead-brand img {
            height: 40px;
            margin-right: 10px;
            width: auto;
        }

        .site-letterhead-logout {
            color: #fff;
            flex: 0 0 auto;
            font-size: 18px;
            text-decoration: none;
        }

        .site-letterhead-logout:hover {
            color: #e8f8ff;
        }

        #sidebarWrapper {
            background: #f8f9fa;
            height: calc(100vh - 70px);
            left: 0;
            overflow-y: auto;
            padding: 0;
            position: fixed;
            top: 70px;
            transition: transform .3s ease;
            width: 250px;
            z-index: 1050;
        }

        #sidebarWrapper.collapsed {
            transform: translateX(-100%);
        }

        #sidebarWrapper .sidebar {
            height: auto;
            min-height: calc(100vh - 70px);
            position: relative;
            width: 250px;
        }

        .main {
            margin-left: 250px;
            padding: 34px 22px 60px;
            transition: margin-left .3s ease;
        }

        .main.sidebar-collapsed {
            margin-left: 0;
        }

        .report-shell {
            background: var(--paper);
            border: 1px solid var(--line);
            margin: 0 auto;
            max-width: 1280px;
            padding: 22px;
        }

        .report-header {
            align-items: center;
            background: var(--ink);
            border-top: 4px solid var(--gold);
            color: #fff;
            display: flex;
            gap: 16px;
            justify-content: space-between;
            margin: -22px -22px 22px;
            padding: 26px 34px;
        }

        .report-title {
            align-items: center;
            display: flex;
            gap: 12px;
            min-width: 0;
        }

        .report-header h1 {
            font-family: Georgia, serif;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: normal;
            margin: 0;
        }

        .sidebar-toggle-btn {
            align-items: center;
            background: transparent;
            border: 1px solid #82939c;
            color: #fff;
            display: inline-flex;
            flex: 0 0 auto;
            font-size: 1.5rem;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .sidebar-toggle-btn:hover,
        .report-actions .btn:hover {
            background: var(--teal);
            border-color: var(--teal);
            color: #fff;
        }

        .report-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .report-actions .btn {
            align-items: center;
            background: transparent;
            border: 1px solid #82939c;
            border-radius: 0;
            color: #fff;
            display: inline-flex;
            justify-content: center;
            min-height: 40px;
            white-space: nowrap;
        }

        .search-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 18px;
        }

        .search-row input {
            max-width: 360px;
            width: 100%;
        }

        .section-title {
            background: var(--ink);
            border-top: 4px solid var(--gold);
            color: #fff;
            font-family: Georgia, serif;
            font-size: 1.25rem;
            font-weight: normal;
            margin: 22px 0 0;
            padding: 18px 22px;
        }

        .nav-tabs {
            border-bottom: 1px solid var(--line);
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 0;
            padding: 16px 0 0;
        }

        .nav-tabs .nav-link {
            background: transparent;
            border: 1px solid var(--line);
            border-bottom: 0;
            border-radius: 0;
            color: var(--muted);
            margin: 0;
            padding: 9px 14px;
            white-space: nowrap;
        }

        .nav-tabs .nav-link.active {
            background: var(--teal);
            border-color: var(--teal);
            color: #fff;
        }

        .nav-tabs .nav-link:hover {
            background: #f7faf9;
            border-color: var(--teal);
            color: var(--teal);
        }

        .table-container {
            margin-top: 18px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table {
            background: var(--paper);
            margin: 0;
            min-width: 900px;
        }

        .table thead th {
            background: #edf2f3;
            border-bottom: 2px solid var(--teal);
            color: #425460;
            font-size: .72rem;
            letter-spacing: .08em;
            padding: 14px 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .table tbody td {
            border-color: #e6ecef;
            padding: 15px 12px;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: #f7faf9;
        }

        .table a {
            color: var(--teal);
            font-weight: 700;
            text-decoration: none;
        }

        .table a:hover {
            color: var(--ink);
            text-decoration: underline;
        }

        @media (max-width: 1199px) {
            #sidebarWrapper {
                transform: translateX(-100%);
                width: min(82vw, 280px);
            }

            #sidebarWrapper.mobile-open {
                transform: translateX(0);
            }

            #sidebarWrapper .sidebar {
                width: 100%;
            }

            .main,
            .main.sidebar-collapsed {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .main {
                padding: 20px 12px 40px;
            }

            .report-shell {
                padding: 16px 12px;
            }

            .report-header {
                align-items: flex-start;
                flex-direction: column;
                margin: -16px -12px 16px;
                padding: 20px;
            }

            .report-actions {
                justify-content: flex-start;
                width: 100%;
            }

            .nav-tabs {
                flex-wrap: nowrap;
                margin-right: -12px;
                overflow-x: auto;
                padding-bottom: 2px;
                scrollbar-width: thin;
            }

            .nav-tabs .nav-item {
                flex: 0 0 auto;
            }
        }

        @media (max-width: 520px) {
            body {
                padding-top: 64px;
            }

            .site-letterhead {
                height: 64px;
                padding: 0 12px;
            }

            #sidebarWrapper {
                height: calc(100vh - 64px);
                top: 64px;
            }

            .site-letterhead-brand {
                font-size: 16px;
            }

            .site-letterhead-brand img {
                height: 32px;
                margin-right: 7px;
            }

            .site-letterhead-logout {
                font-size: 14px;
            }

            .report-title {
                gap: 9px;
            }

            .report-header h1 {
                font-size: 1.7rem;
            }

            .report-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .report-actions .btn,
            .search-row input {
                max-width: none;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="site-letterhead">
        <div class="site-letterhead-brand">
            <img src="../assets/img/logo.png" alt="Inua Premium Logo">
            <span>Inua Premium Services</span>
        </div>
        <a class="site-letterhead-logout" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </header>

    <div class="sidebar" id="sidebarWrapper">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <main class="main" id="mainContent">
        <div class="report-shell">
            <header class="report-header">
                <div class="report-title">
                    <button type="button" class="sidebar-toggle-btn" id="sidebarToggleMain" aria-label="Toggle navigation" aria-expanded="true">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1>Due Loans</h1>
                </div>
                <div class="report-actions">
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
            </header>

            <h2 class="section-title">All Clients with Due Loans</h2>

            <nav aria-label="Filter due loans by repayment day">
                <ul class="nav nav-tabs report-tabs">
                    <?php foreach ($days as $day): ?>
                        <?php $dayLabel = ($day === 'all') ? 'All Days' : $day; ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($selected_day === $day) ? 'active' : ''; ?>"
                               href="?day=<?= rawurlencode($day); ?>"
                               <?= ($selected_day === $day) ? 'aria-current="page"' : ''; ?>>
                                <?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="search-row">
                <label class="visually-hidden" for="searchInput">Search due loans</label>
                <input type="search" id="searchInput" placeholder="Search by borrower or phone..." class="form-control">
            </div>

            <div class="table-container">
                <table id="dueLoansTable" class="table table-bordered">
                    <thead>
                        <tr>
                            <th scope="col">Borrower</th>
                            <th scope="col">Phone Number</th>
                            <th scope="col">Loan ID</th>
                            <th scope="col">Total Amount (KSH)</th>
                            <th scope="col">Total Paid (KSH)</th>
                            <th scope="col">Loan Balance (KSH)</th>
                            <th scope="col">Due Amount (KSH)</th>
                            <th scope="col">Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($due_loan_rows)): ?>
                            <?php foreach ($due_loan_rows as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) $row['borrower_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars((string) $row['phone_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <a href="repayment_details.php?loanId=<?= (int) $row['loan_id']; ?>">
                                            <?= (int) $row['loan_id']; ?>
                                        </a>
                                    </td>
                                    <td><?= number_format((float) $row['total_disbursed'], 2); ?></td>
                                    <td><?= number_format((float) $row['total_paid'], 2); ?></td>
                                    <td><?= number_format((float) $row['loan_balance'], 2); ?></td>
                                    <td><?= number_format((float) $row['due_amount'], 2); ?></td>
                                    <td><?= htmlspecialchars((string) $row['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-row">
                                <td colspan="8" class="text-center">No clients with due loans found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleButton = document.getElementById('sidebarToggleMain');
            const sidebarWrapper = document.getElementById('sidebarWrapper');
            const mainContent = document.getElementById('mainContent');
            const searchInput = document.getElementById('searchInput');
            const table = document.getElementById('dueLoansTable');
            const mobileSidebar = window.matchMedia('(max-width: 1199px)');

            function updateSidebarMode() {
                sidebarWrapper.classList.remove('collapsed', 'mobile-open');
                mainContent.classList.remove('sidebar-collapsed');
                toggleButton.setAttribute('aria-expanded', String(!mobileSidebar.matches));
            }

            toggleButton.addEventListener('click', function () {
                if (mobileSidebar.matches) {
                    const isOpen = sidebarWrapper.classList.toggle('mobile-open');
                    toggleButton.setAttribute('aria-expanded', String(isOpen));
                    return;
                }

                const isCollapsed = sidebarWrapper.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed', isCollapsed);
                toggleButton.setAttribute('aria-expanded', String(!isCollapsed));
            });

            mobileSidebar.addEventListener('change', updateSidebarMode);

            searchInput.addEventListener('input', function () {
                const filter = this.value.toLowerCase().replace(/[^a-z0-9]/g, '');
                const rows = table.querySelectorAll('tbody tr:not(.empty-row)');

                rows.forEach(function (row) {
                    const matches = Array.from(row.cells).some(function (cell) {
                        return cell.textContent.toLowerCase().replace(/[^a-z0-9]/g, '').includes(filter);
                    });
                    row.hidden = !matches;
                });
            });
        });
    </script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
