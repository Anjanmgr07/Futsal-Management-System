<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'review_team') {
        $teamId = filter_var($_POST['team_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['verification_status'] ?? '');
        if ($teamId && in_array($status, ['pending', 'verified', 'rejected'], true)) {
            $update = db()->prepare('UPDATE teams SET verification_status = ? WHERE id = ?');
            $update->execute([$status, $teamId]);
            flash('success', 'Team verification updated.');
        } else {
            flash('error', 'That team review was not valid.');
        }
        redirect('admin.php#teams');
    }
    if ($action === 'add_venue') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $area = trim((string) ($_POST['area'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $rate = filter_var($_POST['hourly_rate'] ?? null, FILTER_VALIDATE_FLOAT);
        $surface = trim((string) ($_POST['surface'] ?? 'Indoor turf'));
        $facilities = trim((string) ($_POST['facilities'] ?? ''));
        if ($name === '' || $area === '' || $address === '' || $rate === false || $rate <= 0 || $rate > 100000 || strlen($name) > 150 || strlen($area) > 100 || strlen($address) > 255 || strlen($phone) > 40 || strlen($surface) > 80 || strlen($facilities) > 500) {
            flash('error', 'Check the ground details and hourly price.');
            redirect('admin.php#venues');
        }
        $insert = db()->prepare('INSERT INTO venues (name, area, address, phone, hourly_rate, surface, facilities) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$name, $area, $address, $phone, number_format((float) $rate, 2, '.', ''), $surface, $facilities]);
        flash('success', 'Ground added to the venue list.');
        redirect('admin.php#venues');
    }
    if ($action === 'toggle_venue') {
        $venueId = filter_var($_POST['venue_id'] ?? null, FILTER_VALIDATE_INT);
        $active = filter_var($_POST['is_active'] ?? null, FILTER_VALIDATE_INT);
        if ($venueId && in_array($active, [0, 1], true)) {
            $update = db()->prepare('UPDATE venues SET is_active = ? WHERE id = ?');
            $update->execute([$active, $venueId]);
            flash('success', $active ? 'Ground is now listed.' : 'Ground removed from public listings.');
        }
        redirect('admin.php#venues');
    }
    if ($action === 'booking_status') {
        $bookingId = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? '');
        if ($bookingId && in_array($status, ['confirmed', 'cancelled'], true)) {
            $update = db()->prepare('UPDATE venue_bookings SET status = ? WHERE id = ? AND status = \'pending\'');
            $update->execute([$status, $bookingId]);
            flash($update->rowCount() ? 'success' : 'error', $update->rowCount() ? 'Booking status updated.' : 'That booking is no longer pending.');
        }
        redirect('admin.php#bookings');
    }
    flash('error', 'That administrator action is not available.');
    redirect('admin.php');
}

$teams = db()->query('SELECT t.*, u.name AS captain_name, u.email FROM teams t JOIN users u ON u.id = t.owner_user_id ORDER BY FIELD(t.verification_status, \'pending\', \'rejected\', \'verified\'), t.created_at DESC')->fetchAll();
$venues = db()->query('SELECT * FROM venues ORDER BY is_active DESC, area, name')->fetchAll();
$bookings = db()->query('SELECT b.*, t.name AS team_name, v.name AS venue_name, v.area FROM venue_bookings b JOIN teams t ON t.id = b.team_id JOIN venues v ON v.id = b.venue_id ORDER BY FIELD(b.status, \'pending\', \'confirmed\', \'cancelled\'), b.starts_at DESC LIMIT 100')->fetchAll();
$pageTitle = 'Admin';
$activePage = 'admin';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Khelmandu operations</span><h1>Admin <em>desk.</em></h1><p>Review team identities, grounds, and incoming venue requests.</p></div></section>
<nav class="admin-tabs" aria-label="Admin sections"><a href="#teams">Teams <span><?= count($teams) ?></span></a><a href="#venues">Grounds <span><?= count($venues) ?></span></a><a href="#bookings">Bookings <span><?= count($bookings) ?></span></a></nav>
<section class="admin-section" id="teams"><div class="section-heading"><div><span class="eyebrow">Community</span><h2>Team verification</h2></div></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Team</th><th>Captain</th><th>Location / level</th><th>Document</th><th>Verification</th><th>Review</th></tr></thead><tbody>
<?php foreach ($teams as $team): ?><tr><td><strong><?= e($team['name']) ?></strong><small><?= (int) $team['player_count'] ?> players</small></td><td><?= e($team['captain_name']) ?><small><?= e($team['email']) ?></small></td><td><?= e($team['home_city']) ?><small><?= e(ucfirst($team['skill_level'])) ?></small></td><td><?= $team['verification_file'] ? '<a class="quiet-link" href="view_document.php?team=' . (int) $team['id'] . '">View document ↗</a>' : '<span class="muted-text">Not uploaded</span>' ?></td><td><span class="status-pill status-<?= e($team['verification_status']) ?>"><?= e(status_label($team['verification_status'])) ?></span></td><td><form class="inline-form" method="post" action="admin.php#teams"><?= csrf_field() ?><input type="hidden" name="action" value="review_team"><input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>"><select name="verification_status" aria-label="Verification status for <?= e($team['name']) ?>"><option value="pending" <?= $team['verification_status'] === 'pending' ? 'selected' : '' ?>>Pending</option><option value="verified" <?= $team['verification_status'] === 'verified' ? 'selected' : '' ?>>Verified</option><option value="rejected" <?= $team['verification_status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option></select><button class="icon-button" type="submit" aria-label="Save verification">✓</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="admin-section" id="venues"><div class="section-heading"><div><span class="eyebrow">Venue directory</span><h2>Manage grounds</h2></div></div>
<div class="admin-venue-grid"><form class="form-panel admin-add-form" method="post" action="admin.php#venues"><?= csrf_field() ?><input type="hidden" name="action" value="add_venue"><span class="eyebrow">Add a venue</span><label>Ground name<input name="name" required maxlength="150"></label><div class="form-grid"><label>Area<input name="area" required maxlength="100"></label><label>Hourly rate (Rs)<input name="hourly_rate" type="number" min="1" step="0.01" required></label></div><label>Address<input name="address" required maxlength="255"></label><div class="form-grid"><label>Phone<input name="phone" maxlength="40"></label><label>Surface<input name="surface" value="Indoor turf" maxlength="80"></label></div><label>Facilities<input name="facilities" maxlength="500" placeholder="Parking, changing rooms"></label><button class="button button-dark" type="submit">Add ground <span aria-hidden="true">+</span></button></form>
<div class="admin-venue-list"><?php foreach ($venues as $venue): ?><article class="admin-venue-row"><div><strong><?= e($venue['name']) ?></strong><small><?= e($venue['area']) ?> · Rs <?= e(number_format((float) $venue['hourly_rate'])) ?>/hr</small></div><span class="status-pill status-<?= $venue['is_active'] ? 'confirmed' : 'cancelled' ?>"><?= $venue['is_active'] ? 'Listed' : 'Hidden' ?></span><form method="post" action="admin.php#venues"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_venue"><input type="hidden" name="venue_id" value="<?= (int) $venue['id'] ?>"><input type="hidden" name="is_active" value="<?= $venue['is_active'] ? 0 : 1 ?>"><button class="text-button" type="submit"><?= $venue['is_active'] ? 'Hide' : 'List' ?></button></form></article><?php endforeach; ?></div></div></section>
<section class="admin-section" id="bookings"><div class="section-heading"><div><span class="eyebrow">Ground requests</span><h2>Venue bookings</h2></div></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Team</th><th>Ground</th><th>Time</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($bookings as $booking): ?><tr><td><strong><?= e($booking['team_name']) ?></strong></td><td><?= e($booking['venue_name']) ?><small><?= e($booking['area']) ?></small></td><td><?= e(date('D, M j · g:i A', strtotime($booking['starts_at']))) ?><small>until <?= e(date('g:i A', strtotime($booking['ends_at']))) ?></small></td><td>Rs <?= e(number_format((float) $booking['total_price'])) ?></td><td><span class="status-pill status-<?= e($booking['status']) ?>"><?= e(status_label($booking['status'])) ?></span></td><td><?php if ($booking['status'] === 'pending'): ?><form class="inline-form" method="post" action="admin.php#bookings"><?= csrf_field() ?><input type="hidden" name="action" value="booking_status"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><button class="button button-small button-dark" name="status" value="confirmed" type="submit">Confirm</button><button class="text-button danger-text" name="status" value="cancelled" type="submit">Decline</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>