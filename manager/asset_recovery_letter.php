<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once dirname(__DIR__) . '/admin/TCPDF/tcpdf.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if (empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

function assetRecoveryValue($value) {
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function fetchAssetRecoveryManager($conn) {
    $profile = ['name' => 'Inua Premium Services', 'email' => 'info@inuapremium.co.ke', 'phone' => 'N/A', 'region' => 'Unassigned'];
    $email = trim((string) ($_SESSION['email'] ?? ''));
    if ($email === '') {
        return $profile;
    }

    $stmt = $conn->prepare("SELECT u.name, u.email, u.phone, COALESCE(a.area_name, 'Unassigned') AS region_name
                            FROM users u LEFT JOIN areas a ON u.area = a.area_id
                            WHERE u.email = ? LIMIT 1");
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

function fetchDefaultedClientsForRecovery($conn) {
    $clients = [];
    $sql = "SELECT b.id, b.full_name, b.mobile, b.unique_number,
                   la.id AS loan_id, la.total_amount, la.loan_release_date,
                   COALESCE(u.name, 'Unassigned') AS loan_officer_name,
                   COALESCE(u.email, '') AS loan_officer_email,
                   GREATEST(COALESCE(la.total_amount, 0)
                       - COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = la.id), 0)
                       - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = la.id), 0), 0) AS outstanding_balance
            FROM borrowers b
            INNER JOIN loan_applications la ON la.borrower = b.id
            LEFT JOIN users u ON b.loan_officer = u.email
            WHERE GREATEST(COALESCE(la.total_amount, 0)
                       - COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = la.id), 0)
                       - COALESCE((SELECT SUM(pa.amount) FROM penalty_actions pa WHERE pa.loan_id = la.id), 0), 0) > 0
            ORDER BY b.full_name ASC, la.id DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
    }
    return $clients;
}

