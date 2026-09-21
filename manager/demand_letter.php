<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
include '../includes/functions.php';

if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__) . '/admin/TCPDF/tcpdf.php';

function fetchBorrowerLoanSummary($conn, $borrowerId) {
    $sql = "SELECT 
                la.id AS loan_id,
                la.principal,
                la.total_amount,
                la.loan_status,
                la.loan_release_date,
                la.loan_duration,
                la.loan_duration_unit,
                COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = la.id), 0) AS total_paid,
                GREATEST(COALESCE(la.total_amount, 0) - COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = la.id), 0), 0) AS outstanding_balance
            FROM loan_applications la
            WHERE la.borrower = ?
              AND la.loan_status IN ('approved', 'rolled_over')
            ORDER BY la.id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $borrowerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $totalBalance = 0.0;
    foreach ($rows as $row) {
        $totalBalance += (float) ($row['outstanding_balance'] ?? 0);
    }

    return [
        'rows' => $rows,
        'total_balance' => $totalBalance,
    ];
}

function fetchManagerProfile($conn) {
    $profile = [
        'name' => 'Inua Premium Services',
        'email' => 'info@inuapremium.co.ke',
        'phone' => 'N/A',
        'region' => 'Unassigned',
    ];

    $email = trim((string) ($_SESSION['email'] ?? ''));
    if ($email === '') {
        return $profile;
    }

    $stmt = $conn->prepare("SELECT u.name, u.email, u.phone, COALESCE(a.area_name, 'Unassigned') AS region_name
                            FROM users u
                            LEFT JOIN areas a ON u.area = a.area_id
                            WHERE u.email = ?
                            LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $profile['name'] = trim((string) ($row['name'] ?? '')) ?: $profile['name'];
            $profile['email'] = trim((string) ($row['email'] ?? '')) ?: $profile['email'];
            $profile['phone'] = trim((string) ($row['phone'] ?? '')) ?: $profile['phone'];
            $profile['region'] = trim((string) ($row['region_name'] ?? '')) ?: $profile['region'];
        }
    }

    return $profile;
}

function calculateLoanMaturityDate($loan) {
    $releaseDate = trim((string) ($loan['loan_release_date'] ?? ''));
    $duration = (int) ($loan['loan_duration'] ?? 0);
    $durationUnit = strtolower(trim((string) ($loan['loan_duration_unit'] ?? '')));

    if ($releaseDate === '' || $duration <= 0) {
        return 'Not available';
    }

    try {
        $maturityDate = new DateTime($releaseDate);
        if (strpos($durationUnit, 'week') !== false) {
            $maturityDate->modify('+' . ($duration * 7) . ' days');
        } elseif (strpos($durationUnit, 'day') !== false) {
            $maturityDate->modify('+' . $duration . ' days');
        } else {
            $maturityDate->modify('+' . $duration . ' months');
        }

        return $maturityDate->format('d F Y');
    } catch (Exception $e) {
        return 'Not available';
    }
}

