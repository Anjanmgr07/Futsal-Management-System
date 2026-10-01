<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $city = trim((string) ($_POST['home_city'] ?? ''));
    $position = trim((string) ($_POST['preferred_position'] ?? '')) ?: 'Flexible';
    $skill = (string) ($_POST['skill_level'] ?? 'intermediate');
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $city === '' || strlen($city) > 100 || $position === '' || strlen($position) > 60 || !in_array($skill, ['beginner', 'intermediate', 'advanced'], true) || strlen($bio) > 500 || strlen($password) < 8 || $password !== (string) ($_POST['password_confirm'] ?? '')) {
        flash('error', 'Check your details and use a matching password of at least 8 characters.');
        redirect('register_player.php');
    }
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $createUser = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, \'player\')');
        $createUser->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int) $pdo->lastInsertId();
        $createProfile = $pdo->prepare('INSERT INTO player_profiles (user_id, home_city, preferred_position, skill_level, bio) VALUES (?, ?, ?, ?, ?)');
        $createProfile->execute([$userId, $city, $position, $skill, $bio]);
        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        flash('success', 'Player profile created. Find a team nearby.');
        redirect('player.php');
    } catch (PDOException $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        if ($error->getCode() === '23000') {
            flash('error', 'That email is already registered. Sign in or use another email.');
            redirect('register_player.php');
        }
        throw $error;
    }
}

$pageTitle = 'Player profile';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-layout register-layout">
    <div class="auth-intro"><span class="eyebrow">Your game, your next side</span><h1>Find a team<br>that <em>fits.</em></h1><p>Build your player profile, meet local teams, and ask to join a side.</p><div class="auth-stat"><strong>01</strong><span>Your profile. Your account.<br>No shared team passwords.</span></div></div>
    <form class="form-panel auth-form register-form" method="post" action="register_player.php">
        <div class="form-heading"><span class="eyebrow">Player registration</span><h2>Create a profile</h2><p>Tell teams a little about your game.</p></div>
        <?= csrf_field() ?>
        <div class="form-grid"><label>Your name<input name="name" autocomplete="name" required maxlength="120"></label><label>Home city<input name="home_city" placeholder="e.g. Kathmandu" required maxlength="100"></label><label>Email address<input name="email" type="email" autocomplete="email" required maxlength="190"></label><label>Preferred position<input name="preferred_position" placeholder="e.g. Keeper, pivot" maxlength="60"></label><label>Skill level<select name="skill_level"><option value="beginner">Beginner</option><option value="intermediate" selected>Intermediate</option><option value="advanced">Advanced</option></select></label><label class="full-field">Short player bio<textarea name="bio" rows="2" maxlength="500" placeholder="Availability, experience, or what you bring to a team"></textarea></label><label>Password<input name="password" type="password" autocomplete="new-password" minlength="8" required></label><label>Confirm password<input name="password_confirm" type="password" autocomplete="new-password" minlength="8" required></label></div>
        <button class="button button-dark button-wide" type="submit">Create player profile <span aria-hidden="true">↗</span></button>
        <p class="form-footnote">Want to manage a side instead? <a href="register.php">Create a team</a></p>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>