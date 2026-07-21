<?php
require __DIR__ . '/db.php';
$ids = array(28,29);
foreach ($ids as $id) {
    $res = $conn->query("SELECT role_id FROM navigation_item_roles WHERE navigation_item_id = $id");
    echo "nav $id: ";
    if ($res && $res->num_rows) {
        while ($r = $res->fetch_assoc()) {
            echo $r['role_id'] . ' ';
        }
    } else {
        echo "no mapping";
    }
    echo PHP_EOL;
}
?>