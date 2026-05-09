# Clinical Trials Recipe Elastic Autocomplete

Adds Search API Autocomplete to the `/trials` page provided by `clinical_trials_gov_recipe_elastic`.

## Prerequisite

Apply `clinical_trials_gov_recipe_elastic` first so the `trials_elasticsearch` View and index already exist before this recipe imports the autocomplete search config.

## What It Installs

- [Search API Autocomplete](https://www.drupal.org/project/search_api_autocomplete)
- `clinical_trials_gov_search_api_autocomplete`
- The `search_api_autocomplete.search.trials_elasticsearch` config entity
- Anonymous and authenticated access to the `trials_elasticsearch` autocomplete search
