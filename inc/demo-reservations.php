<?php

/**
 * Reservations for demo sites.
 *
 * Reservations are shared across the whole dashboard rather than private to a
 * user — the point is to let people see which demo sites are already spoken
 * for. They are held in a single option on the dashboard site, keyed by the
 * blog ID of the site in this dashboard's own network.
 *
 * Keying on the local ID rather than the demo environment's means reserving
 * works whether or not demo answers. The demo API is only consulted to point out
 * that a site has no counterpart over there — see components/site-status-list.php.
 */

/**
 * Every environment has its own database, so a reservation made on dev, staging,
 * demo or local is stored only there and nobody else can see it. Production is
 * the only copy of the dashboard the whole team shares.
 *
 * wp_get_environment_type() rather than getenv() because WP_ENVIRONMENT_TYPE is
 * just as often a constant in wp-config.php as an environment variable, and
 * reading only the variable would have shown production users a notice telling
 * them to go and use the production dashboard.
 */
function hale_dash_is_production() {
	return wp_get_environment_type() === 'production';
}

function hale_dash_environment_name() {
	return ucfirst(wp_get_environment_type());
}

/**
 * blogID => site name for this dashboard's own network, so reservations can be
 * labelled without asking demo. get_blog_details() is cached per site, so this
 * costs no queries on a warm cache and needs no switch_to_blog().
 *
 * @return array
 */
function hale_dash_get_local_site_names() {
	$names = [];

	foreach (get_sites(['number' => 0]) as $site) {
		$details = get_blog_details($site->blog_id);

		if ($details) {
			$names[(int) $site->blog_id] = $details->blogname;
		}
	}

	return $names;
}

/**
 * slug => blogID for this dashboard's own network. Only the migration below
 * needs it, which is why it pays for a switch_to_blog() per site rather than
 * being folded into the site list's existing loop.
 *
 * @return array
 */
function hale_dash_get_local_site_ids_by_slug() {
	$ids = [];

	foreach (get_sites(['number' => 0]) as $site) {
		switch_to_blog($site->blog_id);
		$slug = trim(strtolower((string) get_option('site_path_slug')), '/');
		restore_current_blog();

		if ($slug !== '') {
			$ids[$slug] = (int) $site->blog_id;
		}
	}

	return $ids;
}

/**
 * Reservations were originally keyed by the site's blog ID on demo, which made
 * the whole feature depend on demo being reachable. This converts an existing
 * option to local blog IDs, once, translating through the slug both
 * environments share.
 *
 * Entries with no counterpart in this network are dropped — there would be no
 * row left to release them from.
 */
function hale_dash_migrate_reservation_keys() {
	if (get_option('hale_dash_reservation_keys_migrated')) {
		return;
	}

	$reservations = get_option('hale_dash_demo_reservations', []);

	if (!is_array($reservations) || empty($reservations)) {
		update_option('hale_dash_reservation_keys_migrated', 1);

		return;
	}

	$demo_ids_by_slug = hale_dash_get_demo_site_ids_by_slug();

	// Demo site ID map unavailable (unreachable or missing usable slug/blogID
	// pairs). Leave the option untouched and try again on a later request rather
	// than discarding reservations we cannot yet translate.
	if (empty($demo_ids_by_slug)) {
		return;
	}

	$slugs_by_demo_id  = array_flip($demo_ids_by_slug);
	$local_ids_by_slug = hale_dash_get_local_site_ids_by_slug();
	$migrated          = [];

	foreach ($reservations as $demo_id => $reservation) {
		$slug     = $slugs_by_demo_id[(int) $demo_id] ?? '';
		$local_id = $slug !== '' ? ($local_ids_by_slug[$slug] ?? 0) : 0;

		if ($local_id) {
			$migrated[(string) $local_id] = $reservation;
		}
	}

	update_option('hale_dash_demo_reservations', $migrated, false);
	update_option('hale_dash_reservation_keys_migrated', 1);
}

add_action('template_redirect', 'hale_dash_migrate_reservation_keys');

/**
 * @return array site_id => ['user_id', 'user_name', 'from', 'to', 'updated']
 */
