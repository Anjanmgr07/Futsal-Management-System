<?php
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();
redirect(!$user ? 'login.php' : ($user['role'] === 'admin' ? 'admin.php' : ($user['role'] === 'player' ? 'player.php' : 'dashboard.php')));