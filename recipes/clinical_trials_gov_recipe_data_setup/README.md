# ClinicalTrials.gov Data Setup Recipe

Applies the ClinicalTrials.gov data setup workflow.

This recipe installs:

- `readonly_field_widget`
- `clinical_trials_gov`
- `clinical_trials_gov_report`

It then runs the `clinical_trials_gov.settings:setUp` config action with the
recipe query to discover fields, create the bundle and fields, and build the
migration configuration.

By default, the recipe uses:

```text
query.cond=Cancer&query.locn=New%20York&filter.overallStatus=RECRUITING%7CNOT_YET_RECRUITING
```

Install it with:

```bash
ddev install trials-data-setup
```

Override the setup query during install with:

```bash
ddev install trials-data-setup --query='query.term=Memorial%20Sloan%20Kettering&filter.overallStatus=RECRUITING%7CNOT_YET_RECRUITING'
```

If you run the recipe directly through Drupal, pass the recipe input with:

```bash
drush recipe ../recipes/clinical_trials_gov_recipe_data_setup --input='clinical_trials_gov_recipe_data_setup.query=query.term=Memorial%20Sloan%20Kettering&filter.overallStatus=RECRUITING%7CNOT_YET_RECRUITING'
```
