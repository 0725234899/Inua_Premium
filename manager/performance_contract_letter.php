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

function getPerformanceManager($conn) {
    $profile = ['name' => 'Inua Premium Services Ltd', 'email' => 'info@inuapremium.co.ke', 'phone' => 'N/A', 'region' => 'Unassigned'];
    $email = trim((string) ($_SESSION['email'] ?? ''));
    $stmt = $conn->prepare("SELECT u.name, u.email, u.phone, COALESCE(a.area_name, 'Unassigned') AS region_name
                            FROM users u LEFT JOIN areas a ON u.area = a.area_id
                            WHERE u.email = ? LIMIT 1");
    if ($stmt && $email !== '') {
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

function getPerformanceStaff($conn) {
    $staff = [];
    $result = $conn->query("SELECT u.id, u.name, u.email, u.phone, u.role_id, COALESCE(r.name, '') AS role_name
                            FROM users u LEFT JOIN roles r ON r.id = u.role_id
                            WHERE u.email IS NOT NULL AND u.email <> '' ORDER BY u.name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['role_label'] = performanceRoleLabel($row['role_id'], $row['role_name']);
            $staff[] = $row;
        }
    }
    return $staff;
}

function performanceRoleLabel($roleId, $roleName = '') {
    $roleName = trim((string) $roleName);
    if ($roleName !== '') {
        return ucwords(strtolower($roleName));
    }

    $knownRoles = [1 => 'Admin', 2 => 'Loan Officer', 3 => 'Client', 4 => 'Manager'];
    return $knownRoles[(int) $roleId] ?? 'Unassigned Role';
}

function performanceValue($value) {
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function performanceDate($value, $fallback = 'Not specified') {
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date ? $date->format('d/m/Y') : $fallback;
}

function performanceMoney($value) {
    return max(0, (float) str_replace(',', '', trim((string) $value)));
}

function rolePerformancePlan($role) {
    $role = strtolower(trim((string) $role));
    if (strpos($role, 'loan') !== false || strpos($role, 'relationship') !== false) {
        return [
            'title' => 'Relationship Officer / Loan Officer',
            'purpose' => 'Grow a healthy, well-managed loan portfolio while providing responsible financial guidance and excellent service to every client.',
            'duties' => [
                'Provide expert guidance on loan products and match solutions to verified client needs in line with company policies.',
                'Develop and maintain strong client relationships while managing an assigned portfolio of at least 70 individual borrowers.',
                'Recruit at least 70 new clients, contribute to a monthly loan-book growth target of KES 600,000, and support sustainable business growth.',
                'Resolve client inquiries and complaints promptly and professionally, meeting or exceeding established service-level agreements.',
                'Conduct responsible customer due diligence, accurate documentation, follow-up, collections, and portfolio monitoring.'
            ],
            'targets' => [
                ['KPI' => 'Marketing and loan disbursement', 'Description' => 'Loans disbursed to eligible, properly documented clients', 'Target' => '70 loans', 'Measure' => 'Number'],
                ['KPI' => 'Collection', 'Description' => 'Scheduled loan amounts collected on time', 'Target' => '90%', 'Measure' => 'Percentage'],
                ['KPI' => 'Arrears', 'Description' => 'Loans in arrears beyond the agreed repayment date', 'Target' => 'Maximum 5%', 'Measure' => 'Percentage'],
                ['KPI' => 'Loan book growth', 'Description' => 'Net monthly growth of the performing loan book', 'Target' => 'KES 600,000 per month', 'Measure' => 'Value']
            ],
            'standards' => [
                'Never recommend or disburse a facility without complete identification, affordability, approval, and supporting documentation.',
                'Protect client information, maintain accurate records, explain terms transparently, and avoid misleading or coercive conduct.',
                'Escalate suspected fraud, conflicts of interest, complaints, data incidents, and material repayment risks immediately.'
            ],
            'procedures' => [
                'Follow the approved client onboarding, KYC, credit assessment, approval, disbursement, repayment, reconciliation, and arrears-escalation procedures.',
                'Update portfolio records promptly after every client interaction, approval, disbursement, collection, promise to pay, or exception.',
                'Submit weekly performance updates covering recruitment, disbursement, collections, arrears, complaints, and corrective actions.'
            ],
            'commission' => 'The source performance schedule provides a gross monthly salary of KES 20,000 plus commission: KES 5,000 for 70 loans, KES 10,000 for 95% collection, and KES 5,000 for arrears below 5%, subject to approved company verification.'
        ];
    }
    if (strpos($role, 'manager') !== false) {
        return [
            'title' => 'Manager',
            'purpose' => 'Lead people and operations with accountability, ensuring that the organization meets its goals through disciplined execution, ethical conduct, and excellent service.',
            'duties' => [
                'Translate organizational goals and vision into clear team plans, measurable targets, timelines, and accountable ownership.',
                'Supervise staff performance, coach employees, review reports, and take timely corrective action where standards are not met.',
                'Protect portfolio quality, operational controls, customer experience, records, assets, and the reputation of Inua Premium Services.',
                'Coordinate recruitment, client service, collections, approvals, reporting, risk escalation, and cross-functional problem solving.',
                'Use accurate management information to identify opportunities, control losses, and deliver sustainable business growth.'
            ],
            'targets' => [
                ['KPI' => 'Team target delivery', 'Description' => 'Approved team and departmental targets achieved', 'Target' => '100%', 'Measure' => 'Percentage'],
                ['KPI' => 'Portfolio quality', 'Description' => 'Collections and arrears maintained within approved thresholds', 'Target' => 'At or above approved threshold', 'Measure' => 'Percentage'],
                ['KPI' => 'Reporting and controls', 'Description' => 'Accurate reports, reviews, escalations, and action plans submitted on time', 'Target' => '100% on time', 'Measure' => 'Compliance'],
                ['KPI' => 'Staff development', 'Description' => 'Performance reviews, coaching, and corrective actions completed', 'Target' => 'Monthly', 'Measure' => 'Completion']
            ],
            'standards' => [
                'Lead fairly and professionally, maintain confidentiality, prevent favoritism, and model the organization\'s values and vision.',
                'Ensure decisions are supported by accurate information, documented approvals, segregation of duties, and timely escalation.',
                'Treat clients and staff with dignity and address complaints, misconduct, fraud indicators, and operational risks without delay.'
            ],
            'procedures' => [
                'Apply approved planning, delegation, supervision, reporting, disciplinary, complaint-handling, risk, and approval procedures.',
                'Hold documented performance reviews and maintain action registers for underperformance, exceptions, and control weaknesses.',
                'Review operational, financial, credit, human-resource, and compliance reports and submit management actions within agreed timelines.'
            ],
            'commission' => 'Remuneration and any performance incentive will be governed by the employee\'s approved employment terms and company policy.'
        ];
    }
    return [
        'title' => $role ?: 'Unassigned Role',
        'purpose' => 'Deliver dependable, accurate, ethical, and customer-focused work that advances the organization\'s goals and vision.',
        'duties' => [
            'Perform all duties assigned for the role faithfully, industriously, accurately, and to the best of the employee\'s ability.',
            'Contribute actively to service quality, operational efficiency, teamwork, customer satisfaction, and sustainable organizational growth.',
            'Maintain complete records, meet agreed deadlines, communicate issues early, and follow authorized instructions and controls.',
            'Protect company property, client information, confidential records, and the professional reputation of Inua Premium Services.',
            'Put consistent additional effort into realizing the organization\'s goals and vision, taking ownership of results rather than waiting for supervision.'
        ],
        'targets' => [
            ['KPI' => 'Role delivery', 'Description' => 'Assigned duties and approved work plan completed to standard', 'Target' => '100%', 'Measure' => 'Completion'],
            ['KPI' => 'Quality and accuracy', 'Description' => 'Work completed accurately with minimal avoidable errors', 'Target' => 'Approved departmental standard', 'Measure' => 'Quality'],
            ['KPI' => 'Customer and team service', 'Description' => 'Requests, internal support, and customer matters handled within agreed timelines', 'Target' => '100% within SLA', 'Measure' => 'Compliance'],
            ['KPI' => 'Initiative and improvement', 'Description' => 'Practical contributions that improve service, control, or efficiency', 'Target' => 'Ongoing', 'Measure' => 'Review']
        ],
        'standards' => [
            'Be honest, respectful, punctual, dependable, and professional in all dealings with clients, colleagues, managers, and partners.',
            'Follow approved instructions, protect confidential information, maintain accurate records, and report errors or risks promptly.',
            'Do not misuse company resources, falsify records, disclose protected information, or act in a way that harms the organization.'
        ],
        'procedures' => [
            'Follow the role-specific operating procedures, approval limits, reporting lines, customer-service standards, and document-control requirements.',
            'Complete assigned work according to the approved workflow and escalate exceptions, complaints, delays, and suspected misconduct to the responsible manager.',
            'Participate in reviews, training, corrective action, and continuous improvement activities required by the organization.'
        ],
        'commission' => 'Remuneration and any performance incentive will be governed by the employee\'s approved employment terms and company policy.'
    ];
}

function generatePerformanceContractPdf($employee, $manager, $plan, $contractDate, $startDate, $monthlySalary, $commissionAmount) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Inua Premium Services Ltd');
    $pdf->SetAuthor($manager['name']);
    $pdf->SetTitle('Performance Contract - ' . $employee['name']);
    $pdf->SetMargins(15, 16, 15);
    $pdf->SetAutoPageBreak(false, 22);

    $safe = function ($value) {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    };
    $stampPath = __DIR__ . '/New Folder/assets/img/company_stamp.JPG';
    $header = function () use ($pdf, $manager) {
        $logoPath = dirname(__DIR__) . '/assets/img/logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 150, 10, 40, 0, 'PNG');
        }
        $pdf->SetDrawColor(0, 110, 105);
        $pdf->SetLineWidth(1.1);
        $pdf->Line(15, 13, 145, 13);
        $pdf->SetDrawColor(210, 0, 0);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(15, 15, 145, 15);
        $pdf->SetTextColor(0, 110, 105);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 9, 'INUA PREMIUM SERVICES LTD', 0, 1, 'L');
        $pdf->SetTextColor(55, 55, 55);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell(0, 5, 'Empowering Businesses | Human Resources and Administration Department', 0, 1, 'L');
        $pdf->Cell(0, 5, 'Tel: ' . $manager['phone'] . ' | Email: ' . $manager['email'] . ' | Region: ' . $manager['region'], 0, 1, 'L');
        $pdf->Ln(5);
    };
    $section = function ($title) use ($pdf) {
        $pdf->Ln(2);
        $pdf->SetFillColor(0, 110, 105);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->Cell(0, 7, $title, 1, 1, 'L', true);
        $pdf->SetTextColor(45, 45, 45);
        $pdf->Ln(1);
    };
    $table = function ($html) use ($pdf) {
        $pdf->SetFont('helvetica', '', 8.1);
        $pdf->writeHTML($html, true, false, true, false, 'L');
    };
    $bulletList = function ($items) use ($pdf, $safe) {
        $pdf->SetFont('helvetica', '', 8.5);
        foreach ($items as $item) {
            $pdf->writeHTML('&bull; ' . $safe($item), true, false, true, false, 'L');
            $pdf->Ln(1);
        }
    };
    $footer = function () use ($pdf) {
        $pdf->SetDrawColor(160, 160, 160);
        $pdf->Line(15, 276, 195, 276);
        $pdf->SetFont('helvetica', 'I', 7.2);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetXY(15, 278);
        $pdf->Cell(180, 4, 'Confidential employment document | Inua Premium Services Ltd', 0, 0, 'C');
    };
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->AddPage();
    $header();
    $pdf->SetTextColor(0, 110, 105);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'PERFORMANCE CONTRACT', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, '(' . $safe($plan['title']) . ')', 0, 1, 'C');
    $pdf->SetTextColor(45, 45, 45);
    $pdf->Ln(3);
    $table('<table border="1" cellpadding="3"><tr bgcolor="#e8f4f3"><td width="25%"><b>Employee</b></td><td width="25%">' . $safe($employee['name']) . '</td><td width="25%"><b>Role</b></td><td width="25%">' . $safe($plan['title']) . '</td></tr><tr><td><b>Email</b></td><td>' . $safe($employee['email']) . '</td><td><b>Contract date</b></td><td>' . $safe(performanceDate($contractDate)) . '</td></tr><tr><td><b>Commencement</b></td><td>' . $safe(performanceDate($startDate)) . '</td><td><b>Reference</b></td><td>PC-' . date('YmdHis') . '</td></tr></table>');
    $section('1. PARTIES AND PURPOSE');
    $pdf->SetFont('helvetica', '', 8.8);
    $pdf->writeHTML('This Performance Contract is made between <b>' . $safe($employee['name']) . '</b>, holder of the employee record selected above, and <b>Inua Premium Services Ltd</b> (the "Employer"). The employee accepts responsibility for delivering the duties, targets, standards, and procedures in this document in accordance with the laws of Kenya, the employment agreement, and the Employer\'s approved policies.', true, false, true, false, 'L');
    $pdf->Ln(3);
    $pdf->writeHTML('<b>Purpose of the role:</b> ' . $safe($plan['purpose']), true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('<b>Personal performance message for ' . $safe($employee['name']) . ':</b> Your contribution as a ' . $safe($plan['title']) . ' is important to the success of Inua Premium Services. You are expected to take ownership of your assigned responsibilities, show initiative beyond the minimum requirement, and convert your daily work into measurable progress for our clients and the organization.', true, false, true, false, 'L');
    $section('2. EMPLOYEE DEDICATION AND ORGANIZATIONAL VISION');
    $pdf->SetFont('helvetica', '', 8.8);
    $pdf->writeHTML('The employee shall at all times act faithfully, industriously, professionally, and to the best of their ability. The employee is expected to put deliberate and sustained effort into realizing the organization\'s goals and vision by taking ownership of assigned results, serving clients responsibly, improving performance, supporting colleagues, and identifying practical ways to strengthen the business. Meeting the minimum duty is not the sole measure of performance; initiative, accountability, integrity, responsiveness, and a willingness to do more for the success of Inua Premium Services are required.', true, false, true, false, 'L');
    $section('3. DUTIES AND RESPONSIBILITIES');
    $bulletList($plan['duties']);
    $pdf->AddPage();
    $header();
    $pdf->SetTextColor(0, 110, 105);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'PERFORMANCE CONTRACT', 0, 1, 'C');
    $pdf->SetTextColor(45, 45, 45);
    $section('4. PERFORMANCE TARGETS AND MEASUREMENT');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->writeHTML('Performance will be reviewed regularly and measured against the role-specific parameters below. Reviews may be conducted weekly, monthly, quarterly, or at any other interval reasonably required by the Employer. Targets may be clarified or revised through an approved written performance plan.', true, false, true, false, 'L');
    $rows = '';
    foreach ($plan['targets'] as $target) {
        $rows .= '<tr><td>' . $safe($target['KPI']) . '</td><td>' . $safe($target['Description']) . '</td><td>' . $safe($target['Target']) . '</td><td>' . $safe($target['Measure']) . '</td></tr>';
    }
    $table('<table border="1" cellpadding="3"><tr bgcolor="#0b6e69" color="#ffffff"><th width="22%"><b>KPI</b></th><th width="43%"><b>Description</b></th><th width="20%"><b>Target</b></th><th width="15%"><b>Measure</b></th></tr>' . $rows . '</table>');
    $section('5. REMUNERATION AND PERFORMANCE INCENTIVE');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->writeHTML('<b>Monthly salary:</b> KES ' . number_format((float) $monthlySalary, 2) . '<br><b>Monthly commission / incentive:</b> KES ' . number_format((float) $commissionAmount, 2), true, false, true, false, 'L');
    $pdf->Ln(2);
    $pdf->writeHTML('The commission or incentive amount is payable only after the Employer verifies the underlying role results, quality, compliance, documentation, and applicable deductions. Nothing in this section overrides the employee\'s signed employment agreement or an approved company remuneration policy.', true, false, true, false, 'L');
    $section('6. WORKING HOURS AND AVAILABILITY');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->writeHTML('The employee shall work the hours communicated by the Employer and remain reasonably available for authorized client, operational, reporting, training, and performance activities. Punctuality, attendance, proper handover, and prior authorization for absence are required. Additional work may be necessary to meet legitimate business needs, subject to applicable law and company policy.', true, false, true, false, 'L');
    $pdf->AddPage();
    $header();
    $pdf->SetTextColor(0, 110, 105);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'PERFORMANCE CONTRACT', 0, 1, 'C');
    $pdf->SetTextColor(45, 45, 45);
    $section('7. POLICIES, STANDARDS AND PROCEDURES');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, 'Required standards', 0, 1, 'L');
    $bulletList($plan['standards']);
    $pdf->Ln(1);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, 'Required procedures', 0, 1, 'L');
    $bulletList($plan['procedures']);
    $pdf->Ln(1);
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->writeHTML('The employee shall comply with all lawful and reasonable instructions, the Employer\'s manuals and approved procedures, customer-protection requirements, information-security controls, anti-fraud controls, data-protection requirements, records-management rules, health and safety requirements, and any future policies formally communicated by the Employer.', true, false, true, false, 'L');
    $section('8. DISCIPLINE AND SUMMARY DISMISSAL');
    $pdf->SetFont('helvetica', '', 8.2);
    $pdf->writeHTML('Disciplinary action may be taken for poor performance, repeated failure to meet reasonable standards, insubordination, unauthorized absence, dishonesty, falsification of records, misuse or disclosure of confidential information, fraud, theft, serious negligence, conflict of interest, harassment, unlawful conduct, or any other conduct that materially harms the Employer, its clients, staff, or reputation. Where permitted by law and the circumstances justify it, the Employer may summarily dismiss an employee who commits serious misconduct or fundamentally breaches this Contract.', true, false, true, false, 'L');
    $section('9. CONFIDENTIALITY, DATA AND COMPANY PROPERTY');
    $pdf->SetFont('helvetica', '', 8.2);
    $pdf->writeHTML('All client, employee, financial, operational, technology, portfolio, and business information obtained through employment is confidential. The employee shall use it only for authorized work, keep it secure, return company property on request or separation, and report any loss, unauthorized access, suspected fraud, or data incident immediately.', true, false, true, false, 'L');
    $pdf->AddPage();
    $header();
    $pdf->SetTextColor(0, 110, 105);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'PERFORMANCE CONTRACT', 0, 1, 'C');
    $pdf->SetTextColor(45, 45, 45);
    $section('10. CONTRACT TERMINATION');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->writeHTML('This employment relationship may be terminated in accordance with the employment agreement, applicable law, and approved company procedure. The Employer may terminate where performance is persistently below the required standard after reasonable review and support, or where either party gives the notice required by the employment terms. The Employer may also terminate without notice where permitted by law for serious misconduct or a fundamental breach of this Contract.', true, false, true, false, 'L');
    $pdf->Ln(3);
    $pdf->writeHTML('Where this Contract or the employment relationship ends, the employee shall complete handover, return company property and records, protect confidential information, and settle any authorized outstanding obligations in accordance with law and company policy.', true, false, true, false, 'L');
    $section('11. ACKNOWLEDGEMENT AND ACCEPTANCE');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->writeHTML('By signing below, the parties confirm that they have read, understood, and accepted the duties, targets, standards, procedures, policies, and terms in this Performance Contract. The employee acknowledges the specific responsibility to apply greater effort and initiative toward the realization of Inua Premium Services\' goals and vision.', true, false, true, false, 'L');
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->Cell(92, 6, 'Employee signature: __________________________', 0, 0, 'L');
    $pdf->Cell(0, 6, 'Date: ____/____/________', 0, 1, 'L');
    $pdf->Ln(5);
    $pdf->Cell(92, 6, 'Witnessed by: _______________________________', 0, 0, 'L');
    $pdf->Cell(0, 6, 'Date: ____/____/________', 0, 1, 'L');
    $pdf->Ln(8);
    $signatureY = $pdf->GetY();
    if (file_exists($stampPath)) {
        $pdf->SetAlpha(0.42);
        $pdf->Image($stampPath, 146, $signatureY - 8, 35, 0, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
        $pdf->SetAlpha(1);
    }
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->Cell(92, 6, 'For Inua Premium Services Ltd', 0, 0, 'L');
    $pdf->Cell(0, 6, 'Date: ____/____/________', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->Cell(92, 5, 'Authorized representative: ' . $safe($manager['name']), 0, 0, 'L');
    $pdf->Cell(0, 5, 'Signature: __________________________', 0, 1, 'L');
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8.3);
    $pdf->Cell(0, 5, 'We are pleased to support your successful and rewarding career with Inua Premium Services Ltd.', 0, 1, 'C');
    $footer();

    return $pdf->Output('', 'S');
}

