<?php
require_once('TCPDF/tcpdf.php');
include 'db.php';

// Custom PDF class extending TCPDF
class PDF extends TCPDF {
    public $logo;

    // Constructor to initialize the logo
    public function __construct($logo) {
        parent::__construct();
        $this->logo = $logo;
    }

    // Custom header with logo and title
    public function Header() {
        if ($this->logo) {
            // Insert the logo in the top left corner
            $this->Image('@' . $this->logo, 10, 10, 30);
        }
        // Set font and title
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(0, 102, 204); // Set text color to blue
        $this->Cell(0, 20, 'Microfinance Reports', 0, 1, 'C');
        $this->Ln(10); // Space after the header
    }

    // Custom footer with page number
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(0, 102, 204); // Set text color to blue
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Function to fetch the logo from the database
function getLogo() {
    global $conn;
    $sql = "SELECT logo FROM settings LIMIT 1";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $imagePath = $row['logo'];
        // Ensure the image exists and is readable
        if (file_exists($imagePath)) {
            return file_get_contents($imagePath); // Read image data
        }
    }
    return null;
}

// Function to fetch all loan applications
function fetchLoans() {
    global $conn;
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
    return $conn->query($sql);
}

// Function to fetch due repayments
function fetchDueRepayments() {
    global $conn;
    $sql = "SELECT 
                borrowers.full_name AS borrower_name, 
                loan_applications.loan_product, 
                SUM(repayments.amount) AS total_amount_due, 
                repayments.repayment_date
            FROM 
                repayments
            INNER JOIN 
                loan_applications ON repayments.loan_id = loan_applications.id
            INNER JOIN 
                borrowers ON loan_applications.borrower = borrowers.id
            WHERE 
                repayments.repayment_date >= CURDATE()
            GROUP BY 
                repayments.loan_id, 
                borrowers.full_name, 
                loan_applications.loan_product, 
                repayments.repayment_date";
    return $conn->query($sql);
}

// Function to fetch overdue repayments
function fetchOverdueRepayments() {
    global $conn;
    $sql = "SELECT 
                borrowers.full_name AS borrower_name, 
                loan_applications.loan_product, 
                repayments.amount, 
                repayments.repayment_date
            FROM 
                repayments
            INNER JOIN 
                loan_applications ON repayments.loan_id = loan_applications.id
            INNER JOIN 
                borrowers ON loan_applications.borrower = borrowers.id
            WHERE 
                repayments.repayment_date < CURDATE()";
    return $conn->query($sql);
}

// Function to generate an HTML table for the fetched results
function generateTable($result) {
    $table = '<table border="1" cellpadding="4" style="border-collapse: collapse; width: 100%;">';
    $table .= '<thead>
                    <tr style="background-color: #d9edf7; text-align: center; font-weight: bold; color: #31708f;">
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
                        <th>Loan Release Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';
    $rowCounter = 0;
    while ($row = $result->fetch_assoc()) {
        $backgroundColor = ($rowCounter % 2 == 0) ? '#f2f2f2' : '#ffffff';
        $table .= '<tr style="background-color: ' . $backgroundColor . '; text-align: center;">
                    <td>' . $row['id'] . '</td>
                    <td>' . $row['borrower_name'] . '</td>
                    <td>' . $row['loan_product_name'] . '</td>
                    <td>' . $row['principal'] . '</td>
                    <td>' . $row['interest'] . '</td>
                    <td>' . $row['interest_method'] . '</td>
                    <td>' . $row['loan_interest'] . '</td>
                    <td>' . $row['loan_duration'] . '</td>
                    <td>' . $row['repayment_cycle'] . '</td>
                    <td>' . $row['number_of_repayments'] . '</td>
                    <td>' . $row['processing_fee'] . '</td>
                    <td>' . $row['registration_fee'] . '</td>
                    <td>' . $row['total_amount'] . '</td>
                    <td>' . $row['loan_release_date'] . '</td>
                    <td>' . $row['loan_status'] . '</td>
                </tr>';
        $rowCounter++;
    }
    $table .= '</tbody></table>';
    return $table;
}

// Get the logo from the database
$logo = getLogo();

// Generate and output the PDF for loan applications
if (isset($_POST['export_loans'])) {
    $result = fetchLoans();
    $pdf = new PDF($logo);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // Add vertical space between the header and the table
    $pdf->Ln(15);

    $table = generateTable($result);
    $pdf->writeHTML($table, true, false, false, false, '');
    $pdf->Output('loan_applications.pdf', 'I');
}

// Generate and output the PDF for due repayments
if (isset($_POST['export_due'])) {
    $result = fetchDueRepayments();
    $pdf = new PDF($logo);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // Add vertical space between the header and the table
    $pdf->Ln(15);

    $table = generateTable($result);
    $pdf->writeHTML($table, true, false, false, false, '');
    $pdf->Output('due_repayments.pdf', 'I');
}

// Generate and output the PDF for overdue repayments
if (isset($_POST['export_overdue'])) {
    $result = fetchOverdueRepayments();
    $pdf = new PDF($logo);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // Add vertical space between the header and the table
    $pdf->Ln(15);

    $table = generateTable($result);
    $pdf->writeHTML($table, true, false, false, false, '');
    $pdf->Output('overdue_repayments.pdf', 'I');
}
?>
