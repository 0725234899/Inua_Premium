<?php
session_start();
require_once '../includes/functions.php';

$conn = db_connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $branch = trim($_POST['branch'] ?? '');
    $savingsProduct = trim($_POST['savings_product'] ?? '');
    $openingBalance = floatval($_POST['opening_balance'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($fullName !== '' && $phone !== '') {
        $stmt = $conn->prepare("
            CREATE TABLE IF NOT EXISTS savings_clients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(150) NOT NULL,
                id_number VARCHAR(50) DEFAULT NULL,
                phone VARCHAR(30) NOT NULL,
                email VARCHAR(150) DEFAULT NULL,
                branch VARCHAR(100) DEFAULT NULL,
                savings_product VARCHAR(100) DEFAULT NULL,
                opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute();

        $insertStmt = $conn->prepare("
            INSERT INTO savings_clients (full_name, id_number, phone, email, branch, savings_product, opening_balance, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insertStmt->execute([$fullName, $idNumber, $phone, $email, $branch, $savingsProduct, $openingBalance, $notes]);
        $successMessage = 'Savings client added successfully.';
    } else {
        $errorMessage = 'Please provide the client name and phone number.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Savings Client</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .main { margin-left: 270px; padding: 24px; }
        .card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<main class="main">
    <div class="container-fluid">
        <div class="card p-4">
            <h2 class="mb-3">Add Savings Client</h2>
            <p class="text-muted">Capture clients who want to open a savings account.</p>

            <?php if (!empty($successMessage)) : ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            <?php if (!empty($errorMessage)) : ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ID Number</label>
                        <input type="text" name="id_number" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Branch</label>
                        <input type="text" name="branch" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Savings Product</label>
                        <input type="text" name="savings_product" class="form-control" placeholder="e.g. Basic Savings">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Opening Balance</label>
                        <input type="number" step="0.01" name="opening_balance" class="form-control" value="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-4">Save Client</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
