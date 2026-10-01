<?php
$pageTitle = $pageTitle ?? 'Khelmandu';
$activePage = $activePage ?? '';
$viewer = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f3f3ed">
    <title><?= e($pageTitle) ?> · Khelmandu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= !$viewer ? 'index.php' : ($viewer['role'] === 'admin' ? 'admin.php' : ($viewer['role'] === 'player' ? 'player.php' : 'dashboard.php')) ?>" aria-label="Khelmandu home"><span class="brand-mark">K</span><span>Khelmandu</span></a>
    <?php if ($viewer): ?>
        <nav class="main-nav" aria-label="Main navigation">
            <?php if ($viewer['role'] === 'admin'): ?>
            <a class="<?= $activePage === 'admin' ? 'active' : '' ?>" href="admin.php">Admin desk</a>
            <?php elseif ($viewer['role'] === 'player'): ?>
            <a class="<?= $activePage === 'player' ? 'active' : '' ?>" href="player.php">Player home</a>
            <a href="player.php#find-team">Find a team</a>
            <?php else: ?>
            <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">Overview</a>
            <a class="<?= $activePage === 'teams' ? 'active' : '' ?>" href="teams.php">Find a team</a>
            <a class="<?= $activePage === 'matches' ? 'active' : '' ?>" href="matches.php">Matches</a>
            <a class="<?= $activePage === 'venues' ? 'active' : '' ?>" href="venues.php">Grounds</a>
            <a class="<?= $activePage === 'bookings' ? 'active' : '' ?>" href="bookings.php">Bookings</a>
            <?php endif; ?>
        </nav>
        <div class="account-menu">
            <a class="account-name" href="<?= $viewer['role'] === 'player' ? 'player.php#profile' : ($viewer['role'] === 'admin' ? 'admin.php' : 'team.php') ?>"><?= e($viewer['team_name'] ?: $viewer['name']) ?></a>
            <form action="logout.php" method="post"><?= csrf_field() ?><button class="text-button" type="submit">Sign out</button></form>
        </div>
    <?php else: ?>
        <div class="public-nav"><a href="login.php">Sign in</a><a class="button button-small" href="register.php">Create team</a></div>
    <?php endif; ?>
</header>
<main class="page-shell">
    <?php foreach (take_flashes() as $notice): ?>
        <div class="notice notice-<?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div>
    <?php endforeach; ?>