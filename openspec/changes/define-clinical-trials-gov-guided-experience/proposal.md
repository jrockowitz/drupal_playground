## Why

People exploring clinical trials need guidance that is compassionate, clear,
safe, and actionable rather than a generic search or chat experience. The
existing ClinicalTrials.gov import and discovery foundation makes it possible to
define that future experience without describing it as current behavior.

## What Changes

- Add persona-aware guidance for patients, caregivers, medical professionals,
  and people who do not identify with those roles.
- Establish accessibility, language, transparency, and human-handoff
  expectations for clinical-trial conversations.
- Prevent diagnostic, eligibility, enrollment, and guaranteed-outcome claims.
- Require useful next steps when no matching open trial is available.
- Add a shareable session summary and appropriate use of closed-trial context.
- Add richer import readiness, completion, re-import, and rollback information
  to support the guided discovery experience.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `ecosystem-clinical-trials-gov`: Add the future guided trial experience and
  import-management requirements to the existing ecosystem.

## Impact

Future implementation will affect the ClinicalTrials.gov RAG chat integration,
conversation/session storage, trial recommendation presentation, accessibility
and language handling, human-support configuration, and the import-management
interface. It may require privacy review before browsing or session context is
used for personalization.
