<?php
include '../includes/functions.php';

if (isset($_POST['addStaff'])) {
    $name = trim($_POST['officerName'] ?? '');
    $email = trim($_POST['officerEmail'] ?? '');
    $phone = trim($_POST['officerPhone'] ?? '');
    $password = $_POST['officerPassword'] ?? '';
    $area = $_POST['areaId'] ?? null;
    $role = (int) ($_POST['roleId'] ?? 0);
    $basicSalary = floatval($_POST['basicSalary'] ?? 0);

    if ($name !== '' && $email !== '' && $password !== '' && $role > 0 && add_user($name, $email, $password, $role, $area, $phone, $basicSalary)) {
        sendWelcomeEmailToStaff($name, $email, $role);
        header('Location: staff.php');
        exit;
    }

    echo '<script>alert("Staff not added.")</script>';
}
?>
