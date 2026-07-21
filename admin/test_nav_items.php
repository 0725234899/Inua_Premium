<?php
require __DIR__ . '/db.php';

function printRow($row) {
    echo "id: {$row['id']} | title: {$row['title']} | url: {$row['url']} | parent_id: {$row['parent_id']}\n";
}

// Check for parent 'Advance'
$parentTitle = 'Advance';
$parentStmt = $conn->prepare("SELECT id, title, url, parent_id FROM navigation_items WHERE title = ? LIMIT 1");
$parentStmt->bind_param('s', $parentTitle);
$parentStmt->execute();
$parentRes = $parentStmt->get_result();
$parent = $parentRes->fetch_assoc();

if ($parent) {
    echo "Found parent navigation item:\n";
    printRow($parent);
    $parent_id = (int)$parent['id'];

    // find children
    $childStmt = $conn->prepare("SELECT id, title, url, parent_id FROM navigation_items WHERE parent_id = ?");
    $childStmt->bind_param('i', $parent_id);
    $childStmt->execute();
    $childRes = $childStmt->get_result();

    if ($childRes->num_rows > 0) {
        echo "Children of 'Advance':\n";
        while ($r = $childRes->fetch_assoc()) {
            printRow($r);
        }
    } else {
        echo "No children found for 'Advance'.\n";
    }

    $childStmt->close();
} else {
    echo "Parent 'Advance' not found.\n";
}

// Also check for item titled 'advance' directly
$childTitle = 'advance';
$directStmt = $conn->prepare("SELECT id, title, url, parent_id FROM navigation_items WHERE title = ? LIMIT 1");
$directStmt->bind_param('s', $childTitle);
$directStmt->execute();
$directRes = $directStmt->get_result();
$direct = $directRes->fetch_assoc();

if ($direct) {
    echo "Found navigation item titled 'advance':\n";
    printRow($direct);
} else {
    echo "Navigation item titled 'advance' not found.\n";
}

$parentStmt->close();
$directStmt->close();
$conn->close();

?>