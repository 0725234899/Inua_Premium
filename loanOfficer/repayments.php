<?php
include '../includes/header.php';
include '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    track_repayment($_POST['loan_id'], $_POST['amount']);
}

$disbursed_loans = db_connect()->query("SELECT loans.*, users.name AS client_name FROM loans JOIN users ON loans.client_id = users.id WHERE status = 'Disbursed'")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Repayments</h2>
<ul>
    <?php foreach ($disbursed_loans as $loan): ?>
    <li>
        <?= $loan['client_name'] ?>: <?= $loan['amount'] ?> (<?= $loan['term'] ?> months at <?= $loan['interest_rate'] ?>%)
        <form method="post" action="" style="display:inline;">
            <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
            <input type="number" name="amount" placeholder="Repayment Amount" required>
            <button type="submit">Repay</button>
        </form>
    </li>
    <?php endforeach; ?>
</ul>

<?php include '../includes/footer.php'; ?>
