## Context

The current ClinicalTrials.gov ecosystem imports studies into Drupal, indexes
them in Elasticsearch and Milvus, and adapts an AI RAG chat interface to trial
search. The future experience must serve people in medically and emotionally
sensitive situations without presenting the AI as a clinician or enrollment
authority. It also needs a clearer import lifecycle so search results can be
traced to an understood dataset state.

## Goals / Non-Goals

**Goals:**

- Adapt trial discovery to a confirmed user role and communication preference.
- Explain why trials were or were not surfaced using source-backed facts.
- Preserve hope and useful next steps without overstating eligibility.
- Provide accessible language selection, human handoff, and a portable summary.
- Make import creation, update, removal, re-import, and rollback state visible
  before an operator changes the indexed trial corpus.

**Non-Goals:**

- Diagnose conditions, recommend treatment, determine final eligibility, or
  guarantee enrollment or outcomes.
- Replace trial coordinators, clinicians, caregivers, or other human support.
- Use browsing history, prior sessions, or inferred identity without consent.
- Present closed trials as currently enrollable.
- Automatically delete imported trials solely because a query changed.

## Decisions

### Confirm persona instead of silently inferring it

The conversation will ask the person to confirm Patient, Caregiver, Medical
Professional, or Other. A returning-session or browsing-context suggestion may
shorten the question only after the person has consented to that context being
used. This is more predictable and privacy-preserving than silent persona
classification.

### Ground explanations in indexed trial facts

Each surfaced trial will include concise matching and limiting factors derived
from indexed ClinicalTrials.gov fields and links to the source study. The system
will label the output as discovery guidance, not an eligibility decision. This
keeps reasoning inspectable without asking the model to make clinical claims.

### Make human support a configured destination

The site will configure organization-specific trial-coordinator and support
destinations. Frustration, uncertainty, a request for medical advice, or a lack
of useful matches will offer that handoff. A broader ClinicalTrials.gov search
may supplement the handoff but will not replace it.

### Treat language and presentation as user-controlled preferences

The interface may detect a likely language, but it will ask for confirmation and
always provide a way to switch. Trial identifiers, statuses, criteria, and
source links remain intact when explanatory text is translated or simplified.

### Separate session summaries from clinical records

The summary will contain the confirmed persona, stated search context, surfaced
trials, explanation factors, limitations, and next steps. Sharing, printing,
saving, and resuming require explicit user action. The summary is not described
or stored as a medical record by this change.

### Preview import lifecycle changes before mutation

The import interface will derive create, update, unchanged, and out-of-scope
counts from the generated migration and current query. Re-import may create and
update records, while removal of out-of-scope records requires an explicit
operator action. Rollback uses the migration's tracked identifiers and reports
its outcome before indexes are rebuilt.

## Risks / Trade-offs

- **Risk: Compassionate language is mistaken for medical authority.** → Keep
  boundaries visible in openings, trial explanations, summaries, and handoffs.
- **Risk: Personalization exposes sensitive browsing or session context.** →
  Require consent, minimize retained context, and provide clear save/resume
  controls.
- **Risk: Translation changes clinical meaning.** → Preserve source facts and
  links, distinguish translated explanation from source content, and allow
  language switching.
- **Risk: No-match behavior creates false hope.** → Offer broader searches and
  human support without claiming that a suitable trial exists.
- **Risk: Query changes remove useful content.** → Preview out-of-scope records
  and require an explicit removal or rollback action.

## Migration Plan

1. Add configuration for support destinations, consent behavior, summary
   retention, and experience copy.
2. Add persona and language confirmation before adaptive responses.
3. Add source-backed trial explanation and boundary presentation.
4. Add no-match, frustration, and medical-advice handoff paths.
5. Add explicit summary save, share, print, and resume behavior.
6. Add import lifecycle preview, re-import, removal, and rollback controls.
7. Verify accessibility, privacy boundaries, import behavior, and index rebuilds
   before enabling the experience by default.

Rollback disables the guided experience and returns to the current trial-focused
RAG interface. Import rollback remains an explicit operator operation and does
not run as part of disabling the chat experience.

## Open Questions

None. Organization-specific support destinations and retention durations are
deployment configuration rather than architectural decisions.
