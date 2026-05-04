# Drupal Playground Trials Elasticsearch

Sets up a Clinical Trials search experience backed by Elasticsearch for the `trial` content type created by the `drupal_playground_trials` recipe.

## What It Installs

### Modules

- [Search API](https://www.drupal.org/project/search_api)
- [Elasticsearch Connector](https://www.drupal.org/project/elasticsearch_connector)
- [Facets](https://www.drupal.org/project/facets)
- `facets_exposed_filters` from [Facets](https://www.drupal.org/project/facets)
- [Better Exposed Filters](https://www.drupal.org/project/better_exposed_filters)
- [Views Autocomplete Filters](https://www.drupal.org/project/views_autocomplete_filters)
- [Search API Spellcheck](https://www.drupal.org/project/search_api_spellcheck)
- [Views Exposed Filters Summary](https://www.drupal.org/project/views_filters_summary)
- `views_filters_summary_search_api` from [Views Exposed Filters Summary](https://www.drupal.org/project/views_filters_summary)
- [Views Ajax History](https://www.drupal.org/project/views_ajax_history)

### Configuration

- A Search API server pointed at the local DDEV Elasticsearch service
- A Search API index for `trial` nodes
- A Views search page at `/trials`
- Views-based exposed facet filters powered by `facets_exposed_filters`
- Autocomplete suggestions on the `/trials` keyword field
- A `Did you mean:` spellcheck prompt on the `/trials` results page
- A header summary of the active exposed filters on `/trials`
- AJAX search state that updates the browser URL and back/forward history

### Default Content

- A `Clinical Trials` shortcut that links to `/trials`

## Prerequisite

This recipe depends on the `drupal_playground_trials` recipe so the `trial` bundle and generated ClinicalTrials.gov fields already exist before Search API configuration is imported.

The recipe uses `facets_exposed_filters` directly in the View configuration rather than separate facet blocks or separately managed facet entities.

## Installation

```shell
# Install Drupal, import starter trials, and apply the trials search recipe.
ddev install trials-setup
```

## Indexing

The `trials-setup` preset clears and rebuilds the `drupal_playground_trials` Search API index automatically.

If you need to rebuild it manually later, run:

```shell
ddev drush search-api:clear drupal_playground_trials -y
ddev drush search-api:index drupal_playground_trials
```

## Steps to review

### Install and login

- ⚫ Run `ddev install trials-setup`.
- ⚫ Confirm the install output includes `Applying ClinicalTrials.gov setup recipe...`.
- ⚫ Confirm the install output includes `Applying Clinical Trials Elasticsearch recipe...`.
- ⚫ Confirm the install output includes `Importing ClinicalTrials.gov studies...`.
- ⚫ Confirm the one-time login URL includes `destination=/trials`.
- ⚫ Open the one-time login URL and confirm you land on `/trials`.

### Search API review

- ⚫ Go to **Configuration → Search and metadata → Search API** at `/admin/config/search/search-api`.
- ⚫ Confirm there is a server named `Drupal Playground Trials`.
- ⚫ Open the server edit page at `/admin/config/search/search-api/server/drupal_playground_trials/edit`.
- ⚫ Confirm the backend is Elasticsearch.
- ⚫ Confirm the URL is `http://elasticsearch:9200`.
- ⚫ Confirm the server status is enabled.
- ⚫ From `/admin/config/search/search-api`, open the index named `Drupal Playground Trials`.
- ⚫ Or go directly to `/admin/config/search/search-api/index/drupal_playground_trials/edit`.
- ⚫ Confirm the datasource is node content limited to the `trial` bundle.
- ⚫ Confirm the server is `Drupal Playground Trials`.
- ⚫ Confirm the index is enabled.
- ⚫ Confirm the index shows imported trial content, or run `ddev drush search-api:index drupal_playground_trials` and refresh.

### View and search page review

- ⚫ Go to **Structure → Views** at `/admin/structure/views`.
- ⚫ Open the view at `/admin/structure/views/view/drupal_playground_trials`.
- ⚫ Confirm the page path is `/trials`.
- ⚫ Confirm the exposed keyword filter labeled `Search` is present.
- ⚫ Confirm the exposed filters also include `Condition/Disease`, `Study status`, `Study phase`, `Study type`, `Sex`, and `Age`.
- ⚫ Confirm `Healthy volunteers` is not present.
- ⚫ Confirm the facet-backed filters render human-friendly labels such as `Recruiting`, `Phase 2`, and `Adult`.
- ⚫ Confirm the facet-backed filters show result counts next to each item.
- ⚫ Visit `/trials`.
- ⚫ Type a keyword into `Search` and confirm autocomplete suggestions appear.
- ⚫ Search for a misspelled keyword and confirm a `Did you mean:` correction appears when appropriate.
- ⚫ Apply multiple facet selections and confirm the result count updates.
- ⚫ Confirm the header summarizes the active filters and result count.
- ⚫ Confirm the full pager renders at the bottom of the results.
- ⚫ Search for a query with no matches and confirm the empty state shows:
- `<h2>No records found.</h2>`
- `<p>Please try different keywords and search again.</p>`

### Default content review

- ⚫ Go to **Configuration → User interface → Shortcuts** at `/admin/config/user-interface/shortcut`.
- ⚫ Open the default shortcut set at `/admin/config/user-interface/shortcut/manage/default`.
- ⚫ Confirm there is a shortcut titled `Clinical Trials`.
- ⚫ Confirm the shortcut link points to `/trials`.

### Command-line review

- ⚫ Run `ddev drush search-api:server-list` and confirm `drupal_playground_trials` is enabled.
- ⚫ Run `ddev drush search-api:list` and confirm `drupal_playground_trials` is enabled.
- ⚫ Run `ddev drush search-api:search drupal_playground_trials leukemia`.
- ⚫ Confirm Search API returns trial results.
