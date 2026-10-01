<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_team();
$teamId = (int) $user['team_id'];

$query = db()->prepare('SELECT m.id, m.kickoff_at, m.status, home.name AS home_name, away.name AS away_name, v.name AS venue_name, v.area AS venue_area FROM matches m JOIN teams home ON home.id = m.home_team_id JOIN teams away ON away.id = m.away_team_id LEFT JOIN venues v ON v.id = m.venue_id WHERE (m.home_team_id = ? OR m.away_team_id = ?) AND m.status = \'scheduled\' AND m.kickoff_at >= NOW() ORDER BY m.kickoff_at LIMIT 4');
$query->execute([$teamId, $teamId]);
$upcoming = $query->fetchAll();
$query = db()->prepare('SELECT COUNT(*) FROM match_requests WHERE to_team_id = ? AND status = \'pending\'');
$query->execute([$teamId]);
$pendingRequests = (int) $query->fetchColumn();
$query = db()->prepare('SELECT COUNT(*) FROM venue_bookings WHERE team_id = ? AND status IN (\'pending\', \'confirmed\') AND starts_at >= NOW()');
$query->execute([$teamId]);
$upcomingBookings = (int) $query->fetchColumn();
$query = db()->prepare('SELECT COUNT(*) FROM teams WHERE id <> ?');
$query->execute([$teamId]);
$teamCount = (int) $query->fetchColumn();

$pageTitle = 'Overview';
$activePage = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<section class="welcome-band">
    <div><span class="eyebrow">Team headquarters</span><h1>Good game starts<br>with a <em>good plan.</em></h1><p><?= e($user['team_name']) ?> · <?= e($user['name']) ?>, captain</p></div>
    <a class="button button-light" href="teams.php">Find your next opponent <span aria-hidden="true">↗</span></a>
    <div class="welcome-mark" aria-hidden="true">K</div>
</section>
<section class="metric-strip" aria-label="Team overview">
    <div class="metric"><span>Teams to meet</span><strong><?= $teamCount ?></strong><small>on Khelmandu</small></div>
    <div class="metric"><span>Match invites</span><strong><?= $pendingRequests ?></strong><small>awaiting your reply</small></div>
    <div class="metric"><span>Ground bookings</span><strong><?= $upcomingBookings ?></strong><small>upcoming or pending</small></div>
    <div class="metric metric-status"><span>Team verification</span><strong class="status-pill status-<?= e($user['verification_status']) ?>"><?= e(status_label($user['verification_status'] ?? 'pending')) ?></strong><small>identity document review</small></div>
</section>
<section class="content-section dashboard-grid">
    <div class="section-main">
        <div class="section-heading"><div><span class="eyebrow">On the calendar</span><h2>Upcoming fixtures</h2></div><a class="quiet-link" href="matches.php">All matches <span aria-hidden="true">→</span></a></div>
        <?php if (!$upcoming): ?>
            <div class="empty-state"><span class="empty-icon">◷</span><h3>Your next fixture is waiting</h3><p>Send a match request to a team at your level.</p><a class="button button-dark" href="teams.php">Browse teams</a></div>
        <?php else: ?>
            <div class="fixture-list">
                <?php foreach ($upcoming as $fixture): ?>
                    <article class="fixture-row"><div class="fixture-date"><strong><?= e(date('d', strtotime($fixture['kickoff_at']))) ?></strong><span><?= e(date('M', strtotime($fixture['kickoff_at']))) ?></span></div><div class="fixture-teams"><strong><?= e($fixture['home_name']) ?> <span>vs</span> <?= e($fixture['away_name']) ?></strong><small><?= e(date('D, M j · g:i A', strtotime($fixture['kickoff_at']))) ?></small></div><div class="fixture-venue"><?= e($fixture['venue_name'] ?: 'Venue to be decided') ?><small><?= e($fixture['venue_area'] ?? '') ?></small></div><span class="status-pill status-scheduled">Scheduled</span></article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <aside class="side-column">
        <div class="side-heading"><span class="eyebrow">Quick moves</span><h2>Make it happen</h2></div>
        <a class="action-link" href="teams.php"><span class="action-number">01</span><span><strong>Find a team</strong><small>Browse by location and level</small></span><span class="action-arrow">↗</span></a>
        <a class="action-link" href="venues.php"><span class="action-number">02</span><span><strong>Book a ground</strong><small>Find an available time slot</small></span><span class="action-arrow">↗</span></a>
        <a class="action-link" href="team.php"><span class="action-number">03</span><span><strong>Your team profile</strong><small>Keep the roster up to date</small></span><span class="action-arrow">↗</span></a>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>