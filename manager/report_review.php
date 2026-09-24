<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__) . '/admin/TCPDF/tcpdf.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

function reviewSafe($value) {
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function reviewManagers($conn) {
    $managers = [];
    $result = $conn->query("SELECT u.id, u.name, u.email, COALESCE(r.name, 'Manager') AS role_name
                            FROM users u LEFT JOIN roles r ON r.id = u.role_id
                            WHERE u.email IS NOT NULL AND u.email <> ''
                              AND (LOWER(COALESCE(r.name, '')) LIKE '%manager%' OR u.role_id IN (3, 4))
                            ORDER BY u.name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $managers[] = $row;
        }
    }
    return $managers;
}

function reviewPeriod($period) {
    $today = date('Y-m-d');
    if ($period === 'annual') {
        $start = date('Y-01-01');
        $end = date('Y-12-31');
        $label = 'Annual review ' . date('Y');
        $factor = 1;
    } elseif ($period === 'monthly') {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        $label = 'Monthly review - ' . date('F Y');
        $factor = 12;
    } else {
        $start = date('Y-m-d', strtotime('monday this week'));
        $end = date('Y-m-d', strtotime($start . ' +6 days'));
        $label = 'Weekly review - ' . date('d M Y', strtotime($start)) . ' to ' . date('d M Y', strtotime($end));
        $period = 'weekly';
        $factor = 52;
    }
    return [
        'start' => $start,
        'end' => $end,
        'as_of' => min($end, $today),
        'label' => $label,
        'factor' => $factor,
        'period' => $period ?? $period,
    ];
}

function reviewRank($rows, $metric, $descending = true) {
    $sorted = $rows;
    usort($sorted, function ($left, $right) use ($metric, $descending) {
        $comparison = ((float) ($left[$metric] ?? 0)) <=> ((float) ($right[$metric] ?? 0));
        return $descending ? -$comparison : $comparison;
    });
    $ranks = [];
    foreach ($sorted as $index => $row) {
        $ranks[$row['name']] = $index + 1;
    }
    return $ranks;
}

