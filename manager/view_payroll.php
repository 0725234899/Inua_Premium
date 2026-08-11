<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
require_once 'db.php';
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function safe($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$conn->query("CREATE TABLE IF NOT EXISTS payroll_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS staff_payrolls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT DEFAULT NULL,
    template_name VARCHAR(150) DEFAULT NULL,
    staff_id INT NOT NULL,
    staff_name VARCHAR(150) NOT NULL,
    staff_email VARCHAR(150) DEFAULT NULL,
    pay_period VARCHAR(100) NOT NULL,
    pay_date DATE NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gross_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    earnings LONGTEXT,
    deductions LONGTEXT,
    notes TEXT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_staff_id (staff_id),
    KEY idx_pay_period (pay_period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$columnCheck = $conn->query("SHOW COLUMNS FROM staff_payrolls LIKE 'region_id'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE staff_payrolls ADD COLUMN region_id INT DEFAULT NULL, ADD COLUMN region_name VARCHAR(150) DEFAULT NULL");
}

$regions = [];
$regionsResult = $conn->query("SELECT area_id, area_name FROM areas ORDER BY area_name ASC");
if ($regionsResult) {
    while ($row = $regionsResult->fetch_assoc()) {
        $regions[] = $row;
    }
}

$selectedRegionId = isset($_GET['region_id']) ? (int)$_GET['region_id'] : 0;
$selectedRegionName = '';
if ($selectedRegionId > 0) {
    foreach ($regions as $region) {
        if ((int)$region['area_id'] === $selectedRegionId) {
            $selectedRegionName = $region['area_name'];
            break;
        }
    }
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_id'])) {
        $clearId = (int)$_POST['clear_id'];
        if ($clearId > 0) {
            $stmt = $conn->prepare("DELETE FROM staff_payrolls WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $clearId);
            if ($stmt->execute()) {
                $message = 'Payroll record cleared successfully.';
            } else {
                $error = 'Unable to clear payroll record. Please try again.';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['send_mail_id'])) {
        $sendMailId = (int)$_POST['send_mail_id'];
        if ($sendMailId > 0) {
            $payrollStmt = $conn->prepare("SELECT sp.*, u.email AS user_email, u.phone AS staff_phone FROM staff_payrolls sp LEFT JOIN users u ON sp.staff_id = u.id WHERE sp.id = ? LIMIT 1");
            $payrollStmt->bind_param('i', $sendMailId);
            $payrollStmt->execute();
            $payrollResult = $payrollStmt->get_result();
            $payroll = $payrollResult ? $payrollResult->fetch_assoc() : null;
            $payrollStmt->close();

            if (!$payroll) {
                $error = 'Payroll record not found.';
            } else {
                $emailRecipient = !empty($payroll['user_email']) ? $payroll['user_email'] : $payroll['staff_email'];
                if (empty($emailRecipient)) {
                    $error = 'Staff email address is not available.';
                } else {
                    $emailCredentials = getEmailAccount();
                    if (empty($emailCredentials['sender_email']) || empty($emailCredentials['sender_app_password'])) {
                        $error = 'Email settings are not configured. Please configure sender email and app password.';
                    } else {
                        $logoPath = __DIR__ . '/../assets/img/logo.png';
                        $mail = new PHPMailer(true);
                        try {
                            $mail->SMTPOptions = [
                                'ssl' => [
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true,
                                ],
                            ];
                            $mail->SMTPDebug = 0;
                            $mail->CharSet = 'UTF-8';
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = $emailCredentials['sender_email'];
                            $mail->Password = $emailCredentials['sender_app_password'];
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->SMTPAutoTLS = true;
                            $mail->Port = 587;
                            $mail->setFrom($emailCredentials['sender_email'], 'Inua Premium Services');
                            $mail->addReplyTo($emailCredentials['sender_email'], 'Inua Premium Services');
                            $mail->addAddress($emailRecipient, $payroll['staff_name']);
                            $mail->isHTML(true);
                            $mail->Subject = 'Payslip Document for ' . ($payroll['pay_period'] ?: 'this period');
                        if (file_exists($logoPath)) {
                            $mail->addEmbeddedImage($logoPath, 'company_logo');
                        }
                        $body = '<html><body style="margin:0;padding:0;font-family:Inter,Arial,sans-serif;background:#eaf5ff;color:#0f172a;">';
                        $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#eaf5ff;padding:24px;">';
                        $body .= '<tr><td align="center">';
                        $body .= '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 28px 80px rgba(14,88,161,0.15);border:1px solid #dbeafe;">';
                        $body .= '<tr><td style="padding:28px 32px;background:#0ea5e9;color:#ffffff;text-align:center;">';
                        if (file_exists($logoPath)) {
                            $body .= '<img src="cid:company_logo" alt="Company Logo" width="96" style="display:block;margin:0 auto 18px;">';
                        }
                        $body .= '<h1 style="margin:0;font-size:28px;font-weight:700;letter-spacing:-0.04em;">Inua Premium Services</h1>';
                        $body .= '<p style="margin:10px 0 0;font-size:15px;color:#dbeafe;">Payslip Document • ' . safe($payroll['pay_period']) . '</p>';
                        $body .= '</td></tr>';
                        $body .= '<tr><td style="padding:0 32px 16px;">';
                        $body .= '<p style="margin:0;font-size:14px;color:#e0f2fe;">Official payroll document for the period, issued for staff records and verification.</p>';
                        $body .= '</td></tr>';
                        $body .= '<tr><td style="padding:28px 32px 16px;">';
                        $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">';
                        $body .= '<tr>';
                        $body .= '<td width="52%" valign="top" style="padding-right:16px;">';
                        $body .= '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:18px;">';
                        $body .= '<p style="margin:0 0 10px;font-size:13px;color:#1d4ed8;font-weight:700;letter-spacing:0.03em;text-transform:uppercase;">Employee</p>';
                        $body .= '<p style="margin:0;font-size:16px;font-weight:600;color:#111827;">' . safe($payroll['staff_name']) . '</p>';
                        $body .= '<p style="margin:8px 0 0;font-size:14px;color:#475569;">' . safe($emailRecipient) . '</p>';
                        $body .= '<p style="margin:8px 0 0;font-size:14px;color:#475569;">' . safe($payroll['staff_phone'] ?: '-') . '</p>';
                        $body .= '</div>';
                        $body .= '</td>';
                        $body .= '<td width="48%" valign="top" style="padding-left:16px;">';
                        $body .= '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:18px;">';
                        $body .= '<p style="margin:0 0 10px;font-size:13px;color:#1d4ed8;font-weight:700;letter-spacing:0.03em;text-transform:uppercase;">Payslip Details</p>';
                        $body .= '<p style="margin:0;font-size:14px;color:#111827;"><strong>Period:</strong> ' . safe($payroll['pay_period']) . '</p>';
                        $body .= '<p style="margin:10px 0 0;font-size:14px;color:#111827;"><strong>Pay date:</strong> ' . safe($payroll['pay_date']) . '</p>';
                        $body .= '<p style="margin:10px 0 0;font-size:14px;color:#111827;"><strong>Region:</strong> ' . safe($payroll['region_name'] ?: '-') . '</p>';
                        $body .= '</div>';
                        $body .= '</td>';
                        $body .= '</tr>';
                        $body .= '</table>';
                        $body .= '</td></tr>';
                        $body .= '<tr><td style="padding-bottom:4px;">';
                        $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;background:#ffffff;">';
                        $body .= '<tr style="background:#0ea5e9;color:#ffffff;"><th align="left" style="padding:18px 20px;font-size:14px;letter-spacing:0.02em;">Earnings & Deductions</th><th align="right" style="padding:18px 20px;font-size:14px;letter-spacing:0.02em;">Amount</th></tr>';
                        $body .= '<tr><td style="padding:16px 20px;font-size:14px;color:#475569;">Basic salary</td><td align="right" style="padding:16px 20px;font-size:14px;color:#111827;">KES ' . number_format((float)$payroll['basic_salary'], 2) . '</td></tr>';
                        $body .= '<tr><td style="padding:16px 20px;font-size:14px;color:#475569;">Gross pay</td><td align="right" style="padding:16px 20px;font-size:14px;color:#111827;">KES ' . number_format((float)$payroll['gross_pay'], 2) . '</td></tr>';
                        $body .= '<tr><td style="padding:16px 20px;font-size:14px;color:#475569;">Total deductions</td><td align="right" style="padding:16px 20px;font-size:14px;color:#111827;">KES ' . number_format((float)$payroll['total_deductions'], 2) . '</td></tr>';
                        $body .= '<tr style="background:#bae6fd;"><td style="padding:16px 20px;font-size:16px;font-weight:700;color:#0f172a;">Net pay</td><td align="right" style="padding:16px 20px;font-size:16px;font-weight:700;color:#0f172a;">KES ' . number_format((float)$payroll['net_pay'], 2) . '</td></tr>';
                        $body .= '</table>';
                        $body .= '</td></tr>';
                        $body .= '<tr><td style="padding:0 0 26px;">';
                        $body .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:20px;">';
                        $body .= '<p style="margin:0;font-size:13px;color:#475569;line-height:1.7;">This payslip document is an official payroll record for the current period. Keep it for your records and present it as needed for verification.</p>';
                        $body .= '</div>';
                        $body .= '</td></tr>';
                        $body .= '<tr><td style="padding:0 0 28px;">';
                        $body .= '<p style="margin:0;font-size:13px;color:#94a3b8;text-align:center;">Inua Premium Services — Trusted payroll for staff and operations.</p>';
                        $body .= '</td></tr>';
                        $body .= '</table>';
                        $body .= '</td></tr></table>';
                        $body .= '</td></tr></table>';
                        $body .= '</body></html>';
                        $mail->Body = $body;
                        $mail->AltBody = "Payslip Document for {$payroll['pay_period']}\nEmployee: {$payroll['staff_name']}\nGross pay: KES " . number_format((float)$payroll['gross_pay'], 2) . "\nTotal deductions: KES " . number_format((float)$payroll['total_deductions'], 2) . "\nNet pay: KES " . number_format((float)$payroll['net_pay'], 2) . "\n\nThis document is issued for payroll record and verification purposes.";
                        $mail->send();
                        $message = 'Payslip document sent successfully to ' . safe($emailRecipient) . '.';
                    } catch (Exception $e) {
                        $error = 'Unable to send payslip email: ' . $mail->ErrorInfo;
                    }
                }
            }
        }
    }
}
}

$recentPayrolls = [];
$sql = "SELECT sp.id, sp.staff_name, sp.staff_id, u.phone AS staff_phone, u.email AS staff_email, sp.region_name, sp.pay_period, sp.pay_date, sp.gross_pay, sp.total_deductions, sp.net_pay, sp.created_at FROM staff_payrolls sp LEFT JOIN users u ON sp.staff_id = u.id";
if ($selectedRegionId > 0) {
    $sql .= " WHERE sp.region_id = " . $selectedRegionId;
}
$sql .= " ORDER BY sp.created_at DESC LIMIT 50";
$recentResult = $conn->query($sql);
if ($recentResult) {
    $recentPayrolls = $recentResult->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Payroll Records</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #eef2ff; color: #0f172a; font-family: 'Inter', sans-serif; }
        .container { max-width: 1140px; margin: 24px auto; padding: 0 18px 32px; }
        .card { border-radius: 24px; background: #fff; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08); border: 1px solid rgba(148, 163, 184, 0.16); padding: 28px; }
        .hero { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 20px; }
        .hero h1 { margin: 0; font-size: 2.3rem; letter-spacing: -0.04em; }
        .summary-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 18px; }
        .badge-soft { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 999px; background: #eef2ff; color: #4338ca; font-size: 0.95rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="card hero">
        <div>
            <h1>Payroll Records</h1>
            <p>Review recent payroll entries and monitor gross pay, deductions, and net pay for staff.</p>
        </div>
        <div class="badge-soft"><?php echo count($recentPayrolls); ?> records</div>
    </div>

    <div class="card mt-4">
        <form method="GET" action="view_payroll.php" class="row g-3 align-items-end mb-3">
            <div class="col-lg-4">
                <label class="form-label" for="region_id">Filter by region</label>
                <select id="region_id" name="region_id" class="form-select">
                    <option value="0">All regions</option>
                    <?php foreach ($regions as $region): ?>
                        <option value="<?php echo (int)$region['area_id']; ?>"<?php echo $selectedRegionId === (int)$region['area_id'] ? ' selected' : ''; ?>>
                            <?php echo safe($region['area_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <button type="submit" class="btn btn-primary">Apply filter</button>
            </div>
        </form>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo safe($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo safe($error); ?></div>
        <?php endif; ?>
        <?php if (empty($recentPayrolls)): ?>
            <p class="text-muted">No payroll records found yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Phone</th>
                            <th>Region</th>
                            <th>Period</th>
                            <th>Pay date</th>
                            <th class="text-end">Gross</th>
                            <th class="text-end">Deductions</th>
                            <th class="text-end">Net pay</th>
                            <th>Saved</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPayrolls as $row): ?>
                            <tr>
                                <td><?php echo safe($row['staff_name']); ?></td>
                                <td><?php echo safe($row['staff_phone'] ?: '-'); ?></td>
                                <td><?php echo safe($row['region_name'] ?: '-'); ?></td>
                                <td><?php echo safe($row['pay_period']); ?></td>
                                <td><?php echo safe($row['pay_date']); ?></td>
                                <td class="text-end">KES <?php echo number_format((float)$row['gross_pay'], 2); ?></td>
                                <td class="text-end">KES <?php echo number_format((float)$row['total_deductions'], 2); ?></td>
                                <td class="text-end">KES <?php echo number_format((float)$row['net_pay'], 2); ?></td>
                                <td><?php echo safe($row['created_at']); ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="view_payroll.php" onsubmit="return confirm('Clear this payroll record?');">
                                            <input type="hidden" name="clear_id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Clear</button>
                                        </form>
                                        <form method="POST" action="view_payroll.php">
                                            <input type="hidden" name="send_mail_id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Send Mail</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
