# DRUPAL-ECA-TIPS.md

> Tips, tricks, and naming conventions for working effectively with the ECA module

---

## Table of Contents

1. [Naming Conventions](#1-naming-conventions)
2. [Model Architecture Patterns](#2-model-architecture-patterns)
3. [Token Tips](#3-token-tips)
4. [Debugging](#4-debugging)
5. [Custom Events: The "Component" Pattern](#5-custom-events-the-component-pattern)
6. [Gateway & Condition Tips](#6-gateway--condition-tips)
7. [Performance Tips](#7-performance-tips)
8. [BPMN.iO Modeller Tips](#8-bpmn-io-modeller-tips)
9. [Config & Deployment Tips](#9-config--deployment-tips)
10. [Common Gotchas](#10-common-gotchas)

---

## 1. Naming Conventions

Good names are the difference between a model library that reads like documentation and one that's a maintenance nightmare. ECA models live in config (`eca.model.*`) and the machine ID cannot be changed after creation, so get naming right from the start.

### Model Machine IDs

Use a structured `{domain}_{verb}_{noun}` or `{domain}_{noun}_{verb}` pattern. The machine ID becomes part of the config filename and appears in Drush output.

```
# Good
content_article_notify_on_publish
user_registration_send_welcome_email
commerce_order_notify_warehouse
webform_submission_create_node

# Bad
model1
test
my_workflow
new_eca
```

Prefix by domain/subsystem so models group together naturally in the config directory and in the ECA admin list:

| Prefix | Domain |
|--------|--------|
| `content_` | Node/media/entity lifecycle |
| `user_` | User registration, login, roles |
| `form_` | Form alter, validation, submit |
| `webform_` | Webform submission events |
| `commerce_` | Commerce orders, carts |
| `queue_` | Queue/cron-based operations |
| `access_` | Access control events |
| `cache_` | Cache invalidation |
| `api_` | Endpoint / HTTP client models |
| `lib_` | Reusable component sub-models |

### Model Labels (Human-Readable Names)

The label is what users see in the admin UI (`/admin/config/workflow/eca`). Write it as a plain-English sentence describing the trigger and outcome:

```
# Good
"Notify editors when article is submitted for review"
"Send welcome email on user registration"
"Invalidate cache tags when landing page is updated"

# Bad
"Article workflow"
"Email"
"My ECA model"
```

### Model Descriptions

Always fill in the **description** field. Include:
- What event(s) trigger the model
- What conditions gate it
- What the outcome is
- Any non-obvious dependencies (contrib modules, specific content types, roles, Views)

```
Triggered by: Content entity pre-save (node, type = article)
Condition: Node is being published for the first time (status changes from 0 to 1)
Action: Sends email notification to all users with the 'editor' role
Dependencies: eca_content, eca_user, eca_views; requires 'editors' view at view_id=editors/page_1
```

### Token Names (ECA Dynamic Tokens)

ECA dynamic tokens are **read-write variables** created within a model. Name them with `camelCase` or `snake_case`, keeping them short but descriptive. Avoid names that collide with Drupal core token types (`node`, `user`, `site`, etc.) unless you specifically mean the entity in context.

```
# Good dynamic token names
articleAuthor       → stores the author entity
recipientList       → stores a list of users from Views
orderTotal          → stores a computed value
currentRole         → stores a role string

# Risky — collides with Drupal's read-only token system
node                → ambiguous: ECA entity or core token?
user                → use currentUser or targetUser instead
```

> **Key distinction:** A **token** is `[tokenName:property]` with brackets. A **token name** (as typed in "Token name" fields) is `tokenName` without brackets. The ECA Guide calls these out explicitly — confusing the two is one of the most common sources of model bugs.

### Custom Event IDs

Custom event IDs are free-text strings used to dispatch and receive internal ECA events. Use namespaced dot-notation:

```
# Good
lib.send_notification_email
lib.process_entity_list
content.after_publish
user.role_changed

# Bad
myevent
event1
sendEmail
```

### BPMN Node Labels (Shape Labels in the Diagram)

Label every shape in the diagram — unlabeled shapes are unreadable after you return to a model weeks later. Use present-tense verb phrases for actions, question-form for gateways:

```
# Events (circles)
"Article saved (pre-save)"
"User logged in"

# Gateways (diamonds)  
"Is article type?"
"Is user anonymous?"
"Has destination param?"

# Actions (rectangles)
"Set article status token"
"Send notification email"
"Redirect to dashboard"
```

---

## 2. Model Architecture Patterns

### One Event Per Model (Default)

Keep each model focused on one primary event. Avoid cramming multiple unrelated events into a single model just to reduce the number of models.

```
# Good: two focused models
content_article_notify_on_review   → event: moderation transition
content_article_invalidate_cache   → event: content update

# Bad: one sprawling model
content_article_everything         → events: presave, insert, update, delete, moderation...
```

### The "Fail Fast" Pattern

Put your most restrictive condition first, immediately after the event. If the model clearly doesn't apply (wrong entity type, wrong bundle, wrong user role), exit immediately rather than loading entities and setting tokens you won't need.

```
Event → [Is node?] →No→ (end)
               ↓Yes
       [Is type = article?] →No→ (end)
               ↓Yes
       ... (actual logic)
```

This is especially important for models triggered by high-frequency events like `content_entity:presave`, which fires on every entity save across the entire site.

### One Model = One Config File

Since each model is one config entity, keep models atomic. Splitting large logic into multiple models via [custom events](#5-custom-events-the-component-pattern) keeps each config file readable and independently deployable/revertable.

---

## 3. Token Tips

### Token vs. Token Name — The #1 Confusion Point

Many ECA action configuration fields accept either a **token** (with brackets) or a **token name** (without brackets). The wrong format silently fails.

| Field asks for | Example correct input | Example wrong input |
|---|---|---|
| Token | `[node:title]` | `node:title` |
| Token name | `myEntity` | `[myEntity]` |
| Token (ECA dynamic) | `[myEntity:title]` | `myEntity:title` |

> When in doubt: fields labelled "Token" → use brackets. Fields labelled "Token name" → no brackets.

### Always Set "Replace Tokens: Yes" on Core Action Plugins

Core action plugins like `Send email` and `Display message to user` do not auto-replace tokens. In ECA 2.0, they expose a **"Replace tokens"** field at the bottom of their config pane. Set it to `yes` every time. If tokens are appearing literally in your email subject or body, this is the first thing to check.

### Use the Token Browser

Enable the contrib `token` module and the Token Browser overlay becomes available inside every ECA property panel. Open it to browse available token syntax before typing token paths by hand — especially useful for complex entity fields and relationships.

### Scoped vs. Loaded Tokens

Tokens provided by the triggering event (like `[node:title]` when the event is a node pre-save) are available **automatically**. Tokens for *related* entities (e.g. the node author's email address) must be **explicitly loaded** first using `Entity: load by reference` or `Entity: load` actions before you can access their properties.

```
# Wrong: assuming author tokens are available
Event: node presave → Send email to [node:author:mail]

# Right: load the author entity first
Event: node presave
  → Entity: load by reference (field: uid, token name: author)
  → Send email to [author:mail]
```

### Global Tokens with the Token Generate Event

Use `eca_base`'s `ECA token generate event` to define tokens that should be available globally throughout all your models — things like `[bracket:open]` / `[bracket:close]` for Tailwind-style class names, or a computed `[site:api_base_url]` for endpoint models. Define them once in a dedicated "global tokens" model rather than redefining them in every model that needs them.

### Token Forwarding with Custom Events (Rename Pattern)

When dispatching a custom event, you can rename tokens in the "Tokens to forward" field using `from_name->to_name` syntax. This is useful when a calling model uses a specific entity token name (`article`) but the reusable sub-model expects a generic name (`entity`):

```
Tokens to forward: article->entity, authorUser->user
```

---

## 4. Debugging

### Step 1: Raise the ECA Log Level

Navigate to `Administration > Configuration > Workflow > ECA > Settings` (`/admin/config/workflow/eca/settings`) and set the log level to **Info** or **Debug**.

- **Info** — logs each step taken (which event, condition, action fired)
- **Debug** — logs all of the above plus the full token context (names and values) at every step

> ⚠️ **Remember to lower the log level back to Warning after debugging.** Debug mode writes a database row for every single ECA processing step across every page request, which will degrade performance and fill your watchdog table rapidly.

### Step 2: Read the ECA Log

The ECA log viewer at `Administration > Configuration > Workflow > ECA > Log` (`/admin/config/workflow/eca/log`) shows ECA-only records in **chronological order** (oldest first — intentional, because new requests add to the bottom and you need to follow the sequence forward in time).

Each log record shows:
- Which model and step fired
- Which user triggered it
- The **full token context** available at that step (token name + value + type)

The token context dump is the fastest way to answer "why isn't `[myToken:field]` working?" — you can see exactly what tokens existed and what their values were at each step.

### Step 3: Use Devel Webprofiler (Alternative)

If you have the `devel` module and its Webprofiler sub-module installed, enable the **ECA toolbar item** in Webprofiler settings. This adds an ECA icon to the toolbar that shows ECA debug output for just the current page request — no database log scanning required, and no persistent performance impact.

### The Minimal Model Technique

When debugging a complex model, comment out (disable by removing successors from) everything after the first action you're not sure about, and add a `Display message` action to confirm what tokens are available. Build up step by step.

### Most Common Causes of "Model Not Firing"

1. Wrong event plugin — e.g. using `content_entity:insert` when you want `content_entity:presave` (insert fires *after* save; presave fires *before*)
2. Condition fails silently — add a debug log message action on both the true and false gateway branches temporarily
3. Token is missing — raise log level to Debug and read the token context at the failing step
4. Model is disabled — check the model list for a red "disabled" indicator
5. Sub-module not enabled — the event/condition/action plugin requires a sub-module that isn't enabled (e.g. using a `eca_workflow` action without enabling that sub-module)

---

## 5. Custom Events: The "Component" Pattern

Custom events are the key to keeping models maintainable at scale. They let you build **reusable sub-models** that multiple other models can call, just like calling a function.

### Custom Event vs. Custom Event (Entity-Aware)

There are two variants — choosing the wrong one is a very common bug:

| Type | Plugin | Trigger Action | Use when |
|---|---|---|---|
| Custom event | `eca_base:eca_custom` | `Trigger a custom event` | Passing scalar tokens (strings, IDs) |
| Custom event (entity-aware) | `content_entity_custom` | `Trigger a custom event (entity-aware)` | Passing a content entity as the primary object |

**The action and the event must be the same type.** Using `Trigger a custom event` action to fire an `ECA custom event (entity-aware)` listener will silently fail.

### Building a Reusable Notification Component

```
# Model 1: lib_send_notification_email (the reusable component)
Event: ECA custom event (ID = lib.send_notification_email)
  → Send email to [recipient] with subject [subject] body [body]

# Model 2: content_article_notify_on_publish (the caller)  
Event: Content entity insert (type = article)
  → Token: set value → name: recipient, value: [node:author:mail]
  → Token: set value → name: subject, value: "Your article [node:title] was published"
  → Token: set value → name: body, value: "..."
  → Trigger custom event (ID = lib.send_notification_email)

# Model 3: user_registration_notify_admin (another caller)
Event: ECA User: Registration
  → Token: set value → name: recipient, value: admin@example.com
  → Token: set value → name: subject, value: "New user: [user:display-name]"
  → Token: set value → name: body, value: "..."
  → Trigger custom event (ID = lib.send_notification_email)
```

This way, changes to the email-sending logic happen in one place.

### Views Loop Pattern (Many-to-One)

The canonical ECA pattern for "do something for each result in a Views query":

```
Model: queue_weekly_digest
Event: ECA cron event (frequency: 0 8 * * 1)  ← every Monday 8am
  → Execute Views query (view_id: subscriber_list, token name: subscribers)
  → Trigger custom event (entity-aware) for each in: subscribers
       Event ID: lib.send_digest_email
       Entity token: subscribers

Model: lib_send_digest_email
Event: ECA custom event entity-aware (ID = lib.send_digest_email)
  → Send email to [entity:mail] with subject "Weekly Digest" body "..."
```

---

## 6. Gateway & Condition Tips

### Gateways Are Branch Points, Not Conditions

In BPMN.iO, the **gateway** (diamond) itself is just a routing shape — the **condition** lives on the **sequence flow arrow** leading out of the gateway, not on the gateway itself. A gateway with no conditions on its outgoing arrows acts as a plain fork/join.

### The "AND" Pattern with Chain Action

ECA sequence flows are OR by default — if you need to require multiple conditions to all pass before an action fires, use the **`Chain action for AND condition`** action plugin from `eca_base`. It groups successor conditions into an AND check.

### Negative Conditions (NOT)

Most ECA condition plugins have a **"Negate"** checkbox that inverts the result. Use it rather than building a workaround gateway. Example: "User does NOT have role 'administrator'" is the `User has role` condition with Negate enabled.

### Default/Fallback Branch

When building a gateway with multiple branches (role A → path 1, role B → path 2, else → path 3), always wire up the else/fallback branch explicitly. Unhandled branches don't cause errors but leave logic gaps that are hard to audit later.

---

## 7. Performance Tips

### Enable Only the Sub-Modules You Need

Each enabled sub-module registers its event, condition, and action plugins. ECA 2.0's dynamic subscription means unused events don't incur subscription overhead — but fewer enabled sub-modules still means a smaller plugin registry, faster discovery, and fewer potential conflicts.

### Avoid High-Frequency Events Without Fast Exits

The following events fire on almost every page request and should be treated with care:

- `content_entity:presave` (every entity save site-wide)
- `kernel:request` / `kernel:response` (every HTTP request)
- `eca_form:form_alter` (every form build)

If you must use these, put your most restrictive condition (entity type/bundle check, route check) as the **very first** step so the model exits in microseconds for non-matching requests.

### Use Queue for Heavy Actions

Actions that involve sending emails, calling external APIs, or doing significant database writes should be pushed to the queue (`eca_queue`) rather than executed inline during an HTTP request. This keeps page response times fast and makes failures retryable.

### Disable Models (Not Just Modellers) in Production

The ECA admin UI and modellers (`bpmn_io`, `eca_ui`) can be disabled on production without affecting model execution. But also consider **disabling specific models** that are only needed during development (debug/test models, temporary models).

---

## 8. BPMN.iO Modeller Tips

### Annotate Everything

Every shape in BPMN.iO has a label field and a documentation/description field. Use both. The diagram label should be a short verb phrase; the documentation field should explain the configuration choices ("Using token `[author:mail]` loaded by the previous Entity: load action").

### Use Color / Lane Grouping for Complex Models

BPMN.iO supports colored shapes and swim lanes. Use them to visually group related steps in complex models:
- Blue for initialization/token setup steps
- Green for "happy path" actions  
- Red/orange for error handling branches
- Grey for logging/debug-only actions (that you'll remove before deploying)

### Export Before Major Changes

Models are stored in config and version-controlled, but it's also worth using the **Export** button in BPMN.iO before making significant structural changes. The exported `.tar.gz` file is a snapshot you can import back if you break something.

### Zoom Out Before Screenshot/Documentation

BPMN.iO has a "fit to canvas" button. Use it before taking screenshots for documentation or PR review so reviewers see the full model at once.

### Test Import on a Fresh Environment

When sharing a model via `.tar.gz` archive (e.g. for the ECA library), test importing it on a clean Drupal site with only the standard profile + required modules. Models pick up dependencies from your current site that may not be obvious until import fails on a different site.

---

## 9. Config & Deployment Tips

### Model Config Files Live in `config/sync`

ECA model config files are named `eca.model.{machine_id}.yml`. They're standard Drupal config entities and should be committed to `config/sync` and deployed through your normal config management pipeline.

```bash
# Export after building/editing models locally
drush config:export

# Deploy to staging/production
drush config:import

# Inspect a specific model's config
cat config/sync/eca.model.content_article_notify_on_publish.yml
```

### Use `drush eca:` Commands During Development

The `eca_development` sub-module provides Drush commands useful in local/dev:

```bash
# List all models with their status
drush eca:list-models

# Manually trigger a specific model (useful for testing cron-triggered models)
drush eca:execute-model content_article_notify_on_publish
```

Disable `eca_development` on production — it has no place there.

### Disable Modeller Modules in Production Composer

Add the modeller module to `require-dev` rather than `require` to prevent it being installed on production:

```json
{
  "require": {
    "drupal/eca": "^2.0"
  },
  "require-dev": {
    "drupal/bpmn_io": "^2.0"
  }
}
```

Or, if the package is in `require`, add `bpmn_io` and `eca_ui` to your production environment's disabled modules list (Drush, environment indicator split config, etc.).

### Review Model Diffs Like Code Diffs

ECA model YAML files are human-readable. During code review, the diff of a model change should tell a clear story. If the diff is inscrutable, the model machine ID naming or BPMN label naming needs improvement.

---

## 10. Common Gotchas

### "My model doesn't fire at all"

1. Check the model is **enabled** (not disabled) in the ECA admin list
2. Check the **event plugin** is correct — `content_entity:insert` vs `content_entity:presave` vs `content_entity:update` are three different events
3. Check the correct **sub-module** is enabled for the event plugin you're using
4. Raise log level to Debug and look for the model in `/admin/config/workflow/eca/log`

### "Tokens aren't replaced in my email"

The `Send email` action (Drupal core) requires **"Replace tokens: yes"** explicitly set in ECA 2.0. Scroll to the bottom of the action config pane — it's easy to miss.

### "`[entity:field_name]` returns nothing"

Either the entity wasn't loaded into that token name, or the field machine name is wrong. Raise log level to Debug and read the token context dump — it shows every token and its type at that step.

### "My custom event does nothing"

- Confirm the **event ID matches exactly** (case-sensitive) between the trigger action and the listener event
- Confirm you used `Trigger a custom event` (not entity-aware) for `ECA custom event`, or `Trigger a custom event (entity-aware)` for `ECA custom event (entity-aware)` — mixing these is the most common custom event bug

### "Square brackets in my CSS class are being eaten as tokens"

Frameworks like Tailwind CSS use `[arbitrary-values]` in class names that conflict with ECA's token replacement. Fix: define two global tokens via the `ECA token generate` event:

```
Token name: bracket_open  →  value: [
Token name: bracket_close →  value: ]
```

Then compose your class: `[bracket_open]&_.cf-form-label[bracket_close]:tw-hidden`

Since token replacement is not recursive by default, this resolves correctly.

### "I need tokens to persist across requests"

ECA dynamic tokens exist only for the duration of one model execution. For persistence, use:

- **Drupal State API** (`eca_base` → `Persistent state: write/read`) — survives across requests, stored in key-value store
- **Entity fields** — write back to a field on an entity and save it
- **Expirable key-value store** (`eca_base`) — for time-limited persistence
- **Private/Shared Temporary Store** (`eca_base`) — for session-scoped values

### "My model fires twice on node save"

Drupal often fires both `presave` and `insert`/`update` events on a single node save. If your model listens to `presave` and you also save the entity inside the model, you may trigger a recursive loop. Guard against this with a state token or Drupal State that marks "this model is already running" and use it as an early-exit condition.

---

## Quick Reference: Naming Convention Cheat Sheet

```
Model machine IDs:    {domain}_{noun}_{verb}
                      content_article_notify_on_publish
                      user_registration_send_welcome

Model labels:         Plain sentence: "Notify editors when article is submitted"

Model descriptions:   Trigger / Condition / Outcome / Dependencies

Dynamic token names:  camelCase, avoid core token type names
                      articleAuthor, recipientList, orderTotal

Custom event IDs:     lib.{purpose} for reusable components
                      {domain}.{action} for domain events
                      lib.send_notification_email
                      content.after_first_publish

BPMN event labels:    "Article saved (pre-save)"
BPMN gateway labels:  "Is article type?" / "Is user anonymous?"
BPMN action labels:   "Send notification email" / "Load article author"
```

---

## References

- ECA Guide — Best Practices: https://ecaguide.org/eca/best_practices/
- ECA Guide — Tokens: https://ecaguide.org/eca/concepts/tokens/
- ECA Guide — Debugging: https://ecaguide.org/eca/debugging/
- ECA Guide — Custom Events: https://ecaguide.org/eca/concepts/custom_events/
- ECA Guide — Loops: https://ecaguide.org/eca/concepts/loops/
- ECA Guide — Tips (Brackets): https://ecaguide.org/eca/tips/brackets/
- DrupalEasy — Using ECA to replace Termcase: https://www.drupaleasy.com/blogs/ultimike/2023/07/using-eca-module-replace-not-drupal-10-ready-contrib-module-termcase
- Code Enigma — Creating Drupal processes with ECA: https://www.codeenigma.com/blog/creating-drupal-processes-event-condition-action-module

---

*See also: [DRUPAL-ECA.md](./DRUPAL-ECA.md) · [DRUPAL-ECA-CONTRIB.md](./DRUPAL-ECA-CONTRIB.md)*

*Last updated: March 2026.*
