<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['email'])) {
    header('Location: ../login.php');
    exit();
}

// payroll_generate.php
// Generates a single payslip as HTML or PDF (if Dompdf is available).
// Usage:
// - POST json={...} with payroll data, or
// - POST form fields: comp_name, comp_addr, emp_name, emp_id, emp_role, pay_period, pay_date
//   plus earnings[] and deductions[] as JSON strings or repeated fields.

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function formatCurrency($n){ return '$' . number_format((float)$n, 2); }

// Render payslip HTML given $d array
function renderPayslipHtml($d){
    $comp_name = e($d['comp_name'] ?? 'Company Name');
    $comp_addr = e($d['comp_addr'] ?? '');
    $emp_name = e($d['emp_name'] ?? 'Employee');
    $emp_id = e($d['emp_id'] ?? '');
    $emp_role = e($d['emp_role'] ?? '');
    $pay_period = e($d['pay_period'] ?? '');
    $pay_date = e($d['pay_date'] ?? '');

    $earnings = $d['earnings'] ?? [];
    $deductions = $d['deductions'] ?? [];

    $totalGross = 0; foreach($earnings as $it){ $totalGross += floatval($it['amount'] ?? 0); }
    $totalDeds = 0; foreach($deductions as $it){ $totalDeds += floatval($it['amount'] ?? 0); }
    $net = $totalGross - $totalDeds;

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Payslip - <?= $emp_name ?> - <?= $pay_period ?></title>
        <style>
            body{font-family: Arial, Helvetica, sans-serif; color:#0f172a;}
            .card{max-width:800px;margin:20px auto;border-radius:8px;overflow:hidden;border:1px solid #e6eef8}
            .header{background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;padding:18px}
            .header h1{margin:0;font-size:20px}
            .meta{display:flex;justify-content:space-between;padding:16px;background:#f8fafc}
            .meta .col{flex:1}
            table{width:100%;border-collapse:collapse;margin-top:12px}
            th,td{padding:8px 6px;text-align:left}
            td.amount{text-align:right;font-weight:600}
            .subtotal{font-weight:700;border-top:2px solid #e6eef8}
            .summary{display:flex;justify-content:space-between;padding:16px;background:#f8fafc}
            .net{background:#2563eb;color:#fff;padding:10px 16px;border-radius:6px}
        </style>
    </head>
    <body>
        <div class="card">
            <div class="header">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <h1><?= $comp_name ?></h1>
                        <div style="opacity:0.85;font-size:13px"><?= $comp_addr ?></div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:12px;opacity:0.85">Pay Period</div>
                        <div style="font-weight:700;font-size:16px"><?= $pay_period ?></div>
                    </div>
                </div>
            </div>

            <div class="meta">
                <div class="col">
                    <div style="font-size:12px;opacity:0.75">Employee Name</div>
                    <div style="font-weight:700"><?= $emp_name ?></div>
                </div>
                <div class="col">
                    <div style="font-size:12px;opacity:0.75">Employee ID</div>
                    <div style="font-weight:700"><?= $emp_id ?></div>
                </div>
                <div class="col">
                    <div style="font-size:12px;opacity:0.75">Designation</div>
                    <div style="font-weight:700"><?= $emp_role ?></div>
                </div>
                <div class="col">
                    <div style="font-size:12px;opacity:0.75">Pay Date</div>
                    <div style="font-weight:700"><?= $pay_date ?></div>
                </div>
            </div>

            <div style="display:flex;gap:24px;padding:18px">
                <div style="flex:1">
                    <div style="font-weight:700;text-transform:uppercase;font-size:12px;color:#16a34a">Earnings</div>
                    <table>
                        <thead><tr><th>Description</th><th style="text-align:right">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach($earnings as $it): ?>
                            <tr>
                                <td><?= e($it['label'] ?? '') ?></td>
                                <td class="amount"><?= formatCurrency($it['amount'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr class="subtotal"><td>Gross Earnings</td><td class="amount"><?= formatCurrency($totalGross) ?></td></tr>
                        </tfoot>
                    </table>
                </div>
                <div style="flex:1">
                    <div style="font-weight:700;text-transform:uppercase;font-size:12px;color:#dc2626">Deductions</div>
                    <table>
                        <thead><tr><th>Description</th><th style="text-align:right">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach($deductions as $it): ?>
                            <tr>
                                <td><?= e($it['label'] ?? '') ?></td>
                                <td class="amount"><?= formatCurrency($it['amount'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr class="subtotal"><td>Total Deductions</td><td class="amount"><?= formatCurrency($totalDeds) ?></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="summary">
                <div>
                    <div style="font-size:12px;opacity:0.85">Gross Earnings</div>
                    <div style="font-weight:700"><?= formatCurrency($totalGross) ?></div>
                </div>
                <div class="net">
                    <div style="font-size:11px;opacity:0.9">Net Pay</div>
                    <div style="font-size:18px;font-weight:800"><?= formatCurrency($net) ?></div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Parse input
$payload = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['json'])) {
        $payload = json_decode($_POST['json'], true);
    } elseif (!empty($_POST['comp_name']) || !empty($_POST['emp_name'])) {
        // collect fields
        $payload = [
            'comp_name' => $_POST['comp_name'] ?? '',
            'comp_addr' => $_POST['comp_addr'] ?? '',
            'emp_name' => $_POST['emp_name'] ?? '',
            'emp_id' => $_POST['emp_id'] ?? '',
            'emp_role' => $_POST['emp_role'] ?? '',
            'pay_period' => $_POST['pay_period'] ?? '',
            'pay_date' => $_POST['pay_date'] ?? '',
        ];
        // earnings and deductions: accept JSON string or repeated fields earnings_label[], earnings_amount[]
        if (!empty($_POST['earnings']) && is_string($_POST['earnings'])) {
            $payload['earnings'] = json_decode($_POST['earnings'], true) ?: [];
        } elseif (!empty($_POST['earnings_label']) && is_array($_POST['earnings_label'])) {
            $payload['earnings'] = [];
            foreach ($_POST['earnings_label'] as $i => $lab) {
                $amt = $_POST['earnings_amount'][$i] ?? 0;
                $payload['earnings'][] = ['label' => $lab, 'amount' => $amt];
            }
        }

        if (!empty($_POST['deductions']) && is_string($_POST['deductions'])) {
            $payload['deductions'] = json_decode($_POST['deductions'], true) ?: [];
        } elseif (!empty($_POST['deductions_label']) && is_array($_POST['deductions_label'])) {
            $payload['deductions'] = [];
            foreach ($_POST['deductions_label'] as $i => $lab) {
                $amt = $_POST['deductions_amount'][$i] ?? 0;
                $payload['deductions'][] = ['label' => $lab, 'amount' => $amt];
            }
        }
    } elseif (!empty($HTTP_RAW_POST_DATA) || strlen(file_get_contents('php://input'))>0) {
        // attempt to decode raw JSON body
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) $payload = $json;
    }
}

// If no payload, show a small helper form
if (!$payload) {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Generate Payslip</title></head><body>';
    echo '<h2>Generate Payslip</h2>';
    echo '<p>Send a POST request with JSON or use the sample form.</p>';
    echo '<form method="post">';
    echo 'Company Name: <input name="comp_name" value="Acme Dynamics"><br>';
    echo 'Company Addr: <input name="comp_addr" value="100 Technology Way"><br>';
    echo 'Employee Name: <input name="emp_name" value="Alex Morgan"><br>';
    echo 'Employee ID: <input name="emp_id" value="EMP-001"><br>';
    echo 'Role: <input name="emp_role" value="Designer"><br>';
    echo 'Pay Period: <input name="pay_period" value="Aug 2026"><br>';
    echo 'Pay Date: <input name="pay_date" value="Aug 31, 2026"><br>';
    echo '<h4>Earnings</h4>';
    echo 'Label: <input name="earnings_label[]" value="Basic Salary"> Amount: <input name="earnings_amount[]" value="6500"><br>';
    echo 'Label: <input name="earnings_label[]" value="HRA"> Amount: <input name="earnings_amount[]" value="1200"><br>';
    echo '<h4>Deductions</h4>';
    echo 'Label: <input name="deductions_label[]" value="Tax"> Amount: <input name="deductions_amount[]" value="1000"><br>';
    echo '<button type="submit">Generate</button>';
    echo '</form>';
    echo '</body></html>';
    exit;
}

// Render HTML
$html = renderPayslipHtml($payload);

// If Dompdf is available, return PDF download
$pdfRequested = !empty($_POST['format']) && $_POST['format']==='pdf';
// also accept ?pdf=1
if (!$pdfRequested && isset($_GET['pdf']) && $_GET['pdf']=='1') $pdfRequested = true;

// Try to locate composer autoload and Dompdf
$dompdfAvailable = false;
if ($pdfRequested) {
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        if (class_exists('Dompdf\Dompdf')) $dompdfAvailable = true;
    }
}

if ($pdfRequested && $dompdfAvailable) {
    // Generate PDF via Dompdf
    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf = $dompdf->output();
    header('Content-Type: application/pdf');
    $fn = sprintf('payslip-%s.pdf', preg_replace('/[^A-Za-z0-9_-]/','', $payload['emp_id'] ?? 'payslip'));
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    echo $pdf;
    exit;
}

// Otherwise output HTML (set headers so browser can download as .html if format=download)
if (!empty($_POST['download']) || (!empty($_GET['download']) && $_GET['download']=='1')) {
    $fn = sprintf('payslip-%s.html', preg_replace('/[^A-Za-z0-9_-]/','', $payload['emp_id'] ?? 'payslip'));
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    echo $html;
    exit;
}

// Default: render in browser
header('Content-Type: text/html; charset=utf-8');
echo $html;

?>