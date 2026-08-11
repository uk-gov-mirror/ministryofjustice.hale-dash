<?php
/**
 * At-a-glance list of every demo site currently reserved, so people don't have
 * to scan the whole list to find what is free.
 */

$reservations = hale_dash_get_reservations();
$site_names   = [];

foreach (array_keys($reservations) as $site_id) {
	$details = get_blog_details((int) $site_id);
	if ($details) {
		$site_names[(int) $site_id] = $details->blogname;
	}
}

// Reservations and their names both come from this network, so the box is fully
// accurate when demo is unreachable. What is missing in that case is the check
// against demo, so a site that no longer exists there cannot be pointed out.
$demo_available = hale_dash_get_demo_sites() !== false;

// Sort by site name so the box reads in the same order as the list below.
uksort($reservations, function ($a, $b) use ($site_names) {
	return strcasecmp($site_names[(int) $a] ?? '', $site_names[(int) $b] ?? '');
});
?>
<div class="hale-dash-reserved-box">
	<h3 class="govuk-heading-s govuk-!-margin-bottom-1"><i class="fa-solid fa-calendar-check hale-dash-reserved-box__icon" aria-hidden="true"></i> Currently reserved on demo<?php echo !empty($reservations) ? ' (' . count($reservations) . ')' : ''; ?></h3>

	<?php if (!$demo_available): ?>
		<p class="hale-dash-reserved-box__warning govuk-body-s"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <span>Demo is not responding, so sites that are missing from it cannot be flagged right now. Reservations below are unaffected.</span></p>
	<?php endif; ?>

	<?php if (empty($reservations)): ?>
		<p class="govuk-body-s govuk-hint govuk-!-margin-0">No demo sites are reserved.</p>
	<?php else: ?>
		<ul class="hale-dash-reserved-box__list govuk-list govuk-body-s govuk-!-margin-0">
			<?php foreach ($reservations as $site_id => $reservation): ?>
				<li class="hale-dash-reserved-box__item">
					<span class="hale-dash-reserved-box__site"><?php echo esc_html($site_names[(int) $site_id] ?? 'Site ' . (int) $site_id); ?></span>
					<span class="hale-dash-reserved-box__who"><?php echo esc_html($reservation['user_name']); ?></span>
					<span class="hale-dash-reserved-box__dates"><?php echo esc_html(hale_dash_format_reservation_dates($reservation)); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
