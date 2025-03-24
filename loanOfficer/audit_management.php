<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
           body {
            background-color: #ffffff;
            color: #212529;
            font-family: 'Open Sans', sans-serif;
            margin: 0;
        }

        .header {
            background-color: #e84545;
            color: #ffffff;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header .logo h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
        }

        .header .navmenu ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
        }

        .header .navmenu ul li {
            margin-right: 20px;
        }

        .header .navmenu ul li a {
            color: #ffffff;
            text-decoration: none;
        }

        .header .navmenu ul li a.active, .header .navmenu ul li a:hover {
            color: #e84545;
        }

        .sidebar {
            background-color: #ffffff;
            color: #3a3939;
            padding: 20px;
            width: 250px;
            position: fixed;
            height: 100%;
            overflow: auto;
        }

        .sidebar .nav-item .nav-link {
            color: #3a3939;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }

        .sidebar .nav-item .nav-link.active, .sidebar .nav-item .nav-link:hover {
            color: #e84545;
        }

        .main {
            margin-left: 270px;
            padding: 20px;
        }

        .table-container {
            overflow-x: auto;
        }

        .container h1 {
            font-size: 28px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .form-group button {
            background-color: #e84545;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }

        .form-group button:hover {
            background-color: #d73434;
        }

        .section {
            padding: 20px 0;
        }

        .container {
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .table th, .table td {
            text-align: center;
        }

        .table th {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
<?php
        include '../includes/functions.php';
       include '../includes/sidebar.php'; 
        include 'includes/header.php';
        ?>
        <main class="main">
        <section class="section">
    <div class="container">
        <h1>Audit Management</h1>
        <form id="search-form" class="mb-4">
            <div class="row">
                <div class="col-md-3 form-group">
                    <label for="from-date">From Date:</label>
                    <input type="date" id="from-date" name="from-date" class="form-control">
                </div>
                <div class="col-md-3 form-group">
                    <label for="from-time">From Time:</label>
                    <input type="time" id="from-time" name="from-time" class="form-control">
                </div>
                <div class="col-md-3 form-group">
                    <label for="to-date">To Date:</label>
                    <input type="date" id="to-date" name="to-date" class="form-control">
                </div>
                <div class="col-md-3 form-group">
                    <label for="to-time">To Time:</label>
                    <input type="time" id="to-time" name="to-time" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label for="staff">Staff:</label>
                <select id="staff" name="staff" class="form-control">
                    <option value="">All</option>
                    <option value="antonio cheruiyot">antonio cheruiyot</option>
                    <option value="vincent kipkurui">vincent kipkurui</option>
                </select>
            </div>

            <div class="form-group">
                <button type="button" class="btn btn-primary" onclick="fetchLogs()">Search</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped" id="audit-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Logged in Branch</th>
                        <th>Staff</th>
                        <th>Category</th>
                        <th>Message</th>
                        <th>Action</th>
                        <th>IP</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Log entries will be inserted here -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function fetchLogs() {
            const fromDate = document.getElementById('from-date').value;
            const fromTime = document.getElementById('from-time').value;
            const toDate = document.getElementById('to-date').value;
            const toTime = document.getElementById('to-time').value;
            const staff = document.getElementById('staff').value;

            const params = new URLSearchParams({
                fromDate: fromDate,
                fromTime: fromTime,
                toDate: toDate,
                toTime: toTime,
                staff: staff
            }).toString();

            fetch(`/path/to/your/api/endpoint?${params}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.querySelector('#audit-table tbody');
                tbody.innerHTML = ''; // Clear existing rows

                data.logs.forEach(log => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${log.time}</td>
                        <td>${log.branch}</td>
                        <td>${log.staff}</td>
                        <td>${log.category}</td>
                        <td>${log.message}</td>
                        <td>${log.action}</td>
                        <td>${log.ip}</td>
                        <td><a href="${log.viewUrl}" class="btn btn-info btn-sm">View</a></td>
                    `;
                    tbody.appendChild(row);
                });
            })
            .catch(error => console.error('Error fetching logs:', error));
        }

        function autoReload() {
            fetchLogs();
            setInterval(fetchLogs, 30000); // Reload every 30 seconds
        }

        document.addEventListener('DOMContentLoaded', () => {
            autoReload();
        });
    </script>
</body>
</html>
