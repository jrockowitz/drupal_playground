# Drupal-AI.md: Setup Guide

A step-by-step guide for installing and configuring the Drupal AI module suite to create an AI-powered site building assistant.

## Later

- [ ] AI Search
- [ ] AI Translations (requires Google AI)
- [ ] AI Validations
- [ ] AI Automators

## Issues

- Dashboard icon missing from navigation.

## Install & Enable AI Modules

```bash
ddev install ai
```

This installs Drupal, enables all AI modules, and configures logging/observability.

Create a keys directory

```bash
mkdir keys;
echo '/keys/' >> .gitignore;
```

## AI Providers

### Configure the OpenAI provider

```bash
# Set key
nano keys/openai.key;
# Add key
ddev launch /admin/config/system/keys/add;
```

- **Key name:** `OpenAI`
- **Key type:** `Authentication`
- **Key provider:** `File`
- **File location:** `../keys/openai.key`
- **Strip trailing line breaks:** `Yes`

```bash
# Setup provider
ddev launch /admin/config/ai/providers/openai;
```

### Configure the Anthropic provider

```bash
# Create a keys directory
mkdir keys;
echo '/keys/' >> .gitignore;
```

```bash
# Set key
nano keys/anthropic.key;
# Add key
ddev launch /admin/config/system/keys/add;
```

- **Key name:** `Anthropic`
- **Key type:** `Authentication`
- **Key provider:** `File`
- **File location:** `../keys/anthropic.key`
- **Strip trailing line breaks:** `Yes`

```bash
# Setup provider
ddev launch /admin/config/ai/providers/anthropic;
```

```bash
# Change/limit provider models
ddev launch /admin/config/ai/settings
```
## AI Chatbot (Assistant)

```bash
# Add an AI Assistant
ddev launch /admin/config/ai/ai-assistant
```

- Fill in the form:
  - **Label:** `Drupal Site Building Helper`
  - **Description:** `An assistant that can help with Drupal site building and configuration tasks.`
  - **Prompt:** `You are an assistant helping a human site administrator on a Drupal website with site building and configuration tasks.`
  - **Agents to use**: `All`
  - __Use the default settings for the rest of the fields.__

```bash
# Add an AI DeepChat Chatbot block
ddev launch /admin/structure/block/add/ai_deepchat_block/gin?region=content;
```

## AI CKEditor

```bash
ddev launch /admin/config/content/formats/manage/basic_html?destination=/admin/config/content/formats
```

- Add both AI icons as the first items in the toolbar.
- In 'AI tools' enable all features.

# AI Content Suggestions

```bash
ddev launch /admin/config/ai/suggestions
```

## AI Logging

This enables you to log any AI request and response as well as additional contextual information.

```bash
ddev launch /admin/config/ai/logging/settings;
ddev launch /admin/config/ai/logging/collection;
```

## AI Observability

Logs AI requests to the Drupal Logger and OpenTelemetry.

```bash
ddev launch /admin/config/ai/observability;
```

## AI API and Agents Explorer

AI Agents Explorer: A helper tool for running and debugging Agents.
AI API Explorer: Adds multiple developers explorers where you can test different settings.

```bash
ddev launch /admin/config/ai/explorers
ddev launch /admin/config/ai/agents/explorer
```

## Additional Resources

- **[AI (Artificial Intelligence) module for Drupal](http://project.pages.drupalcode.org/ai/)**
- [Drupal AI Initiative](https://www.drupal.org/about/starshot/initiatives/ai)
- [Video walkthrough on YouTube](https://www.youtube.com/watch?v=hptyElqmo6Q)
- [Drupal AI project page on Drupal.org](https://www.drupal.org/project/ai)
- [Source article on Drupalize.me](https://drupalize.me/blog/drupal-ai-how-set-it-and-try-it-out)
- [Drupal AI Ecosystem Part 1: Setup and AI CKEditor Configuration](https://opensenselabs.com/blog/drupal-ai-series/ai-module-setup-and-ckeditor)
- [AI in Drupal: a focused guide to practical implementation](https://www.qed42.com/insights/ai-in-drupal-a-focused-guide-to-practical-implementation)