function hale_dash_get_reservations() {
	$reservations = get_option('hale_dash_demo_reservations', []);

	if (!is_array($reservations)) {
		return [];
	}

	return hale_dash_prune_expired_reservations($reservations);
}

/**
 * Auto-release: a reservation with a "to" date is dropped once we are past that
 * date (it stays reserved through the whole of the "to" day itself). Open-ended
 * reservations — those with no "to" date — never expire. Pruning happens lazily
 * on read and the cleaned list is written back, so no cron job is needed.
 */
function hale_dash_prune_expired_reservations($reservations) {
	$today   = current_time('Y-m-d');
	$changed = false;

	foreach ($reservations as $site_id => $reservation) {
		if (!empty($reservation['to']) && $reservation['to'] < $today) {
			unset($reservations[$site_id]);
			$changed = true;
		}
	}

	if ($changed) {
		update_option('hale_dash_demo_reservations', $reservations, false);
	}

	return $reservations;
}

function hale_dash_get_reservation($site_id) {
	$reservations = hale_dash_get_reservations();

	return $reservations[(string) $site_id] ?? null;
}

/**
 * Anyone logged in may reserve a free site, but only the person holding a
 * reservation (or a network admin) may change or release it.
 */
function hale_dash_can_edit_reservation($reservation) {
	if (!is_user_logged_in()) {
		return false;
	}

	if (empty($reservation)) {
		return true;
	}

	return (int) $reservation['user_id'] === get_current_user_id() || current_user_can('manage_network');
}

/**
 * Only accept a real Y-m-d date — rejects both malformed input and dates the
 * browser's native picker would never produce, e.g. 2026-02-31.
 */
function hale_dash_sanitize_date($value) {
	$value = sanitize_text_field(wp_unslash($value));
	$date  = DateTime::createFromFormat('Y-m-d', $value);

	return ($date && $date->format('Y-m-d') === $value) ? $value : '';
}

function hale_dash_format_reservation_dates($reservation) {
	$from = !empty($reservation['from']) ? wp_date('j M Y', strtotime($reservation['from'])) : '';
	$to   = !empty($reservation['to']) ? wp_date('j M Y', strtotime($reservation['to'])) : '';

	if ($from && $to) {
		return "$from to $to";
	}

	if ($from) {
		return "from $from";
	}

	if ($to) {
		return "until $to";
	}

	return 'no dates set';
}

/**
 * Reserve controls for one demo site: an editable form for whoever may change
 * the reservation, a read-only badge for everyone else, nothing at all for a
 * free site viewed by a logged-out user.
 *
 * Returned rather than echoed because the site list caches its markup and has to
 * splice these in per request — see hale_dash_fill_reserve_placeholders().
 *
 * @param int $site_id blogID of the site in this dashboard's own network.
 */
function hale_dash_render_reserve_control($site_id) {
	$reservation = hale_dash_get_reservation($site_id);
	$can_edit    = hale_dash_can_edit_reservation($reservation);
	$is_reserved = !empty($reservation);

	if (!$can_edit) {
		if (!$is_reserved) {
			return '';
		}

		return '<p class="hale-dash-reserve__status govuk-!-margin-0"><span class="hale-dash-reserve__badge"><i class="fa-solid fa-lock" aria-hidden="true"></i> Reserved</span> <span class="hale-dash-reserve__by">'
			. esc_html($reservation['user_name']) . ' &middot; ' . esc_html(hale_dash_format_reservation_dates($reservation)) . '</span></p>';
	}

	ob_start();
	?>
	<form class="hale-dash-reserve" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<input type="hidden" name="action" value="hale_dash_reserve_site">
		<input type="hidden" name="site_id" value="<?php echo esc_attr($site_id); ?>">
		<?php wp_nonce_field('hale_dash_reserve_site_' . $site_id); ?>

		<div class="govuk-checkboxes__item hale-dash-reserve__check">
			<input class="govuk-checkboxes__input" type="checkbox" id="hd-reserve-<?php echo esc_attr($site_id); ?>" name="reserved" value="1" <?php checked($is_reserved); ?>>
			<label class="govuk-label govuk-checkboxes__label" for="hd-reserve-<?php echo esc_attr($site_id); ?>">Reserved</label>
		</div>

		<div class="hale-dash-reserve__field hale-dash-reserve__field--from">
			<label class="govuk-label govuk-label--s" for="hd-reserve-from-<?php echo esc_attr($site_id); ?>">From</label>
			<input class="govuk-input" type="date" id="hd-reserve-from-<?php echo esc_attr($site_id); ?>" name="from" value="<?php echo esc_attr($reservation['from'] ?? ''); ?>">
		</div>

		<div class="hale-dash-reserve__field hale-dash-reserve__field--to">
			<label class="govuk-label govuk-label--s" for="hd-reserve-to-<?php echo esc_attr($site_id); ?>">To</label>
			<input class="govuk-input" type="date" id="hd-reserve-to-<?php echo esc_attr($site_id); ?>" name="to" value="<?php echo esc_attr($reservation['to'] ?? ''); ?>">
		</div>

		<div class="hale-dash-reserve__actions">
			<button class="govuk-button govuk-button--secondary hale-dash-reserve__save" data-module="govuk-button" type="submit"><i class="fa-solid fa-save" aria-hidden="true"></i> Save</button>
			<?php if ($is_reserved): ?>
				<button class="govuk-button govuk-button--warning hale-dash-reserve__save" data-module="govuk-button" type="submit" name="release" value="1"><i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i> Release</button>
			<?php endif; ?>
		</div>
	</form>
	<?php

	return ob_get_clean();
}

