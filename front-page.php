<?php

/**
 * Template Name: Dashboard Page
 *
 * @package Hale Dash
 * @copyright Ministry of Justice
 * @version 2.0
 */

get_header();

echo festiveGreeting(time());
echo getBrithday();
$sites = get_sites();
?>

<div class="govuk-grid-column-full">
	<div class="govuk-grid-row hale-dash-layout">
		<aside class="govuk-grid-column-one-third hale-dash-metrics-col">
			<h2 class="govuk-heading-l">Platform metrics</h2>
			<?php include get_stylesheet_directory() . '/components/feature-metrics.php'; ?>
			<?php if (!hale_dash_is_production()): ?>
				<p class="hale-dash-env-notice govuk-body-s">Reservations made on <strong><?php echo esc_html(hale_dash_environment_name()); ?></strong> are stored in this environment only — nobody else will see them. Use the production dashboard to reserve a demo site.</p>
			<?php endif; ?>
			<?php include get_stylesheet_directory() . '/components/demo-reservations-summary.php'; ?>
		</aside>

		<section class="govuk-grid-column-two-thirds hale-dash-sites-col">
			<h2 class="govuk-heading-l">Sites</h2>
			<?php include get_stylesheet_directory() . '/components/site-search.php'; ?>
			<?php include get_stylesheet_directory() . '/components/site-status-list.php'; ?>
		</section>
	</div>
</div>

<?php
get_footer();