function generateAssetRecoveryPdf($client, $manager) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services');
    $pdf->SetAuthor($manager['name']);
    $pdf->SetTitle('Asset Recovery Notice - ' . $client['full_name']);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(false, 15);
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
    $pdf->Cell(0, 5, 'Asset Recovery and Collections Department', 0, 1, 'L');
    $pdf->Cell(0, 5, 'Tel: ' . $manager['phone'] . ' | Email: ' . $manager['email'], 0, 1, 'L');
    $pdf->Cell(0, 5, 'Region: ' . $manager['region'], 0, 1, 'L');
    $pdf->SetFont('helvetica', 'BI', 9);
    $pdf->Cell(0, 5, 'Issuing Manager: ' . $manager['name'], 0, 1, 'L');
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', 'B', 9.5);
    $pdf->Cell(0, 5, 'REFERENCE: AR-' . date('YmdHis'), 0, 1, 'R');
    $pdf->Cell(0, 5, 'DATE: ' . date('d/m/Y'), 0, 1, 'R');
    $pdf->Ln(2);

    $pdf->SetTextColor(210, 0, 0);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'FORMAL ASSET RECOVERY NOTICE', 0, 1, 'C');
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Ln(2);

    $clientName = assetRecoveryValue($client['full_name']);
    $phone = assetRecoveryValue($client['mobile']);
    $idNumber = assetRecoveryValue($client['unique_number']);
    $balance = number_format((float) $client['outstanding_balance'], 2);
    $loanId = (int) $client['loan_id'];
    $loanDate = $client['loan_release_date'] ? date('d/m/Y', strtotime($client['loan_release_date'])) : 'N/A';

    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML('<b>To: ' . $clientName . '</b>', true, false, true, false, 'L');
    $pdf->writeHTML('Phone: <b>' . $phone . '</b> &nbsp;&nbsp; ID Number: <b>' . $idNumber . '</b>', true, false, true, false, 'L');
    $pdf->writeHTML('Loan Reference: <b>#' . $loanId . '</b> &nbsp;&nbsp; Loan Release Date: <b>' . $loanDate . '</b>', true, false, true, false, 'L');
    $pdf->Ln(3);

    $pdf->writeHTML('<b>SUBJECT: FINAL NOTICE OF INTENDED ASSET RECOVERY</b>', true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('Dear ' . $clientName . ',', true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('Our records show that your loan account with <b>Inua Premium Services</b> remains in default. The outstanding balance currently due and payable is <b>KES ' . $balance . '</b>. Despite the obligation to keep the account up to date, the required payment has not been received.', true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('This letter serves as a <b>formal and final notice</b> that unless the full outstanding balance is settled, or an acceptable written repayment arrangement is agreed with our office, the company may proceed with <b>asset recovery action</b> in accordance with the loan agreement and applicable law.', true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('You are required to contact the Asset Recovery and Collections Department immediately to regularize the account. Any payment or arrangement should be confirmed using the official contact details above. Please retain all payment confirmations for your records.', true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('<b>Important:</b> This notice does not authorize unlawful entry, harassment, or removal of property. Any recovery process will be carried out through the lawful procedures applicable to the loan agreement.', true, false, true, false, 'L');
    $pdf->Ln(5);

    $pdf->MultiCell(0, 6, 'Yours faithfully,', 0, 'L');
    $pdf->Ln(2);
    $stampPath = __DIR__ . '/New Folder/assets/img/company_stamp.JPG';
    if (file_exists($stampPath)) {
        $stampY = $pdf->GetY();
        $pdf->SetAlpha(0.45);
        $pdf->Image($stampPath, 150, $stampY - 2, 34, 0, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
        $pdf->SetAlpha(1);
    }
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->MultiCell(0, 6, $manager['name'], 0, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->MultiCell(0, 5, 'Issuing Manager', 0, 'L');
    $pdf->MultiCell(0, 5, 'Inua Premium Services', 0, 'L');
    $pdf->MultiCell(0, 5, 'Phone: ' . $manager['phone'] . ' | Email: ' . $manager['email'], 0, 'L');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->MultiCell(0, 5, 'This document is an official asset recovery notice issued by Inua Premium Services. It should be delivered and acted upon in accordance with the loan agreement and applicable law.', 1, 'L');
    $pdf->Line(15, 270, 195, 270);
    $pdf->Cell(0, 5, 'Official Asset Recovery and Collections Notice', 0, 1, 'C');
    return $pdf->Output('', 'S');
}

function sendAssetRecoveryEmail($email, $clientName, $pdfContent, $filename) {
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
    $mail->addAddress($email, 'Loan Officer');
    $mail->isHTML(true);
    $mail->Subject = 'Asset Recovery Notice - ' . $clientName;
    $mail->Body = '<p>Dear Loan Officer,</p><p>Please find attached the formal asset recovery notice for <strong>' . htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') . '</strong>.</p><p>Regards,<br>Asset Recovery and Collections Department</p>';
    $mail->AltBody = 'Please find attached the formal asset recovery notice for ' . $clientName . '.';
    $mail->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
    $mail->send();
}

$clients = fetchDefaultedClientsForRecovery($conn);
$sendMessage = '';
$sendStatus = '';
$selectedClientId = (int) ($_POST['client_id'] ?? $_GET['client_id'] ?? 0);
$selectedClient = null;
foreach ($clients as $client) {
    if ((int) $client['id'] === $selectedClientId && $selectedClient === null) {
        $selectedClient = $client;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_asset_recovery_letter'])) {
    if (!$selectedClient) {
        $sendMessage = 'Please select a defaulted client with an outstanding balance.';
        $sendStatus = 'danger';
    } elseif (!filter_var($selectedClient['loan_officer_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $sendMessage = 'The selected client does not have a valid assigned loan officer email.';
        $sendStatus = 'danger';
    } else {
        try {
            $manager = fetchAssetRecoveryManager($conn);
            $pdf = generateAssetRecoveryPdf($selectedClient, $manager);
            $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $selectedClient['full_name']) ?: 'client';
            sendAssetRecoveryEmail($selectedClient['loan_officer_email'], $selectedClient['full_name'], $pdf, 'asset_recovery_notice_' . $safeName . '.pdf');
            $sendMessage = 'The asset recovery notice was sent to ' . $selectedClient['loan_officer_name'] . ' for delivery to ' . $selectedClient['full_name'] . '.';
            $sendStatus = 'success';
        } catch (Exception $e) {
            $sendMessage = 'The asset recovery notice could not be sent: ' . $e->getMessage();
            $sendStatus = 'danger';
        }
    }
}

$manager = fetchAssetRecoveryManager($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Recovery Letter</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body { background:#f8f8f8; color:#282828; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        .page-container { max-width:1100px; margin:30px auto; padding:24px; }
        .shell { background:#fff; border-radius:18px; box-shadow:0 12px 28px rgba(0,0,0,.08); }
        .company-header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #ef4444; padding-bottom:18px; margin-bottom:24px; }
        .company-header img { height:70px; width:auto; object-fit:contain; }
        .form-panel { background:#fff7f7; border:1px solid #f4d1d1; border-radius:12px; padding:20px; }
        .preview-panel { background:#fff; border:1px solid #e7e7e7; border-radius:16px; padding:28px; min-height:100%; }
        .section-title { color:#0b2f9f; font-size:1.05rem; font-weight:700; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid #ef4444; }
        .form-label { font-weight:600; }
        .form-control,.form-select { border-radius:8px; border-color:#d9d9d9; }
        .form-control:focus,.form-select:focus { border-color:#0b2f9f; box-shadow:0 0 0 .2rem rgba(11,47,159,.12); }
        .btn-primary { background:linear-gradient(135deg,#e84545,#ff6b6b); border:none; }
        .preview-line { border-bottom:1px solid #d9d9d9; padding:8px 0; }
        @media(max-width:768px){ .page-container{margin:10px auto;padding:10px;} .company-header img{height:52px;} }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="page-container">
    <div class="shell p-4">
        <div class="company-header">
            <div><div class="fw-bold text-uppercase text-secondary" style="letter-spacing:1px;">Inua Premium Services</div><div class="text-muted small">Asset Recovery and Collections Department</div></div>
            <img src="../assets/img/logo.png" alt="Inua Premium Services Logo">
        </div>
        <?php if ($sendMessage): ?><div class="alert alert-<?= htmlspecialchars($sendStatus); ?>"><?= htmlspecialchars($sendMessage); ?></div><?php endif; ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div><h2 class="mb-1">Asset Recovery Letter</h2><div class="text-muted">Select a defaulted client and send the formal notice to the assigned loan officer.</div></div>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="form-panel">
                    <div class="section-title">Defaulted Client</div>
                    <form method="post" action="asset_recovery_letter.php">
                        <div class="mb-3">
                            <label class="form-label" for="clientId">Client Loan in Default</label>
                            <select class="form-select" name="client_id" id="clientId" required>
                                <option value="">Select a defaulted client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= (int) $client['id']; ?>" <?= $selectedClient && (int) $selectedClient['id'] === (int) $client['id'] ? 'selected' : ''; ?> data-officer-email="<?= assetRecoveryValue($client['loan_officer_email']); ?>">
                                        <?= htmlspecialchars($client['full_name']); ?> - Loan #<?= (int) $client['loan_id']; ?> - KES <?= number_format((float) $client['outstanding_balance'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Only clients with an outstanding balance are listed.</div>
                        </div>
                        <?php if ($selectedClient): ?>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Client Name</label><input class="form-control" value="<?= htmlspecialchars($selectedClient['full_name']); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" value="<?= htmlspecialchars($selectedClient['mobile']); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Outstanding Balance</label><input class="form-control" value="KES <?= number_format((float) $selectedClient['outstanding_balance'], 2); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Assigned Loan Officer</label><input class="form-control" value="<?= htmlspecialchars($selectedClient['loan_officer_name']); ?>" readonly></div>
                                <div class="col-12"><label class="form-label">Officer Email</label><input class="form-control" value="<?= htmlspecialchars($selectedClient['loan_officer_email']); ?>" readonly></div>
                            </div>
                        <?php endif; ?>
                        <button type="submit" name="send_asset_recovery_letter" class="btn btn-primary btn-lg w-100 mt-4">Send Asset Recovery Letter</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="preview-panel">
                    <div class="section-title">Document Preview</div>
                    <p class="text-muted">The assigned loan officer will receive a branded PDF for delivery to the selected defaulted client.</p>
                    <div class="preview-line"><strong>Client:</strong> <?= assetRecoveryValue($selectedClient['full_name'] ?? 'Not selected'); ?></div>
                    <div class="preview-line"><strong>Loan:</strong> <?= $selectedClient ? '#' . (int) $selectedClient['loan_id'] : 'Not selected'; ?></div>
                    <div class="preview-line"><strong>Balance:</strong> <?= $selectedClient ? 'KES ' . number_format((float) $selectedClient['outstanding_balance'], 2) : 'Not selected'; ?></div>
                    <div class="preview-line"><strong>Recipient:</strong> <?= assetRecoveryValue($selectedClient['loan_officer_name'] ?? 'Assigned loan officer'); ?></div>
                    <div class="alert alert-light border mt-4">The notice includes the default status, recoverable balance, formal recovery warning, lawful-process statement, manager details, and company stamp.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('clientId').addEventListener('change', function () {
        if (this.value) window.location.href = 'asset_recovery_letter.php?client_id=' + encodeURIComponent(this.value);
    });
</script>
</body>
</html>
