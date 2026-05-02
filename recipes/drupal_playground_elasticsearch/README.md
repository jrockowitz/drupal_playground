# Drupal Playground Elasticsearch

Sets up a simple Search API experience backed by Elasticsearch Connector for `article` content in Drupal Playground, including autocomplete suggestions and spellcheck.

## What It Installs

- Search API
- Elasticsearch Connector
- Facets
- Facets Exposed Filters
- Better Exposed Filters
- Search API Autocomplete
- Search API Spellcheck
- Views Exposed Filters Summary
- Views Ajax History
- A Search API server pointed at the local DDEV Elasticsearch service
- A Search API index for `article` nodes
- A simple Views search page at `/search/articles`
- Autocomplete suggestions on the `/search/articles` keyword field
- A `Did you mean:` spellcheck prompt on the `/search/articles` results page
- A header summary of the active exposed filters on `/search/articles`
- AJAX search state that updates the browser URL and back/forward history
- An `Articles` shortcut that links to `/search/articles`
- Ten default-content demo articles stored in the recipe

## Local DDEV Elasticsearch

This recipe expects a local Elasticsearch service in DDEV at `http://elasticsearch:9200`.

This repository commits the DDEV service files directly so the local setup is version-controlled.

If you are adding Elasticsearch to a different DDEV project from scratch, the reference add-on command is:

```shell
ddev add-on get ddev/ddev-elasticsearch
```

In this repository you do not need to run that add-on command because the Elasticsearch service files are already committed. The local setup flow for this project is:

```shell
ddev start
ddev describe
```

After `ddev start`, confirm the `elasticsearch` service is listed as `OK` and exposed at `http://drupal-playground.ddev.site:9200`.

## Installation

```shell
# Install Drupal and apply the Elasticsearch preset.
ddev install elastic
```

## Indexing

Run indexing after applying the recipe so the recipe-provided demo articles appear in search results:

```shell
ddev drush search-api:index drupal_playground_articles
```

## Autocomplete

This recipe includes `drupal/search_api_autocomplete:^1.11`.

The current Elasticsearch Connector backend in this project supports Search API spellcheck but does not advertise the `search_api_autocomplete` backend feature. Because of that, the recipe configures Search API Autocomplete with the **live results** suggester for the article search view instead of the server-backed suggester.

That gives the `/search/articles` keyword field autocomplete suggestions based on the indexed article content without depending on backend-native autocomplete support.

The autocomplete search entity is:

- `search_api_autocomplete.search.drupal_playground_article_search`

The recipe grants the generated autocomplete permission to:

- anonymous users
- authenticated users

That keeps the public article search page working for both visitors and signed-in users.

## Spellcheck

This recipe includes `drupal/search_api_spellcheck:^4.0`.

The article search view adds the `Search API Spellcheck "Did You Mean"` header plugin so misspelled queries can suggest a corrected search. In this recipe the spellcheck prompt is configured to:

- show a single best correction
- hide itself when the search already has results
- use collation when available from the backend

## Exposed Filter Summary

This recipe includes `drupal/views_filters_summary:^3.4`.

Because the article search page is backed by a Search API index, the recipe also enables the bundled `views_filters_summary_search_api` submodule so the selected fulltext and Search API filter values are summarized correctly.

The article search view replaces the plain result-count header with a Views Exposed Filters Summary header configured to:

- display the result count
- summarize the `Search` and `Tags` exposed filters
- show filter labels
- avoid per-filter remove links
- keep the summary compatible with the AJAX search page

## AJAX History

This recipe includes `drupal/views_ajax_history:^1.8`.

The article search view already uses AJAX for filtering and paging. This module adds browser history support to that AJAX behavior so users can:

- share the current filtered search URL
- use the browser back button after changing filters or paging results
- use the browser forward button to return to a later AJAX search state

The recipe enables the `ajax_history` display extender on the article search view.

## Facets

This recipe includes `drupal/facets:^3.0` and uses the `facets_exposed_filters` submodule to turn the `Tags` filter on `/search/articles` into a real tag facet.

The Elasticsearch recipe installs the needed search UI modules for this feature:

- `facets`
- `facets_exposed_filters`
- `better_exposed_filters`

The underlying Composer package used for this setup is:

```shell
composer require 'drupal/facets:^3.0'
```

Facets 3 is the recommended approach for new Search API and Views integrations. In this recipe, the `Tags` filter is implemented as a **Facets 3 exposed filter** inside the search view rather than as a separate facet block.

The facet source for this setup is the existing search page display:

- `search_api:views_page__drupal_playground_article_search__page_1`

The facet target is the indexed tags field in the `drupal_playground_articles` Search API index.

Current behavior:

- keyword search remains in place
- keyword search supports autocomplete suggestions
- misspelled keywords can show a spellcheck correction
- the header summarizes the active search and tag filters
- AJAX filter and pager changes update browser history
- the `Tags` filter is a choice-based tags facet
- Search API relevance remains the default sort

