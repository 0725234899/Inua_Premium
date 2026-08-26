<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['email'])) {
    header('Location: ../login.php');
    exit();
}

// payroll_bulk.php
// Accepts a CSV upload and generates a ZIP of individual payslip HTML files.
// Expected CSV header (recommended): name,id,role,pay_period,pay_date,basic,hra,bonus,federal_tax,health_insurance,401k

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function formatCurrency($n){ return '$' . number_format((float)$n, 2); }

function renderPayslipHtml($d){
    // minimal renderer - similar to payroll_generate.php
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
    <!doctype html>
    <html lang="en"><head><meta charset="utf-8"><title>Payslip - <?= $emp_name ?></title></head><body>
    <div style="max-width:700px;margin:10px auto;font-family:Arial,Helvetica,sans-serif;">
        <h2><?= $comp_name ?></h2>
        <div><?= $comp_addr ?></div>
        <h3>Payslip for <?= $emp_name ?> (<?= $emp_id ?>)</h3>
        <div>Role: <?= $emp_role ?> | Period: <?= $pay_period ?> | Date: <?= $pay_date ?></div>
        <hr>
        <h4>Earnings</h4>
        <table style="width:100%;border-collapse:collapse"> <?php foreach($earnings as $it): ?>
            <tr><td><?= e($it['label'] ?? '') ?></td><td style="text-align:right"><?= formatCurrency($it['amount'] ?? 0) ?></td></tr>
        <?php endforeach; ?>
            <tr style="font-weight:700"><td>Gross</td><td style="text-align:right"><?= formatCurrency($totalGross) ?></td></tr>
        </table>
        <h4>Deductions</h4>
        <table style="width:100%;border-collapse:collapse"> <?php foreach($deductions as $it): ?>
            <tr><td><?= e($it['label'] ?? '') ?></td><td style="text-align:right"><?= formatCurrency($it['amount'] ?? 0) ?></td></tr>
        <?php endforeach; ?>
            <tr style="font-weight:700"><td>Total Deductions</td><td style="text-align:right"><?= formatCurrency($totalDeds) ?></td></tr>
        </table>
        <hr>
        <div style="text-align:right;font-weight:800;font-size:18px">Net Pay: <?= formatCurrency($net) ?></div>
    </div>
    </body></html>
    <?php
    return ob_get_clean();
}

// Simple CSV parsing helper: returns an array of associative rows
function parseCsvFile($path){
    $out = [];
    if (!file_exists($path)) return $out;
    if (($h = fopen($path, 'r')) === false) return $out;
    $headers = fgetcsv($h);
    if ($headers === false) { fclose($h); return $out; }
    // normalize headers
    $headers = array_map(function($v){ return trim(strtolower($v)); }, $headers);
    while (($row = fgetcsv($h)) !== false) {
        $r = [];
        foreach ($headers as $i => $hname) {
            $r[$hname] = isset($row[$i]) ? $row[$i] : '';
        }
        $out[] = $r;
    }
    fclose($h);
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['csv']['tmp_name'];
    $rows = parseCsvFile($tmp);
    if (empty($rows)) {
        echo 'CSV appears empty or malformed.'; exit;
    }

    if (!class_exists('ZipArchive')) {
        echo 'ZipArchive not available on this PHP installation.'; exit;
    }

    $zip = new ZipArchive();
    $zipName = tempnam(sys_get_temp_dir(), 'paysl_') . '.zip';
    if ($zip->open($zipName, ZipArchive::CREATE) !== true) {
        echo 'Failed to create ZIP.'; exit;
    }

    // company defaults from form fields
    $comp_name = $_POST['comp_name'] ?? 'Company';
    $comp_addr = $_POST['comp_addr'] ?? '';

    foreach ($rows as $i => $row) {
        // map CSV columns into expected structure
        $data = [
            'comp_name' => $comp_name,
            'comp_addr' => $comp_addr,
            'emp_name' => $row['name'] ?? ($row['employee'] ?? 'Employee'),
            'emp_id' => $row['id'] ?? ($row['employee_id'] ?? 'id'.$i),
            'emp_role' => $row['role'] ?? '',
            'pay_period' => $row['pay_period'] ?? '',
            'pay_date' => $row['pay_date'] ?? '',
            'earnings' => [],
            'deductions' => [],
        ];

        // Common earnings columns
        $earnCols = ['basic','hra','bonus','other_earning','overtime'];
        foreach ($earnCols as $col) {
            if (isset($row[$col]) && strlen(trim($row[$col]))>0) {
                $data['earnings'][] = ['label' => ucfirst(str_replace('_',' ',$col)), 'amount' => floatval($row[$col])];
            }
        }
        // Common deduction columns
        $dedCols = ['federal_tax','state_tax','health_insurance','401k','other_deduction'];
        foreach ($dedCols as $col) {
            if (isset($row[$col]) && strlen(trim($row[$col]))>0) {
                $label = $col === '401k' ? '401(k) Contribution' : ucfirst(str_replace('_',' ',$col));
                $data['deductions'][] = ['label' => $label, 'amount' => floatval($row[$col])];
            }
        }

        // If CSV contains custom earnings/deductions as JSON column
        if (!empty($row['earnings'])) {
            $j = json_decode($row['earnings'], true);
            if (is_array($j)) $data['earnings'] = array_merge($data['earnings'], $j);
        }
        if (!empty($row['deductions'])) {
            $j = json_decode($row['deductions'], true);
            if (is_array($j)) $data['deductions'] = array_merge($data['deductions'], $j);
        }

        $html = renderPayslipHtml($data);
        $fname = sprintf('%s_%s.html', preg_replace('/[^A-Za-z0-9_-]/','', $data['emp_id']), preg_replace('/[^A-Za-z0-9_-]/','', str_replace(' ', '_', $data['pay_period'])));
        $zip->addFromString($fname, $html);
    }

    $zip->close();

    // send zip as download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="payslips.zip"');
    header('Content-Length: ' . filesize($zipName));
    readfile($zipName);
    unlink($zipName);
    exit;
}

// If not POST, show an upload form
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Bulk Payslip Generator</title></head><body>
<h2>Bulk Payslip Generator (CSV → ZIP of HTML payslips)</h2>
<p>CSV should have headers like: name,id,role,pay_period,pay_date,basic,hra,bonus,federal_tax,health_insurance,401k</p>
<form method="post" enctype="multipart/form-data">
    Company Name: <input name="comp_name" value="Acme Dynamics"><br>
    Company Addr: <input name="comp_addr" value="100 Technology Way"><br>
    CSV file: <input type="file" name="csv" accept=".csv"><br>
    <button type="submit">Generate ZIP</button>
</form>
</body></html>