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

$sql_overdue = "SELECT
                    borrowers.full_name AS borrower_name,
                    borrowers.mobile AS phone_number,
                    SUM(repayments.amount - repayments.paid) AS total_overdue
                FROM repayments
                INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id
                INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
                WHERE repayments.repayment_date < CURDATE()
                    AND (repayments.amount - repayments.paid) > 0
                    AND borrowers.loan_officer = ?
                    $day_filter
                GROUP BY borrowers.full_name, borrowers.mobile
                ORDER BY total_overdue DESC, borrowers.full_name";

$stmt_overdue = $conn->prepare($sql_overdue);
if (!$stmt_overdue) {
    throw new RuntimeException('Unable to prepare the overdue repayments query: ' . $conn->error);
}
if ($selected_day !== 'all') {
    $stmt_overdue->bind_param('ss', $email, $selected_day);
} else {
    $stmt_overdue->bind_param('s', $email);
}
$stmt_overdue->execute();
$result_overdue = $stmt_overdue->get_result();

$arrears_rows = [];
$total_overdue = 0.0;
while ($row = $result_overdue->fetch_assoc()) {
    $arrears_rows[] = $row;
    $total_overdue += (float) $row['total_overdue'];
}
$total_overdue_count = count($arrears_rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arrears List</title>
    <link href="../assets/img/logo.png" rel="icon">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
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

        .report-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
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
        }

        .report-actions .btn:hover {
            background: var(--teal);
            border-color: var(--teal);
            color: #fff;
        }

        .total-summary {
            background: #f7faf9;
            border: 1px solid var(--line);
            margin: 0;
            padding: 14px;
            text-align: center;
        }

        .nav-tabs {
            border-bottom: 1px solid var(--line);
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 16px 0 0;
        }

        .nav-tabs .nav-link {
            background: transparent;
            border: 1px solid var(--line);
            border-bottom: 0;
            border-radius: 0;
            color: var(--muted);
            white-space: nowrap;
        }

        .nav-tabs .nav-link.active {
            background: var(--teal);
            border-color: var(--teal);
            color: #fff;
        }

        .nav-tabs .nav-link:hover {
            border-color: var(--teal);
            color: var(--teal);
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

        .table-container {
            margin-top: 0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table {
            margin: 0;
            min-width: 620px;
        }

        .table th {
            background: #edf2f3;
            border-bottom: 2px solid var(--teal);
            color: #425460;
            font-size: .72rem;
            letter-spacing: .08em;
            padding: 14px 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .table td {
            border-color: #e6ecef;
            padding: 15px 12px;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: #f7faf9;
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

            .main {
                margin-left: 0;
            }

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
                align-items: stretch;
                flex-direction: column;
                gap: 16px;
                margin: -16px -12px 16px;
                padding: 20px;
            }

            .report-actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .report-actions .btn {
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
            .site-letterhead {
                height: 64px;
                padding: 0 12px;
            }

            body {
                padding-top: 64px;
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
                grid-template-columns: 1fr;
            }

            .total-summary {
                font-size: .95rem;
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
                    <h1>Arrears List</h1>
                </div>
                <div class="report-actions">
                    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                    <button type="button" id="downloadOverdueRepayments" class="btn btn-outline-danger">Download Arrears List</button>
                </div>
            </header>

            <p class="total-summary">
                <strong>Total Arrears:</strong> KSH <?= number_format($total_overdue, 2); ?>
                <span class="mx-2" aria-hidden="true">|</span>
                <strong>Clients in Arrears:</strong> <?= number_format($total_overdue_count); ?>
            </p>

            <nav aria-label="Filter arrears by repayment day">
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
                <label class="visually-hidden" for="searchInput">Search arrears</label>
                <input type="search" id="searchInput" class="form-control" placeholder="Search by borrower or phone...">
            </div>

            <h2 class="section-title">Arrears List</h2>
            <div class="table-container">
                <table id="overdueRepaymentsTable" class="table table-bordered">
                    <thead>
                        <tr>
                            <th scope="col">Borrower</th>
                            <th scope="col">Phone Number</th>
                            <th scope="col" class="text-end">Total Overdue Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($arrears_rows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $row['borrower_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) $row['phone_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-end">KSH <?= number_format((float) $row['total_overdue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($total_overdue_count === 0): ?>
                            <tr class="empty-row"><td colspan="3">No arrears found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="text-center mt-4 mb-3">
        <p><em>Powered by AntonTech</em></p>
    </footer>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggleMain');
            const sidebar = document.getElementById('sidebarWrapper');
            const mainContent = document.getElementById('mainContent');
            const searchInput = document.getElementById('searchInput');
            const table = document.getElementById('overdueRepaymentsTable');
            const mobileSidebar = window.matchMedia('(max-width: 1199px)');

            function setSidebarMode() {
                sidebar.classList.remove('collapsed', 'mobile-open');
                mainContent.classList.remove('sidebar-collapsed');
                sidebarToggle.setAttribute('aria-expanded', mobileSidebar.matches ? 'false' : 'true');
            }

            sidebarToggle.addEventListener('click', function () {
                if (mobileSidebar.matches) {
                    const isOpen = sidebar.classList.toggle('mobile-open');
                    sidebarToggle.setAttribute('aria-expanded', String(isOpen));
                    return;
                }

                const isCollapsed = sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed', isCollapsed);
                sidebarToggle.setAttribute('aria-expanded', String(!isCollapsed));
            });

            mobileSidebar.addEventListener('change', setSidebarMode);

            function normalizeSearchText(text) {
                return text.toLowerCase().replace(/[^a-z0-9]/g, '');
            }

            searchInput.addEventListener('input', function () {
                const filter = normalizeSearchText(searchInput.value);
                const rows = table.querySelectorAll('tbody tr:not(.empty-row)');
                rows.forEach(function (row) {
                    const rowText = normalizeSearchText(row.textContent);
                    row.hidden = !rowText.includes(filter);
                });
            });

            document.getElementById('downloadOverdueRepayments').addEventListener('click', function () {
                if (!window.jspdf || typeof window.jspdf.jsPDF !== 'function') {
                    window.alert('The PDF export library could not be loaded. Please try again later.');
                    return;
                }

                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                if (typeof doc.autoTable !== 'function') {
                    window.alert('The PDF table plugin could not be loaded. Please try again later.');
                    return;
                }

                const pageWidth = doc.internal.pageSize.getWidth();
                const logo = new Image();
                logo.src = '../assets/img/logo.png';

                function savePdf(includeLogo) {
                    if (includeLogo) {
                        doc.addImage(logo, 'PNG', (pageWidth - 40) / 2, 10, 40, 25);
                    }

                    const headingY = includeLogo ? 42 : 18;
                    doc.setFontSize(11);
                    doc.text('Loan Officer: ' + <?= json_encode($email, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, pageWidth / 2, headingY, { align: 'center' });
                    doc.setFontSize(18);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Arrears List', pageWidth / 2, headingY + 10, { align: 'center' });

                    const summaryY = headingY + 22;
                    doc.setFontSize(11);
                    doc.setFont('helvetica', 'normal');
                    doc.text('Total Arrears: KSH <?= number_format($total_overdue, 2); ?>', 10, summaryY);
                    doc.text('Clients in Arrears: <?= $total_overdue_count; ?>', 10, summaryY + 7);

                    doc.autoTable({
                        html: '#overdueRepaymentsTable',
                        startY: summaryY + 14,
                        margin: { left: 10, right: 10 },
                        headStyles: { fillColor: [23, 35, 49], textColor: [255, 255, 255] },
                        bodyStyles: { fontSize: 10 },
                        styles: { overflow: 'linebreak' },
                        didParseCell: function (data) {
                            if (data.section === 'body' && data.column.index === 2) {
                                data.cell.styles.halign = 'right';
                            }
                        }
                    });

                    doc.setFontSize(9);
                    doc.setFont('helvetica', 'italic');
                    doc.setTextColor(135, 206, 235);
                    doc.text('Powered by AntonTech', pageWidth / 2, doc.internal.pageSize.getHeight() - 10, { align: 'center' });
                    doc.save('Arrears_List.pdf');
                }

                logo.onload = function () {
                    savePdf(true);
                };
                logo.onerror = function () {
                    savePdf(false);
                };
            });
        });
    </script>
</body>
</html>
