## 1. Experience Configuration

- [ ] 1.1 Add schema-backed configuration for support destinations, consent behavior, summary retention, and guided-experience copy
- [ ] 1.2 Add administrative configuration and validation for the new experience settings
- [ ] 1.3 Add Kernel and Functional coverage for defaults, validation, and configuration access

## 2. Adaptive and Safe Conversation

- [ ] 2.1 Add explicit persona and language confirmation to the trial chat intake
- [ ] 2.2 Add consent capture, withdrawal, and context filtering for browsing and previous-session personalization
- [ ] 2.3 Add trial-discovery boundary responses for diagnosis, treatment, eligibility, enrollment, and outcome requests
- [ ] 2.4 Add source-backed matching and limiting-factor presentation with NCT identifiers and source links
- [ ] 2.5 Add Functional JavaScript coverage for persona, language, consent, and medical-boundary flows

## 3. Guidance and Human Handoff

- [ ] 3.1 Add role-aware no-results responses with query refinement and broader-search actions
- [ ] 3.2 Add configured human handoff for requests for a person, medical advice, frustration, and overwhelm
- [ ] 3.3 Add explicit labeling and presentation rules for closed and past trials
- [ ] 3.4 Add accessibility and multilingual tests that preserve trial facts, links, labels, focus, and announcements

## 4. Session Summary

- [ ] 4.1 Add a summary model for confirmed persona, user-provided context, surfaced trials, explanation factors, limitations, and next steps
- [ ] 4.2 Add explicit save, resume, print, and share actions with retention enforcement
- [ ] 4.3 Add persona-aware summary presentation and verify that it is not represented as a medical record
- [ ] 4.4 Add access, privacy, and lifecycle tests for saved and shared summaries

## 5. Import Lifecycle

- [ ] 5.1 Add create, update, unchanged, out-of-scope, readiness, and migration-status preview calculations
- [ ] 5.2 Add explicit re-import behavior that creates and updates without automatically deleting out-of-scope trials
- [ ] 5.3 Add explicit out-of-scope removal and migration rollback operations with confirmation and result reporting
- [ ] 5.4 Rebuild affected Elasticsearch and Milvus indexes only after successful import lifecycle operations
- [ ] 5.5 Add Kernel and Functional coverage for preview counts, re-import, removal, rollback, and index sequencing

## 6. Verification and Rollout

- [ ] 6.1 Run targeted module, accessibility, JavaScript, migration, and search integration tests
- [ ] 6.2 Run repository code review checks for every changed module, Recipe, configuration, and test file
- [ ] 6.3 Verify the current trial-focused RAG interface remains available when the guided experience is disabled
- [ ] 6.4 Document deployment configuration, privacy review, enablement, and rollback procedures
