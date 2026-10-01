<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_player();
$playerId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $action = (string) ($_POST['action'] ?? 'save_profile');
    if ($action === 'save_profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $city = trim((string) ($_POST['home_city'] ?? ''));
        $position = trim((string) ($_POST['preferred_position'] ?? ''));
        $skill = (string) ($_POST['skill_level'] ?? '');
        $bio = trim((string) ($_POST['bio'] ?? ''));
        if ($name === '' || strlen($name) > 120 || $city === '' || strlen($city) > 100 || $position === '' || strlen($position) > 60 || !in_array($skill, ['beginner', 'intermediate', 'advanced'], true) || strlen($bio) > 500) {
            flash('error', 'Check your player details and try again.');
            redirect('player.php#profile');
        }
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $playerId]);
        $pdo->prepare('UPDATE player_profiles SET home_city = ?, preferred_position = ?, skill_level = ?, bio = ? WHERE user_id = ?')->execute([$city, $position, $skill, $bio, $playerId]);
        $pdo->commit();
        flash('success', 'Player profile updated.');
        redirect('player.php#profile');
    }
    if ($action === 'join') {
        $teamId = filter_var($_POST['team_id'] ?? null, FILTER_VALIDATE_INT);
        $message = trim((string) ($_POST['message'] ?? ''));
        if (!$teamId || strlen($message) > 500) {
            flash('error', 'That team request is not valid.');
            redirect('player.php#find-team');
        }
        $pdo = db();
        $member = $pdo->prepare('SELECT team_id FROM team_members WHERE player_user_id = ?');
        $member->execute([$playerId]);
        if ($member->fetchColumn()) {
            flash('error', 'You are already a member of a team.');
            redirect('player.php#find-team');
        }
        $exists = $pdo->prepare('SELECT id FROM teams WHERE id = ?');
        $exists->execute([$teamId]);
        if (!$exists->fetchColumn()) {
            flash('error', 'That team could not be found.');
            redirect('player.php#find-team');
        }
        $pending = $pdo->prepare('SELECT id FROM team_join_requests WHERE team_id = ? AND player_user_id = ? AND status = \'pending\'');
        $pending->execute([$teamId, $playerId]);
        if ($pending->fetchColumn()) {
            flash('error', 'Your request is already waiting for this team.');
        } else {
            $pdo->prepare('INSERT INTO team_join_requests (team_id, player_user_id, message) VALUES (?, ?, ?)')->execute([$teamId, $playerId, $message]);
            flash('success', 'Join request sent to the team captain.');
        }
        redirect('player.php#find-team');
    }
    if ($action === 'cancel_request') {
        $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
        if ($requestId) {
            $cancel = db()->prepare('UPDATE team_join_requests SET status = \'cancelled\' WHERE id = ? AND player_user_id = ? AND status = \'pending\'');
            $cancel->execute([$requestId, $playerId]);
            flash($cancel->rowCount() ? 'success' : 'error', $cancel->rowCount() ? 'Join request cancelled.' : 'That request is no longer pending.');
        }
        redirect('player.php#requests');
    }
    flash('error', 'That player action is not available.');
    redirect('player.php');
}

$query = db()->prepare('SELECT u.name, u.email, p.* FROM users u JOIN player_profiles p ON p.user_id = u.id WHERE u.id = ?');
$query->execute([$playerId]);
$profile = $query->fetch();
$query = db()->prepare('SELECT t.id, t.name, t.home_city, tm.joined_at FROM team_members tm JOIN teams t ON t.id = tm.team_id WHERE tm.player_user_id = ?');
$query->execute([$playerId]);
$currentTeam = $query->fetch();
$query = db()->prepare('SELECT r.id, r.status, r.created_at, t.name AS team_name FROM team_join_requests r JOIN teams t ON t.id = r.team_id WHERE r.player_user_id = ? ORDER BY r.created_at DESC LIMIT 8');
$query->execute([$playerId]);
$requests = $query->fetchAll();

