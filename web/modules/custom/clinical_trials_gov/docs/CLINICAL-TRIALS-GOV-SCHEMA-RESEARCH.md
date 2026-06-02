# Clinical Trials Schema Research

## Problem

ClinicalTrials.gov contains rich metadata, but the data is too vast and too unstructured for a patient-facing search interface. Key fields like eligibility criteria are stored as plain text and cannot be queried. A simpler, more structured schema is needed to power a search experience for patients and caregivers—one that an LLM can also work with effectively.

## Standards and Reference Projects

### WHO Trial Registration Data Set (TRDS)

The WHO TRDS is a minimum information standard for clinical trial registration that applies to all interventional trials worldwide. The current version (v1.3.1) contains 24 elements including trial identifiers, sponsor, funding source, health conditions, interventions, study type, recruitment status, eligibility criteria, outcomes, ethics review, and completion date. It provides a useful baseline for what fields matter, but it does not break eligibility criteria into structured, machine-readable subfields.

- **Reference:** <https://trialsearch.who.int/Default.aspx>

### Clinical Trial Knowledge Base (CTKB)

CTKB is an open-source project from Columbia University and part of the OHDSI community. It uses a natural language processing tool called Criteria2Query to transform free-text eligibility criteria from ClinicalTrials.gov into discrete, structured concepts encoded using the OMOP Common Data Model. At the time of publication, CTKB contained 87,504 distinctive standardized concepts extracted from 352,110 clinical trials, broken down into categories: Condition (47.82%), Drug (23.01%), Procedure (13.73%), Measurement (24.70%), and Observation (5.28%). CTKB had a web application with RESTful APIs (15 query types), but the hosted instance at ctkb.io is no longer available online. The project appears to be inactive.

- **GitHub:** <https://github.com/ninglab/ctkg>
- **PubMed:** <https://pubmed.ncbi.nlm.nih.gov/33813032/>
- **Paper:** <https://dl.acm.org/doi/10.1016/j.jbi.2021.103771>
- **Status:** Project is no longer active or available online.
- **Key value:** The schema design and approach to structuring eligibility criteria remain a useful reference, even though the live instance is unavailable.

### OpenTrials

OpenTrials is a collaborative open database that threads together registry entries, journal papers, regulatory documents, and structured data for each trial. Their patient-facing view aimed to provide search by region, condition, drug, and eligibility filtering. The project is open source on GitHub.

- **Paper:** Goldacre and Gray, "OpenTrials: towards a collaborative open database of all available information on all clinical trials" (Trials, 2016)
- **Website:** <https://opentrials.net/>
- **GitHub:** <https://github.com/opentrials>
- **Status:** Project is no longer active or available online.

## Proposed Data Processing Pipeline

1. **Ingest** — Pull raw data from ClinicalTrials.gov into Drupal.
2. **AI-Powered Structuring** — Use large language models with a defined JSON schema to parse unstructured fields (especially eligibility criteria) into discrete, queryable properties such as age minimum, age maximum, specific conditions, required procedures, and exclusion criteria.
3. **Human Review** — Have staff review and validate the AI-generated structured data for accuracy. Generate lay-friendly titles and simplified eligibility descriptions during this step.
4. **Store Enriched Data** — Save the structured, validated fields alongside the original ClinicalTrials.gov data in Drupal.
5. **Build Search** — Build the patient-facing search interface on top of the clean, structured fields. The original plain-text eligibility can still be included as supplemental context, but the primary search queries against the parsed, validated fields.

## Key Insight

The core problem is that eligibility criteria and other critical fields in ClinicalTrials.gov are plain text—not broken into searchable components like specific diseases, age ranges, or required lab values. The solution is to do the heavy processing once at ingest time using large language models, store the structured results, and then build fast, accurate search on top of clean data rather than parsing free text on every query.

## Next Steps

- Investigate CTKB API access and determine if their pre-processed structured data can be consumed directly.
- Review the CTKB schema (ec_condition, ec_drug, etc.) as a model for structuring eligibility in Drupal.
- Evaluate Schema.org MedicalTrial type for any additional properties worth incorporating.
- Define the JSON schema that will be used to prompt the LLM during the AI structuring step.