function sendPerformanceContractEmail($staff, $pdfContent, $filename, $monthlySalary, $commissionAmount) {
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
    $mail->setFrom($credentials['sender_email'], 'Inua Premium Services Ltd');
    $mail->addAddress($staff['email'], $staff['name']);
    $mail->isHTML(true);
    $mail->Subject = 'Performance Contract - Inua Premium Services Ltd';
    $mail->Body = '<p>Dear ' . htmlspecialchars($staff['name'], ENT_QUOTES, 'UTF-8') . ',</p><p>Please find attached your formal Performance Contract prepared specifically for your role as <strong>' . htmlspecialchars($staff['role_label'], ENT_QUOTES, 'UTF-8') . '</strong> at Inua Premium Services Ltd.</p><p>Your contract records a monthly salary of <strong>KES ' . number_format($monthlySalary, 2) . '</strong> and a monthly commission or incentive amount of <strong>KES ' . number_format($commissionAmount, 2) . '</strong>, subject to the signed employment terms and verification of performance.</p><p>The contract sets out your distinct duties, measurable expectations, policies, standards, procedures, and the importance of applying consistent effort toward the organization\'s goals and vision. Please review, sign, and return it as instructed by Human Resources and Administration.</p><p>Regards,<br>Human Resources and Administration Department<br>Inua Premium Services Ltd</p>';
    $mail->AltBody = 'Please find attached your role-specific Performance Contract. Monthly salary: KES ' . number_format($monthlySalary, 2) . '. Monthly commission or incentive: KES ' . number_format($commissionAmount, 2) . '. Please review, sign, and return it as instructed.';
    $mail->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
    $mail->send();
}

