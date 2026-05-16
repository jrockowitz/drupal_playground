# ClinicalTrials.gov Data Elastic Recipe

Sets up a ClinicalTrials.gov data search experience backed by Elasticsearch for the `trial` content type created by the `clinical_trials_gov_recipe_data_setup` recipe.

## What It Installs

### Modules

- [Search API](https://www.drupal.org/project/search_api)
- [Elasticsearch Connector](https://www.drupal.org/project/elasticsearch_connector)
- [Facets](https://www.drupal.org/project/facets)
- `facets_exposed_filters` from [Facets](https://www.drupal.org/project/facets)
- [Better Exposed Filters](https://www.drupal.org/project/better_exposed_filters)
- [Search API Spellcheck](https://www.drupal.org/project/search_api_spellcheck)
- [Search API Autocomplete](https://www.drupal.org/project/search_api_autocomplete)
- [Views Exposed Filters Summary](https://www.drupal.org/project/views_filters_summary)
- `views_filters_summary_search_api` from [Views Exposed Filters Summary](https://www.drupal.org/project/views_filters_summary)
- [Views Ajax History](https://www.drupal.org/project/views_ajax_history)
- `clinical_trials_gov_data`

### Configuration

- A Search API server pointed at the local DDEV Elasticsearch service
- A Search API index for `trial` nodes
- A Views search page at `/trials`
- A Search API Autocomplete search definition for the `trials_elasticsearch` index
- Views-based exposed facet filters powered by `facets_exposed_filters`
- A `Did you mean:` spellcheck prompt on the `/trials` results page
- Search API Autocomplete suggestions on the `/trials` keyword input
- A header summary of the active exposed filters on `/trials`
- AJAX search state that updates the browser URL and back/forward history

### Default Content

- A `Clinical Trials` shortcut that links to `/trials`

## Prerequisite

This recipe depends on the `clinical_trials_gov_recipe_data_setup` recipe so the `trial` bundle and generated ClinicalTrials.gov fields already exist before Search API configuration is imported.

The recipe uses `facets_exposed_filters` directly in the View configuration rather than separate facet blocks or separately managed facet entities.

## Installation

```shell
# Install Drupal, import starter trials, and apply the ClinicalTrials.gov data search recipe.
ddev install trials-data-elastic
```

## Indexing

The `trials-data-elastic` preset clears and rebuilds the `trials_elasticsearch` Search API index automatically.

If you need to rebuild it manually later, run:

```shell
ddev drush search-api:clear trials_elasticsearch -y
ddev drush search-api:index trials_elasticsearch
```

## Steps to review

### Install and login

- ⚫ Run `ddev install trials-data-elastic`.
- ⚫ Confirm the install output includes `Applying ClinicalTrials.gov data Elasticsearch recipe...`.
- ⚫ Confirm the install output includes `Importing ClinicalTrials.gov studies...`.
- ⚫ Confirm the one-time login URL includes `destination=/trials`.
- ⚫ Open the one-time login URL and confirm you land on `/trials`.

### Search API review

- ⚫ Go to **Configuration → Search and metadata → Search API** at `/admin/config/search/search-api`.
- ⚫ Confirm there is a server named `Clinical Trials`.
- ⚫ Open the server edit page at `/admin/config/search/search-api/server/trials_elasticsearch/edit`.
- ⚫ Confirm the backend is Elasticsearch.
- ⚫ Confirm the URL is `http://elasticsearch:9200`.
- ⚫ Confirm the server status is enabled.
- ⚫ From `/admin/config/search/search-api`, open the index named `Clinical Trials`.
- ⚫ Or go directly to `/admin/config/search/search-api/index/trials_elasticsearch/edit`.
- ⚫ Confirm the datasource is node content limited to the `trial` bundle.
- ⚫ Confirm the server is `Clinical Trials`.
- ⚫ Confirm the index is enabled.
- ⚫ Confirm the index shows imported trial content, or run `ddev drush search-api:index trials_elasticsearch` and refresh.

### View and search page review

- ⚫ Go to **Structure → Views** at `/admin/structure/views`.
- ⚫ Open the view at `/admin/structure/views/view/trials_elasticsearch`.
- ⚫ Confirm the page path is `/trials`.
- ⚫ Confirm the exposed keyword filter labeled `Search` is present.
- ⚫ Confirm the exposed filters also include `Condition/Disease`, `Study status`, `Study phase`, `Study type`, `Sex`, and `Age`.
- ⚫ Confirm `Healthy volunteers` is not present.
- ⚫ Confirm the facet-backed filters render human-friendly labels such as `Recruiting`, `Phase 2`, and `Adult`.
- ⚫ Confirm the facet-backed filters show result counts next to each item.
- ⚫ Visit `/trials`.
- ⚫ Type the prefix of a stored condition and confirm autocomplete suggestions appear from `trial_cond` values.
- ⚫ Type the prefix of a stored keyword and confirm autocomplete suggestions appear from `trial_keyword` values.
- ⚫ Search for a misspelled keyword and confirm a `Did you mean:` correction appears when appropriate.
- ⚫ Apply multiple facet selections and confirm the result count updates.
- ⚫ Confirm the header summarizes the active filters and result count.
- ⚫ Confirm the full pager renders at the bottom of the results.
- ⚫ Search for a query with no matches and confirm the empty state shows:
- `<h2>No records found.</h2>`
- `<p>Please try different keywords and search again.</p>`

### Autocomplete review

- ⚫ Go to the `Clinical Trials` index Autocomplete tab.
- ⚫ Confirm Search API Autocomplete is enabled for the `Clinical Trials (Elasticsearch)` search.
- ⚫ Visit `/trials`.
- ⚫ Type the prefix of a stored condition and confirm autocomplete suggestions appear from `trial_cond` values.
- ⚫ Type the prefix of a stored keyword and confirm autocomplete suggestions appear from `trial_keyword` values.

### Default content review

- ⚫ Go to **Configuration → User interface → Shortcuts** at `/admin/config/user-interface/shortcut`.
- ⚫ Open the default shortcut set at `/admin/config/user-interface/shortcut/manage/default`.
- ⚫ Confirm there is a shortcut titled `Clinical Trials`.
- ⚫ Confirm the shortcut link points to `/trials`.

### Command-line review

- ⚫ Run `ddev drush search-api:server-list` and confirm `trials_elasticsearch` is enabled.
- ⚫ Run `ddev drush search-api:list` and confirm `trials_elasticsearch` is enabled.
- ⚫ Run `ddev drush search-api:search trials_elasticsearch leukemia`.
- ⚫ Confirm Search API returns trial results.