function generateReportReviewSpeechPdf($metrics, $officers, $periodLabel, $periodType, $projection, $managerName) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor($managerName);
    $pdf->SetTitle('Portfolio Report Review Speech - ' . $periodLabel);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(false, 15);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $safe = function ($value) { return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8'); };
    $money = function ($value) { return 'KES ' . number_format((float) $value, 2); };
    $header = function ($title, $subtitle = 'Management Report Review') use ($pdf, $safe, $managerName) {
        $logoPath = dirname(__DIR__) . '/assets/img/logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 150, 10, 40, 0, 'PNG');
        }
        $pdf->SetDrawColor(0, 47, 196);
        $pdf->SetLineWidth(1.2);
        $pdf->Line(15, 13, 145, 13);
        $pdf->SetDrawColor(210, 0, 0);
        $pdf->SetLineWidth(0.6);
        $pdf->Line(15, 15, 145, 15);
        $pdf->SetTextColor(0, 47, 196);
        $pdf->SetFont('helvetica', 'B', 17);
        $pdf->Cell(0, 9, 'INUA PREMIUM SERVICES', 0, 1, 'L');
        $pdf->SetTextColor(45, 45, 45);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell(0, 5, $subtitle, 0, 1, 'L');
        $pdf->Cell(0, 5, 'Prepared by: ' . $safe($managerName) . ' | Generated: ' . date('d/m/Y H:i'), 0, 1, 'L');
        $pdf->Ln(4);
        $pdf->SetTextColor(210, 0, 0);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 8, $title, 0, 1, 'C');
        $pdf->SetTextColor(45, 45, 45);
        $pdf->Ln(2);
    };
    $section = function ($title) use ($pdf) {
        $pdf->SetFillColor(11, 47, 159);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->Cell(0, 7, $title, 1, 1, 'L', true);
        $pdf->SetTextColor(45, 45, 45);
        $pdf->Ln(2);
    };
    $table = function ($html) use ($pdf) {
        $pdf->SetFont('helvetica', '', 7.7);
        $pdf->writeHTML($html, true, false, true, false, 'L');
    };
    $footer = function ($page) use ($pdf) {
        $pdf->SetDrawColor(160, 160, 160);
        $pdf->Line(15, 276, 195, 276);
        $pdf->SetFont('helvetica', 'I', 7.2);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetXY(15, 278);
        $pdf->Cell(180, 4, 'Confidential management speech | Report review | Page ' . $page . ' of 20', 0, 0, 'C');
    };
    $metricTable = function ($title, $items) use ($section, $table, $money) {
        $section($title);
        $rows = '';
        foreach ($items as $item) {
            $value = $item[2] ? $money($item[1]) : number_format((float) $item[1], 2) . ($item[3] ?? '');
            $rows .= '<tr><td width="42%"><b>' . htmlspecialchars($item[0], ENT_QUOTES, 'UTF-8') . '</b></td><td width="23%">' . $value . '</td><td width="35%">' . htmlspecialchars($item[4], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><th width="42%">Metric</th><th width="23%">Value</th><th width="35%">Review focus</th></tr>' . $rows . '</table>');
    };
    $speech = function ($text) use ($pdf) {
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 5, $text, 0, 'L');
    };

    $pages = [];
    $pages[] = function () use ($header, $speech, $periodLabel, $periodType, $managerName, $footer) {
        $header('PORTFOLIO REPORT REVIEW SPEECH', 'Management Information and Performance Review');
        $speech('Prepared for ' . $managerName . '. This twenty-page speech document supports the ' . $periodType . ' report review for ' . $periodLabel . '. It presents the portfolio position, customer risk, officer ranking, projections, and decisions requested from management.');
        $speech('Purpose: to create one disciplined conversation around capital deployed, portfolio quality, customer outcomes, accountability, and the actions required for the next review cycle.');
        $footer(1);
    };
    $pages[] = function () use ($header, $section, $table, $metrics, $money, $speech, $footer) {
        $header('1. EXECUTIVE REVIEW');
        $section('Opening position');
        $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><th width="48%">Indicator</th><th width="22%">Result</th><th width="30%">Management meaning</th></tr><tr><td>Principal amount</td><td>' . $money($metrics['principal']) . '</td><td>Capital deployed.</td></tr><tr><td>Loan book</td><td>' . $money($metrics['loanBook']) . '</td><td>Outstanding exposure.</td></tr><tr><td>Performing book</td><td>' . $money($metrics['performingBook']) . '</td><td>Healthy outstanding exposure.</td></tr><tr><td>Arrears / PAR</td><td>' . $money($metrics['arrears']) . ' / ' . number_format($metrics['par'], 2) . '%</td><td>Portfolio quality signal.</td></tr></table>');
        $speech('The central message is that growth must be read together with quality. A larger book is valuable only when the performing book is protected and arrears remain controlled.');
        $footer(2);
    };
    $pages[] = function () use ($header, $section, $table, $metrics, $footer) {
        $header('2. REVIEW SCOPE AND METHOD');
        $section('How this review is measured');
        $table('<table border="1" cellpadding="4"><tr><td width="38%"><b>Approved loans</b></td><td>Only loan applications with status approved are included.</td></tr><tr><td><b>Loan book</b></td><td>Approved total amount less recorded repayments.</td></tr><tr><td><b>Performing book</b></td><td>Loan book less arrears, floored at zero.</td></tr><tr><td><b>PAR</b></td><td>Arrears divided by loan book.</td></tr><tr><td><b>Customer measures</b></td><td>Active borrowers and active borrowers with overdue balances.</td></tr></table>');
        $footer(3);
    };
    $pages[] = function () use ($header, $metricTable, $metrics, $footer) { $header('3. PRINCIPAL AMOUNT'); $metricTable('Capital deployment', [['Principal amount', $metrics['principal'], true, '', 'Assess deployment against approved plans.'], ['Number of approved loans', $metrics['loanCount'], false, '', 'Check conversion and credit discipline.'], ['Loan officers included', $metrics['officers'], false, '', 'Confirm coverage and accountability.']]); $footer(4); };
    $pages[] = function () use ($header, $metricTable, $metrics, $footer) { $header('4. LOAN BOOK'); $metricTable('Outstanding exposure', [['Loan book', $metrics['loanBook'], true, '', 'Protect repayment quality.'], ['Principal amount', $metrics['principal'], true, '', 'Compare deployment with outstanding exposure.'], ['Book retention ratio', $metrics['principal'] > 0 ? ($metrics['loanBook'] / $metrics['principal']) * 100 : 0, false, '%', 'Monitor the amount still outstanding.']]); $footer(5); };
    $pages[] = function () use ($header, $metricTable, $metrics, $footer) { $header('5. PERFORMING BOOK'); $metricTable('Healthy portfolio position', [['Performing book', $metrics['performingBook'], true, '', 'Prioritize retention and timely repayment.'], ['Performing share', $metrics['loanBook'] > 0 ? ($metrics['performingBook'] / $metrics['loanBook']) * 100 : 0, false, '%', 'Measure quality of the outstanding book.'], ['Arrears value', $metrics['arrears'], true, '', 'Assign recovery ownership.']]); $footer(6); };
    $pages[] = function () use ($header, $metricTable, $metrics, $footer) { $header('6. ARREARS'); $metricTable('Collections position', [['Arrears', $metrics['arrears'], true, '', 'Segment by age and amount.'], ['Customers in arrears', $metrics['customersInArrears'], false, '', 'Create named customer action plans.'], ['Customer arrears percentage', $metrics['customers'] > 0 ? ($metrics['customersInArrears'] / $metrics['customers']) * 100 : 0, false, '%', 'Track movement every review.']]); $footer(7); };
    $pages[] = function () use ($header, $metricTable, $metrics, $footer) { $header('7. PORTFOLIO AT RISK'); $metricTable('PAR position', [['PAR', $metrics['par'], false, '%', 'Compare against approved tolerance.'], ['Loan book', $metrics['loanBook'], true, '', 'Use as the exposure denominator.'], ['Performing book', $metrics['performingBook'], true, '', 'Protect through early intervention.']]); $footer(8); };
    $pages[] = function () use ($header, $metricTable, $metrics, $footer) { $header('8. CUSTOMER BASE'); $metricTable('Customer outcomes', [['Number of customers', $metrics['customers'], false, '', 'Review active service coverage.'], ['Customers in arrears', $metrics['customersInArrears'], false, '', 'Prioritize contact and restructuring.'], ['Percentage of customers in arrears', $metrics['customers'] > 0 ? ($metrics['customersInArrears'] / $metrics['customers']) * 100 : 0, false, '%', 'Improve repayment education and follow-up.']]); $footer(9); };
    $pages[] = function () use ($header, $section, $table, $metrics, $footer) { $header('9. CUSTOMER RISK DISCUSSION'); $section('Questions for management'); $table('<table border="1" cellpadding="5"><tr><td>1</td><td>Which customer segments account for the largest arrears?</td></tr><tr><td>2</td><td>Are customers in arrears receiving documented follow-up?</td></tr><tr><td>3</td><td>What early-warning indicators should be escalated?</td></tr><tr><td>4</td><td>How can service quality protect the performing book?</td></tr></table>'); $footer(10); };
    $pages[] = function () use ($header, $section, $table, $officers, $footer) { $header('10. COMPOSITE OFFICER RANKING'); $section('Overall ranking from all eight metrics'); $rows = ''; foreach ($officers as $index => $officer) { $rows .= '<tr><td>' . ($index + 1) . '</td><td>' . htmlspecialchars($officer['name'], ENT_QUOTES, 'UTF-8') . '</td><td>' . number_format($officer['score'], 2) . '</td><td>' . $officer['rank_principal'] . '</td><td>' . $officer['rank_performingBook'] . '</td><td>' . $officer['rank_loanBook'] . '</td><td>' . $officer['rank_arrears'] . '</td><td>' . $officer['rank_par'] . '</td><td>' . $officer['rank_customers'] . '</td><td>' . $officer['rank_customersInArrears'] . '</td><td>' . $officer['rank_customersInArrearsPercentage'] . '</td></tr>'; } $table('<table border="1" cellpadding="3"><tr bgcolor="#e8edff"><th>Rank</th><th>Officer</th><th>Score</th><th>P</th><th>PB</th><th>LB</th><th>A</th><th>PAR</th><th>C</th><th>CA</th><th>CA%</th></tr>' . $rows . '</table>'); $footer(11); };
    $pages[] = function () use ($header, $section, $table, $officers, $money, $footer) { $header('11. POSITIVE PERFORMANCE COMPARISON'); $section('Higher is stronger for these measures'); $rows = ''; foreach ($officers as $officer) { $rows .= '<tr><td>' . htmlspecialchars($officer['name'], ENT_QUOTES, 'UTF-8') . '</td><td>' . $money($officer['principal']) . '</td><td>' . $money($officer['performingBook']) . '</td><td>' . $money($officer['loanBook']) . '</td><td>' . $officer['customers'] . '</td></tr>'; } $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><th>Officer</th><th>Principal</th><th>Performing book</th><th>Loan book</th><th>Customers</th></tr>' . $rows . '</table>'); $footer(12); };
    $pages[] = function () use ($header, $section, $table, $officers, $money, $footer) { $header('12. RISK PERFORMANCE COMPARISON'); $section('Lower is stronger for these measures'); $rows = ''; foreach ($officers as $officer) { $rows .= '<tr><td>' . htmlspecialchars($officer['name'], ENT_QUOTES, 'UTF-8') . '</td><td>' . $money($officer['arrears']) . '</td><td>' . number_format($officer['par'], 2) . '%</td><td>' . $officer['customersInArrears'] . '</td><td>' . number_format($officer['customersInArrearsPercentage'], 2) . '%</td></tr>'; } $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><th>Officer</th><th>Arrears</th><th>PAR</th><th>Customers in arrears</th><th>Customers in arrears %</th></tr>' . $rows . '</table>'); $footer(13); };
    $pages[] = function () use ($header, $section, $table, $projection, $money, $periodLabel, $footer) { $header('13. PROJECTIONS'); $section('Annualized management view'); $table('<table border="1" cellpadding="5"><tr bgcolor="#e8edff"><th>Metric</th><th>Current period</th><th>Projected annual</th></tr><tr><td>Principal amount</td><td>' . $money($projection['currentPrincipal']) . '</td><td>' . $money($projection['principal']) . '</td></tr><tr><td>Performing book</td><td>' . $money($projection['currentPerformingBook']) . '</td><td>' . $money($projection['performingBook']) . '</td></tr><tr><td>Loan book</td><td>' . $money($projection['currentLoanBook']) . '</td><td>' . $money($projection['loanBook']) . '</td></tr><tr><td>Arrears</td><td>' . $money($projection['currentArrears']) . '</td><td>' . $money($projection['arrears']) . '</td></tr><tr><td>Customers</td><td>' . number_format($projection['currentCustomers']) . '</td><td>' . number_format($projection['customers']) . '</td></tr></table>'); $footer(14); };
    $pages[] = function () use ($header, $section, $table, $projection, $footer) { $header('14. PROJECTED RISK RATIOS'); $section('Ratios held constant for planning'); $table('<table border="1" cellpadding="5"><tr bgcolor="#e8edff"><th>Ratio</th><th>Current</th><th>Projection</th><th>Planning note</th></tr><tr><td>PAR</td><td>' . number_format($projection['currentPar'], 2) . '%</td><td>' . number_format($projection['par'], 2) . '%</td><td>Requires quality controls.</td></tr><tr><td>Customers in arrears %</td><td>' . number_format($projection['currentCustomerArrearsPct'], 2) . '%</td><td>' . number_format($projection['customerArrearsPct'], 2) . '%</td><td>Requires collections action.</td></tr></table>'); $footer(15); };
    $pages[] = function () use ($header, $section, $table, $officers, $footer) { $header('15. OFFICER ACTION PLAN'); $section('Targeted accountability'); $rows = ''; foreach (array_slice($officers, 0, 10) as $index => $officer) { $rows .= '<tr><td>' . ($index + 1) . '</td><td>' . htmlspecialchars($officer['name'], ENT_QUOTES, 'UTF-8') . '</td><td>' . ($officer['par'] > 0 ? 'Review arrears and PAR exposures' : 'Protect performing book and grow quality pipeline') . '</td><td>Before next review</td></tr>'; } $table('<table border="1" cellpadding="4"><tr bgcolor="#e8edff"><th>Rank</th><th>Officer</th><th>Required focus</th><th>Due</th></tr>' . $rows . '</table>'); $footer(16); };
    $pages[] = function () use ($header, $speech, $footer) { $header('16. SPEECH: ACCOUNTABILITY'); $speech('Colleagues, this review is an accountability conversation. Each officer is expected to understand not only the size of the portfolio assigned, but also the quality of that portfolio, the customers behind it, and the actions needed to keep the book performing. Rankings are a management tool for support and improvement, not a substitute for evidence or coaching.'); $footer(17); };
    $pages[] = function () use ($header, $speech, $footer) { $header('17. SPEECH: CUSTOMER AND RISK'); $speech('Our customers remain at the centre of the review. Arrears and PAR tell us where the repayment experience is breaking down. We must combine respectful customer contact, accurate records, realistic action plans, and prompt escalation. A performing book is built through daily discipline, not through reporting at the end of a period.'); $footer(18); };
    $pages[] = function () use ($header, $speech, $footer) { $header('18. SPEECH: GROWTH AND PROJECTIONS'); $speech('The projections in this pack are planning signals, not promises. They show what the current period would imply if the observed pace continued. Management should use them to test staffing, capital, collections capacity, and service readiness. Growth should be approved only when the projected risk profile remains acceptable.'); $footer(19); };
    $pages[] = function () use ($header, $section, $table, $footer) { $header('19. RESOLUTIONS AND SIGN-OFF'); $section('Decisions requested'); $table('<table border="1" cellpadding="5"><tr><td>1</td><td>Confirm the priority officers and customer accounts for follow-up.</td></tr><tr><td>2</td><td>Approve owners and deadlines for arrears and PAR reduction.</td></tr><tr><td>3</td><td>Confirm the next weekly, monthly, and annual review dates.</td></tr><tr><td>4</td><td>Record any data corrections required before the next pack.</td></tr></table>'); $footer(20); };

    foreach ($pages as $page) {
        $pdf->AddPage();
        $page();
    }
    return $pdf->Output('', 'S');
}

function sendReportReviewEmail($recipient, $pdfContent, $filename, $periodLabel) {
    $credentials = getEmailAccount();
    if (empty($credentials['sender_email']) || empty($credentials['sender_app_password'])) {
        throw new Exception('Email settings are not configured.');
    }
    $mail = new PHPMailer(true);
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth = true;
    $mail->Username = $credentials['sender_email'];
    $mail->Password = $credentials['sender_app_password'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($credentials['sender_email'], 'Inua Premium Services');
    $mail->addAddress($recipient['email'], $recipient['name']);
    $mail->isHTML(true);
    $mail->Subject = 'Portfolio Report Review - ' . $periodLabel;
    $mail->Body = '<p>Dear ' . htmlspecialchars($recipient['name'], ENT_QUOTES, 'UTF-8') . ',</p><p>Attached is the twenty-page portfolio report review speech for <strong>' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '</strong>.</p><p>The document covers principal, performing book, loan book, arrears, PAR, customer outcomes, officer rankings, comparisons, projections, action planning, and sign-off.</p><p>Regards,<br>Inua Premium Services</p>';
    $mail->AltBody = 'Attached is the twenty-page portfolio report review speech for ' . $periodLabel . '.';
    $mail->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
    $mail->send();
}

$reviewType = in_array($_REQUEST['review_period'] ?? 'weekly', ['weekly', 'monthly', 'annual'], true) ? $_REQUEST['review_period'] : 'weekly';
$period = reviewPeriod($reviewType);
$conn->query("CREATE TABLE IF NOT EXISTS loan_officer_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NULL,
    template_name VARCHAR(100) NOT NULL,
    expense_type VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    loan_officer_id INT NOT NULL,
    loan_officer_name VARCHAR(100) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'cash',
    recorded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_loan_officer (loan_officer_id),
    KEY idx_expense_date (expense_date)
)");

$officers = [];
$officerResult = $conn->query("SELECT id, name, email FROM users WHERE role_id = '2' ORDER BY name ASC");
$loanSql = "SELECT l.id, l.borrower, l.principal, l.total_amount,
                   COALESCE(SUM(r.paid), 0) AS total_paid,
                   COALESCE((SELECT SUM(GREATEST(COALESCE(r2.amount, 0) - COALESCE(r2.paid, 0), 0))
                             FROM repayments r2
                             WHERE r2.loan_id = l.id AND r2.repayment_date <= ? AND r2.repayment_date < CURDATE()), 0) AS arrears
            FROM loan_applications l
            INNER JOIN borrowers b ON b.id = l.borrower
            LEFT JOIN repayments r ON r.loan_id = l.id
            WHERE l.loan_status = 'approved' AND b.loan_officer = ?
              AND l.loan_release_date BETWEEN ? AND ?
            GROUP BY l.id, l.borrower, l.principal, l.total_amount";
while ($officer = $officerResult ? $officerResult->fetch_assoc() : null) {
    $loanStmt = $conn->prepare($loanSql);
    $loanStmt->bind_param('ssss', $period['as_of'], $officer['email'], $period['start'], $period['end']);
    $loanStmt->execute();
    $loanRows = $loanStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $borrowers = [];
    $principal = 0.0;
    $loanBook = 0.0;
    $arrears = 0.0;
    foreach ($loanRows as $loan) {
        $outstanding = max(0.0, (float) $loan['total_amount'] - (float) $loan['total_paid']);
        $principal += (float) $loan['principal'];
        $loanBook += $outstanding;
        $arrears += (float) $loan['arrears'];
        $borrowerId = (int) $loan['borrower'];
        if (!isset($borrowers[$borrowerId])) {
            $borrowers[$borrowerId] = ['balance' => 0.0, 'arrears' => 0.0];
        }
        $borrowers[$borrowerId]['balance'] += $outstanding;
        $borrowers[$borrowerId]['arrears'] += (float) $loan['arrears'];
    }
    $customers = 0;
    $customersInArrears = 0;
    foreach ($borrowers as $borrower) {
        if ($borrower['balance'] > 0) {
            $customers++;
            if ($borrower['arrears'] > 0) {
                $customersInArrears++;
            }
        }
    }
    $performingBook = max(0.0, $loanBook - $arrears);
    $par = $loanBook > 0 ? ($arrears / $loanBook) * 100 : 0.0;
    $customersInArrearsPercentage = $customers > 0 ? ($customersInArrears / $customers) * 100 : 0.0;
    $officers[] = [
        'name' => $officer['name'],
        'principal' => round($principal, 2),
        'performingBook' => round($performingBook, 2),
        'loanBook' => round($loanBook, 2),
        'arrears' => round($arrears, 2),
        'par' => round($par, 2),
        'customers' => $customers,
        'customersInArrears' => $customersInArrears,
        'customersInArrearsPercentage' => round($customersInArrearsPercentage, 2),
        'loanCount' => count($loanRows),
    ];
}

$metricNames = ['principal', 'performingBook', 'loanBook', 'arrears', 'par', 'customers', 'customersInArrears', 'customersInArrearsPercentage'];
$positiveMetrics = ['principal', 'performingBook', 'loanBook', 'customers'];
$ranks = [];
foreach ($metricNames as $metric) {
    $ranks[$metric] = reviewRank($officers, $metric, in_array($metric, $positiveMetrics, true));
}
foreach ($officers as &$officer) {
    $score = 0.0;
    foreach ($metricNames as $metric) {
        $max = 0.0;
        foreach ($officers as $candidate) {
            $max = max($max, (float) $candidate[$metric]);
        }
        $value = (float) $officer[$metric];
        $normalized = in_array($metric, $positiveMetrics, true) ? ($max > 0 ? $value / $max : 0) : ($max > 0 ? 1 - ($value / $max) : 1);
        $score += $normalized;
        $officer['rank_' . $metric] = $ranks[$metric][$officer['name']] ?? count($officers);
    }
    $officer['score'] = round(($score / count($metricNames)) * 100, 2);
}
unset($officer);
usort($officers, fn ($left, $right) => $right['score'] <=> $left['score']);

$metrics = [
    'principal' => array_sum(array_column($officers, 'principal')),
    'performingBook' => array_sum(array_column($officers, 'performingBook')),
    'loanBook' => array_sum(array_column($officers, 'loanBook')),
    'arrears' => array_sum(array_column($officers, 'arrears')),
    'customers' => array_sum(array_column($officers, 'customers')),
    'customersInArrears' => array_sum(array_column($officers, 'customersInArrears')),
    'loanCount' => array_sum(array_column($officers, 'loanCount')),
    'officers' => count($officers),
];
$metrics['par'] = $metrics['loanBook'] > 0 ? ($metrics['arrears'] / $metrics['loanBook']) * 100 : 0;
$metrics['customerArrearsPct'] = $metrics['customers'] > 0 ? ($metrics['customersInArrears'] / $metrics['customers']) * 100 : 0;
$projection = [
    'currentPrincipal' => $metrics['principal'], 'currentPerformingBook' => $metrics['performingBook'], 'currentLoanBook' => $metrics['loanBook'], 'currentArrears' => $metrics['arrears'], 'currentCustomers' => $metrics['customers'], 'currentPar' => $metrics['par'], 'currentCustomerArrearsPct' => $metrics['customerArrearsPct'],
    'principal' => $metrics['principal'] * $period['factor'], 'performingBook' => $metrics['performingBook'] * $period['factor'], 'loanBook' => $metrics['loanBook'] * $period['factor'], 'arrears' => $metrics['arrears'] * $period['factor'], 'customers' => $metrics['customers'] * $period['factor'], 'par' => $metrics['par'], 'customerArrearsPct' => $metrics['customerArrearsPct'],
];

$managers = reviewManagers($conn);
$sendMessage = '';
$sendStatus = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_report_review'])) {
    $managerId = (int) ($_POST['manager_id'] ?? 0);
    $selectedManager = null;
    foreach ($managers as $manager) {
        if ((int) $manager['id'] === $managerId) {
            $selectedManager = $manager;
            break;
        }
    }
    if (!$selectedManager || !filter_var($selectedManager['email'], FILTER_VALIDATE_EMAIL)) {
        $sendMessage = 'Select a manager with a valid email address.';
        $sendStatus = 'danger';
    } else {
        try {
            $pdfContent = generateReportReviewSpeechPdf($metrics, $officers, $period['label'], $reviewType, $projection, $_SESSION['email'] ?? 'Management');
            sendReportReviewEmail($selectedManager, $pdfContent, 'portfolio_report_review_' . date('Ymd_His') . '.pdf', $period['label']);
            $sendMessage = 'The twenty-page report review speech was sent to ' . $selectedManager['name'] . '.';
            $sendStatus = 'success';
        } catch (Exception $exception) {
            $sendMessage = 'The report review could not be sent: ' . $exception->getMessage();
            $sendStatus = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Review Dashboard</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --ink: #172331; --muted: #687582; --line: #dbe3e8; --paper: #fff; --canvas: #f2f5f6; --teal: #147d78; --gold: #c7973e; }
        body { background: linear-gradient(135deg, #f2f5f6, #eaf3f1); color: var(--ink); font-family: "Trebuchet MS", Arial, sans-serif; }
        .shell { margin: 30px auto 60px; max-width: 1400px; padding: 0 18px; }
        .hero, .panel, .metric { background: var(--paper); border: 1px solid var(--line); box-shadow: 0 12px 30px rgba(23,35,49,.06); }
        .hero { background: var(--ink); border-top: 5px solid var(--gold); color: #fff; padding: 28px 32px; }
        .hero h1 { font-family: Georgia, serif; font-weight: normal; margin: 0 0 8px; }
        .hero p { color: #c7d3d8; margin: 0; }
        .panel { margin-top: 20px; padding: 22px; }
        .panel h2 { border-bottom: 1px solid var(--line); font-family: Georgia, serif; font-size: 1.3rem; margin: 0 0 18px; padding-bottom: 12px; }
        .periods { display: flex; flex-wrap: wrap; gap: 8px; }
        .periods a, .btn-review { border: 1px solid var(--teal); color: var(--teal); padding: 9px 15px; text-decoration: none; }
        .periods a.active, .periods a:hover, .btn-review { background: var(--teal); color: #fff; }
        .metrics { display: grid; gap: 14px; grid-template-columns: repeat(4, 1fr); }
        .metric { border-top: 4px solid var(--teal); padding: 17px; }
        .metric:nth-child(2n) { border-top-color: var(--gold); }
        .metric span { color: var(--muted); display: block; font-size: .75rem; text-transform: uppercase; }
        .metric strong { display: block; font-family: Georgia, serif; font-size: 1.4rem; margin-top: 7px; }
        .table-responsive { overflow-x: auto; }
        .table { min-width: 1050px; }
        .table th { background: #edf2f3; color: #425460; font-size: .72rem; text-transform: uppercase; white-space: nowrap; }
        .table td, .table th { border-color: var(--line); vertical-align: middle; }
        @media (max-width: 900px) { .metrics { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 520px) { .metrics { grid-template-columns: 1fr; } .hero { padding: 22px; } }
    </style>
</head>
<body>
<div class="shell">
    <section class="hero">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div><div class="text-uppercase small">Management information</div><h1>Portfolio Report Review</h1><p><?php echo reviewSafe($period['label']); ?> | Approved loans only</p></div>
            <a href="report_analytics.php" class="btn btn-outline-light">Back to Analytics</a>
        </div>
    </section>
    <section class="panel">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div><h2>Review period</h2><p class="text-muted mb-0">Compare principal, quality, customer outcomes, and officer performance.</p></div>
            <div class="periods">
                <a class="<?php echo $reviewType === 'weekly' ? 'active' : ''; ?>" href="?review_period=weekly">Weekly Review</a>
                <a class="<?php echo $reviewType === 'monthly' ? 'active' : ''; ?>" href="?review_period=monthly">Monthly Review</a>
                <a class="<?php echo $reviewType === 'annual' ? 'active' : ''; ?>" href="?review_period=annual">Annual Review</a>
            </div>
        </div>
    </section>
    <?php if ($sendMessage !== ''): ?><div class="alert alert-<?php echo reviewSafe($sendStatus); ?> mt-3"><?php echo reviewSafe($sendMessage); ?></div><?php endif; ?>
    <section class="panel">
        <h2>Key metrics</h2>
        <div class="metrics">
            <div class="metric"><span>Principal amount</span><strong>KES <?php echo number_format($metrics['principal'], 2); ?></strong></div>
            <div class="metric"><span>Performing book</span><strong>KES <?php echo number_format($metrics['performingBook'], 2); ?></strong></div>
            <div class="metric"><span>Loan book</span><strong>KES <?php echo number_format($metrics['loanBook'], 2); ?></strong></div>
            <div class="metric"><span>Arrears</span><strong>KES <?php echo number_format($metrics['arrears'], 2); ?></strong></div>
            <div class="metric"><span>PAR</span><strong><?php echo number_format($metrics['par'], 2); ?>%</strong></div>
            <div class="metric"><span>Number of customers</span><strong><?php echo number_format($metrics['customers']); ?></strong></div>
            <div class="metric"><span>Customers in arrears</span><strong><?php echo number_format($metrics['customersInArrears']); ?></strong></div>
            <div class="metric"><span>% customers in arrears</span><strong><?php echo number_format($metrics['customerArrearsPct'], 2); ?>%</strong></div>
        </div>
    </section>
    <section class="panel">
        <h2>Annualized projections</h2>
        <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Metric</th><th>Current <?php echo reviewSafe($reviewType); ?></th><th>Projected annual</th></tr></thead><tbody>
            <tr><td>Principal amount</td><td>KES <?php echo number_format($projection['currentPrincipal'], 2); ?></td><td>KES <?php echo number_format($projection['principal'], 2); ?></td></tr>
            <tr><td>Performing book</td><td>KES <?php echo number_format($projection['currentPerformingBook'], 2); ?></td><td>KES <?php echo number_format($projection['performingBook'], 2); ?></td></tr>
            <tr><td>Loan book</td><td>KES <?php echo number_format($projection['currentLoanBook'], 2); ?></td><td>KES <?php echo number_format($projection['loanBook'], 2); ?></td></tr>
            <tr><td>Arrears</td><td>KES <?php echo number_format($projection['currentArrears'], 2); ?></td><td>KES <?php echo number_format($projection['arrears'], 2); ?></td></tr>
            <tr><td>PAR</td><td><?php echo number_format($projection['currentPar'], 2); ?>%</td><td><?php echo number_format($projection['par'], 2); ?>%</td></tr>
            <tr><td>Customers</td><td><?php echo number_format($projection['currentCustomers']); ?></td><td><?php echo number_format($projection['customers']); ?></td></tr>
            <tr><td>Customers in arrears %</td><td><?php echo number_format($projection['currentCustomerArrearsPct'], 2); ?>%</td><td><?php echo number_format($projection['customerArrearsPct'], 2); ?>%</td></tr>
        </tbody></table></div>
    </section>
    <section class="panel">
        <h2>Loan officer ranking and metric comparison</h2>
        <div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Overall rank</th><th>Officer</th><th>Score</th><th>Principal</th><th>P rank</th><th>Performing book</th><th>PB rank</th><th>Loan book</th><th>LB rank</th><th>Arrears</th><th>A rank</th><th>PAR</th><th>PAR rank</th><th>Customers</th><th>C rank</th><th>Customers in arrears</th><th>CA rank</th><th>Customers in arrears %</th><th>CA% rank</th></tr></thead><tbody>
            <?php foreach ($officers as $rank => $officer): ?><tr><td><strong><?php echo $rank + 1; ?></strong></td><td><?php echo reviewSafe($officer['name']); ?></td><td><?php echo number_format($officer['score'], 2); ?></td><td>KES <?php echo number_format($officer['principal'], 2); ?></td><td><?php echo $officer['rank_principal']; ?></td><td>KES <?php echo number_format($officer['performingBook'], 2); ?></td><td><?php echo $officer['rank_performingBook']; ?></td><td>KES <?php echo number_format($officer['loanBook'], 2); ?></td><td><?php echo $officer['rank_loanBook']; ?></td><td>KES <?php echo number_format($officer['arrears'], 2); ?></td><td><?php echo $officer['rank_arrears']; ?></td><td><?php echo number_format($officer['par'], 2); ?>%</td><td><?php echo $officer['rank_par']; ?></td><td><?php echo number_format($officer['customers']); ?></td><td><?php echo $officer['rank_customers']; ?></td><td><?php echo number_format($officer['customersInArrears']); ?></td><td><?php echo $officer['rank_customersInArrears']; ?></td><td><?php echo number_format($officer['customersInArrearsPercentage'], 2); ?>%</td><td><?php echo $officer['rank_customersInArrearsPercentage']; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <p class="text-muted small mt-3 mb-0">Composite score gives equal weight to all eight metrics. Higher is better for principal, performing book, loan book, and customers; lower is better for arrears, PAR, customers in arrears, and customer arrears percentage.</p>
    </section>
    <section class="panel">
        <h2>Send 20-page report review speech</h2>
        <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="review_period" value="<?php echo reviewSafe($reviewType); ?>">
            <div class="col-md-8"><label for="manager_id" class="form-label">Manager recipient</label><select name="manager_id" id="manager_id" class="form-select" required><option value="">Select manager</option><?php foreach ($managers as $manager): ?><option value="<?php echo (int) $manager['id']; ?>"><?php echo reviewSafe($manager['name'] . ' - ' . $manager['email']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><button class="btn-review w-100" type="submit" name="send_report_review">Send PDF to Manager</button></div>
        </form>
        <p class="text-muted small mt-3 mb-0">The PDF uses the branded form header structure and contains exactly 20 pages covering the metrics, projections, rankings, speech, action plan, and sign-off.</p>
    </section>
</div>
</body>
</html>
