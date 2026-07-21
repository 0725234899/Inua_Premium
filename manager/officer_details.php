<?php
include_once("../includes/functions.php");
include_once("db.php"); // Ensure database connection is included

// Get loan officer ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid loan officer ID.");
}

$officer_id = intval($_GET['id']); // Ensure ID is an integer
$messages = [];
$errors = [];
$editingClientId = isset($_GET['edit_client']) ? intval($_GET['edit_client']) : 0;
$editClient = null;

if (isset($_GET['delete_client'])) {
    $deleteClientId = intval($_GET['delete_client']);
    if ($deleteClientId > 0) {
        $conn->begin_transaction();
        try {
            $stmtLoanApps = $conn->prepare("SELECT id FROM loan_applications WHERE borrower = ?");
            $stmtLoanApps->bind_param("i", $deleteClientId);
            $stmtLoanApps->execute();
            $loanAppIds = $stmtLoanApps->get_result()->fetch_all(MYSQLI_ASSOC);

            if (!empty($loanAppIds)) {
                $loanAppIdList = array_map(function ($row) { return (int) $row['id']; }, $loanAppIds);
                $placeholders = implode(',', array_fill(0, count($loanAppIdList), '?'));
                $stmtRepay = $conn->prepare("DELETE FROM repayments WHERE loan_id IN ($placeholders)");
                $stmtRepay->bind_param(str_repeat('i', count($loanAppIdList)), ...$loanAppIdList);
                $stmtRepay->execute();

                $stmtDeleteApps = $conn->prepare("DELETE FROM loan_applications WHERE borrower = ?");
                $stmtDeleteApps->bind_param("i", $deleteClientId);
                $stmtDeleteApps->execute();
            }

            $stmtDeleteClient = $conn->prepare("DELETE FROM borrowers WHERE id = ?");
            $stmtDeleteClient->bind_param("i", $deleteClientId);
            $stmtDeleteClient->execute();
            $conn->commit();
            $messages[] = 'Client removed successfully.';
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Unable to remove the client: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_client'])) {
    $clientId = intval($_POST['client_id'] ?? 0);
    $loanOfficerEmail = trim($_POST['loanOfficer'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $businessName = trim($_POST['business_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $guarantorName = trim($_POST['guarantor_name'] ?? '');
    $guarantorPhone = trim($_POST['guarantor_phone'] ?? '');

    if ($clientId <= 0) {
        $errors[] = 'The client reference is missing.';
    }
    if ($loanOfficerEmail === '') {
        $errors[] = 'Please select a loan officer.';
    }
    if ($fullName === '') {
        $errors[] = 'Please enter the client full name.';
    }
    if ($idNumber === '') {
        $errors[] = 'Please enter the ID number.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE borrowers SET full_name = ?, business_name = ?, unique_number = ?, mobile = ?, email = ?, loan_officer = ?, guarantor_name = ?, guarantor_phone = ? WHERE id = ?");
        $stmt->bind_param("ssssssssi", $fullName, $businessName, $idNumber, $mobile, $email, $loanOfficerEmail, $guarantorName, $guarantorPhone, $clientId);
        if ($stmt->execute()) {
            $messages[] = 'Client details updated successfully.';
            $editingClientId = 0;
        } else {
            $errors[] = 'Unable to update client details.';
        }
    }
}

if ($editingClientId > 0) {
    $stmtEdit = $conn->prepare("SELECT id, full_name, business_name, unique_number, mobile, email, loan_officer, guarantor_name, guarantor_phone FROM borrowers WHERE id = ? LIMIT 1");
    $stmtEdit->bind_param("i", $editingClientId);
    $stmtEdit->execute();
    $editClient = $stmtEdit->get_result()->fetch_assoc();
}

// Fetch loan officer details using INNER JOIN to get role name
$sql_officer = "SELECT u.*, r.name AS role_name 
                FROM users u 
                INNER JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ?";
$stmt = $conn->prepare($sql_officer);
$stmt->bind_param("i", $officer_id);
$stmt->execute();
$result_officer = $stmt->get_result();
$officer = $result_officer->fetch_assoc();

if (!$officer) {
    die("Loan officer not found.");
}

$loanOfficersStmt = $conn->prepare("SELECT id, name AS full_name, email FROM users WHERE role_id = '2' ORDER BY name");
$loanOfficersStmt->execute();
$loanOfficers = $loanOfficersStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch clients assigned to this officer using INNER JOIN
$sql_clients = "SELECT b.id, b.full_name, b.email, b.mobile 
                FROM borrowers b 
                INNER JOIN users u ON b.loan_officer = u.email 
                WHERE u.id = ?";
$stmt_clients = $conn->prepare($sql_clients);
$stmt_clients->bind_param("i", $officer_id);
$stmt_clients->execute();
$result_clients = $stmt_clients->get_result();

// Calculate total loan amount using INNER JOIN
$sql_total_loan = "SELECT SUM(r.amount) AS total_loan_amount 
                   FROM repayments r 
                   INNER JOIN loan_applications l ON r.loan_id = l.id
                   INNER JOIN borrowers b ON l.borrower = b.id 
                   INNER JOIN users u ON b.loan_officer = u.email
                   WHERE b.loan_officer = ?";
$stmt_total_loan = $conn->prepare($sql_total_loan);
$stmt_total_loan->bind_param("i", $officer_id);
$stmt_total_loan->execute();
$total_loan_amount = $stmt_total_loan->get_result()->fetch_assoc()['total_loan_amount'] ?? 0;

// Calculate overdue loan amount using INNER JOIN
$sql_overdue_loan = "SELECT SUM(r.amount) AS overdue_loan_amount 
                     FROM repayments r 
                     INNER JOIN loan_applications l ON r.loan_id = l.id
                     INNER JOIN borrowers b ON l.borrower = b.id 
                     INNER JOIN users u ON b.loan_officer = u.email
                     WHERE u.id = ? 
                     AND r.repayment_date < CURDATE() 
                     AND r.paid < r.amount";
$stmt_overdue_loan = $conn->prepare($sql_overdue_loan);
$stmt_overdue_loan->bind_param("i", $officer_id);
$stmt_overdue_loan->execute();
$overdue_loan_amount = $stmt_overdue_loan->get_result()->fetch_assoc()['overdue_loan_amount'] ?? 0;

// Calculate Portfolio at Risk (PAR)
$par = ($total_loan_amount > 0) ? ($overdue_loan_amount / $total_loan_amount) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Loan Officer Details</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
     <style>
        body {
            background-color: #ffffff;
            font-family: 'Open Sans', sans-serif;
            color: #212529;
            margin: 0;
        }
        .container {
            margin-top: 30px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 20px;
        }
        .header {
            background-color: #e84545;
            color: #ffffff;
            padding: 15px;
            text-align: center;
            font-size: 24px;
        }
        .table-container {
            overflow-x: auto;
        }
        .table th {
            background-color: #e84545;
            color: white;
        }
        .btn-back {
            background-color: #e84545;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
        }
        .btn-back:hover {
            background-color: #d23838;
            color: white;
        }
    </style>
</head>
<body>
    <?php include("includes/header.php"); ?>
    <div class="container">
        <h1 class="mb-4">Loan Officer Details</h1>
        <div class="row">
            <div class="col-md-6">
                <div class="card p-3">
                    <h3>Officer Information</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($officer['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($officer['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($officer['phone']); ?></p>
                    <p><strong>Role:</strong> <?php echo htmlspecialchars($officer['role_name']); ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-3">
                    <h3>Portfolio at Risk (PAR)</h3>
                    <p><strong>Total Loan Amount:</strong> KSh <?php echo number_format($total_loan_amount, 2); ?></p>
                    <p><strong>Overdue Loan Amount:</strong> KSh <?php echo number_format($overdue_loan_amount, 2); ?></p>
                    <p><strong>PAR:</strong> <?php echo number_format($par, 2); ?>%</p>
                </div>
            </div>
        </div>

        <h2 class="mt-4">Assigned Clients</h2>
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
        <?php endforeach; ?>

        <?php if ($editClient): ?>
            <div class="card p-3 mb-3">
                <h4>Edit Client</h4>
                <form action="officer_details.php?id=<?php echo (int)$officer_id; ?>&edit_client=<?php echo (int)$editingClientId; ?>" method="post">
                    <input type="hidden" name="update_client" value="1">
                    <input type="hidden" name="client_id" value="<?php echo (int)$editClient['id']; ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Loan Officer</label>
                            <select name="loanOfficer" class="form-control" required>
                                <option value="">Select loan officer</option>
                                <?php foreach ($loanOfficers as $loanOfficer): ?>
                                    <option value="<?php echo htmlspecialchars($loanOfficer['email'], ENT_QUOTES); ?>"
                                        <?php echo (($editClient['loan_officer'] ?? '') === $loanOfficer['email']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loanOfficer['full_name'] . ' (' . $loanOfficer['email'] . ')', ENT_QUOTES); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($editClient['full_name'] ?? '', ENT_QUOTES); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="mobile" class="form-control" value="<?php echo htmlspecialchars($editClient['mobile'] ?? '', ENT_QUOTES); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ID Number</label>
                            <input type="text" name="id_number" class="form-control" value="<?php echo htmlspecialchars($editClient['unique_number'] ?? '', ENT_QUOTES); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editClient['email'] ?? '', ENT_QUOTES); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Business Name</label>
                            <input type="text" name="business_name" class="form-control" value="<?php echo htmlspecialchars($editClient['business_name'] ?? '', ENT_QUOTES); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Guarantor Name</label>
                            <input type="text" name="guarantor_name" class="form-control" value="<?php echo htmlspecialchars($editClient['guarantor_name'] ?? '', ENT_QUOTES); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Guarantor Phone</label>
                            <input type="text" name="guarantor_phone" class="form-control" value="<?php echo htmlspecialchars($editClient['guarantor_phone'] ?? '', ENT_QUOTES); ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="officer_details.php?id=<?php echo (int)$officer_id; ?>" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_clients->num_rows > 0): ?>
                        <?php while ($client = $result_clients->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($client['id']); ?></td>
                                <td><a href="client_details.php?id=<?php echo htmlspecialchars($client['id']); ?>"><?php echo htmlspecialchars($client['full_name']); ?></a></td>

                                <td><?php echo htmlspecialchars($client['email']); ?></td>
                                <td><?php echo htmlspecialchars($client['mobile']); ?></td>
                                <td>
                                    <a href="officer_details.php?id=<?php echo (int)$officer_id; ?>&edit_client=<?php echo (int)$client['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                    <a href="officer_details.php?id=<?php echo (int)$officer_id; ?>&delete_client=<?php echo (int)$client['id']; ?>" class="btn btn-sm btn-danger ms-1" onclick="return confirm('Remove this client from the database?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No clients assigned to this officer.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="view_loanOfficers.php" class="btn btn-secondary mt-3">Back to Loan Officers</a>
    </div>
</body>
</html>
