<?php
include '../includes/functions.php';

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    if ($id > 0) {
        delete_user($id);
    }
}

header('Location: staff.php');
exit;
