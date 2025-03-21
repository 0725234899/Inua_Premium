<?php
require_once('TCPDF/tcpdf.php'); // Include the TCPDF library
include 'db.php';

// Function to fetch loan data
function getLoans() {
    global $conn;
    $loans = array();

    $sql = "SELECT 
                l.id, 
                b.full_name AS borrower_name, 
                p.name AS loan_product_name, 
                l.principal, 
                l.interest, 
                l.interest_method, 
                l.loan_interest, 
                l.loan_duration, 
                l.repayment_cycle, 
                l.number_of_repayments, 
                l.processing_fee, 
                l.registration_fee, 
                l.total_amount, 
                l.loan_release_date,
                l.loan_status
            FROM loan_applications l 
            INNER JOIN borrowers b ON l.borrower = b.id 
            INNER JOIN loan_products p ON l.loan_product = p.id";

    $result = $conn->query($sql);

    if ($result === FALSE) {
        echo "Error: " . $conn->error;
        return $loans;
    }

    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
    }
    return $loans;
}

$loans = getLoans();

// Extend TCPDF class for custom header and footer
class PDF extends TCPDF {
    public function Header() {
        $image_file = 'assets/img/logo.png'; // Ensure correct path
        if (file_exists($image_file)) {
            $this->Image($image_file, 15, 8, 25); // Adjust position and size
        }
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Loan Applications Report', 0, 1, 'C');
        $this->Ln(5); // Line break
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Create new PDF document
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Inua Premium');
$pdf->SetTitle('Loan Applications Report');
$pdf->SetSubject('Report');
$pdf->SetKeywords('TCPDF, PDF, report, loan');
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Table styling
$html = '<style>
            table { border-collapse: collapse; width: 100%; font-size: 10pt; }
            th, td { border: 1px solid #000; padding: 5px; text-align: center; }
            th { background-color: #4CAF50; color: white; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            tr:hover { background-color: #ddd; }
        </style>';

$html .= '<table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Borrower</th>
                    <th>Loan Product</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Interest Method</th>
                    <th>Loan Interest %</th>
                    <th>Duration (months)</th>
                    <th>Repayment Cycle</th>
                    <th>Number of Repayments</th>
                    <th>Processing Fee %</th>
                    <th>Registration Fee %</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>';

if (!empty($loans)) {
    foreach ($loans as $loan) {
        $html .= "<tr>
                    <td>{$loan['id']}</td>
                    <td>{$loan['borrower_name']}</td>
                    <td>{$loan['loan_product_name']}</td>
                    <td>{$loan['principal']}</td>
                    <td>{$loan['interest']}</td>
                    <td>{$loan['interest_method']}</td>
                    <td>{$loan['loan_interest']}</td>
                    <td>{$loan['loan_duration']}</td>
                    <td>{$loan['repayment_cycle']}</td>
                    <td>{$loan['number_of_repayments']}</td>
                    <td>{$loan['processing_fee']}</td>
                    <td>{$loan['registration_fee']}</td>
                    <td>{$loan['total_amount']}</td>
                </tr>";
    }
} else {
    $html .= "<tr><td colspan='13' style='text-align:center; font-weight: bold;'>No loans found</td></tr>";
}

$html .= '</tbody></table>';

// Write the HTML table to the PDF
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->lastPage();

// Output PDF
$pdf->Output('loan_applications_report.pdf', 'I');
?>
