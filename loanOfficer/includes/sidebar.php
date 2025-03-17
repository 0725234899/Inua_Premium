<?php
include 'functions.php';

$userId = 1; // Assume a logged-in user with ID 1
$roleId = getUserRole($userId);
$navItems = getNavigationItems($roleId);

function renderNavItems($items, $parentId = NULL) {
    $html = '';
    foreach ($items as $item) {
        if ($item['parent_id'] == $parentId) {
            $subItems = array_filter($items, function($i) use ($item) {
                return $i['parent_id'] == $item['id'];
            });

            $hasSubItems = !empty($subItems);
            $html .= '<li class="nav-item">';
            $html .= '<a class="nav-link" href="' . $item['url'] . '"' . ($hasSubItems ? ' data-toggle="collapse"' : '') . '>';
            if ($item['icon']) {
                $html .= '<i class="' . $item['icon'] . '" title="' . $item['title'] . '"></i>';
            }
            $html .= '<span>' . $item['title'] . '</span>';
            $html .= '</a>';

            if ($hasSubItems) {
                $html .= '<ul class="collapse list-unstyled">';
                $html .= renderNavItems($items, $item['id']);
                $html .= '</ul>';
            }

            $html .= '</li>';
        }
    }
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Microfinance Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        .sidebar {
            background-color: #f8f9fa;
            padding: 10px;
            height: 100vh;
        }

        .sidebar .nav-link {
            color: #333;
        }

        .sidebar .nav-link.active {
            background-color: #007bff;
            color: #fff;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <ul class="sidebar-nav" id="sidebar-nav">
            <?= renderNavItems($navItems) ?>
        </ul>
    </aside><!-- End Sidebar -->

    <!-- Include Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