function generateDemandLetterPdf($clientName, $phone, $idNumber, $balance, $date, $managerProfile, $loan = [], $loanId = null, $mpesaTill = '') {
    $reference = 'DL-' . date('Y') . '-' . str_pad((string) ($loanId > 0 ? $loanId : rand(1000, 9999)), 4, '0', STR_PAD_LEFT);
    $deadline = date('d F Y', strtotime('+5 days'));
    $maturityDate = calculateLoanMaturityDate($loan);

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor('Inua Premium Services');
    $pdf->SetTitle('Demand Letter');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

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
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 10, 'INUA PREMIUM SERVICES', 0, 1, 'L');
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'Demand and Recovery Department', 0, 1, 'L');
    $pdf->Cell(0, 5, 'Tel: ' . $managerProfile['phone'] . ' | Email: ' . $managerProfile['email'], 0, 1, 'L');
    $pdf->Cell(0, 5, 'Region: ' . $managerProfile['region'], 0, 1, 'L');
    $pdf->Ln(4);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, 'REFERENCE: ' . $reference, 0, 1, 'R');
    $pdf->Cell(0, 7, 'DATE: ' . $date, 0, 1, 'R');
    $pdf->Ln(6);

    $pdf->SetTextColor(210, 0, 0);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'FORMAL DEMAND LETTER', 0, 1, 'C');
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Ln(4);

    $safeClientName = htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8');
    $safeBalance = number_format((float) $balance, 2);
    $safeDeadline = htmlspecialchars($deadline, ENT_QUOTES, 'UTF-8');
    $safeMaturityDate = htmlspecialchars($maturityDate, ENT_QUOTES, 'UTF-8');
    $safeMpesaTill = htmlspecialchars($mpesaTill, ENT_QUOTES, 'UTF-8');

    $pdf->SetFont('helvetica', '', 11);
    $pdf->writeHTML('<div><b>SUBJECT:</b> Formal Demand for Payment of <b>Outstanding Loan Balance</b></div>', true, false, true, false, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML('<div><b>Loan Maturity Date:</b> ' . $safeMaturityDate . '</div>', true, false, true, false, 'L');
    $pdf->Ln(4);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->writeHTML('Dear ' . $safeClientName . ',', true, false, true, false, 'L');
    $pdf->Ln(2);

    $pdf->writeHTML('We write on behalf of Inua Premium Services regarding your <b>outstanding loan account</b>. Our records indicate that the account remains in <b>default</b> and that the amount currently due and payable is <b>KES ' . $safeBalance . '</b>.', true, false, true, false, 'L');
    $pdf->Ln(2);

    $pdf->writeHTML('Accordingly, this letter constitutes a <b>formal demand for payment</b>. You are required to settle the <b>full outstanding balance</b> on or before <b>' . $safeDeadline . '</b>. If you are unable to settle the amount in full, you must contact our office before this deadline to discuss an acceptable repayment arrangement.', true, false, true, false, 'L');
    $pdf->Ln(2);

    $pdf->writeHTML('<b>Payment Instructions:</b> Please make payment via M-Pesa Till Number <b>' . $safeMpesaTill . '</b>. this our official till number.Dont use any other payment mode.', true, false, true, false, 'L');
    $pdf->Ln(2);

    $pdf->writeHTML('<b>Failure to make payment</b> or agree to a repayment arrangement within the stated period may result in further recovery action and additional charges in accordance with the terms of your loan agreement and applicable law. This notice does not waive any rights or remedies available to Inua Premium Services.', true, false, true, false, 'L');
    $pdf->Ln(2);

    $pdf->writeHTML('Please treat this matter as <b>urgent</b> and contact our office using the details provided above should you require clarification or wish to make payment arrangements.', true, false, true, false, 'L');
    $pdf->Ln(8);

    $salutationY = $pdf->GetY();
    $pdf->MultiCell(0, 7, 'Yours faithfully,', 0, 'L');

    $stampPath = __DIR__ . '/New Folder/assets/img/company_stamp.JPG';
    if (file_exists($stampPath)) {
        $pdf->SetAlpha(0.45);
        $pdf->Image($stampPath, 130, $salutationY - 2, 45, 0, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
        $pdf->SetAlpha(1);
    }

    $signaturePath = __DIR__ . '/New Folder/assets/img/manager_signature.png';
    if (file_exists($signaturePath)) {
        $pdf->SetAlpha(0.9);
        $pdf->Image($signaturePath, 140, $salutationY + 9, 25, 0, 'PNG', '', '', true, 300, '', false, false, 0, false, false, false);
        $pdf->SetAlpha(1);
    }

    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->MultiCell(0, 7, $managerProfile['name'], 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(0, 6, 'Credit & Recovery Department', 0, 'L');
    $pdf->MultiCell(0, 6, 'Phone: ' . $managerProfile['phone'], 0, 'L');
    $pdf->MultiCell(0, 6, 'Region: ' . $managerProfile['region'], 0, 'L');
    $pdf->MultiCell(0, 6, 'Email: ' . $managerProfile['email'], 0, 'L');

    $pdf->Line(15, 255, 195, 255);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'This document is an official notice issued by Inua Premium Services.', 0, 1, 'C');

    return $pdf->Output('', 'S');
}

function sendDemandLetterEmail($recipientEmail, $subject, $body, $pdfContent, $filename) {
    $emailCredentials = getEmailAccount();
    if (empty($emailCredentials['sender_email']) || empty($emailCredentials['sender_app_password'])) {
        throw new Exception('Email settings are not configured.');
    }

    $mail = new PHPMailer(true);
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth = true;
    $mail->Username = $emailCredentials['sender_email'];
    $mail->Password = $emailCredentials['sender_app_password'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($emailCredentials['sender_email'], 'Inua Premium Services');
    $mail->addAddress($recipientEmail);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = '<p>Dear Client,</p><p>Please find attached the demand letter in PDF format.</p>';
    $mail->AltBody = $body;
    $mail->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
    $mail->send();
}

$borrower = null;
$loanSummary = ['rows' => [], 'total_balance' => 0.0];
$sendMessage = '';
$sendStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_demand_letter'])) {
    $borrowerId = (int) ($_POST['borrower_id'] ?? 0);
    $recipientType = $_POST['recipient_type'] ?? 'client';
    $recipientEmail = trim((string) ($_POST['recipient_email'] ?? ''));
    $mpesaTill = trim((string) ($_POST['mpesa_till'] ?? ''));

    if ($borrowerId <= 0) {
        $sendMessage = 'Please select a valid client first.';
        $sendStatus = 'danger';
    } elseif ($mpesaTill === '' || !preg_match('/^\d+$/', $mpesaTill)) {
        $sendMessage = 'Please enter a valid M-Pesa Till number.';
        $sendStatus = 'danger';
    } else {
        $borrowerStmt = $conn->prepare("SELECT id, full_name, mobile, unique_number, email, loan_officer FROM borrowers WHERE id = ? LIMIT 1");
        if ($borrowerStmt) {
            $borrowerStmt->bind_param('i', $borrowerId);
            $borrowerStmt->execute();
            $borrower = $borrowerStmt->get_result()->fetch_assoc();
            $borrowerStmt->close();
        }

        if (!$borrower) {
            $sendMessage = 'Client record not found.';
            $sendStatus = 'danger';
        } else {
            $loanSummary = fetchBorrowerLoanSummary($conn, $borrowerId);
            $totalBalance = (float) ($loanSummary['total_balance'] ?? 0);

            if ($totalBalance <= 0) {
                $sendMessage = 'This client does not have an outstanding defaulted balance to demand.';
                $sendStatus = 'warning';
            } else {
                $officerEmail = '';
                if (!empty($borrower['loan_officer'])) {
                    $officerStmt = $conn->prepare("SELECT email FROM users WHERE email = ? LIMIT 1");
                    if ($officerStmt) {
                        $officerStmt->bind_param('s', $borrower['loan_officer']);
                        $officerStmt->execute();
                        $officerRow = $officerStmt->get_result()->fetch_assoc();
                        if ($officerRow) {
                            $officerEmail = (string) ($officerRow['email'] ?? '');
                        }
                        $officerStmt->close();
                    }
                }

                if ($recipientType === 'loan_officer') {
                    $recipientEmail = $officerEmail ?: $recipientEmail;
                } else {
                    $recipientEmail = $recipientEmail ?: (string) ($borrower['email'] ?? '');
                }

                if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                    $sendMessage = 'Please provide a valid email address for the selected recipient.';
                    $sendStatus = 'danger';
                } else {
                    $date = date('d F Y');
                    $clientName = (string) ($borrower['full_name'] ?? 'Client');
                    $phone = (string) ($borrower['mobile'] ?? 'N/A');
                    $idNumber = (string) ($borrower['unique_number'] ?? 'N/A');
                    $balance = $totalBalance;

                    $plainBody = "Dear {$clientName},\n\nThis letter serves as a formal demand for the immediate settlement of the outstanding loan balance. Our records indicate that the remaining amount due and payable is KES " . number_format($balance, 2) . ".\n\nWe kindly request that you contact our office urgently to arrange payment or settle the balance in full. Failure to comply may result in additional recovery measures.\n\nSincerely,\nInua Premium Services";

                    $subject = 'Demand Letter - Outstanding Loan Balance';
                    $loanIdForLetter = 0;
                    if (!empty($loanSummary['rows'])) {
                        $loanIdForLetter = (int) ($loanSummary['rows'][0]['loan_id'] ?? 0);
                    }

                    $managerProfile = fetchManagerProfile($conn);
                    $loanForLetter = $loanSummary['rows'][0] ?? [];
                    $pdfContent = generateDemandLetterPdf($clientName, $phone, $idNumber, $balance, $date, $managerProfile, $loanForLetter, $loanIdForLetter, $mpesaTill);
                    $filename = 'demand_letter_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $clientName) . '.pdf';

                    try {
                        sendDemandLetterEmail($recipientEmail, $subject, $plainBody, $pdfContent, $filename);
                        $sendMessage = 'The demand letter PDF was sent successfully to the selected ' . ($recipientType === 'loan_officer' ? 'loan officer' : 'client') . '.';
                        $sendStatus = 'success';
                    } catch (Exception $e) {
                        $sendMessage = 'The letter was prepared, but the email could not be sent: ' . htmlspecialchars($e->getMessage());
                        $sendStatus = 'danger';
                    }
                }
            }
        }
    }
}

