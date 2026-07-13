# Ecosystem: Drupal AI Specification

## Purpose

Define the current Drupal AI workspace, presets, Recipes, and installed
development capabilities.

## Requirements

### Requirement: Drupal AI worktree identity

Drupal AI ecosystem work SHALL use the `drupal_ai` worktree and the shared
foundation specifications.

#### Scenario: Begin focused AI work

- **WHEN** a change primarily targets the Drupal AI module ecosystem or its local integrations
- **THEN** the developer selects `drupal_ai`

### Requirement: Foundational AI installation

Every Drupal Playground install SHALL apply the foundational AI and AI Devel
Recipes.

#### Scenario: Install the shared AI foundation

- **WHEN** the shared Recipe sequence runs
- **THEN** the AI module, OpenAI provider, external moderation, Key integration, API Explorer, Assistant API, development cache, and AI logging are installed or configured

### Requirement: Provider presets

The ecosystem SHALL support OpenAI, Anthropic, and Gemini provider Recipes as
pre-configuration presets.

#### Scenario: Select a provider

- **WHEN** `openai`, `anthropic`, or `gemini` is passed to `ddev install`
- **THEN** its provider Recipe is applied before other optional ecosystem Recipes

### Requirement: Full AI preset

The `ai` preset SHALL compose the current AI demonstration features in tracked
order.

#### Scenario: Install the AI demonstration suite

- **WHEN** `ddev install ai` runs
- **THEN** it applies the AI Chat, AI CKEditor, AI Content Suggestions, AI Devel, AI Automators, AI CKEditor Explain, AI MCP, and AI Schema.org JSON-LD Recipes
- **AND** the generated login URL targets `/admin/config/ai`

### Requirement: AI development capabilities

The full AI preset SHALL provide chat and assistant configuration, CKEditor AI
tools, content suggestions, field automators, an explain workflow, local MCP
experimentation, and AI-generated Schema.org JSON-LD fields.

#### Scenario: Inspect the installed AI site

- **WHEN** the full AI preset completes
- **THEN** the tracked configurations for those capabilities are present
- **AND** provider features that require credentials rely on the corresponding local key configuration
