<?php require_once __DIR__ . '/../auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FitGym Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/fitgym/pictures/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/fitgym/css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="brand">
            <img src="/fitgym/pictures/favicon.png" alt="FitGym">
            <span>FitGym Admin</span>
        </div>
        <nav class="admin-nav">
            <a href="/fitgym/php/admin/index.php">Dashboard</a>
            <a href="/fitgym/php/admin/users.php">Users</a>
            <a href="/fitgym/php/admin/trainers.php">Trainers</a>
            <a href="/fitgym/php/admin/classes.php">Classes</a>
            <a href="/fitgym/php/admin/bookings.php">Bookings</a>
            <a href="/fitgym/php/admin/recommendations.php">Recommendations</a>
            <a href="/fitgym/php/admin/tips.php">Tips</a>
            <a href="/fitgym/php/admin/content.php">Content</a>
            <a href="/fitgym/php/admin/reports.php">Reports</a>
            <a href="/fitgym/php/admin/notifications.php">Notifications</a>
            <a href="/fitgym/php/admin/reviews.php">Feedback</a>
            <a href="/fitgym/php/admin/settings.php">Settings</a>
        </nav>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <div>
                <h1>Admin Panel</h1>
                <p>Manage FitGym operations</p>
            </div>
            <div class="topbar-actions">
                <span class="admin-user"><?= esc($_SESSION['admin_name'] ?? 'Admin') ?></span>
                <a class="btn-outline" href="/fitgym/php/admin/logout.php">Logout</a>
            </div>
        </header>
        <main class="admin-content">
