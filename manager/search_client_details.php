<?php
header('Content-Type: application/json');
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$query = trim($_GET['query'] ?? '');
if ($query === '') {
    echo json_encode(['error' => 'Enter a client name or phone number.']);
    exit();
}

$search = '%' . $query . '%';
$sql_client = "SELECT b.id, b.full_name, b.mobile, b.unique_number, COALESCE(u.name, '') AS loan_officer_name
               FROM borrowers b
               LEFT JOIN users u ON b.loan_officer = u.email
               WHERE b.full_name LIKE ? OR b.mobile LIKE ?
               ORDER BY b.full_name
               LIMIT 10";
$stmt_client = $conn->prepare($sql_client);
$stmt_client->bind_param('ss', $search, $search);
$stmt_client->execute();
$clientResult = $stmt_client->get_result();

$clients = [];
$sql_loans = "SELECT id, principal, total_amount, loan_duration, loan_release_date,
                    COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = loan_applications.id), 0) AS total_paid,
                    GREATEST(0, total_amount - COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = loan_applications.id), 0)) AS dues_arrears,
                    CASE
                        WHEN total_amount - COALESCE((SELECT SUM(r.paid) FROM repayments r WHERE r.loan_id = loan_applications.id), 0) <= 0 THEN 'Cleared'
                        ELSE 'Not Cleared'
                    END AS loan_status
             FROM loan_applications
             WHERE borrower = ?
             ORDER BY loan_release_date DESC, id DESC";
$stmt_loans = $conn->prepare($sql_loans);

while ($client = $clientResult->fetch_assoc()) {
    $client['loans'] = [];
    $stmt_loans->bind_param('i', $client['id']);
    $stmt_loans->execute();
    $loanResult = $stmt_loans->get_result();

    while ($loan = $loanResult->fetch_assoc()) {
        $client['loans'][] = $loan;
    }
    $clients[] = $client;
}

echo json_encode($clients);
?>
