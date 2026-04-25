# ClinicalTrials.gov

Drupal integration with the [ClinicalTrials.gov API v2](https://clinicaltrials.gov/data-api/api).

## Submodules

- **clinical_trials_gov_report** — Admin report at `/admin/reports/status/clinical-trials-gov`
- **clinical_trials_gov_test** — Stub manager and JSON fixtures for testing

## Services

| Service | Interface | Description |
|---|---|---|
| `clinical_trials_gov.api` | `ClinicalTrialsGovApiInterface` | Low-level HTTP client |
| `clinical_trials_gov.manager` | `ClinicalTrialsGovManagerInterface` | Fetches and organises API data |
| `clinical_trials_gov.builder` | `ClinicalTrialsGovBuilderInterface` | Converts study data to render arrays |

## Development

The `test/` directory contains a standalone PHP explorer for the API (proof-of-concept). Run it directly via a PHP web server — it has no Drupal dependency.
