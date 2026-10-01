<?php
require __DIR__ . '/includes/bootstrap.php';
require_team();
$venues = db()->query('SELECT * FROM venues WHERE is_active = 1 ORDER BY area, name')->fetchAll();
$pageTitle = 'Grounds';
$activePage = 'venues';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading venue-heading"><div><span class="eyebrow">Local pitches, ready to play</span><h1>Find your <em>ground.</em></h1><p>Browse futsal venues and request a time that works for your team.</p></div><span class="heading-note">Payment arranged with venue</span></section>
<?php if (!$venues): ?><div class="empty-state roomy"><h2>No grounds listed yet</h2><p>Check back soon for venues in your area.</p></div><?php else: ?>
<section class="venue-grid" aria-label="Futsal grounds">
    <?php foreach ($venues as $venue): ?>
        <article class="venue-card"><div class="venue-color-block"><span class="venue-label"><?= e($venue['surface']) ?></span><span class="venue-court-mark" aria-hidden="true"><span></span></span><span class="venue-area"><?= e($venue['area']) ?></span></div>
            <div class="venue-info"><div class="venue-title"><div><span class="eyebrow"><?= e($venue['area']) ?> · KATHMANDU VALLEY</span><h2><?= e($venue['name']) ?></h2></div><strong class="venue-rate">Rs <?= e(number_format((float) $venue['hourly_rate'])) ?><small>/ hour</small></strong></div>
                <p class="venue-address"><?= e($venue['address']) ?><?= $venue['phone'] ? ' · ' . e($venue['phone']) : '' ?></p><p class="venue-facilities"><?= e($venue['facilities']) ?></p>
                <form class="booking-form" method="post" action="bookings.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><input type="hidden" name="venue_id" value="<?= (int) $venue['id'] ?>">
                    <label>Kick-off<input type="datetime-local" name="starts_at" required min="<?= e(date('Y-m-d\TH:i', time() + 3600)) ?>"></label><label>Hours<select name="duration"><option value="1">1 hour</option><option value="2">2 hours</option><option value="3">3 hours</option></select></label><button class="button button-dark" type="submit">Request slot <span aria-hidden="true">↗</span></button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>