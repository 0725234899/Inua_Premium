<?php
require_once('TCPDF/tcpdf.php');
include 'db.php';

// Custom PDF class extending TCPDF
class PDF extends TCPDF {
    public $logo;

    public function __construct($logo) {
        parent::__construct();
        $this->logo = $logo;
    }

    // Custom header with logo and title
    public function Header() {
        if ($this->logo) {
            $this->Image('@' . $this->logo, 10, 10, 30);
        }
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(0, 102, 204); 
        $this->Cell(0, 20, 'Microfinance Reports', 0, 1, 'C');
        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 10, date('d-m-Y'), 0, 1, 'C');
        $this->Ln(5);
    }

    // Custom footer
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(0, 102, 204);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
        $this->Ln(3);
        $this->Cell(0, 10, 'Empowering Financial Growth', 0, 0, 'C');
    }
}

// Fetch logo from the database
function getLogo() {
    global $conn;
    $sql = "SELECT logo FROM settings LIMIT 1";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $imagePath = "../assets/img/".$row['logo'];
        return file_exists($imagePath) ? file_get_contents($imagePath) : null;
    }
    return null;
}

// Fetch all loan applications
function fetchLoans() {
    global $conn;
    $sql = "SELECT l.id, b.full_name AS borrower_name, p.name AS loan_product_name, 
                   l.principal, l.interest, l.loan_interest, l.loan_duration, 
                   l.repayment_cycle, l.number_of_repayments, l.total_amount, 
                   l.loan_release_date, l.loan_status
            FROM loan_applications l 
            INNER JOIN borrowers b ON l.borrower = b.id 
            INNER JOIN loan_products p ON l.loan_product = p.id";
    return $conn->query($sql);
}

// Fetch due repayments
function fetchDueRepayments() {
    global $conn;
    $sql = "SELECT borrowers.full_name AS borrower_name, loan_applications.loan_product, 
                   SUM(repayments.amount) AS total_amount_due, repayments.repayment_date
            FROM repayments
            INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id
            INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
            WHERE repayments.repayment_date >= CURDATE()
            GROUP BY repayments.loan_id, borrowers.full_name, 
                     loan_applications.loan_product, repayments.repayment_date";
    return $conn->query($sql);
}

// Fetch overdue repayments
function fetchOverdueRepayments() {
    global $conn;
    $sql = "SELECT borrowers.full_name AS borrower_name, loan_applications.loan_product, 
                   repayments.amount, repayments.repayment_date
            FROM repayments
            INNER JOIN loan_applications ON repayments.loan_id = loan_applications.id
            INNER JOIN borrowers ON loan_applications.borrower = borrowers.id
            WHERE repayments.repayment_date < CURDATE()";
    return $conn->query($sql);
}

// Generate an HTML table for fetched results
function generateTable($result, $columns, $isFinancial = false) {
    $table = '<table border="1" cellpadding="4" style="border-collapse: collapse; width: 100%;">';
    $table .= '<thead>
                <tr style="background-color: #d9edf7; text-align: center; font-weight: bold; color: #31708f;">';

    foreach ($columns as $column) {
        $table .= "<th>$column</th>";
    }
    $table .= '</tr></thead><tbody>';

    $totalAmount = 0;
    $rowCounter = 0;
    while ($row = $result->fetch_assoc()) {
        $backgroundColor = ($rowCounter % 2 == 0) ? '#f2f2f2' : '#ffffff';
        $table .= '<tr style="background-color: ' . $backgroundColor . '; text-align: center;">';

        foreach ($row as $key => $value) {
            if ($isFinancial && in_array($key, ['total_amount_due', 'principal', 'interest', 'loan_interest', 'total_amount'])) {
                $value = "KES " . number_format($value, 2);
                $totalAmount += $row[$key];
            }
            if ($key === 'loan_release_date' || $key === 'repayment_date') {
                $value = date('d-m-Y', strtotime($value));
            }
            $table .= "<td>$value</td>";
        }
        $table .= '</tr>';
        $rowCounter++;
    }

    if ($isFinancial) {
        $table .= '<tr style="font-weight: bold; background-color: #d9edf7;">
                    <td colspan="' . (count($columns) - 1) . '" style="text-align: right;">Total:</td>
                    <td>KES ' . number_format($totalAmount, 2) . '</td>
                   </tr>';
    }

    $table .= '</tbody></table>';
    return $table;
}

// Generate PDF report
function generatePDF($result, $title, $columns, $isFinancial = false) {
    $logo = getLogo();
    $pdf = new PDF($logo);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(15);
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->Ln(5);

    $table = generateTable($result, $columns, $isFinancial);
    $pdf->writeHTML($table, true, false, false, false, '');
    $pdf->Output("$title.pdf", 'I');
}

// Handle report exports
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['export_loans'])) {
        generatePDF(fetchLoans(), "Loan Applications", [
            'ID', 'Borrower', 'Loan Product', 'Principal', 'Interest', 'Loan Interest %', 
            'Duration (months)', 'Repayment Cycle', 'Number of Repayments', 'Total Amount', 
            'Loan Release Date', 'Status'
        ], true);
    } elseif (isset($_POST['export_due'])) {
        generatePDF(fetchDueRepayments(), "Due Repayments", [
            'Borrower', 'Loan Product', 'Total Amount Due', 'Due Date'
        ], true);
    } elseif (isset($_POST['export_overdue'])) {
        generatePDF(fetchOverdueRepayments(), "Overdue Repayments", [
            'Borrower', 'Loan Product', 'Amount Due', 'Due Date'
        ], true);
    }
}
?>
