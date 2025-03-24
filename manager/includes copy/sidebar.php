<?php
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
            $html .= '<a class="nav-link' . ($hasSubItems ? ' collapsed' : '') . '" href="' . $item['url'] . '"';
            if ($hasSubItems) {
                $html .= ' data-bs-toggle="collapse" data-bs-target="#collapse-' . $item['id'] . '" aria-expanded="false" aria-controls="collapse-' . $item['id'] . '"';
            }
            $html .= '>';
            if ($item['icon']) {
                $html .= '<i class="' . $item['icon'] . '" title="' . $item['title'] . '"></i>';
            }
            $html .= '<span>' . $item['title'] . '</span>';
            $html .= '</a>';

            if ($hasSubItems) {
                $html .= '<ul id="collapse-' . $item['id'] . '" class="collapse list-unstyled">';
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
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        .sidebar {
            background-color: #f8f9fa;
            padding: 10px;
            height: 100vh;
            width: 250px; /* Adjust width as needed */
            position: fixed;
        }

        .sidebar .nav-link {
            color: #333;
            display: block;
            padding: 10px;
            text-decoration: none;
        }

        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background-color: #007bff;
            color: #fff;
        }

        .sidebar .collapse {
            margin-left: 20px; /* Indentation for sub-menu items */
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <ul class="sidebar-nav" id="sidebar-nav">
            <?= renderNavItems($navItems) ?>
        </ul>
    </aside><!-- End Sidebar -->

    <!-- Include Bootstrap and jQuery JS -->
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
