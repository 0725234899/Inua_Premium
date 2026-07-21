<?php
$conn = new mysqli('localhost','root','','microfinance');
if ($conn->connect_error) { die($conn->connect_error); }

foreach (['navigation_items','roles','navigation_item_roles'] as $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    echo $table . ' => ' . ($res->num_rows ? 'exists' : 'missing') . PHP_EOL;
}

$res = $conn->query('DESCRIBE navigation_items');
while ($row = $res->fetch_assoc()) {
    echo 'navigation_items: ' . $row['Field'] . ' ' . $row['Type'] . PHP_EOL;
}

$res = $conn->query('DESCRIBE roles');
while ($row = $res->fetch_assoc()) {
    echo 'roles: ' . $row['Field'] . ' ' . $row['Type'] . PHP_EOL;
}

$res = $conn->query('SELECT * FROM roles ORDER BY id LIMIT 10');
while ($row = $res->fetch_assoc()) {
    echo 'role: ' . print_r($row, true) . PHP_EOL;
}
