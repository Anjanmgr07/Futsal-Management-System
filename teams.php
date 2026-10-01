<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_team();
$teamId = (int) $user['team_id'];
$city = trim((string) ($_GET['city'] ?? ''));
$skill = (string) ($_GET['skill'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$mySkillQuery = db()->prepare('SELECT skill_level FROM teams WHERE id = ?');
$mySkillQuery->execute([$teamId]);
$mySkill = $mySkillQuery->fetchColumn() ?: 'intermediate';
$venues = db()->query('SELECT id, name, area FROM venues WHERE is_active = 1 ORDER BY area, name')->fetchAll();
$sql = 'SELECT t.id, t.name, t.home_city, t.skill_level, t.player_count, t.bio, t.verification_status FROM teams t WHERE t.id <> ?';
$params = [$teamId];
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
$sql .= ' ORDER BY (t.skill_level = ?) DESC, t.created_at DESC LIMIT 60';
$params[] = $mySkill;
$query = db()->prepare($sql);
$query->execute($params);
$teams = $query->fetchAll();

$pageTitle = 'Find a team';
$activePage = 'teams';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading teams-heading"><div><span class="eyebrow">The next fixture is out there</span><h1>Find your <em>opponent.</em></h1><p>Discover local teams and invite the right one to play.</p></div><span class="heading-count"><strong><?= count($teams) ?></strong> teams</span></section>
<form class="filter-bar" method="get" action="teams.php">
    <label class="filter-search"><span class="sr-only">Search teams or cities</span><input name="q" value="<?= e($search) ?>" placeholder="Team or city"></label>
    <label><span class="sr-only">City</span><input name="city" value="<?= e($city) ?>" placeholder="Home city"></label>
    <label><span class="sr-only">Skill level</span><select name="skill"><option value="">All skill levels</option><?php foreach (['beginner', 'intermediate', 'advanced'] as $level): ?><option value="<?= e($level) ?>" <?= $skill === $level ? 'selected' : '' ?>><?= e(ucfirst($level)) ?></option><?php endforeach; ?></select></label>
    <button class="button button-dark" type="submit">Filter <span aria-hidden="true">⌕</span></button>
</form>
<?php if (!$teams): ?>
    <div class="empty-state roomy"><span class="empty-icon">⌕</span><h2>No teams found</h2><p>Try another city or skill level.</p></div>
<?php else: ?>
    <section class="team-grid" aria-label="Teams">
        <?php foreach ($teams as $opponent): ?>
            <article class="team-card">
                <div class="team-card-top"><div class="team-avatar" aria-hidden="true"><?= e(strtoupper(substr($opponent['name'], 0, 1))) ?></div><span class="skill-tag skill-<?= e($opponent['skill_level']) ?>"><?= e($opponent['skill_level']) ?></span></div>
                <h2><?= e($opponent['name']) ?></h2>
                <p class="team-place"><span aria-hidden="true">⌖</span> <?= e($opponent['home_city']) ?></p>
                <p class="team-bio"><?= e($opponent['bio'] ?: 'Ready to meet a new opponent and get a match on the calendar.') ?></p>
                <div class="team-card-foot"><span><?= (int) $opponent['player_count'] ?> players <?php if ($opponent['verification_status'] === 'verified'): ?><span class="verified-mark" title="Identity verified">✓</span><?php endif; ?></span>
                    <button class="button button-outline request-open" type="button" data-dialog="request-<?= (int) $opponent['id'] ?>">Challenge <span aria-hidden="true">↗</span></button>
                </div>
                <dialog class="request-dialog" id="request-<?= (int) $opponent['id'] ?>"><form method="post" action="matches.php" class="dialog-form">
                    <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="team_id" value="<?= (int) $opponent['id'] ?>">
                    <button class="dialog-close" type="button" aria-label="Close">×</button><span class="eyebrow">Send a challenge</span><h2><?= e($opponent['name']) ?></h2>
                    <label>Proposed kick-off<input type="datetime-local" name="proposed_at" required min="<?= e(date('Y-m-d\TH:i', time() + 3600)) ?>"></label>
                    <label>Ground<select name="venue_id"><option value="">We'll decide later</option><?php foreach ($venues as $venue): ?><option value="<?= (int) $venue['id'] ?>"><?= e($venue['name']) ?> · <?= e($venue['area']) ?></option><?php endforeach; ?></select></label>
                    <label>Message<textarea name="message" rows="3" maxlength="500" placeholder="A quick note for the captain"></textarea></label>
                    <button class="button button-dark button-wide" type="submit">Send match request <span aria-hidden="true">↗</span></button>
                </form></dialog>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>