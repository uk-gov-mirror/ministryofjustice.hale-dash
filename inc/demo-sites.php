<?php

/**
 * Demo environment site data.
 *
 * get_sites() only ever returns the network of whichever environment the
 * dashboard is running on, so demo's own public hc-rest endpoint is the only way
 * to see what exists over there.
 *
 * This is a cross-check, not a source of truth. The dashboard works entirely
 * from its own network; the demo list is consulted solely to point out sites
 * that have no counterpart on demo. Nothing here should become load-bearing —
 * demo goes down, and the dashboard must not go with it.
 */

/**
 * @return array|false Decoded API response, or false if it could not be fetched.
 */
function hale_dash_get_demo_sites() {
	$transient_key = 'hale_dash_demo_sites_data';
	$cached        = get_transient($transient_key);

	if (is_array($cached)) {
		return $cached;
	}

	// Failures are cached too, or every request while demo is down would pay the
	// full request timeout — twice over on a site list cache miss, since the
	// summary box and the site list each ask for the list. Kept much shorter
	// than the success cache so the dashboard picks demo back up quickly.
	if ($cached === 'unavailable') {
		return false;
	}

	$demo_sites = hale_dash_fetch_demo_sites();

	if ($demo_sites === false) {
		set_transient($transient_key, 'unavailable', MINUTE_IN_SECONDS);

		return false;
	}

	set_transient($transient_key, $demo_sites, 5 * MINUTE_IN_SECONDS);

	return $demo_sites;
}

/**
 * The uncached request itself. Split out so the caching above reads as one
 * decision rather than being threaded through each failure branch.
 *
 * @return array|false
 */
function hale_dash_fetch_demo_sites() {
	$response = wp_remote_get(
		'https://demo.websitebuilder.service.justice.gov.uk/wp-json/hc-rest/v1/sites/domain',
		['timeout' => 10]
	);

	if (is_wp_error($response)) {
		error_log('Demo site list request failed: ' . $response->get_error_message());
		return false;
	}

	$status_code = wp_remote_retrieve_response_code($response);
	if ($status_code !== 200) {
		error_log("Demo site list request returned HTTP $status_code.");
		return false;
	}

	$demo_sites = json_decode(wp_remote_retrieve_body($response), true);

	if (!is_array($demo_sites)) {
		error_log('Demo site list response could not be decoded.');
		return false;
	}

	return $demo_sites;
}

/**
 * slug => blogID, so a site in the dashboard's own network can be matched to its
 * counterpart on demo. The blog IDs differ between environments, so the slug is
 * the only thing the two lists have in common.
 *
 * @return array
 */
function hale_dash_get_demo_site_ids_by_slug() {
	$demo_sites = hale_dash_get_demo_sites();

	if (!is_array($demo_sites)) {
		return [];
	}

	$ids = [];

	foreach ($demo_sites as $demo_site) {
		if (!isset($demo_site['blogID'], $demo_site['slug'])) {
			continue;
		}

		$slug = strtolower(trim((string) $demo_site['slug'], '/'));

		// The network's main site has no slug on either side, so an empty one
		// would match every slugless site rather than a single demo instance.
		if ($slug === '') {
			continue;
		}

		$ids[$slug] = (int) $demo_site['blogID'];
	}

	return $ids;
}

