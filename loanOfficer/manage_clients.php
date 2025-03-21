<?php
include '../includes/header.php';
include '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    if ($action == 'add') {
        add_user($_POST['name'], $_POST['email'], $_POST['password'], 'client');
    } elseif ($action == 'update') {
        update_user($_POST['id'], $_POST['name'], $_POST['email'], $_POST['password']);
    } elseif ($action == 'delete') {
        delete_user($_POST['id']);
    }
}

$clients = db_connect()->query("SELECT * FROM users WHERE role = 'client'")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Manage Clients</h2>
<form method="post" action="">
    <input type="hidden" name="action" value="add">
    <input type="text" name="name" placeholder="Name" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Add Client</button>
</form>

<h3>Existing Clients</h3>
<ul>
    <?php foreach ($clients as $client): ?>
    <li>
        <?= $client['name'] ?> (<?= $client['email'] ?>)
        <form method="post" action="" style="display:inline;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $client['id'] ?>">
            <button type="submit">Delete</button>
        </form>
    </li>
    <?php endforeach; ?>
</ul>

<?php include '../includes/footer.php'; ?>
