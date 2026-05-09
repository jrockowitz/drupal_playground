# ClinicalTrials.gov Setup Recipe

Applies the ClinicalTrials.gov setup workflow.

This recipe installs:

- `readonly_field_widget`
- `clinical_trials_gov`
- `clinical_trials_gov_report`

It then runs the `clinical_trials_gov.settings:setUp` config action with the
recipe query to discover fields, create the bundle and fields, and build the
migration configuration.