The `ddev install elastic` preset clears the Search API index before reindexing content so Elasticsearch field mappings stay in sync with the recipe configuration.

## Steps to Review (⚫ = step  ✅ = pass  ❌ = fail)

### Local Elasticsearch service

- ⚫ Run `ddev describe`.
- ⚫ Confirm the `elasticsearch` service is listed as `OK`.
- ⚫ Confirm the service is exposed at `http://drupal-playground.ddev.site:9200`.
- ⚫ Review the local DDEV setup files:
  - [.ddev/.env.elasticsearch](/Users/rockowij/Sites/drupal_playground/.ddev/.env.elasticsearch)
  - [.ddev/docker-compose.elasticsearch.yaml](/Users/rockowij/Sites/drupal_playground/.ddev/docker-compose.elasticsearch.yaml)
  - [.ddev/elasticsearch/docker-compose.elasticsearch8.yaml](/Users/rockowij/Sites/drupal_playground/.ddev/elasticsearch/docker-compose.elasticsearch8.yaml)
  - [.ddev/elasticsearch/config/elasticsearch.yml](/Users/rockowij/Sites/drupal_playground/.ddev/elasticsearch/config/elasticsearch.yml)

### Install and login

- ⚫ Run `ddev install elastic`.
- ⚫ Confirm the install output includes `Applying Elasticsearch recipe...`.
- ⚫ Confirm the install output includes `Created content for Drupal Playground Elasticsearch recipe.`.
- ⚫ Confirm the install output includes `Clearing Elasticsearch index to rebuild mappings...`.
- ⚫ Confirm the install output includes `Indexing Elasticsearch demo content...`.
- ⚫ Run `ddev uli`.
- ⚫ Open the one-time login URL and confirm you land in the Drupal admin area.

### Search API server review

- ⚫ Go to **Configuration → Search and metadata → Search API** at `/admin/config/search/search-api`.
- ⚫ Confirm there is a server named `Drupal Playground Elasticsearch`.
- ⚫ Open the server edit page at `/admin/config/search/search-api/server/drupal_playground_elasticsearch/edit`.
- ⚫ Confirm the backend is Elasticsearch.
- ⚫ Confirm the connector is `standard`.
- ⚫ Confirm the URL is `http://elasticsearch:9200`.
- ⚫ Confirm the server status is enabled.

### Search index review

- ⚫ From `/admin/config/search/search-api`, open the index named `Drupal Playground Articles`.
- ⚫ Or go directly to `/admin/config/search/search-api/index/drupal_playground_articles/edit`.
- ⚫ Confirm the datasource is node content limited to the `article` bundle.
- ⚫ Confirm the indexed fields include `Title`, `Body`, and `Authored on`.
- ⚫ Confirm the indexed fields also include `Author name` and `Tags`.
- ⚫ Confirm the server is `Drupal Playground Elasticsearch`.
- ⚫ Confirm the index is enabled.
- ⚫ Confirm the index shows `10` items indexed, or run `ddev drush search-api:index drupal_playground_articles` and refresh.

### Default content review

- ⚫ Go to **Content** at `/admin/content`.
- ⚫ Confirm the original three article titles are:
- `Elasticsearch`
- `Drupal Search API`
- `Elasticsearch connector for Drupal & DDEV`
- ⚫ Confirm the additional article titles are:
  - `Search relevance in Drupal with Elasticsearch`
  - `How indexing works in Drupal Search API`
  - `Running Elasticsearch locally with DDEV`
  - `Building a Views-based search page with Search API`
  - `When to use the Elasticsearch connector module`
  - `Debugging failed indexing in a local Drupal search setup`
  - `Autocomplete, facets, and spellcheck in Drupal search`
- ⚫ Open each article and confirm it has a summary.
- ⚫ Confirm each article has multiple `<h2>` headings in the body.
- ⚫ Confirm the articles have relevant tags attached:
  - `Elasticsearch`
  - `Search API`
  - `Drupal`
  - `DDEV`
  - `Full-text search`
  - `Indexing`
  - `Connector`
- ⚫ Go to **Configuration → User interface → Shortcuts** at `/admin/config/user-interface/shortcut`.
- ⚫ Open the default shortcut set at `/admin/config/user-interface/shortcut/manage/default`.
- ⚫ Confirm there is a shortcut titled `Articles`.
- ⚫ Confirm the shortcut link points to `/search/articles`.

### View and search page review

