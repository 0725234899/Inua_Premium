<?php
require_once 'db.php';

// Filter parameters
$status_filter = $_GET['status'] ?? 'ALL';

if ($status_filter !== 'ALL') {
    $stmt = $pdo->prepare("SELECT * FROM advances WHERE status = ? ORDER BY applied_at DESC");
    $stmt->execute([$status_filter]);
} else {
    $stmt = $pdo->query("SELECT * FROM advances ORDER BY applied_at DESC");
}

$advances = $stmt->fetchAll();

// Summary calculations
$total_amount = array_sum(array_column($advances, 'amount'));
$total_count = count($advances);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Advance Reports</title>
</head>
<body>
    <h2>Advance Summary & Filter Report</h2>
    
    <form method="GET">
        <label>Filter by Status:</label>
        <select name="status" onchange="this.form.submit()">
            <option value="ALL" <?= $status_filter === 'ALL' ? 'selected' : ''; ?>>All</option>
            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
    </form>
    <br>

    <div style="border: 1px solid #ccc; padding: 10px; width: 300px; mb-10;">
        <strong>Total Records:</strong> <?= $total_count; ?><br>
        <strong>Total Amount:</strong> $<?= number_format($total_amount, 2); ?>
    </div>
    <br>

    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Staff Name</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Applied Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($advances)): ?>
                <?php foreach ($advances as $advance): ?>
                    <tr>
                        <td><?= htmlspecialchars($advance['id']); ?></td>
                        <td><?= htmlspecialchars($advance['staff_name']); ?></td>
                        <td>$<?= number_format($advance['amount'], 2); ?></td>
                        <td><?= htmlspecialchars($advance['status']); ?></td>
                        <td><?= htmlspecialchars($advance['applied_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No report data found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <br>
    <a href="view_advances.php">Back to Main List</a>
</body>
</html>