$manager = getPerformanceManager($conn);
$staffList = getPerformanceStaff($conn);
$sendMessage = '';
$sendStatus = '';
$formValues = ['staff_id' => '', 'contract_date' => date('Y-m-d'), 'start_date' => date('Y-m-d'), 'monthly_salary' => '20000', 'commission_amount' => '5000'];
$selectedStaff = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_performance_contract'])) {
    $formValues['staff_id'] = (int) ($_POST['staff_id'] ?? 0);
    $formValues['contract_date'] = trim((string) ($_POST['contract_date'] ?? ''));
    $formValues['start_date'] = trim((string) ($_POST['start_date'] ?? ''));
    $formValues['monthly_salary'] = trim((string) ($_POST['monthly_salary'] ?? ''));
    $formValues['commission_amount'] = trim((string) ($_POST['commission_amount'] ?? ''));
    foreach ($staffList as $staff) {
        if ((int) $staff['id'] === $formValues['staff_id']) {
            $selectedStaff = $staff;
            break;
        }
    }
    $monthlySalary = performanceMoney($formValues['monthly_salary']);
    $commissionAmount = performanceMoney($formValues['commission_amount']);
    if (!$selectedStaff) {
        $sendMessage = 'Select a valid staff member with an email address.';
        $sendStatus = 'danger';
    } elseif ($monthlySalary <= 0 || $commissionAmount < 0) {
        $sendMessage = 'Enter a valid monthly salary and commission amount.';
        $sendStatus = 'danger';
    } else {
        try {
            $plan = rolePerformancePlan($selectedStaff['role_label']);
            $pdf = generatePerformanceContractPdf($selectedStaff, $manager, $plan, $formValues['contract_date'], $formValues['start_date'], $monthlySalary, $commissionAmount);
            $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $selectedStaff['name']) ?: 'staff_member';
            sendPerformanceContractEmail($selectedStaff, $pdf, 'performance_contract_' . $safeName . '.pdf', $monthlySalary, $commissionAmount);
            $sendMessage = 'The performance contract was sent successfully to ' . $selectedStaff['name'] . '.';
            $sendStatus = 'success';
        } catch (Exception $e) {
            $sendMessage = 'The performance contract could not be sent: ' . $e->getMessage();
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
    <title>Performance Contract</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body { background:#f8f8f8; color:#282828; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        .page-container { max-width:1080px; margin:30px auto; padding:24px; }
        .shell { background:#fff; border-radius:18px; box-shadow:0 12px 28px rgba(0,0,0,.08); }
        .company-header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #d40000; padding-bottom:18px; margin-bottom:24px; }
        .company-header img { height:70px; width:auto; object-fit:contain; }
        .form-panel { background:#f4fbfa; border:1px solid #c8e4e1; border-radius:12px; padding:22px; }
        .preview-panel { background:#fff; border:1px solid #e7e7e7; border-radius:16px; padding:28px; min-height:100%; }
        .section-title { color:#006e69; font-size:1.05rem; font-weight:700; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid #d40000; }
        .form-label { font-weight:600; }
        .form-control,.form-select { border-radius:8px; border-color:#d9d9d9; }
        .form-control:focus,.form-select:focus { border-color:#006e69; box-shadow:0 0 0 .2rem rgba(0,110,105,.12); }
        .btn-primary { background:linear-gradient(135deg,#006e69,#0b8d84); border:none; }
        .preview-line { border-bottom:1px solid #d9d9d9; padding:9px 0; }
        @media(max-width:768px){ .page-container{margin:10px auto;padding:10px;} .company-header img{height:52px;} }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="page-container">
    <div class="shell p-4">
        <div class="company-header">
            <div><div class="fw-bold text-uppercase text-secondary" style="letter-spacing:1px;">Inua Premium Services Ltd</div><div class="text-muted small">Performance Management and Human Resources</div></div>
            <img src="../assets/img/logo.png" alt="Inua Premium Services Logo">
        </div>
        <?php if ($sendMessage): ?><div class="alert alert-<?= htmlspecialchars($sendStatus); ?>"><?= htmlspecialchars($sendMessage); ?></div><?php endif; ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div><h2 class="mb-1">Performance Contract</h2><div class="text-muted">Prepare and email a formal, role-specific performance contract to selected staff.</div></div>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="form-panel">
                    <div class="section-title">Contract Recipient</div>
                    <form method="post" action="performance_contract_letter.php">
                        <div class="mb-3"><label class="form-label" for="staff_id">Select Staff Member</label><select class="form-select" id="staff_id" name="staff_id" required><option value="">Choose staff member</option><?php foreach ($staffList as $staff): ?><option value="<?= (int) $staff['id']; ?>" data-name="<?= performanceValue($staff['name']); ?>" data-email="<?= performanceValue($staff['email']); ?>" data-role="<?= performanceValue($staff['role_label']); ?>" <?= (int) $formValues['staff_id'] === (int) $staff['id'] ? 'selected' : ''; ?>><?= performanceValue($staff['name']); ?> - <?= performanceValue($staff['role_label']); ?> (<?= performanceValue($staff['email']); ?>)</option><?php endforeach; ?></select></div>
                        <div class="row g-3"><div class="col-md-6"><label class="form-label" for="contract_date">Contract Date</label><input type="date" class="form-control" id="contract_date" name="contract_date" value="<?= performanceValue($formValues['contract_date']); ?>" required></div><div class="col-md-6"><label class="form-label" for="start_date">Commencement Date</label><input type="date" class="form-control" id="start_date" name="start_date" value="<?= performanceValue($formValues['start_date']); ?>" required></div><div class="col-md-6"><label class="form-label" for="monthly_salary">Monthly Salary (KES)</label><input type="number" class="form-control" id="monthly_salary" name="monthly_salary" min="1" step="0.01" value="<?= performanceValue($formValues['monthly_salary']); ?>" required></div><div class="col-md-6"><label class="form-label" for="commission_amount">Monthly Commission / Incentive (KES)</label><input type="number" class="form-control" id="commission_amount" name="commission_amount" min="0" step="0.01" value="<?= performanceValue($formValues['commission_amount']); ?>" required></div></div>
                        <div class="alert alert-light border mt-4 mb-0">The contract includes duties, role-based performance targets, remuneration guidance, working hours, policies, standards, procedures, disciplinary terms, termination terms, signature fields, and the company stamp.</div>
                        <button type="submit" name="send_performance_contract" class="btn btn-primary btn-lg w-100 mt-4">Send Performance Contract</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="preview-panel">
                    <div class="section-title">Document Preview</div>
                    <p class="text-muted">The recipient will receive a formal PDF based on the source performance contract and customized to the selected role.</p>
                    <div class="preview-line"><strong>Staff member:</strong> <span id="previewName"><?= $selectedStaff ? performanceValue($selectedStaff['name']) : 'Not selected'; ?></span></div>
                    <div class="preview-line"><strong>Email:</strong> <span id="previewEmail"><?= $selectedStaff ? performanceValue($selectedStaff['email']) : 'Not selected'; ?></span></div>
                    <div class="preview-line"><strong>Role:</strong> <span id="previewRole"><?= $selectedStaff ? performanceValue($selectedStaff['role_label']) : 'Role-specific plan'; ?></span></div>
                    <div class="preview-line"><strong>Monthly salary:</strong> KES <?= number_format((float) $formValues['monthly_salary'], 2); ?></div>
                    <div class="preview-line"><strong>Monthly commission:</strong> KES <?= number_format((float) $formValues['commission_amount'], 2); ?></div>
                    <div class="preview-line"><strong>Source targets:</strong> 70 loans, 90% collection, maximum 5% arrears, KES 600,000 monthly loan-book growth</div>
                    <div class="alert alert-light border mt-4">The employee is expressly required to apply greater effort and initiative toward the organization’s goals and vision.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('staff_id').addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        document.getElementById('previewName').textContent = option.dataset.name || 'Not selected';
        document.getElementById('previewEmail').textContent = option.dataset.email || 'Not selected';
        document.getElementById('previewRole').textContent = option.dataset.role || 'Role-specific plan';
    });
</script>
</body>
</html>
