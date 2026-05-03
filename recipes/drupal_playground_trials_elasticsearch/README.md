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
- Autocomplete suggestions on the `/trials` keyword field
- A `Did you mean:` spellcheck prompt on the `/trials` results page
- A header summary of the active exposed filters on `/trials`
- AJAX search state that updates the browser URL and back/forward history

### Default Content

- A `Clinical Trials` shortcut that links to `/trials`

## Prerequisite

This recipe depends on the `drupal_playground_trials` recipe so the `trial` bundle and generated ClinicalTrials.gov fields already exist before Search API configuration is imported.

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
