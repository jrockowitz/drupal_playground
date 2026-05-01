# Drupal Playground Elasticsearch

Sets up a simple Search API experience backed by Elasticsearch Connector for `article` content in Drupal Playground.

## What It Installs

- Search API
- Elasticsearch Connector
- Facets
- Facets Exposed Filters
- Better Exposed Filters
- A Search API server pointed at the local DDEV Elasticsearch service
- A Search API index for `article` nodes
- A simple Views search page at `/search/articles`
- An `Articles` shortcut that links to `/search/articles`
- Three default-content demo articles stored in the recipe

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

## Facets

This recipe includes `drupal/facets:^3.0` and uses the `facets_exposed_filters` submodule to turn the `Tags` filter on `/search/articles` into a real tag facet.

The package used for this setup is:

```shell
composer require 'drupal/facets:^3.0'
```

Facets 3 is the recommended approach for new Search API and Views integrations. In this recipe, the `Tags` filter is implemented as a **Facets 3 exposed filter** inside the search view rather than as a separate facet block.

The facet source for this setup is the existing search page display:

- `search_api:views_page__drupal_playground_article_search__page_1`

The facet target is the indexed tags field in the `drupal_playground_articles` Search API index.

Current behavior:

- keyword search remains in place
- the `Authored by` filter remains in place
- the `Tags` filter is a choice-based tags facet
- Search API relevance remains the default sort

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
- ⚫ Confirm the index shows `3` items indexed, or run `ddev drush search-api:index drupal_playground_articles` and refresh.

### Default content review

- ⚫ Go to **Content** at `/admin/content`.
- ⚫ Confirm the three article titles are:
  - `Elasticsearch`
  - `Drupal Search API`
  - `Elasticsearch connector for Drupal & DDEV`
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
- ⚫ Confirm the exposed filters also include `Authored by` and a Facets-powered `Tags` filter.
- ⚫ Edit the `Tags` filter and confirm the filter plugin is `Facets filter`.
- ⚫ Confirm the facet settings are attached to the current page display and tag field.
- ⚫ Confirm the default sort is Search API relevance descending.
- ⚫ Visit `/search/articles`.
- ⚫ Search for `Drupal`.
- ⚫ Narrow the results with the `Tags` facet.
- ⚫ Narrow the results with the `Authored by` filter.
- ⚫ Confirm the search results page shows the three demo articles with teaser content.
- ⚫ Confirm the result teasers show article preview content without errors.

- ⚫ Confirm the `facets` module is enabled.
- ⚫ Confirm the `facets_exposed_filters` module is enabled.
- ⚫ Confirm the `better_exposed_filters` module is enabled.
- ⚫ Confirm the tags facet is attached to `search_api:views_page__drupal_playground_article_search__page_1`.
- ⚫ Confirm `/search/articles` shows tag choices instead of a free-text `Tags` input.
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

The recipe ships three `article` nodes as default content:

- Elasticsearch
- Drupal Search API
- Elasticsearch connector for Drupal & DDEV

The recipe also ships one shortcut in the default shortcut set:

- Articles

The current search page uses exposed filters for:

- `Search`
- `Authored by`
- `Tags`

The `Tags` filter is implemented as a Facets 3 exposed filter rather than a separate facet block.

## Scope

This first version intentionally keeps the setup small:

- indexes only `article` nodes
- uses a standard no-auth local Elasticsearch connection
- provides a minimal Views-backed search page

Out of scope for v1:

- autocomplete
- spellcheck
- production authentication or hardening

## References

- [DDEV Elasticsearch add-on](https://addons.ddev.com/addons/ddev/ddev-elasticsearch)
- [Elasticsearch Connector project page](https://www.drupal.org/project/elasticsearch_connector)
- [Required modules for Elasticsearch Connector](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/elasticsearch-connector/using-elasticsearch-connector-80x/installing-drupal-modules/required-modules)
- [Standard Elasticsearch connector setup](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/elasticsearch-connector/using-elasticsearch-connector-80x/set-up-a-search-api-server-with-an-elasticsearch-backend/the-0)
- [Add a search UI with Views](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/elasticsearch-connector/using-elasticsearch-connector-80x/add-a-search-ui)
- [Elasticsearch Connector 8.0.x local environment guide](https://www.drupal.org/docs/contributed-modules/elasticsearch-connector/developing-elasticsearch-connector-80x/set-up-a-local-environment)