/**
 * The site list caches its rendered markup in a transient shared by everyone, so
 * the reserve controls — which differ per user and change the moment somebody
 * books a site — cannot be baked into it. The cached markup carries an
 * <!--hd-reserve:ID--> marker where each control belongs instead, and this fills
 * them in on the way out.
 */
function hale_dash_fill_reserve_placeholders($html) {
	return preg_replace_callback(
		'/<!--hd-reserve:(\d+)-->/',
		function ($matches) {
			return hale_dash_render_reserve_control((int) $matches[1]);
		},
		$html
	);
}

/**
 * Handles the per-site reserve form. Unticking the box releases the site.
 */
function hale_dash_handle_reserve_site() {
	$redirect = wp_get_referer() ?: home_url('/');
	$site_id  = isset($_POST['site_id']) ? absint($_POST['site_id']) : 0;

	if (!$site_id || !is_user_logged_in()) {
		wp_safe_redirect($redirect);
		exit;
	}

	check_admin_referer('hale_dash_reserve_site_' . $site_id);

	// Ensure the site exists in this network and isn't the dashboard itself.
	if (!get_blog_details($site_id) || $site_id === get_current_blog_id()) {
		wp_safe_redirect($redirect);
		exit;
	}

	$reservations = hale_dash_get_reservations();
	$existing     = $reservations[(string) $site_id] ?? null;

	if (!hale_dash_can_edit_reservation($existing)) {
		wp_safe_redirect($redirect);
		exit;
	}

	// Released either by the explicit Release button or by unticking the box.
	if (!empty($_POST['release']) || empty($_POST['reserved'])) {
		unset($reservations[(string) $site_id]);
	} else {
		$from  = hale_dash_sanitize_date($_POST['from'] ?? '');
		$to    = hale_dash_sanitize_date($_POST['to'] ?? '');
		$today = current_time('Y-m-d');

		// A "to" date already in the past would be saved and then dropped by the
		// pruner on the very next read, so the site would silently show as free
		// again. Hold it to today instead.
		if ($to && $to < $today) {
			$to = $today;
		}

		// A backwards range is almost always a typo — clamp rather than reject,
		// so the user doesn't lose the rest of the form.
		if ($from && $to && $to < $from) {
			$to = $from;
		}

		$user = wp_get_current_user();

		$reservations[(string) $site_id] = [
			'user_id'   => get_current_user_id(),
			'user_name' => $user->display_name,
			'from'      => $from,
			'to'        => $to,
			'updated'   => time(),
		];
	}

	update_option('hale_dash_demo_reservations', $reservations, false);

	wp_safe_redirect($redirect);
	exit;
}

add_action('admin_post_hale_dash_reserve_site', 'hale_dash_handle_reserve_site');
