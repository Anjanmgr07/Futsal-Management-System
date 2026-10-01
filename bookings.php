<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_team();
$teamId = (int) $user['team_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $venueId = filter_var($_POST['venue_id'] ?? null, FILTER_VALIDATE_INT);
        $duration = filter_var($_POST['duration'] ?? null, FILTER_VALIDATE_INT);
        $rawStart = (string) ($_POST['starts_at'] ?? '');
        $start = DateTime::createFromFormat('!Y-m-d\TH:i', $rawStart);
        if (!$venueId || !in_array($duration, [1, 2, 3], true) || !$start || $start->format('Y-m-d\TH:i') !== $rawStart || $start <= new DateTime()) {
            flash('error', 'Choose a valid future time and a duration of 1 to 3 hours.');
            redirect('venues.php');
        }
        $end = (clone $start)->modify('+' . $duration . ' hours');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $venueQuery = $pdo->prepare('SELECT hourly_rate FROM venues WHERE id = ? AND is_active = 1 FOR UPDATE');
            $venueQuery->execute([$venueId]);
            $venue = $venueQuery->fetch();
            if (!$venue) {
                $pdo->rollBack();
                flash('error', 'That ground is no longer available.');
                redirect('venues.php');
            }
            $overlap = $pdo->prepare('SELECT id FROM venue_bookings WHERE venue_id = ? AND status IN (\'pending\', \'confirmed\') AND starts_at < ? AND ends_at > ? LIMIT 1');
            $overlap->execute([$venueId, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
            if ($overlap->fetchColumn()) {
                $pdo->rollBack();
                flash('error', 'That time overlaps another request. Choose a different slot.');
                redirect('venues.php');
            }
            $insert = $pdo->prepare('INSERT INTO venue_bookings (team_id, venue_id, starts_at, ends_at, total_price) VALUES (?, ?, ?, ?, ?)');
            $insert->execute([$teamId, $venueId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), number_format((float) $venue['hourly_rate'] * $duration, 2, '.', '')]);
            $pdo->commit();
            flash('success', 'Ground time requested. The venue can confirm your booking.');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        redirect('bookings.php');
    }

    if ($action === 'cancel') {
        $bookingId = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
        if ($bookingId) {
            $cancel = db()->prepare('UPDATE venue_bookings SET status = \'cancelled\' WHERE id = ? AND team_id = ? AND status IN (\'pending\', \'confirmed\') AND starts_at > NOW()');
            $cancel->execute([$bookingId, $teamId]);
            flash($cancel->rowCount() ? 'success' : 'error', $cancel->rowCount() ? 'Booking cancelled.' : 'This booking can no longer be cancelled.');
        }
        redirect('bookings.php');
    }
    flash('error', 'That booking action is not available.');
    redirect('bookings.php');
}

$query = db()->prepare('SELECT b.*, v.name AS venue_name, v.area, v.address FROM venue_bookings b JOIN venues v ON v.id = b.venue_id WHERE b.team_id = ? ORDER BY b.starts_at DESC LIMIT 100');
$query->execute([$teamId]);
$bookings = $query->fetchAll();
$pageTitle = 'Bookings';
$activePage = 'bookings';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Ground time for your team</span><h1>Your <em>bookings.</em></h1><p>Track requested and confirmed time slots.</p></div><a class="button button-dark" href="venues.php">Browse grounds <span aria-hidden="true">↗</span></a></section>
<?php if (!$bookings): ?><div class="empty-state roomy"><span class="empty-icon">◷</span><h2>No bookings yet</h2><p>Pick a local ground and request a time for your team.</p><a class="button button-dark" href="venues.php">Find a ground</a></div><?php else: ?>
<section class="table-wrap"><table class="data-table"><thead><tr><th>Ground</th><th>Date &amp; time</th><th>Duration</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($bookings as $booking): ?><tr><td><strong><?= e($booking['venue_name']) ?></strong><small><?= e($booking['area']) ?></small></td><td><?= e(date('D, M j · g:i A', strtotime($booking['starts_at']))) ?></td><td><?= e(date('g:i A', strtotime($booking['starts_at']))) ?>–<?= e(date('g:i A', strtotime($booking['ends_at']))) ?></td><td>Rs <?= e(number_format((float) $booking['total_price'])) ?></td><td><span class="status-pill status-<?= e($booking['status']) ?>"><?= e(status_label($booking['status'])) ?></span></td><td><?php if (in_array($booking['status'], ['pending', 'confirmed'], true) && strtotime($booking['starts_at']) > time()): ?><form method="post" action="bookings.php" data-confirm="Cancel this ground booking?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><button class="text-button danger-text" type="submit">Cancel</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>