# Clinical Trials Recipe Milvus

## Goals

- Build a DeepChat interface for finding clinical trials as a patient, caregiver, or medical professional.
- Implement a local Milvus database for trial retrieval in DDEV.
- Provide `ddev install trials-milvus` to set up ClinicalTrials.gov content, AI Search, Milvus, and DeepChat together.

## Summary

This recipe layers a Milvus-backed AI chat and RAG experience on top of the existing
`clinical_trials_gov_recipe_setup` foundation.

It installs:

- `ai_agents`
- `ai_assistant_api`
- `ai_chatbot`
- `ai_search`
- `search_api`
- `ai_vdb_provider_milvus`

It configures:

- local Milvus provider settings for `http://milvus:19530`
- a Search API AI Search server and index for imported `trial` nodes
- a trials-specific AI assistant and AI agent
- a DeepChat block labeled `Ask AI about Clinical Trials`
- DeepChat visibility on `<front>` and `/trials`

When combined with `clinical_trials_gov_recipe_elastic`, the `/trials` page becomes hybrid:

- Elasticsearch powers listing, filtering, and faceting
- Milvus powers the chat-based RAG assistant

## Requirements

- DDEV is installed and running for this project.
- The AI recipe prerequisites exist:
  - `keys/openai.key`
  - `keys/anthropic.key`
- The local Milvus DDEV service file is present at `.ddev/docker-compose.milvus.yaml`.

## Notes

- This recipe is additive and does not replace `trials-elastic`.
- Retrieval is grounded in imported Drupal `trial` nodes, not live ClinicalTrials.gov API calls.
- This recipe does not provide its own `/trials` route or fallback page.
- The canonical `/trials` page comes from `clinical_trials_gov_recipe_elastic` when that recipe is installed.
- The DeepChat block uses the exact user-facing copy:
  - Label: `Ask AI about Clinical Trials`
  - First message: `Ask our AI Assistant to help you search for a clinical trials.`

## DDEV Setup

These are the steps used to add Milvus to the local DDEV environment:

1. Create `.ddev/docker-compose.milvus.yaml`.
2. Define the supporting Milvus stack in that compose file:
   - `etcd`
   - `minio`
   - `milvus`
   - `attu`
3. Mount local persistent storage under `.ddev/milvus/volumes/` for:
   - `etcd`
   - `minio`
   - `milvus`
4. Add `.ddev/milvus/.gitignore` so the local Milvus volume data is not committed.
5. Expose the Milvus gRPC and health ports inside DDEV:
   - `19530`
   - `9091`
6. Expose Attu through DDEV on port `8521` so the local Milvus UI is available at:
   - `https://drupal-playground.ddev.site:8521`
7. Restart DDEV with `ddev restart` so the new Milvus services are created.

## Setup Steps

### 1. Start DDEV and Milvus

- Run `ddev restart`.
- Run `ddev describe`.
- Confirm the Milvus UI is exposed at `https://drupal-playground.ddev.site:8521`.

### 2. Choose an install flow

- Run `ddev install trials-milvus` for Milvus chat and indexing only.
- Run `ddev install trials-elastic` for the Elasticsearch-backed `/trials` page only.
- Run `ddev install trials-elastic trials-milvus` for the hybrid `/trials` page.
- Confirm the install output includes:
  - `Applying AI recipe...`
  - `Applying ClinicalTrials.gov setup recipe...`
  - `Applying Clinical Trials Milvus recipe...`
  - `Importing ClinicalTrials.gov studies...`
  - `Indexing ClinicalTrials.gov studies in Milvus...`
- Confirm ClinicalTrials.gov imports are limited to `30` items during install.
- If `trials-elastic` is part of the install command, confirm the one-time login URL includes `destination=/trials`.

### 3. Review the Milvus provider configuration

- Go to `/admin/config/ai/vdb_providers/milvus`.
- Confirm:
  - Server is `http://milvus`
  - Port is `19530`
  - API key is blank for local DDEV usage

### 4. Review the AI Search server and index

- Go to `/admin/config/search/search-api`.
- Confirm there is a server named `Clinical Trials Milvus`.
- Open `/admin/config/search/search-api/server/trials_milvus/edit`.
- Confirm:
  - Backend is `AI Search`
  - Vector Database is `Milvus DB`
  - Collection is `trials_milvus`
- Open `/admin/config/search/search-api/index/trials_milvus/edit`.
- Confirm:
  - Datasource is node content
  - Bundle is limited to `trial`
  - Server is `Clinical Trials Milvus`

### 5. Verify indexing

- Run `ddev drush search-api:clear trials_milvus -y`.
- Run `ddev drush search-api:index trials_milvus --limit=10 --batch-size=5 --time-limit=30`.
- Repeat the indexing command if your embeddings provider rate-limits large runs.
- Run `ddev drush search-api:status trials_milvus` and confirm the index reaches `100% Complete`.

## Verification Steps

### Front end

- Visit `<front>`.
- Confirm a DeepChat block appears with the label `Ask AI about Clinical Trials`.
- Confirm the initial message says `Ask our AI Assistant to help you search for a clinical trials.`

### Trials page

- If `trials-elastic` is installed, visit `/trials`.
- Confirm the Elasticsearch trials page renders with its keyword search and filters.
- Confirm the DeepChat block appears on the page.
- Confirm search and listing behavior still come from Elasticsearch while chat answers come from the Milvus assistant.

### Assistant behavior

- Ask a patient-oriented question such as `What clinical trials might help with lung cancer?`
- Ask a caregiver-oriented question such as `I am helping my father find a trial in New York.`
- Ask a medical-professional question such as `Show concise recruiting trials for colorectal cancer.`
- Confirm the assistant stays grounded in site trial content and suggests next steps when no strong match is found.

## Manual Maintenance Commands

- Reimport trial content:
  - `ddev drush migrate:rollback clinical_trials_gov -y`
  - `ddev drush migrate:import clinical_trials_gov --limit=30`
- Reindex Milvus content:
  - `ddev drush search-api:clear trials_milvus -y`
  - `ddev drush search-api:index trials_milvus --limit=10 --batch-size=5 --time-limit=30`
  - `ddev drush search-api:status trials_milvus`

## References

- [AI Search](https://www.drupal.org/project/ai_search)
- [Milvus VDB Provider](https://www.drupal.org/project/ai_vdb_provider_milvus)
- [Milvus VDB Provider repository](https://git.drupalcode.org/project/ai_vdb_provider_milvus)
- [QED42 semantic search article](https://www.qed42.com/insights/setting-up-ai-powered-semantic-search-in-drupal)
- [Vardot RAG chatbot article](https://www.vardot.com/en-us/ideas/blog/step-step-guide-creating-rag-based-drupal-ai-chatbot)
- [Giving Drupal's Search Superpowers~~](https://www.davidlab.co/blog/2025-06/giving-drupals-search-superpowers-my-adventure-vector-databases-and-milvus)~~
- [Building Semantic Search in Drupal with Milvus: A Complete Step-by-Step Guide](https://bonnici.co.nz/blog/drupal-semantic-search-milvus-guide)
