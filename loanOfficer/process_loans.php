<?php
include '../includes/header.php';
include '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    if ($action == 'disburse') {
        disburse_loan($_POST['id']);
    }
}

$approved_loans = db_connect()->query("SELECT loans.*, users.name AS client_name FROM loans JOIN users ON loans.client_id = users.id WHERE status = 'Approved'")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Process Loans</h2>
<h3>Approved Loans</h3>
<ul>
    <?php foreach ($approved_loans as $loan): ?>
    <li>
        <?= $loan['client_name'] ?>: <?= $loan['amount'] ?> (<?= $loan['term'] ?> months at <?= $loan['interest_rate'] ?>%)
        <form method="post" action="" style="display:inline;">
            <input type="hidden" name="action" value="disburse">
            <input type="hidden" name="id" value="<?= $loan['id'] ?>">
            <button type="submit">Disburse</button>
        </form>
    </li>
    <?php endforeach; ?>
</ul>

<?php include '../includes/footer.php'; ?>
