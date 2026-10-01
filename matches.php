<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_team();
$teamId = (int) $user['team_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $opponentId = filter_var($_POST['team_id'] ?? null, FILTER_VALIDATE_INT);
        $rawTime = (string) ($_POST['proposed_at'] ?? '');
        $kickoff = DateTime::createFromFormat('!Y-m-d\TH:i', $rawTime);
        $message = trim((string) ($_POST['message'] ?? ''));
        $venueId = filter_var($_POST['venue_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$opponentId || $opponentId === $teamId || !$kickoff || $kickoff->format('Y-m-d\TH:i') !== $rawTime || $kickoff <= new DateTime() || strlen($message) > 500) {
            flash('error', 'Choose another team and a valid future kick-off time.');
            redirect('teams.php');
        }
        $check = db()->prepare('SELECT id FROM teams WHERE id = ?');
        $check->execute([$opponentId]);
        if (!$check->fetchColumn()) {
            flash('error', 'That team could not be found.');
            redirect('teams.php');
        }
        if ($venueId) {
            $check = db()->prepare('SELECT id FROM venues WHERE id = ? AND is_active = 1');
            $check->execute([$venueId]);
            if (!$check->fetchColumn()) {
                flash('error', 'Choose an available ground or leave the venue undecided.');
                redirect('teams.php');
            }
        } else {
            $venueId = null;
        }
        $check = db()->prepare('SELECT id FROM match_requests WHERE status = \'pending\' AND ((from_team_id = ? AND to_team_id = ?) OR (from_team_id = ? AND to_team_id = ?))');
        $check->execute([$teamId, $opponentId, $opponentId, $teamId]);
        if ($check->fetchColumn()) {
            flash('error', 'There is already a pending request between these teams.');
            redirect('teams.php');
        }
        $insert = db()->prepare('INSERT INTO match_requests (from_team_id, to_team_id, venue_id, proposed_at, message) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([$teamId, $opponentId, $venueId, $kickoff->format('Y-m-d H:i:s'), $message]);
        flash('success', 'Match request sent.');
        redirect('matches.php');
    }

    if ($action === 'respond') {
        $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($_POST['decision'] ?? '');
        if (!$requestId || !in_array($decision, ['accepted', 'declined'], true)) {
            flash('error', 'That match response was not valid.');
            redirect('matches.php');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare('SELECT * FROM match_requests WHERE id = ? AND to_team_id = ? AND status = \'pending\' FOR UPDATE');
            $query->execute([$requestId, $teamId]);
            $request = $query->fetch();
            if (!$request) {
                $pdo->rollBack();
                flash('error', 'That request is no longer waiting for a response.');
                redirect('matches.php');
            }
            if ($decision === 'accepted' && strtotime($request['proposed_at']) <= time()) {
                $pdo->rollBack();
                flash('error', 'That proposed kick-off has already passed.');
                redirect('matches.php');
            }
            $update = $pdo->prepare('UPDATE match_requests SET status = ? WHERE id = ?');
            $update->execute([$decision, $requestId]);
            if ($decision === 'accepted') {
                $insert = $pdo->prepare('INSERT INTO matches (request_id, home_team_id, away_team_id, venue_id, kickoff_at) VALUES (?, ?, ?, ?, ?)');
                $insert->execute([$requestId, $request['from_team_id'], $request['to_team_id'], $request['venue_id'], $request['proposed_at']]);
            }
            $pdo->commit();
            flash('success', $decision === 'accepted' ? 'Match confirmed and added to the fixture list.' : 'Match request declined.');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        redirect('matches.php');
    }

    flash('error', 'That action is not available.');
    redirect('matches.php');
}

$query = db()->prepare('SELECT r.*, sender.name AS sender_name, v.name AS venue_name FROM match_requests r JOIN teams sender ON sender.id = r.from_team_id LEFT JOIN venues v ON v.id = r.venue_id WHERE r.to_team_id = ? AND r.status = \'pending\' ORDER BY r.proposed_at');
$query->execute([$teamId]);
$inbox = $query->fetchAll();
$query = db()->prepare('SELECT r.*, recipient.name AS recipient_name, v.name AS venue_name FROM match_requests r JOIN teams recipient ON recipient.id = r.to_team_id LEFT JOIN venues v ON v.id = r.venue_id WHERE r.from_team_id = ? AND r.status = \'pending\' ORDER BY r.proposed_at');
$query->execute([$teamId]);
$outbox = $query->fetchAll();
$query = db()->prepare('SELECT m.*, home.name AS home_name, away.name AS away_name, v.name AS venue_name, v.area AS venue_area FROM matches m JOIN teams home ON home.id = m.home_team_id JOIN teams away ON away.id = m.away_team_id LEFT JOIN venues v ON v.id = m.venue_id WHERE m.home_team_id = ? OR m.away_team_id = ? ORDER BY m.kickoff_at DESC LIMIT 50');
$query->execute([$teamId, $teamId]);
$fixtures = $query->fetchAll();

$pageTitle = 'Matches';
$activePage = 'matches';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Meet, agree, play</span><h1>Match <em>board.</em></h1><p>Match requests and confirmed fixtures for your team.</p></div><a class="button button-dark" href="teams.php">Find an opponent <span aria-hidden="true">↗</span></a></section>
<section class="content-section split-sections">
    <div class="section-main"><div class="section-heading"><div><span class="eyebrow">Your inbox</span><h2>Incoming requests <span class="count-badge"><?= count($inbox) ?></span></h2></div></div>
        <?php if (!$inbox): ?><div class="empty-state compact"><p>No incoming match requests right now.</p></div><?php else: ?>
            <div class="request-list"><?php foreach ($inbox as $request): ?><article class="request-row"><div class="request-mark" aria-hidden="true">↙</div><div class="request-detail"><strong><?= e($request['sender_name']) ?></strong><span><?= e(date('D, M j · g:i A', strtotime($request['proposed_at']))) ?><?= $request['venue_name'] ? ' · ' . e($request['venue_name']) : '' ?></span><?php if ($request['message']): ?><p><?= e($request['message']) ?></p><?php endif; ?></div><div class="request-actions"><form method="post" action="matches.php"><?= csrf_field() ?><input type="hidden" name="action" value="respond"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="decision" value="accepted"><button class="button button-small button-dark" type="submit">Accept</button></form><form method="post" action="matches.php"><?= csrf_field() ?><input type="hidden" name="action" value="respond"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="decision" value="declined"><button class="text-button" type="submit">Decline</button></form></div></article><?php endforeach; ?></div>
        <?php endif; ?>
        <div class="section-heading subheading"><div><span class="eyebrow">Sent from your team</span><h2>Waiting on a reply</h2></div></div>
        <?php if (!$outbox): ?><div class="empty-state compact"><p>No outstanding requests sent.</p></div><?php else: ?><div class="request-list"><?php foreach ($outbox as $request): ?><article class="request-row"><div class="request-mark muted" aria-hidden="true">↗</div><div class="request-detail"><strong><?= e($request['recipient_name']) ?></strong><span><?= e(date('D, M j · g:i A', strtotime($request['proposed_at']))) ?></span></div><span class="status-pill status-pending">Pending</span></article><?php endforeach; ?></div><?php endif; ?>
    </div>
    <aside class="side-column schedule-column"><div class="side-heading"><span class="eyebrow">All the way to kick-off</span><h2>Fixture list</h2></div>
        <?php if (!$fixtures): ?><div class="empty-state compact"><p>Accepted matches will appear here.</p></div><?php else: ?><div class="schedule-list"><?php foreach ($fixtures as $fixture): ?><article class="schedule-item"><div class="schedule-time"><strong><?= e(date('d', strtotime($fixture['kickoff_at']))) ?></strong><span><?= e(date('M · g:i A', strtotime($fixture['kickoff_at']))) ?></span></div><div class="schedule-match"><strong><?= e($fixture['home_name']) ?></strong><span>vs</span><strong><?= e($fixture['away_name']) ?></strong><small><?= e($fixture['venue_name'] ?: 'Venue to be decided') ?><?= $fixture['venue_area'] ? ' · ' . e($fixture['venue_area']) : '' ?></small></div><span class="status-pill status-<?= e($fixture['status']) ?>"><?= e(status_label($fixture['status'])) ?></span></article><?php endforeach; ?></div><?php endif; ?>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>