## ADDED Requirements

### Requirement: Confirmed persona adaptation

The guided trial experience SHALL adapt its language, pacing, and level of
clinical detail to a persona confirmed by the user.

#### Scenario: Start without consented context

- **WHEN** no consented persona context is available
- **THEN** the experience asks the user to select Patient, Caregiver, Medical Professional, or Other
- **AND** it does not infer the persona from browsing or session history

#### Scenario: Adapt to a confirmed persona

- **WHEN** the user confirms a persona
- **THEN** patient and caregiver explanations default to clear plain language
- **AND** medical-professional explanations prioritize concise criteria and trial details
- **AND** Other receives neutral language and can state a preferred level of detail

### Requirement: Medical safety boundaries

The guided trial experience SHALL identify itself as discovery guidance and
SHALL NOT diagnose, recommend treatment, determine final eligibility, guarantee
enrollment, or guarantee an outcome.

#### Scenario: User requests a medical or eligibility decision

- **WHEN** the user asks the experience to diagnose a condition, choose treatment, or confirm eligibility
- **THEN** it explains the applicable boundary
- **AND** it offers source trial information and an appropriate human contact as the next step

### Requirement: Source-backed trial explanations

Trial results SHALL explain matching and limiting factors using available
ClinicalTrials.gov data and SHALL link to the source study.

#### Scenario: Present a potentially relevant trial

- **WHEN** the system surfaces a trial
- **THEN** it identifies the facts that contributed to the match
- **AND** it identifies known factors that may limit relevance
- **AND** it states that the trial team determines final eligibility
- **AND** it provides the NCT identifier and source link

### Requirement: No-dead-end guidance

The guided experience SHALL offer actionable next steps when it cannot surface
a useful open trial.

#### Scenario: No useful open trial is found

- **WHEN** no indexed open trial meets the current search context
- **THEN** a patient or caregiver is offered query refinement, a broader ClinicalTrials.gov search, and configured human support
- **AND** a medical professional is offered query refinement and the option to review a factual no-results summary
- **AND** the experience does not claim that a suitable trial exists

#### Scenario: User needs human support

- **WHEN** the user requests a person, seeks medical advice, or expresses frustration or overwhelm
- **THEN** the experience offers the configured trial coordinator or support destination

### Requirement: Accessible language control

The guided experience SHALL support understandable presentation and explicit
language preference without changing source trial facts.

#### Scenario: Offer another language

- **WHEN** the interface detects or receives a language preference different from the site default
- **THEN** it asks the user to confirm the preferred language
- **AND** it provides a control to switch languages
- **AND** translated explanations preserve trial identifiers, statuses, criteria, and source links

### Requirement: Consent-based personalization

Browsing context and previous-session context SHALL be used only after the user
consents to that use.

#### Scenario: Context is available without consent

- **WHEN** browsing or previous-session context is technically available but consent has not been recorded
- **THEN** the experience starts without that context

#### Scenario: User withdraws personalization consent

- **WHEN** the user withdraws consent
- **THEN** subsequent guidance stops using the affected context

### Requirement: User-controlled session summary

The guided experience SHALL provide a user-controlled summary of the search and
its limitations.

#### Scenario: Create a summary

- **WHEN** the user requests an end-of-session summary
- **THEN** it includes the confirmed persona, user-provided context, surfaced trials, explanation factors, limitations, and next steps
- **AND** medical-professional summaries prioritize criteria and trial listings while patient and caregiver summaries include additional plain-language context

#### Scenario: Retain or share a summary

- **WHEN** the user chooses to save, resume, print, or share the summary
- **THEN** the system performs only the selected action
- **AND** it does not characterize the summary as a medical record

### Requirement: Closed-trial context

Closed or past trials SHALL be presented only as clearly labeled historical or
research context.

#### Scenario: Surface a closed trial

- **WHEN** a closed or past trial is relevant to the user's research context
- **THEN** its enrollment status is prominent
- **AND** it is not presented as a current enrollment option

### Requirement: Import lifecycle preview and control

The ClinicalTrials.gov import workflow SHALL preview dataset changes and require
explicit operator control for destructive operations.

#### Scenario: Preview a configured import

- **WHEN** an operator reviews a valid generated migration
- **THEN** the interface reports selected, create, update, unchanged, and out-of-scope counts
- **AND** it reports whether the migration is ready to run

#### Scenario: Re-import studies

- **WHEN** an operator explicitly starts a re-import
- **THEN** the migration creates new records and updates changed records using tracked source identifiers
- **AND** out-of-scope records are not deleted unless the operator explicitly selects a removal operation

#### Scenario: Roll back an import

- **WHEN** an operator explicitly starts rollback for the generated migration
- **THEN** the workflow removes records tracked by that migration
- **AND** it reports the rollback outcome before affected search indexes are rebuilt