$selectedBorrowerId = (int) ($_GET['borrower_id'] ?? 0);
if ($selectedBorrowerId > 0 && !$borrower) {
    $borrowerStmt = $conn->prepare("SELECT id, full_name, mobile, unique_number, email, loan_officer FROM borrowers WHERE id = ? LIMIT 1");
    if ($borrowerStmt) {
        $borrowerStmt->bind_param('i', $selectedBorrowerId);
        $borrowerStmt->execute();
        $borrower = $borrowerStmt->get_result()->fetch_assoc();
        $borrowerStmt->close();
    }
}

if ($borrower) {
    $loanSummary = fetchBorrowerLoanSummary($conn, (int) $borrower['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demand Letter</title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f8f8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .page-container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 24px;
        }
        .card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
        }
        .company-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #ef4444;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }
        .company-header img {
            height: 70px;
            width: auto;
            object-fit: contain;
        }
        .client-box {
            background: #fff7f7;
            border: 1px solid #f4d1d1;
            border-radius: 12px;
            padding: 18px;
        }
        .letter-preview {
            border: 1px solid #e7e7e7;
            border-radius: 16px;
            padding: 28px;
            background: #fff;
            min-height: 500px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #e84545, #ff6b6b);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #d93d3d, #eb5a5a);
        }
        .search-result-item {
            cursor: pointer;
            transition: 0.2s ease;
        }
        .search-result-item:hover {
            background: #fff5f5;
        }
        .status-badge {
            font-size: 0.8rem;
            border-radius: 999px;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="page-container">
    <div class="card p-4">
        <div class="company-header">
            <div>
                <div class="fw-bold text-uppercase text-secondary" style="letter-spacing: 1px;">Inua Premium Services</div>
                <div class="text-muted small">Demand and Recovery Department</div>
            </div>
            <img src="../assets/img/logo.png" alt="Inua Premium Services Logo">
        </div>

        <?php if (!empty($sendMessage)): ?>
            <div class="alert alert-<?= htmlspecialchars($sendStatus); ?>" role="alert">
                <?= htmlspecialchars($sendMessage); ?>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Demand Letter</h2>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card client-box h-100">
                    <h5 class="fw-bold mb-3">Search Defaulted Client</h5>
                    <form id="clientSearchForm" class="mb-3" novalidate>
                        <div class="input-group">
                            <input type="text" id="clientSearchInput" class="form-control" placeholder="Search client name or phone" aria-label="Search client name or phone">
                            <button type="submit" class="btn btn-primary" id="clientSearchButton">Search</button>
                        </div>
                    </form>

                    <?php if ($borrower): ?>
                        <form method="post" action="demand_letter.php">
                            <input type="hidden" name="borrower_id" value="<?= (int) $borrower['id']; ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Client Name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($borrower['full_name'] ?? ''); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Phone</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($borrower['mobile'] ?? ''); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">ID Number</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($borrower['unique_number'] ?? ''); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Loan Balance</label>
                                <input type="text" class="form-control" value="KES <?= number_format((float) $loanSummary['total_balance'], 2); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="mpesaTill">M-Pesa Till Number</label>
                                <input type="text" name="mpesa_till" id="mpesaTill" class="form-control" inputmode="numeric" pattern="[0-9]+" maxlength="20" placeholder="Enter payment Till number" required>
                                <div class="form-text">This Till number will appear in the sent demand-letter PDF.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Send To</label>
                                <select name="recipient_type" class="form-select" id="recipientType">
                                    <option value="client">Client</option>
                                    <option value="loan_officer">Loan Officer</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Recipient Email</label>
                                <input type="email" name="recipient_email" id="recipientEmail" class="form-control" value="<?= htmlspecialchars((string) ($borrower['email'] ?? '')); ?>" required>
                            </div>

                            <button type="submit" name="send_demand_letter" class="btn btn-primary w-100">Send Demand Letter</button>
                        </form>
                    <?php else: ?>
                        <div class="text-muted small">Search for a client to generate and deliver the demand letter.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="letter-preview">
                    <?php if ($borrower): ?>
                        <?php $balance = (float) ($loanSummary['total_balance'] ?? 0); ?>
                        <div class="mb-4">
                            <h3 class="fw-bold text-dark mb-3">Demand Letter Preview</h3>
                            <div class="small text-muted mb-2">Date: <?= date('d F Y'); ?></div>
                        </div>

                        <p>Dear <?= htmlspecialchars($borrower['full_name'] ?? 'Client'); ?>,</p>
                        <p>We write to formally notify you that your loan account with Inua Premium Services has an outstanding balance that remains unpaid. Our records indicate that the remaining loan balance due and payable is <strong>KES <?= number_format($balance, 2); ?></strong>.</p>
                        <p>This notice serves as a formal demand for immediate settlement of the outstanding amount. We kindly request that you arrange payment without further delay to avoid additional charges, escalation of the account, and further recovery action in accordance with the loan agreement.</p>
                        <p>Please contact our office immediately to discuss payment arrangements or settle the full outstanding amount. Failure to do so may result in further administrative and recovery measures.</p>
                        <p class="mt-4 mb-0">
                            Sincerely,<br>
                            <strong>Inua Premium Services</strong><br>
                            Credit & Recovery Department
                        </p>
                    <?php else: ?>
                        <div class="text-muted text-center py-5">Select a client to preview the demand letter.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="clientSearchModal" tabindex="-1" aria-labelledby="clientSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clientSearchModalLabel">Client Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="clientSearchResults"></div>
        </div>
    </div>
</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const clientSearchForm = document.getElementById('clientSearchForm');
        const clientSearchInput = document.getElementById('clientSearchInput');
        const clientSearchButton = document.getElementById('clientSearchButton');
        const recipientType = document.getElementById('recipientType');
        const recipientEmail = document.getElementById('recipientEmail');

        if (recipientType && recipientEmail) {
            recipientType.addEventListener('change', function () {
                const borrowerEmail = <?= json_encode((string) ($borrower['email'] ?? '')); ?>;
                const officerEmail = <?= json_encode((string) ($borrower['loan_officer'] ?? '')); ?>;
                recipientEmail.value = (this.value === 'loan_officer') ? officerEmail : borrowerEmail;
            });
        }

        if (clientSearchForm) {
            clientSearchForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                const query = (clientSearchInput.value || '').trim();
                if (!query) {
                    document.getElementById('clientSearchResults').innerHTML = '<p class="text-danger mb-0">Enter a client name or phone number.</p>';
                    new bootstrap.Modal(document.getElementById('clientSearchModal')).show();
                    return;
                }

                clientSearchButton.disabled = true;
                clientSearchButton.innerHTML = 'Searching...';

                try {
                    const response = await fetch('search_client_details.php?query=' + encodeURIComponent(query), { headers: { 'Accept': 'application/json' } });
                    const clients = await response.json();
                    if (!response.ok || !Array.isArray(clients)) {
                        throw new Error('Unable to search clients.');
                    }

                    if (!clients.length) {
                        document.getElementById('clientSearchResults').innerHTML = '<p class="text-muted mb-0">No client found with that name or phone number.</p>';
                    } else {
                        document.getElementById('clientSearchResults').innerHTML = clients.map(client => {
                            const loanRows = (client.loans || []).map(loan => `
                                <tr class="search-result-item" data-borrower-id="${client.id}" data-borrower-name="${escapeHtml(client.full_name || 'Client')}" data-borrower-phone="${escapeHtml(client.mobile || 'N/A')}" data-borrower-idnumber="${escapeHtml(client.unique_number || 'N/A')}" data-borrower-email="${escapeHtml(client.email || '')}" data-loan-balance="${Number(loan.dues_arrears || 0).toFixed(2)}">
                                    <td><a href="repayment_details.php?loanId=${encodeURIComponent(loan.id)}">${escapeHtml(loan.id)}</a></td>
                                    <td>KES ${Number(loan.principal || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                    <td>KES ${Number(loan.total_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                    <td>${escapeHtml(loan.loan_duration || '')}</td>
                                    <td>KES ${Number(loan.total_paid || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                    <td>KES ${Number(loan.dues_arrears || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                    <td>${escapeHtml(loan.loan_status || 'Not Cleared')}</td>
                                </tr>
                            `).join('');

                            return `
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                        <div>
                                            <h6 class="mb-1 fw-bold">${escapeHtml(client.full_name || 'Client')}</h6>
                                            <div class="text-muted small">Phone: ${escapeHtml(client.mobile || 'N/A')} | ID: ${escapeHtml(client.unique_number || 'N/A')} | Officer: ${escapeHtml(client.loan_officer_name || 'Not assigned')}</div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary select-client-btn" data-borrower-id="${client.id}" data-borrower-name="${escapeHtml(client.full_name || 'Client')}" data-borrower-phone="${escapeHtml(client.mobile || 'N/A')}" data-borrower-idnumber="${escapeHtml(client.unique_number || 'N/A')}" data-borrower-email="${escapeHtml(client.email || '')}" data-borrower-loanofficer="${escapeHtml(client.loan_officer_email || '')}">
                                            Select Client
                                        </button>
                                    </div>
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Loan ID</th>
                                                    <th>Principal</th>
                                                    <th>Total Amount</th>
                                                    <th>Duration</th>
                                                    <th>Total Paid</th>
                                                    <th>Outstanding</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${loanRows || '<tr><td colspan="7" class="text-center text-muted">No loan records found.</td></tr>'}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    }

                    const modal = new bootstrap.Modal(document.getElementById('clientSearchModal'));
                    modal.show();

                    document.querySelectorAll('.select-client-btn').forEach(button => {
                        button.addEventListener('click', function () {
                            const borrowerId = this.dataset.borrowerId;
                            const borrowerName = this.dataset.borrowerName;
                            const borrowerPhone = this.dataset.borrowerPhone;
                            const borrowerIdNumber = this.dataset.borrowerIdnumber;
                            const borrowerEmail = this.dataset.borrowerEmail;
                            const borrowerLoanOfficer = this.dataset.borrowerLoanofficer;

                            const form = document.querySelector('form[action="demand_letter.php"]');
                            if (!form) {
                                window.location.href = 'demand_letter.php?borrower_id=' + encodeURIComponent(borrowerId);
                                return;
                            }

                            const hidden = form.querySelector('input[name="borrower_id"]');
                            if (hidden) hidden.value = borrowerId;

                            const clientNameField = form.querySelector('input.form-control');
                            if (clientNameField) clientNameField.value = borrowerName;

                            const phoneField = form.querySelectorAll('input.form-control')[1];
                            if (phoneField) phoneField.value = borrowerPhone;

                            const idField = form.querySelectorAll('input.form-control')[2];
                            if (idField) idField.value = borrowerIdNumber;

                            const emailField = document.getElementById('recipientEmail');
                            if (emailField) emailField.value = borrowerEmail || borrowerLoanOfficer || '';

                            const modalEl = document.getElementById('clientSearchModal');
                            const modalInstance = bootstrap.Modal.getInstance(modalEl);
                            if (modalInstance) modalInstance.hide();
                        });
                    });
                } catch (error) {
                    document.getElementById('clientSearchResults').innerHTML = '<p class="text-danger mb-0">' + escapeHtml(error.message || 'Unable to search clients.') + '</p>';
                    new bootstrap.Modal(document.getElementById('clientSearchModal')).show();
                } finally {
                    clientSearchButton.disabled = false;
                    clientSearchButton.innerHTML = 'Search';
                }
            });
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    });
</script>
</body>
</html>
