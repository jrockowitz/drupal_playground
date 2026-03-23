# DRUPAL-ECA.md

> Event – Condition – Action: a no-code workflow engine for Drupal 9/10/11+

---

## Table of Contents

1. [What Is ECA?](#1-what-is-eca)
2. [Core Concepts](#2-core-concepts)
3. [Architecture](#3-architecture)
4. [Installation](#4-installation)
5. [Modellers](#5-modellers)
6. [Bundled Sub-Modules](#6-bundled-sub-modules)
7. [Tokens](#7-tokens)
8. [Configuration Management](#8-configuration-management)
9. [Ecosystem & Contrib Integrations](#9-ecosystem--contrib-integrations)
10. [Developer Integration](#10-developer-integration)
11. [Common Use Cases](#11-common-use-cases)
12. [Best Practices](#12-best-practices)
13. [ECA vs. Rules](#13-eca-vs-rules)
14. [References](#14-references)

---

## 1. What Is ECA?

ECA (Event – Condition – Action) is the no-code automation and rules engine for Drupal 9 and later. It is a processing engine that subscribes to Drupal events, validates them against configured models, and — when conditions pass — executes one or more actions.

Key characteristics:

- **No core dependencies beyond Drupal core.** ECA core ships with zero contrib requirements.
- **Plugin-driven.** Events, conditions, and actions are all plugins, easily extended by any module.
- **Config-first.** Models are stored as configuration entities and travel through standard config management (CMI, Drush, etc.).
- **Modeller-agnostic.** The processing engine is decoupled from the UI. You choose the modeller that fits your team.
- **Production-safe.** On live sites you can run ECA models with no modeller module enabled at all.

ECA 2.0 (released July 2024) raised the minimum requirement to Drupal 10.3 / PHP 8.1 and introduced dynamic event subscription — the engine now subscribes only to events actually used by models on the site, rather than all 200+ available events.

---

## 2. Core Concepts

### Event

An **event** is a trigger: something that happens in Drupal — a user logs in, a node is saved, a form is submitted, a cron run fires, a custom endpoint is hit, etc. ECA leverages Drupal's existing Symfony event system (via `EventDispatcher`) and adds its own plugin layer on top.

### Condition

A **condition** is a gate. Zero or more conditions can be attached to a transition between an event and an action. If any condition fails, that branch of execution is skipped. Conditions can evaluate user roles, field values, entity type/bundle, token comparisons, state values, and more.

### Action

An **action** is the task to perform when an event fires and all conditions pass: send an email, display a message, set a field value, invalidate cache, redirect a user, create an entity, call a custom event, etc. ECA reuses Drupal core's `ActionInterface` plugin system and adds its own action plugins.

### Model

A **model** is the complete definition of one or more event–condition–action chains. Models are stored in config as `eca.model.*.yml` entities. A single site can have many models; ECA processes all of them for each event.

### BPMN Shape Vocabulary (BPMN.iO Modeller)

| Shape | Meaning |
|-------|---------|
| Circle (Start Event) | Event trigger |
| Diamond (Gateway) | Condition / branching logic |
| Rectangle (Task) | Action |
| Arrow (Sequence Flow) | Condition on a transition |

---

## 3. Architecture

```
Drupal Event System (Symfony EventDispatcher)
        │
        ▼
 ECA Event Subscriber
        │  matches models configured for this event
        ▼
 ECA Processor
        │  evaluates conditions on each transition
        ▼
 ECA Action Executor
        │  executes qualifying actions
        ▼
 Drupal Action Plugins / ECA Action Plugins
```

ECA 2.0 introduced **dynamic event subscription**: only events referenced by at least one active model are subscribed to, eliminating overhead from the previous approach of subscribing to every known event.

Plugin managers exist for:
- Modellers
- Events
- Conditions
- Actions (delegates to Drupal core's `ActionManager` plus ECA's own)

---

## 4. Installation

### Minimum stack

- Drupal 10.3+ (for ECA 2.x) or Drupal 9/10 (for ECA 1.x)
- PHP 8.1+ (ECA 2.x)

### Composer

```bash
# ECA core + recommended BPMN.iO modeller
composer require drupal/eca drupal/bpmn_io

# Enable core + UI + modeller + the sub-modules you need
drush en eca eca_base eca_content eca_form eca_user eca_workflow eca_ui bpmn_io
```

> On production you can enable `eca` and sub-modules without `bpmn_io` or `eca_ui`. Models loaded via config deployment run fine.

### DDEV quickstart

```bash
ddev composer require drupal/eca drupal/bpmn_io
ddev drush en eca eca_base eca_content eca_form eca_user eca_ui bpmn_io -y
ddev drush cr
```

---

## 5. Modellers

ECA core has no built-in UI. You attach one or more modeller modules.

### BPMN.iO (Recommended)

- **Project:** `drupal/bpmn_io`
- A JavaScript BPMN 2.0 diagram tool embedded directly in the Drupal admin UI.
- Drag-and-drop shapes; property panel on the right for configuring plugins.
- Admin path: `/admin/config/workflow/eca`
- Best choice for visual, collaborative workflow design.

### Camunda Modeller (Desktop)

- External desktop application; create `.bpmn` files offline, import into Drupal.
- Useful for complex models or offline / version-controlled editing.

### ECA Classic Modeller

- Form API-based "low-level" modeller included with ECA itself.
- Useful for developers who prefer config-driven setup without a diagram.
- Exposes all plugins directly in Drupal forms.

> On production, **disable modeller modules** to reduce attack surface and eliminate unnecessary JavaScript assets. Models continue to execute normally.

---

## 6. Bundled Sub-Modules

Enable sub-modules selectively based on project needs:

| Sub-module | Purpose |
|------------|---------|
| `eca_access` | Control access on entities and fields |
| `eca_base` | Core base events, conditions, and actions (token set/get, loops, etc.) |
| `eca_cache` | Read, write, or invalidate cache items |
| `eca_config` | React to configuration change events |
| `eca_content` | Content entity events, conditions, and actions (node save, delete, etc.) |
| `eca_endpoint` | Define custom routes on the fly; interact with request/response |
| `eca_form` | Form API events, conditions, and actions |
| `eca_log` | React to and write Drupal log messages |
| `eca_migrate` | Events for Drupal Migrate operations |
| `eca_misc` | Miscellaneous core/kernel events and conditions |
| `eca_queue` | Queue API events and actions |
| `eca_render` | Render API events/actions for blocks, views, Twig |
| `eca_user` | User events (login, register, role change) and actions |
| `eca_views` | Execute Views queries and export results within ECA |
| `eca_workflow` | Content moderation/workflow state transition actions |
| `eca_ui` | Admin interface (enable in dev; optional in prod) |
| `eca_development` | Drush commands for ECA developers |

---

## 7. Tokens

Tokens are the primary mechanism for passing data between steps in an ECA model.

### Two Token Layers

**Drupal-native tokens** (read-only)
: Provided by Drupal core and contrib modules. Examples: `[node:title]`, `[current-user:mail]`, `[site:name]`. Browsable via the Token module's Token Browser overlay inside BPMN.iO.

**ECA dynamic tokens** (read-write)
: Set by ECA itself within a model run (e.g. via `Token: set value` action). Scoped to the current model execution; not persistent across requests unless explicitly stored.

### Token Storage for Persistence

For values that must persist beyond a single model run:

- **Drupal State API** — via `eca_base` state read/write actions
- **Entity fields** — write back to content entities
- **Temp store / session** — via appropriate ECA actions
- **Custom tokens via ECA 2.0** — models can now expose their own tokens by implementing `hook_token_info` through ECA's event system

### Token Replacement Gotchas

- Non-ECA action plugins (core's `send_email`, `display_message`) may not auto-replace tokens. Look for the **"Replace tokens"** field at the bottom of their config panes in ECA 2.0 and set it to `yes`.
- Tailwind CSS or other frameworks using square-bracket syntax (e.g. `[&_.classname]:tw-hidden`) conflict with token replacement. Workaround: define global tokens `[bracket:open]` and `[bracket:close]` with values `[` and `]`, then compose the class string using those tokens.
- Token replacement is **not recursive by default**, which avoids infinite loops but means composed tokens must be set explicitly.

---

## 8. Configuration Management

ECA models are standard Drupal configuration entities (`eca.model.*`). They integrate natively with all config management workflows:

```bash
# Export all config including ECA models
drush config:export

# Import ECA models to a target environment
drush config:import

# List ECA-specific config
drush config:list | grep eca.model

# ECA Development sub-module adds Drush commands
drush eca:list-models
drush eca:execute-model my_model_id
```

### Deployment Best Practices

- **Version-control models** as part of your site config (`config/sync`). Treat them like any other config artifact.
- **Keep modeller modules out of production** `composer.json`'s `require` (use `require-dev`) or at least disable them in production — models run without them.
- **Use feature flags** (config splits, environment indicators) when models behave differently per environment.
- **Tag your models** with a machine-name prefix by domain (e.g. `content_`, `user_`, `commerce_`) to keep lists navigable.

---

## 9. Ecosystem & Contrib Integrations

The ECA ecosystem is actively growing. Notable integrations:

| Module | Purpose |
|--------|---------|
| [`eca_webform`](https://www.drupal.org/project/eca_webform) | Webform submission events, conditions, and actions (746+ installs; ECA 2.x version for Drupal 10.4+/11) |
| `eca_commerce` | Drupal Commerce order and cart workflow events |
| `eca_metatag` | Metatag field manipulation |
| `eca_ai` (AI module integration) | Trigger AI operations (chat, moderation, TTS) via ECA models |
| `eca_helper` | Community-contributed extra helpers |
| `bpmn_io` | The recommended visual BPMN modeller |

> **For Webform module users:** `eca_webform` is maintained by the ECA core team (jurgenhaas) and exposes Webform submission lifecycle hooks as ECA events. The Webform module itself does not include ECA hooks natively — the integration lives in the separate `eca_webform` project. As of August 2025 the 2.x branch is current, requiring Drupal ^10.4 || ^11.

Full ecosystem listing: https://www.drupal.org/project/eca/ecosystem

---

## 10. Developer Integration

### Writing a Custom Event Plugin

```php
<?php
// src/Plugin/ECA/Event/MyCustomEvent.php

namespace Drupal\my_module\Plugin\ECA\Event;

use Drupal\eca\Attribute\Token;
use Drupal\eca\Plugin\ECA\Event\EventBase;

/**
 * @EcaEvent(
 *   id = "my_module_my_event",
 *   label = @Translation("My Module: My Event"),
 *   event_name = MyModuleEvents::MY_EVENT,
 *   event_class = "\Drupal\my_module\Event\MyEvent",
 * )
 */
#[Token(
  name: 'my_entity',
  description: 'The entity from my event.',
  classes: [\Drupal\my_module\Event\MyEvent::class],
  properties: [
    new Token(name: 'id', description: 'The entity ID.'),
    new Token(name: 'label', description: 'The entity label.'),
  ],
)]
class MyCustomEvent extends EventBase {
  // Implement getData() to expose tokens from the event object.
}
```

> In ECA 2.0, annotate event plugins with `#[Token]` PHP attributes so ECA can expose dynamic tokens to the UI automatically.

### Writing a Custom Action Plugin

```php
<?php
namespace Drupal\my_module\Plugin\Action;

use Drupal\eca\Plugin\Action\ActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * @Action(
 *   id = "my_module_do_something",
 *   label = @Translation("My Module: Do Something"),
 *   category = @Translation("My Module"),
 * )
 */
class DoSomething extends ActionBase {

  use PluginFormTrait;

  public function execute($object = NULL): void {
    $field_name = $this->configuration['field_name'];
    // Support tokens in select/dropdown fields (ECA 2.0+)
    if ($field_name === '_eca_token') {
      $field_name = $this->getTokenValue('field_name', 'default');
    }
    // ... do work
  }

}
```

### Writing a Custom Condition Plugin

Implement `\Drupal\eca\Plugin\ECA\Condition\StringConditionBase` or extend `ConditionBase`. Return a boolean from `evaluate()`.

### Reusable "Component" Pattern

Build generic sub-models that respond to a **custom event** with token arguments. Other models trigger that custom event and pass tokens as arguments. Example: a universal "send notification email" model that consumes `[recipient]`, `[subject]`, and `[body]` tokens set by the calling model.

---

## 11. Common Use Cases

- **Content moderation notifications** — notify editors/reviewers when a node transitions to "needs review"
- **Welcome emails** — send a personalized onboarding message on user registration
- **Cache invalidation** — tag-based cache clearing when specific content types are updated
- **Redirect handling** — replace standalone redirect modules (403 → login, post-login redirect)
- **Field validation** — augment or replace custom validation hooks with ECA Form conditions
- **Webform post-processing** — respond to submission events: create entities, send emails, call external APIs
- **Scheduled/queued tasks** — trigger queued operations on cron or content events
- **AI-augmented workflows** — auto-generate tags, summaries, or alt text when content is created (via AI module integration)
- **Custom endpoints** — define lightweight REST-like routes via `eca_endpoint` without writing a controller

---

## 12. Best Practices

### Model Organization

- Use a **naming convention** for model IDs and labels: `{domain}_{verb}_{noun}` (e.g. `content_notify_on_publish`).
- Keep models **focused**: one primary event per model, with branching via gateways rather than many top-level events in a single model.
- Add a **description** to every model explaining its purpose and any non-obvious dependencies.
- **Split large models** into a triggering model + reusable sub-models called via custom events.

### Token Hygiene

- Prefer **ECA-specific action plugins** over core action plugins where available — they handle token replacement natively.
- For core action plugins, always set **"Replace tokens": yes** explicitly.
- Define globally useful tokens (e.g. `[bracket:open]`, `[bracket:close]`, `[site:base_url]`) once via the `ECA token generate` event rather than setting them in every model.
- Document token names used by each model in the model description.

### Configuration & Deployment

- Store all models in **config/sync** and review diffs in code review just like code changes.
- Disable modeller modules (`bpmn_io`, `eca_ui`, `eca_classic_modeller`) on production environments.
- Use **ECA Development** sub-module and its Drush commands only in local/dev.
- Test models in **staging with realistic content** — token availability at runtime depends heavily on what event was triggered and what data was loaded.

### Performance

- ECA 2.0's dynamic event subscription removes the overhead of subscribing to unused events — **update to ECA 2.x** on Drupal 10.3+ sites.
- Avoid models that trigger on very high-frequency events (e.g. every entity load) unless the model exits quickly via early conditions.
- Use **ECA Queue** for heavy or asynchronous operations rather than doing them inline on every event.
- Cache entity loads inside actions where possible; ECA does not automatically cache objects across a model run.

### Debugging & Logging

- Enable **ECA Log** sub-module and use the "Set ECA log level" action at the start of a model during development.
- Check `admin/reports/dblog` for ECA-tagged messages.
- The **ECA Development** Drush command `drush eca:execute-model` can help trigger models manually during development.
- Use **condition-based early exits** (gateways with a "stop" path) to make model logic explicit and debuggable.

### Security

- ECA models stored in config are not user-submitted data, but treat them with the same review discipline as code: **review all model changes in MRs**.
- When using `eca_endpoint`, validate and sanitize all request data with conditions before taking any action.
- Be mindful of what the **anonymous user** can trigger — events fired by anonymous requests will execute all matching models.

---

## 13. ECA vs. Rules

| Aspect | ECA | Rules (D9+) |
|--------|-----|-------------|
| Drupal version | 9, 10, 11 | 9, 10 (actively maintained but slower) |
| Architecture | Symfony events + plugin managers | Symfony events + plugin managers |
| UI | BPMN.iO (visual diagrams) / Classic Form | Form-based |
| Config management | Native CMI config entities | Native CMI config entities |
| Performance (D10) | ECA 2.0 dynamic subscriptions | Full subscription model |
| Community momentum | High / growing rapidly | Moderate |
| Webform integration | `eca_webform` module | `rules` issue queue |
| AI integration | `eca_ai` ecosystem | Limited |
| Learning curve | BPMN notation intuitive for non-devs | Familiar form-based UI |

For new Drupal 10/11 projects, **ECA is the recommended choice**. Rules remains a valid option for teams with existing D7 Rules experience who prefer its UI style.

---

## 14. References

### Official Resources

- **ECA on Drupal.org:** https://www.drupal.org/project/eca
- **ECA Guide (primary documentation site):** https://ecaguide.org/
- **ECA Ecosystem modules:** https://www.drupal.org/project/eca/ecosystem
- **ECA 2.0.0 release notes:** https://www.drupal.org/project/eca/releases/2.0.0
- **ECA Slack channel:** `#eca` on Drupal Slack

### Modellers

- **BPMN.iO module:** https://www.drupal.org/project/bpmn_io

### Ecosystem Modules

- **ECA Webform:** https://www.drupal.org/project/eca_webform
- **ECA Guide — Webform integration:** https://ecaguide.org/plugins/eca/webform/

### Developer Change Records

- **Tokens provided by events are now discoverable (ECA 2.0):** https://www.drupal.org/node/3444312
- **Drop-down config fields now support tokens:** https://www.drupal.org/node/3447358
- **ECA models can provide tokens via hook_token_info:** https://www.drupal.org/project/eca/issues/3441450

### Community Articles

- Code Enigma — "Creating Drupal processes with the ECA module": https://www.codeenigma.com/blog/creating-drupal-processes-event-condition-action-module
- ImageX — "The ECA Module: Setting Up Automated Actions": https://imagexmedia.com/blog/eca-module-drupal
- QED42 — "Automating workflows in Drupal with ECA": https://www.qed42.com/insights/automating-workflows-in-drupal-with-eca-event-condition-action
- TO THE NEW — "Automating Drupal Workflows with ECA (Feb 2026)": https://www.tothenew.com/blog/automation-in-drupal-with-eca-event-condition-action
- Drupfan — "Event-Condition-Action: Creating Custom Workflow": https://drupfan.com/en/blog/event-condition-action-creating-custom-workflow-drupal-eca-module

### DrupalCon / Camp Sessions

- SWO DrupalCamp 2025 — "Getting Started with ECA in Drupal 10/11": https://swo.drupalcanada.org/events/2025/sessions/getting-started-eca-event-condition-action-drupal

---

*Last updated: March 2026. ECA is under active development — always verify version compatibility against the current ECA release and your Drupal core version.*
