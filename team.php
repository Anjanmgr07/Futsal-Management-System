<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_team();
$teamId = (int) $user['team_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    if (($_POST['action'] ?? '') === 'join_response') {
        $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($_POST['decision'] ?? '');
        if (!$requestId || !in_array($decision, ['accepted', 'declined'], true)) {
            flash('error', 'That player request was not valid.');
            redirect('team.php#join-requests');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $requestQuery = $pdo->prepare('SELECT * FROM team_join_requests WHERE id = ? AND team_id = ? AND status = \'pending\' FOR UPDATE');
            $requestQuery->execute([$requestId, $teamId]);
            $request = $requestQuery->fetch();
            if (!$request) {
                $pdo->rollBack();
                flash('error', 'That request is no longer waiting for a response.');
                redirect('team.php#join-requests');
            }
            if ($decision === 'accepted') {
                $teamQuery = $pdo->prepare('SELECT player_count FROM teams WHERE id = ? FOR UPDATE');
                $teamQuery->execute([$teamId]);
                if ((int) $teamQuery->fetchColumn() >= 20) {
                    $pdo->rollBack();
                    flash('error', 'Your roster is full. Update the roster size before accepting another player.');
                    redirect('team.php#join-requests');
                }
                $memberQuery = $pdo->prepare('SELECT team_id FROM team_members WHERE player_user_id = ?');
                $memberQuery->execute([$request['player_user_id']]);
                if ($memberQuery->fetchColumn()) {
                    $pdo->rollBack();
                    flash('error', 'That player has already joined another team.');
                    redirect('team.php#join-requests');
                }
                $pdo->prepare('INSERT INTO team_members (team_id, player_user_id, join_request_id) VALUES (?, ?, ?)')->execute([$teamId, $request['player_user_id'], $requestId]);
                $pdo->prepare('UPDATE teams SET player_count = player_count + 1 WHERE id = ?')->execute([$teamId]);
            }
            $pdo->prepare('UPDATE team_join_requests SET status = ? WHERE id = ?')->execute([$decision, $requestId]);
            $pdo->commit();
            flash('success', $decision === 'accepted' ? 'Player added to your roster.' : 'Player request declined.');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        redirect('team.php#join-requests');
    }
    $captain = trim((string) ($_POST['captain_name'] ?? ''));
    $team = trim((string) ($_POST['team_name'] ?? ''));
    $city = trim((string) ($_POST['home_city'] ?? ''));
    $skill = (string) ($_POST['skill_level'] ?? '');
    $players = filter_var($_POST['player_count'] ?? null, FILTER_VALIDATE_INT);
    $bio = trim((string) ($_POST['bio'] ?? ''));
    if ($captain === '' || strlen($captain) > 120 || $team === '' || strlen($team) > 120 || $city === '' || strlen($city) > 100 || !in_array($skill, ['beginner', 'intermediate', 'advanced'], true) || $players === false || $players < 3 || $players > 20 || strlen($bio) > 500) {
        flash('error', 'Check the team details and try again.');
        redirect('team.php');
    }

    $newDocument = null;
    $oldDocumentQuery = db()->prepare('SELECT verification_file FROM teams WHERE id = ?');
    $oldDocumentQuery->execute([$teamId]);
    $oldDocument = $oldDocumentQuery->fetchColumn();
    try {
        $newDocument = save_verification_upload($_FILES['verification_document'] ?? []);
        $pdo = db();
        $pdo->beginTransaction();
        $updateUser = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
        $updateUser->execute([$captain, $user['id']]);
        $sql = 'UPDATE teams SET name = ?, home_city = ?, skill_level = ?, player_count = ?, bio = ?';
        $params = [$team, $city, $skill, $players, $bio];
        if ($newDocument !== null) {
            $sql .= ', verification_file = ?, verification_status = \'pending\'';
            $params[] = $newDocument;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $teamId;
        $updateTeam = $pdo->prepare($sql);
        $updateTeam->execute($params);
        $pdo->commit();
        if ($newDocument !== null && $oldDocument) {
            $oldPath = dirname(__DIR__) . '/khelmandu-private/verification/' . basename($oldDocument);
            if (is_file($oldPath)) {
                unlink($oldPath);
            }
        }
        flash('success', $newDocument ? 'Profile saved. Your new document is awaiting review.' : 'Team profile saved.');
        redirect('team.php');
    } catch (Throwable $error) {
        if ($newDocument !== null) {
            $newPath = dirname(__DIR__) . '/khelmandu-private/verification/' . $newDocument;
            if (is_file($newPath)) {
                unlink($newPath);
            }
        }
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        if (!$error instanceof RuntimeException) {
            throw $error;
        }
        flash('error', $error->getMessage());
        redirect('team.php');
    }
}

$query = db()->prepare('SELECT t.*, u.name AS captain_name, u.email FROM teams t JOIN users u ON u.id = t.owner_user_id WHERE t.id = ?');
$query->execute([$teamId]);
$team = $query->fetch();
$query = db()->prepare('SELECT r.id, r.message, r.created_at, u.name, p.home_city, p.preferred_position, p.skill_level FROM team_join_requests r JOIN users u ON u.id = r.player_user_id JOIN player_profiles p ON p.user_id = u.id WHERE r.team_id = ? AND r.status = \'pending\' ORDER BY r.created_at');
$query->execute([$teamId]);
$joinRequests = $query->fetchAll();
$query = db()->prepare('SELECT u.name, p.preferred_position, p.skill_level, tm.joined_at FROM team_members tm JOIN users u ON u.id = tm.player_user_id JOIN player_profiles p ON p.user_id = u.id WHERE tm.team_id = ? ORDER BY u.name');
$query->execute([$teamId]);
$members = $query->fetchAll();
$pageTitle = 'Team profile';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Your team, your details</span><h1>Team <em>profile.</em></h1><p>Keep your details current so the right teams can find you.</p></div><span class="status-pill status-<?= e($team['verification_status']) ?>"><?= e(status_label($team['verification_status'])) ?> identity</span></section>
<section class="profile-layout"><form class="form-panel profile-form" method="post" action="team.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="section-heading profile-form-heading"><div><span class="eyebrow">Team information</span><h2>About your side</h2></div></div>
    <div class="form-grid"><label>Captain name<input name="captain_name" value="<?= e($team['captain_name']) ?>" required maxlength="120"></label><label>Team name<input name="team_name" value="<?= e($team['name']) ?>" required maxlength="120"></label><label>Home city<input name="home_city" value="<?= e($team['home_city']) ?>" required maxlength="100"></label><label>Skill level<select name="skill_level"><?php foreach (['beginner', 'intermediate', 'advanced'] as $level): ?><option value="<?= e($level) ?>" <?= $team['skill_level'] === $level ? 'selected' : '' ?>><?= e(ucfirst($level)) ?></option><?php endforeach; ?></select></label><label>Players on roster<input name="player_count" type="number" min="3" max="20" value="<?= (int) $team['player_count'] ?>" required></label><label class="full-field">Team bio<textarea name="bio" rows="4" maxlength="500" placeholder="What should a potential opponent know about your team?"><?= e($team['bio']) ?></textarea></label></div>
    <div class="document-panel"><div><span class="eyebrow">Team verification</span><h3>School ID or citizenship document</h3><p><?= $team['verification_file'] ? 'A document is on file. Uploading a new one restarts review.' : 'Upload a team identity document for administrator review.' ?></p></div><label class="upload-button">Choose document<input type="file" name="verification_document" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"></label><small>JPG, PNG, or PDF · max 5 MB</small></div>
    <div class="profile-submit"><span>Account: <?= e($team['email']) ?></span><button class="button button-dark" type="submit">Save team profile <span aria-hidden="true">↗</span></button></div>
</form><aside class="profile-aside"><span class="eyebrow">Your team page</span><div class="profile-monogram" aria-hidden="true"><?= e(strtoupper(substr($team['name'], 0, 1))) ?></div><h2><?= e($team['name']) ?></h2><p><?= e($team['home_city']) ?> · <?= e(ucfirst($team['skill_level'])) ?></p><div class="profile-aside-stat"><strong><?= (int) $team['player_count'] ?></strong><span>players on roster</span></div><a class="quiet-link" href="teams.php">Browse other teams <span aria-hidden="true">→</span></a></aside></section>
<section class="team-roster-sections">
    <div id="join-requests"><div class="section-heading"><div><span class="eyebrow">Player applications</span><h2>Requests to join <span class="count-badge"><?= count($joinRequests) ?></span></h2></div></div>
        <?php if (!$joinRequests): ?><div class="empty-state compact"><p>No players are waiting to join your team.</p></div><?php else: ?><div class="player-team-list"><?php foreach ($joinRequests as $request): ?><article class="player-team-row"><div class="player-request-icon" aria-hidden="true">+ </div><div class="player-team-info"><strong><?= e($request['name']) ?> · <?= e($request['preferred_position']) ?></strong><span><?= e($request['home_city']) ?> · <?= e(ucfirst($request['skill_level'])) ?> · applied <?= e(date('M j', strtotime($request['created_at']))) ?></span><?php if ($request['message']): ?><small><?= e($request['message']) ?></small><?php endif; ?></div><div class="request-actions"><form method="post" action="team.php#join-requests"><?= csrf_field() ?><input type="hidden" name="action" value="join_response"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="decision" value="accepted"><button class="button button-small button-dark" type="submit">Accept</button></form><form method="post" action="team.php#join-requests"><?= csrf_field() ?><input type="hidden" name="action" value="join_response"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="decision" value="declined"><button class="text-button" type="submit">Decline</button></form></div></article><?php endforeach; ?></div><?php endif; ?>
    </div>
    <div class="team-roster"><div class="section-heading"><div><span class="eyebrow">Confirmed members</span><h2>Player roster</h2></div></div>
        <?php if (!$members): ?><div class="empty-state compact"><p>Players you accept will show up here.</p></div><?php else: ?><div class="player-team-list"><?php foreach ($members as $member): ?><article class="player-team-row"><div class="team-avatar" aria-hidden="true"><?= e(strtoupper(substr($member['name'], 0, 1))) ?></div><div class="player-team-info"><strong><?= e($member['name']) ?></strong><span><?= e($member['preferred_position']) ?> · <?= e(ucfirst($member['skill_level'])) ?> · joined <?= e(date('M Y', strtotime($member['joined_at']))) ?></span></div></article><?php endforeach; ?></div><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>