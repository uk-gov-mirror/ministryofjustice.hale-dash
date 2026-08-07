<?php
/**
 * Search fields for a site list. Filtering is handled by assets/js/search.js,
 * which keys off the data attributes on each .hale-dash-site-item.
 */
?>
<div class="hale-dash-search">
	<div class="hale-dash-search__field hale-dash-search__field--name">
		<label class="govuk-label govuk-label--s" for="hd-search-name">Search by name, slug or URL</label>
		<input class="govuk-input" type="search" id="hd-search-name" placeholder="e.g. 'Law Commission' or 'lawcom'" autocomplete="off">
	</div>
	<div class="hale-dash-search__field">
		<label class="govuk-label govuk-label--s" for="hd-search-id">Search by ID</label>
		<input class="govuk-input hale-dash-search__id-input" type="search" id="hd-search-id" placeholder="e.g. 42" inputmode="numeric" autocomplete="off">
	</div>
	<p class="hale-dash-search__count govuk-body-s govuk-hint" id="hd-search-count" aria-live="polite"></p>
</div>
