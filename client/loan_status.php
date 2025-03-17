<?php
include '../includes/header.php';
include '../includes/functions.php';

$loans = db_connect()->query("SELECT * FROM loans WHERE client_id = 1")->fetchAll(PDO::FETCH_ASSOC); // Change this to dynamic value

?>

<h2>Loan Status</h2>
<ul>
    <?php foreach ($loans as $loan): ?>
    <li>
        <?= $loan['amount'] ?> (<?= $loan['term'] ?> months at <?= $loan['interest_rate'] ?>%): <?= $loan['status'] ?>
    </li>
    <?php endforeach; ?>
</ul>

<?php include '../includes/footer.php'; ?>
