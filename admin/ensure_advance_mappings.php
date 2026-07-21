<?php
require __DIR__ . '/db.php';
// Find child items titled 'advance' with a parent
$stmt = $conn->prepare("SELECT id FROM navigation_items WHERE title = ? AND parent_id IS NOT NULL");
$title = 'advance';
$stmt->bind_param('s', $title);
$stmt->execute();
$res = $stmt->get_result();
$ids = [];
while ($r = $res->fetch_assoc()) {
    $ids[] = $r['id'];
}
$stmt->close();

$defaultRole = 1;
foreach ($ids as $id) {
    $check = $conn->prepare("SELECT 1 FROM navigation_item_roles WHERE navigation_item_id = ? AND role_id = ? LIMIT 1");
    $check->bind_param('ii', $id, $defaultRole);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO navigation_item_roles (navigation_item_id, role_id) VALUES (?, ?)");
        $ins->bind_param('ii', $id, $defaultRole);
        $ins->execute();
        echo "Inserted mapping for nav $id -> role $defaultRole\n";
        $ins->close();
    } else {
        echo "Mapping already exists for nav $id -> role $defaultRole\n";
    }
    $check->close();
}

$conn->close();
?>