<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $query = db()->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
    $query->execute([$email]);
    $account = $query->fetch();
    if ($account && password_verify((string) ($_POST['password'] ?? ''), $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        redirect($account['role'] === 'admin' ? 'admin.php' : ($account['role'] === 'player' ? 'player.php' : 'dashboard.php'));
    }
    flash('error', 'That email and password do not match.');
    redirect('login.php');
}

$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-layout">
    <div class="auth-intro">
        <span class="eyebrow">Your next match starts here</span>
        <h1>Find your<br><em>five-a-side.</em></h1>
        <p>Meet teams at your level, plan a fixture, and get back on the pitch.</p>
        <div class="auth-stat"><strong>01</strong><span>Pick your people.<br>We'll help with the rest.</span></div>
    </div>
    <form class="form-panel auth-form" method="post" action="login.php">
        <div class="form-heading"><span class="eyebrow">Welcome back</span><h2>Sign in</h2><p>Use your team captain account.</p></div>
        <?= csrf_field() ?>
        <label>Email address<input name="email" type="email" autocomplete="email" required maxlength="190"></label>
        <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
        <button class="button button-dark button-wide" type="submit">Sign in <span aria-hidden="true">↗</span></button>
        <p class="form-footnote">New to Khelmandu? <a href="register.php">Create a team</a> or <a href="register_player.php">create a player profile</a>.</p>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>