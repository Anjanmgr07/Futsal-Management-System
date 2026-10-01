<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $captain = trim((string) ($_POST['captain_name'] ?? ''));
    $team = trim((string) ($_POST['team_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $city = trim((string) ($_POST['home_city'] ?? ''));
    $skill = (string) ($_POST['skill_level'] ?? '');
    $players = filter_var($_POST['player_count'] ?? null, FILTER_VALIDATE_INT);
    $password = (string) ($_POST['password'] ?? '');
    $validSkills = ['beginner', 'intermediate', 'advanced'];

    if ($captain === '' || strlen($captain) > 120 || $team === '' || strlen($team) > 120 || $city === '' || strlen($city) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($skill, $validSkills, true) || $players === false || $players < 3 || $players > 20 || strlen($password) < 8 || $password !== (string) ($_POST['password_confirm'] ?? '')) {
        flash('error', 'Check the team details and use a matching password of at least 8 characters.');
        redirect('register.php');
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();
        $insertUser = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $insertUser->execute([$captain, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int) $pdo->lastInsertId();
        $insertTeam = $pdo->prepare('INSERT INTO teams (owner_user_id, name, home_city, skill_level, player_count) VALUES (?, ?, ?, ?, ?)');
        $insertTeam->execute([$userId, $team, $city, $skill, $players]);
        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        flash('success', 'Your team is ready. Find an opponent to get started.');
        redirect('dashboard.php');
    } catch (PDOException $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        if ($error->getCode() === '23000') {
            flash('error', 'That email is already registered. Sign in or use another email.');
            redirect('register.php');
        }
        throw $error;
    }
}

$pageTitle = 'Create a team';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-layout register-layout">
    <div class="auth-intro">
        <span class="eyebrow">Build your side</span>
        <h1>Make room<br>for <em>one more.</em></h1>
        <p>Set up your team once. Find opponents, agree a time, and make the match happen.</p>
        <div class="auth-stat"><strong>5v5</strong><span>Your crew, your level,<br>your local pitch.</span></div>
    </div>
    <form class="form-panel auth-form register-form" method="post" action="register.php">
        <div class="form-heading"><span class="eyebrow">Get on the board</span><h2>Create a team</h2><p>Your captain account manages the team profile.</p></div>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>Captain name<input name="captain_name" autocomplete="name" required maxlength="120"></label>
            <label>Team name<input name="team_name" required maxlength="120"></label>
            <label>Email address<input name="email" type="email" autocomplete="email" required maxlength="190"></label>
            <label>Home city<input name="home_city" placeholder="e.g. Kathmandu" required maxlength="100"></label>
            <label>Skill level<select name="skill_level" required><option value="beginner">Beginner</option><option value="intermediate" selected>Intermediate</option><option value="advanced">Advanced</option></select></label>
            <label>Players on roster<input name="player_count" type="number" min="3" max="20" value="5" required></label>
            <label>Password<input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
            <label>Confirm password<input name="password_confirm" type="password" autocomplete="new-password" minlength="8" required></label>
        </div>
        <button class="button button-dark button-wide" type="submit">Create team <span aria-hidden="true">↗</span></button>
        <p class="form-footnote">Already have a team account? <a href="login.php">Sign in</a></p>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>