- ⚫ Go to **Structure → Views** at `/admin/structure/views`.
- ⚫ Open the view at `/admin/structure/views/view/drupal_playground_article_search`.
- ⚫ Confirm the view uses `drupal_playground_articles` as its Search API data source.
- ⚫ Confirm the page path is `/search/articles`.
- ⚫ Confirm the exposed fulltext filter labeled `Search` is present.
- ⚫ Confirm the `Search` field uses Search API Autocomplete.
- ⚫ Type `Drupal` into the search field and confirm autocomplete suggestions appear.
- ⚫ Confirm the exposed filters also include a Facets-powered `Tags` filter.
- ⚫ Edit the `Tags` filter and confirm the filter plugin is `Facets filter`.
- ⚫ Confirm the facet settings are attached to the current page display and tag field.
- ⚫ Confirm the default sort is Search API relevance descending.
- ⚫ Confirm the tags facet renders as a multi-select list of tag names, not raw term IDs.
- ⚫ Visit `/search/articles`.
- ⚫ Search for a misspelled version of `Elasticsearch` and confirm a `Did you mean:` correction appears.
- ⚫ Search for `Drupal`.
- ⚫ Narrow the results with the `Tags` facet by selecting `Drupal`.
- ⚫ Confirm the filtered page keeps the keyword query and reduces the results to the Drupal-tagged articles.
- ⚫ Confirm the search results page shows the demo articles with teaser content.
- ⚫ Confirm the result teasers show article preview content without errors.

- ⚫ Confirm the `facets` module is enabled.
- ⚫ Confirm the `facets_exposed_filters` module is enabled.
- ⚫ Confirm the `better_exposed_filters` module is enabled.
- ⚫ Confirm the `search_api_autocomplete` module is enabled.
- ⚫ Confirm the `search_api_spellcheck` module is enabled.
- ⚫ Confirm the `views_filters_summary` module is enabled.
- ⚫ Confirm the `views_filters_summary_search_api` submodule is enabled.
- ⚫ Confirm the `views_ajax_history` module is enabled.
- ⚫ Confirm the tags facet is attached to `search_api:views_page__drupal_playground_article_search__page_1`.
- ⚫ Confirm `/search/articles` shows tag choices instead of a free-text `Tags` input.
- ⚫ Confirm `/search/articles` attaches the Search API Autocomplete JavaScript and endpoint for `drupal_playground_article_search`.
- ⚫ Confirm `/search/articles` attaches the Views Exposed Filters Summary JavaScript.
- ⚫ Search for `Drupal` and select the `Drupal` tag.
- ⚫ Confirm the header summarizes the active filters and result count.
- ⚫ Confirm `/search/articles` attaches the Views Ajax History JavaScript and `viewsAjaxHistory` Drupal settings.
- ⚫ Change the search keywords, tags, or pager with AJAX and confirm the browser URL updates.
- ⚫ Use the browser back button and confirm the previous AJAX search state is restored.
- ⚫ Confirm selecting a tag narrows the results correctly.

### Command-line review

- ⚫ Run `ddev drush search-api:server-list` and confirm `drupal_playground_elasticsearch` is enabled.
- ⚫ Run `ddev drush search-api:list` and confirm `drupal_playground_articles` is enabled.
- ⚫ Run `ddev drush search-api:search drupal_playground_articles Drupal`.
- ⚫ Confirm Search API returns the recipe-provided articles.

## Verification

Check the Search API server status:

```shell
ddev drush search-api:server-status drupal_playground_elasticsearch
```

Visit the search page:

```shell
ddev launch /search/articles
```

Or use the one-time login URL after `ddev install elastic` and browse to:

- `/search/articles`

The recipe ships ten `article` nodes as default content:

- Elasticsearch
- Drupal Search API
- Elasticsearch connector for Drupal & DDEV
- Search relevance in Drupal with Elasticsearch
- How indexing works in Drupal Search API
- Running Elasticsearch locally with DDEV
- Building a Views-based search page with Search API
- When to use the Elasticsearch connector module
- Debugging failed indexing in a local Drupal search setup
- Autocomplete, facets, and spellcheck in Drupal search

The recipe also ships one shortcut in the default shortcut set:

- Articles

The current search page uses exposed filters for:

- `Search`
- `Tags`

The `Tags` filter is implemented as a Facets 3 exposed filter rather than a separate facet block.

## Scope

This first version intentionally keeps the setup small:

- indexes only `article` nodes
- uses a standard no-auth local Elasticsearch connection
- provides a minimal Views-backed search page

Out of scope for v1:

- production authentication or hardening

## References

- [DDEV Elasticsearch add-on](https://addons.ddev.com/addons/ddev/ddev-elasticsearch)
- [Elasticsearch Connector project page](https://www.drupal.org/project/elasticsearch_connector)
- [Required modules for Elasticsearch Connector](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/elasticsearch-connector/using-elasticsearch-connector-80x/installing-drupal-modules/required-modules)
- [Standard Elasticsearch connector setup](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/elasticsearch-connector/using-elasticsearch-connector-80x/set-up-a-search-api-server-with-an-elasticsearch-backend/the-0)
- [Add a search UI with Views](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/elasticsearch-connector/using-elasticsearch-connector-80x/add-a-search-ui)
- [Elasticsearch Connector 8.0.x local environment guide](https://www.drupal.org/docs/contributed-modules/elasticsearch-connector/developing-elasticsearch-connector-80x/set-up-a-local-environment)
