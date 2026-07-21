<?php
include '../includes/functions.php';

if (isset($_POST['updateStaff'])) {
    $id = intval($_POST['staffId'] ?? 0);
    $name = trim($_POST['officerName'] ?? '');
    $email = trim($_POST['officerEmail'] ?? '');
    $phone = trim($_POST['officerPhone'] ?? '');
    $password = trim($_POST['officerPassword'] ?? '');
    $area = trim($_POST['areaId'] ?? '');
    $role = trim($_POST['roleId'] ?? '');

    if ($id <= 0 || $name === '' || $email === '' || $phone === '' || $area === '' || $role === '') {
        header('Location: staff.php');
        exit;
    }

    $conn = db_connect();
    $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, phone = ?, area = ?, role_id = ?' . ($password !== '' ? ', password = ?' : '') . ' WHERE id = ?');

    $params = [$name, $email, $phone, $area, $role];
    if ($password !== '') {
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }
    $params[] = $id;

    if ($stmt->execute($params)) {
        header('Location: staff.php');
        exit;
    }
}

header('Location: staff.php');
exit;
