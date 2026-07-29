<?php
include '../includes/functions.php';

if (isset($_POST['updateStaff'])) {
    $id = intval($_POST['staffId'] ?? 0);
    $name = trim($_POST['officerName'] ?? '');
    $email = strtolower(trim($_POST['officerEmail'] ?? ''));
    $phone = trim($_POST['officerPhone'] ?? '');
    $password = trim($_POST['officerPassword'] ?? '');
    $area = trim($_POST['areaId'] ?? '');
    $role = trim($_POST['roleId'] ?? '');

    if ($id <= 0 || $name === '' || $email === '' || $phone === '' || $area === '' || $role === '') {
        header('Location: staff.php');
        exit;
    }

    $conn = db_connect();
    try {
        $conn->beginTransaction();

        $oldStmt = $conn->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $oldStmt->execute([$id]);
        $oldUser = $oldStmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldUser) {
            $conn->rollBack();
            header('Location: staff.php');
            exit;
        }

        $oldEmail = $oldUser['email'];

        $sql = 'UPDATE users SET name = ?, email = ?, phone = ?, area = ?, role_id = ?';
        if ($password !== '') {
            $sql .= ', password = ?';
        }
        $sql .= ' WHERE id = ?';

        $params = [$name, $email, $phone, $area, $role];
        if ($password !== '') {
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $params[] = $id;

        $stmt = $conn->prepare($sql);
        if (!$stmt->execute($params)) {
            $conn->rollBack();
            header('Location: staff.php');
            exit;
        }

        if ($email !== $oldEmail) {
            $updateBorrowers = $conn->prepare('UPDATE borrowers SET loan_officer = ? WHERE loan_officer = ?');
            $updateBorrowers->execute([$email, $oldEmail]);
        }

        $conn->commit();

        // Send a welcome / update notification email to the staff member
        sendWelcomeEmailToStaff($name, $email, $role);

        header('Location: staff.php');
        exit;
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Error updating staff: ' . $e->getMessage());
    }
}

header('Location: staff.php');
exit;
