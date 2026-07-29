<!-- ======= Header ======= -->
<header id="header" class="header" style="background-color:red">
        <div class="container-fluid d-flex align-items-center justify-content-between">
            <a href="index.html" class="logo d-flex align-items-center me-auto me-xl-0">
                <h1 class="sitename">Inua Premium Services</h1><span>.</span>
            </a>

            <nav id="navmenu" class="navmenu d-flex align-items-center justify-content-end">
                <button id="sidebarToggle" class="mobile-nav-toggle btn btn-link text-white p-0 me-3" type="button" aria-label="Toggle sidebar" style="font-size: 24px;">
                    <i class="bi bi-list"></i>
                </button>
                <ul class="d-flex align-items-center mb-0" style="gap: 20px; list-style: none; margin: 0; padding: 0;">
                    <li><a href="admin.php" class="active">Admin</a></li>
                    <li><a href="index.php?logout=1">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <style>
        .sidebar {
            transition: transform 0.3s ease;
        }
        .sidebar.collapsed {
            transform: translateX(-100%);
        }
        .main.sidebar-collapsed,
        main.sidebar-collapsed,
        #mainContent.sidebar-collapsed {
            margin-left: 0 !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toggleButton = document.getElementById('sidebarToggle');
            var sidebar = document.querySelector('.sidebar');
            var mainContent = document.querySelector('.main, main, #mainContent');

            if (!toggleButton || !sidebar) {
                return;
            }

            toggleButton.addEventListener('click', function () {
                sidebar.classList.toggle('collapsed');
                if (mainContent) {
                    mainContent.classList.toggle('sidebar-collapsed');
                }
            });
        });
    </script>
