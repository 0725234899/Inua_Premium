<?php
header('Content-Type: application/json');
include 'db.php';

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
if ($query === '') {
	echo json_encode([]);
	exit;
}

$q = "%" . $query . "%";
$sql = "SELECT b.id, b.full_name, b.mobile, b.unique_number, COALESCE(u.name, '') AS loan_officer_name
		FROM borrowers b
		LEFT JOIN users u ON b.loan_officer = u.email
		WHERE b.full_name LIKE ? OR b.mobile LIKE ? OR b.unique_number LIKE ?
		LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->bind_param('sss', $q, $q, $q);
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while ($row = $res->fetch_assoc()) {
	$out[] = [
		'id' => $row['id'],
		'full_name' => $row['full_name'],
		'mobile' => $row['mobile'],
		'unique_number' => $row['unique_number'],
		'loan_officer_name' => $row['loan_officer_name']
	];
}

echo json_encode($out);
?>
