# DRUPAL-ECA-CONTRIB.md

> Contrib modules whose functionality can be replaced (fully or partially) by an ECA model

This document catalogues Drupal contributed modules that ECA can supersede, organized by functional category. Each entry notes the ECA sub-modules and approach required, and whether the replacement is full or partial.

**Confidence tiers used below:**
- ✅ **Confirmed** — ECA library model exists, or officially documented by ECA maintainers
- 🔶 **Community-documented** — reported working in issue queues or community articles
- ⚠️ **Partial** — ECA covers the common use case but not all edge cases or UI features

---

## Table of Contents

1. [User & Login Flow](#1-user--login-flow)
2. [Notifications & Email](#2-notifications--email)
3. [Content & Field Manipulation](#3-content--field-manipulation)
4. [Access Control](#4-access-control)
5. [Caching](#5-caching)
6. [Redirects & Routing](#6-redirects--routing)
7. [Miscellaneous Workflow](#7-miscellaneous-workflow)
8. [Caveats & Guidance](#8-caveats--guidance)
9. [References](#9-references)

---

## 1. User & Login Flow

### `r4032login` — Redirect 403 to User Login

**Replaces:** Redirecting anonymous users from 403 Access Denied pages to `/user/login`.

**ECA approach:**
- Sub-modules: `eca_misc`, `eca_user`
- Event: `Kernel: Response created` (response event)
- Conditions: response status = 403, current user is anonymous
- Action: `Redirect to URL` → `/user/login`

**Status:** ✅ Confirmed — an official ECA library model exists at `ecaguide.org/library/use_case/redirect_403_to_login_page/`.

**Caveat:** There is a known complexity around which kernel event to use. The sample model in the ECA Guide may require using the request controller event rather than the response event to ensure the redirect fires correctly. Test thoroughly and monitor the `#3516391` issue on drupal.org.

---

### `login_destination` / `redirect_after_login`

**Replaces:** Redirecting users to a specific URL or role-based destination after login, with optional `?destination=` parameter awareness.

**ECA approach:**
- Sub-modules: `eca_user`
- Event: `ECA User: Login of a user`
- Conditions: user role check (gateway)
- Action: `Redirect to URL` per role branch
- Token `[current-page:query:destination]` (requires contrib Token module) used as a condition to skip the redirect when a `?destination=` parameter is present

**Status:** ✅ Confirmed — the ECA Feature Demo library model (`ecaguide.org/library/use_case/eca_feature_demo/`) covers the role-based redirect pattern exactly. The `?destination=` exclusion is documented in ECA issue `#3306012`.

**Caveat:** Also exclude one-time login links and password reset links from the redirect — add conditions checking the current route name (`user.reset.*`) to prevent those flows from being hijacked.

---

### `redirect_page_by_role`

**Replaces:** Redirecting users to different pages on login based on their role(s).

**ECA approach:** Identical to `login_destination` above. Login event → role condition gateway → per-role redirect action branches.

**Status:** ✅ Confirmed — directly documented in ECA issue `#3351016` with pointer to the Feature Demo library model.

---

## 2. Notifications & Email

### `message` + `message_notify`

**Replaces:** The Message/Message Notify stack used to create message entities and dispatch notifications.

**ECA approach:**
- Sub-modules: `eca_content`, `eca_user`
- Event: any content or user lifecycle event (insert, update, transition, login)
- Action: `Send email` (core) or `Easy Email: Send` (recommended for HTML templates)
- Tokens populate recipient, subject, and body dynamically

**Status:** 🔶 Community-documented. ECA removes the need for a message entity layer entirely for notification-only use cases. Combine with the **Easy Email** module for rich HTML email templates — Easy Email has native ECA integration.

**Caveat:** If you need a persistent log of sent messages or a message center UI for users, `message` still provides value ECA alone does not. For pure fire-and-forget notifications, ECA is a clean replacement.

---

### Welcome email / user registration email modules

**Replaces:** Modules that send customized welcome or onboarding emails on user registration.

**ECA approach:**
- Sub-modules: `eca_user`
- Event: `ECA User: Registration of a new user`
- Action: `Send email` with `[user:display-name]`, `[user:mail]`, `[site:login-url]` tokens

**Status:** ✅ Confirmed — an official ECA library model exists: `ecaguide.org/library/simple/send_email_on_user_registration_with_field_values/`.

---

### Simplenews (basic notification use cases only)

**Replaces:** The "notify subscribers when content is published" pattern.

**ECA approach:**
- Sub-modules: `eca_content`, `eca_views`
- Event: `Content entity: Insert` (filtered to specific bundle + published status)
- Action: `Execute Views query` → loop results (subscriber list) → `Trigger custom event (entity-aware)` → `Send email`

**Status:** 🔶 Community-documented. ECA handles the trigger-and-send loop via a Views-driven subscriber list.

**Caveat:** Not a replacement for Simplenews's subscription management UI, opt-in/opt-out flows, or newsletter queue/batch sending. Only the "on publish → notify" pattern is covered.

---

## 3. Content & Field Manipulation

### `auto_entitylabel` — Automatic Entity Labels

**Replaces:** Automatically setting an entity's label/title from a token pattern on save.

**ECA approach:**
- Sub-modules: `eca_content`
- Event: `Content entity: Pre-save`
- Condition: entity type/bundle check
- Actions: `Token: set value` (build the label string from tokens) → `Entity: set field value` (write to the title/label field)

**Status:** 🔶 Community-documented. The token system provides the same dynamic pattern capability; no dedicated UI for the pattern, but the model is straightforward.

**Caveat:** `auto_entitylabel` has a dedicated pattern field on the entity form and handles edge cases like empty tokens gracefully. ECA requires the model author to handle those edge cases explicitly via conditions.

---

### `termcase` — Enforce Case on Taxonomy Terms

**Replaces:** Forcing taxonomy term names to lowercase (or other case) on save.

**ECA approach:**
- Sub-modules: `eca_content`
- Ecosystem module: `eca_tamper`
- Event: `Content entity: Pre-save` (filtered to taxonomy_term bundle)
- Action: `Tamper: Convert case` → `Token: set value` → `Entity: set field value` (name)

**Status:** ✅ Confirmed — directly documented in a DrupalEasy article as a working replacement for Termcase, which has no active Drupal 10 release. The ECA Tamper module provides the Convert Case action.

---

### `computed_field` — Computed Field Values

**Replaces:** Fields whose value is computed from other field values at save time.

**ECA approach:**
- Sub-modules: `eca_content`
- Event: `Content entity: Pre-save`
- Actions: build the computed value via token expressions → `Entity: set field value`

**Status:** 🔶 Community-documented for simple token-expressible computations.

**Caveat:** Fields requiring PHP-level computation (database queries, complex logic) still need code. ECA covers cases like concatenation, date formatting, or simple arithmetic via token and Tamper plugins.

---

### `field_validation` — Field Validation Rules

**Replaces:** Contrib-defined validation rules on field values (min/max length, regex, allowed values, etc.).

**ECA approach:**
- Sub-modules: `eca_form`
- Event: `ECA Form: Form validate`
- Condition: compare field value (scalar compare, regex via Tamper)
- Action: `Form: Set form error` (blocks submission and displays message)

**Status:** ✅ Confirmed — cited by ECA lead maintainer Jürgen Haas in DrupalCon/DrupalDays sessions as a direct replacement target.

**Caveat:** `field_validation` provides a dedicated per-field UI that non-developers can configure. ECA is more powerful but requires understanding the model builder.

---

### `conditional_fields` — Conditional Field Visibility

**Replaces:** Showing or hiding form fields based on the value of another field.

**ECA approach:**
- Sub-modules: `eca_form`
- Event: `ECA Form: Form alter` or field widget event
- Action: `Form: Set field states` (sets Drupal `#states` on fields dynamically)

**Status:** ⚠️ Partial — the ECA issue queue confirms that most `conditional_fields` behavior can now be achieved via ECA Form's states support. However, complex nested conditions and some field type combinations may have edge cases.

**Caveat:** ECA issue `#3508747` ("states do not change back if not applicable") tracks an open edge case. For complex conditional display requirements, `conditional_fields` remains more mature.

---

## 4. Access Control

### `role_delegation` / `roleassign` — Automatic Role Assignment

**Replaces:** Automatically assigning roles to users based on registration data, email domain, profile fields, or other conditions.

**ECA approach:**
- Sub-modules: `eca_user`
- Event: `ECA User: Registration of a new user` or `ECA User: Login of a user`
- Condition: scalar compare on `[user:mail]` domain, field value, etc.
- Action: `User: Add role` or `User: Remove role`

**Status:** 🔶 Community-documented.

**Caveat:** These modules also address *delegated manual* role assignment (allowing editors to assign a subset of roles without full admin access). ECA only covers *automated* role assignment. The delegation UI is not replaced.

---

### Simple rule-based node access (`content_access` basic patterns)

**Replaces:** Simple "deny/allow access based on content type and user role" rules.

**ECA approach:**
- Sub-modules: `eca_access`
- Event: `ECA Access: Determining entity access`
- Condition: entity type/bundle + user role
- Action: `Set access result` (allow/deny/neutral)

**Status:** 🔶 Community-documented for simple cases.

**Caveat:** Complex per-node ACLs, node grants, and per-user override patterns are better served by dedicated access control modules. ECA is appropriate for blanket rules, not fine-grained per-record access.

---

## 5. Caching

### Cache invalidation trigger modules

**Replaces:** Modules that invalidate specific cache tags or bins when content is created, updated, or deleted.

**ECA approach:**
- Sub-modules: `eca_content`, `eca_cache`
- Event: `Content entity: Insert`, `Content entity: Update`, or `Content entity: Delete`
- Condition: entity type/bundle filter
- Action: `Cache ECA: invalidate` (by tag) or `Cache: invalidate tags`

**Status:** 🔶 Community-documented.

---

## 6. Redirects & Routing

### Custom lightweight endpoints (replaces small controller modules)

**Replaces:** Simple contributed or custom modules that define a single route with basic logic (e.g. redirect hub, token-based landing pages).

**ECA approach:**
- Sub-modules: `eca_endpoint`
- Event: `ECA Endpoint: Request received` (defines the route on the fly)
- Conditions: validate request parameters
- Actions: build and return a response or redirect

**Status:** ✅ Confirmed — `eca_endpoint` is a first-class ECA sub-module explicitly designed for this purpose.

**Caveat:** Not suitable for endpoints requiring complex authentication, file streaming, or high-throughput API responses.

---

## 7. Miscellaneous Workflow

### `rules` — Drupal Rules Module

**Replaces:** The entire Rules paradigm for event-driven automation.

**ECA approach:** ECA is the direct spiritual and architectural successor to Drupal Rules. It was created specifically because Rules did not complete a stable Drupal 8/9/10 port for complex use cases. ECA uses the same Symfony event system and Drupal action plugin infrastructure, but with a modern plugin architecture, config entity storage, and visual BPMN modelling.

**Status:** ✅ Confirmed — explicitly stated by the ECA maintainers as the intended replacement.

---

### `publish_content` / simple publish/unpublish action modules

**Replaces:** One-click publish or bulk publish actions on entity lists.

**ECA approach:**
- Sub-modules: `eca_content`
- Event: any trigger (form submit, custom event, Views Bulk Operations action)
- Action: `Entity: publish` or `Entity: unpublish`

**Status:** 🔶 Community-documented.

---

### Simple `scheduler`-triggered notifications (not scheduling itself)

**Replaces:** Using Scheduler's Rules integration to fire notifications when content is scheduled or published by Scheduler.

**ECA approach:**
- Sub-modules: `eca_content`
- Event: `Content entity: Update`
- Condition: `Scheduler: Update triggered by Scheduler` (a condition plugin being developed in Scheduler issue `#3363972`)
- Action: send notification

**Status:** ⚠️ Partial — ECA replaces the *notification side* of Scheduler+Rules. The Scheduler condition plugin that distinguishes a Scheduler-triggered save from a manual save is in active development. Scheduling itself (setting publish/unpublish dates via a UI field) remains in the `scheduler` module.

---

## 8. Caveats & Guidance

### Full vs. Partial Replacement

ECA replaces the *automation logic* of these modules — the "when X happens, do Y" behavior. It does not replace:

- **Editorial UIs** — configuration forms, field widgets, per-entity settings screens
- **Database-level features** — node grants, complex ACL tables, queue processing infrastructure
- **Hardened edge-case handling** — years of bug fixes in mature modules for tricky field types, multilingual scenarios, etc.

Evaluate each module on a case-by-case basis. The maintainer's guidance: each ECA model you introduce is one fewer contrib module to keep updated and secured.

### ECA + Tamper = Field Transformation Swiss Army Knife

The [`eca_tamper`](https://www.drupal.org/project/eca_tamper) ecosystem module brings the full Tamper plugin library into ECA models: case conversion, regex find/replace, string padding, URL encode/decode, type casting, and more. This combination covers a large portion of single-purpose field-transformation contrib modules, particularly those that have stalled on Drupal 10/11 compatibility.

### Start with the ECA Library

Before building a replacement model from scratch, check the official ECA model library at **https://ecaguide.org/library/**. Many common patterns are available as downloadable, importable `.tar.gz` archives that can be imported into any Drupal site in one click.

### When NOT to Replace

Keep the contrib module when:

- The module provides a **dedicated configuration UI** that non-developer site builders rely on
- The module's behavior is **deeply integrated** with other contrib (e.g. `scheduler` + `scheduler_content_moderation_integration`)
- The module handles **complex database-level operations** (ACL grants, node access records)
- The module is well-maintained, security-covered, and the ECA replacement would add meaningful complexity

---

## 9. References

### ECA Library Models (Importable)

- Redirect 403 to Login Page: https://ecaguide.org/library/use_case/redirect_403_to_login_page/
- Role-based redirect after login (Feature Demo): https://ecaguide.org/library/use_case/eca_feature_demo/
- Send email on user registration: https://ecaguide.org/library/simple/send_email_on_user_registration_with_field_values/
- Full library: https://ecaguide.org/library/

### Drupal.org Issue Queue References

- `r4032login` replacement model documentation: https://www.drupal.org/project/eca/issues/3337208
- 403 redirect event complexity (ongoing): https://www.drupal.org/project/eca/issues/3516391
- Redirect after login with `?destination=` awareness: https://www.drupal.org/project/eca/issues/3306012
- Role-based redirect after login: https://www.drupal.org/project/eca/issues/3351016
- Conditional fields via ECA Form states: https://www.drupal.org/project/eca/issues/3348940
- Scheduler + ECA condition plugin: https://www.drupal.org/project/scheduler/issues/3363972

### Community Articles

- DrupalEasy — "Using ECA to replace Termcase": https://www.drupaleasy.com/blogs/ultimike/2023/07/using-eca-module-replace-not-drupal-10-ready-contrib-module-termcase
- ImageX — "The ECA Module: Setting Up Automated Actions": https://imagexmedia.com/blog/eca-module-drupal

### Ecosystem Modules

- ECA Tamper (field transformation): https://www.drupal.org/project/eca_tamper
- ECA ecosystem listing: https://www.drupal.org/project/eca/ecosystem

---

*See also: [DRUPAL-ECA.md](./DRUPAL-ECA.md) for the full ECA architecture and developer reference.*

*Last updated: March 2026.*