$city = trim((string) ($_GET['city'] ?? $profile['home_city']));
$skill = (string) ($_GET['skill'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT t.id, t.name, t.home_city, t.skill_level, t.player_count, t.bio, t.verification_status, (SELECT r.status FROM team_join_requests r WHERE r.player_user_id = ? AND r.team_id = t.id ORDER BY r.id DESC LIMIT 1) AS request_status FROM teams t WHERE 1 = 1';
$params = [$playerId];
if ($currentTeam) {
    $sql .= ' AND t.id <> ?';
    $params[] = $currentTeam['id'];
}
if ($city !== '') {
    $sql .= ' AND t.home_city LIKE ?';
    $params[] = '%' . $city . '%';
}
if (in_array($skill, ['beginner', 'intermediate', 'advanced'], true)) {
    $sql .= ' AND t.skill_level = ?';
    $params[] = $skill;
}
if ($search !== '') {
    $sql .= ' AND (t.name LIKE ? OR t.home_city LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY (t.skill_level = ?) DESC, t.name LIMIT 60';
$params[] = $profile['skill_level'];
$query = db()->prepare($sql);
$query->execute($params);
$teams = $query->fetchAll();

$pageTitle = 'Player home';
$activePage = 'player';
require __DIR__ . '/includes/header.php';
?>
<section class="welcome-band player-welcome"><div><span class="eyebrow">Player profile</span><h1>Find your<br><em>next side.</em></h1><p><?= e($profile['name']) ?> · <?= e($profile['home_city']) ?> · <?= e(ucfirst($profile['skill_level'])) ?></p></div><a class="button button-light" href="#find-team">Browse teams <span aria-hidden="true">↗</span></a><div class="welcome-mark" aria-hidden="true">P</div></section>
<section class="player-summary">
    <div class="player-current-team"><span class="eyebrow">Current team</span><?php if ($currentTeam): ?><strong><?= e($currentTeam['name']) ?></strong><small><?= e($currentTeam['home_city']) ?> · joined <?= e(date('M Y', strtotime($currentTeam['joined_at']))) ?></small><?php else: ?><strong>Free agent</strong><small>Send a request to join a local team.</small><?php endif; ?></div>
    <div class="player-requests" id="requests"><span class="eyebrow">Join requests</span><strong><?= count(array_filter($requests, static fn($request) => $request['status'] === 'pending')) ?></strong><small>waiting for a captain</small></div>
</section>
<section class="content-section player-content">
    <div class="section-main" id="find-team"><div class="section-heading"><div><span class="eyebrow">Find a team</span><h2>Local sides <span class="count-badge"><?= count($teams) ?></span></h2></div></div>
        <form class="filter-bar player-filters" method="get" action="player.php"><label><span class="sr-only">Search by team or city</span><input name="q" value="<?= e($search) ?>" placeholder="Team or city"></label><label><span class="sr-only">City</span><input name="city" value="<?= e($city) ?>" placeholder="Any city"></label><label><span class="sr-only">Skill</span><select name="skill"><option value="">All levels</option><?php foreach (['beginner', 'intermediate', 'advanced'] as $level): ?><option value="<?= e($level) ?>" <?= $skill === $level ? 'selected' : '' ?>><?= e(ucfirst($level)) ?></option><?php endforeach; ?></select></label><button class="button button-dark" type="submit">Filter</button></form>
        <?php if (!$teams): ?><div class="empty-state compact"><p>No teams match that search yet.</p></div><?php else: ?><div class="player-team-list"><?php foreach ($teams as $team): ?><article class="player-team-row"><div class="team-avatar" aria-hidden="true"><?= e(strtoupper(substr($team['name'], 0, 1))) ?></div><div class="player-team-info"><strong><?= e($team['name']) ?><?php if ($team['verification_status'] === 'verified'): ?> <span class="verified-mark" title="Identity verified">✓</span><?php endif; ?></strong><span><?= e($team['home_city']) ?> · <?= e(ucfirst($team['skill_level'])) ?> · <?= (int) $team['player_count'] ?> players</span><small><?= e($team['bio'] ?: 'Looking for local players and new opponents.') ?></small></div><?php if ($currentTeam): ?><span class="status-pill status-confirmed">On a team</span><?php elseif ($team['request_status'] === 'pending'): ?><span class="status-pill status-pending">Request sent</span><?php else: ?><form method="post" action="player.php#find-team" class="player-join-form"><?= csrf_field() ?><input type="hidden" name="action" value="join"><input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>"><input type="hidden" name="message" value="<?= e($profile['preferred_position'] . ' · ' . ucfirst($profile['skill_level'])) ?>"><button class="button button-outline button-small" type="submit">Request to join <span aria-hidden="true">↗</span></button></form><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
    </div>
    <aside class="side-column" id="profile"><div class="section-heading"><div><span class="eyebrow">Your details</span><h2>Player card</h2></div></div>
        <form class="player-profile-form" method="post" action="player.php#profile"><?= csrf_field() ?><input type="hidden" name="action" value="save_profile"><label>Name<input name="name" value="<?= e($profile['name']) ?>" required maxlength="120"></label><label>Home city<input name="home_city" value="<?= e($profile['home_city']) ?>" required maxlength="100"></label><label>Preferred position<input name="preferred_position" value="<?= e($profile['preferred_position']) ?>" required maxlength="60"></label><label>Skill level<select name="skill_level"><?php foreach (['beginner', 'intermediate', 'advanced'] as $level): ?><option value="<?= e($level) ?>" <?= $profile['skill_level'] === $level ? 'selected' : '' ?>><?= e(ucfirst($level)) ?></option><?php endforeach; ?></select></label><label>Player bio<textarea name="bio" rows="3" maxlength="500"><?= e($profile['bio']) ?></textarea></label><button class="button button-dark button-wide" type="submit">Save profile</button><small class="player-email"><?= e($profile['email']) ?></small></form>
    </aside>
</section>
<?php if ($requests): ?><section class="player-request-history"><div class="section-heading"><div><span class="eyebrow">Your applications</span><h2>Join request history</h2></div></div><div class="player-team-list"><?php foreach ($requests as $request): ?><article class="player-team-row"><div class="player-request-icon" aria-hidden="true">↗</div><div class="player-team-info"><strong><?= e($request['team_name']) ?></strong><span>Sent <?= e(date('M j, Y', strtotime($request['created_at']))) ?></span></div><span class="status-pill status-<?= e($request['status']) ?>"><?= e(status_label($request['status'])) ?></span><?php if ($request['status'] === 'pending'): ?><form method="post" action="player.php#requests"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_request"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><button class="text-button danger-text" type="submit">Cancel request</button></form><?php endif; ?></article><?php endforeach; ?></div></section><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>