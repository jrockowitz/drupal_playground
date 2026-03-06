# Drupal Playground AI Recipe

Installs and configures the Drupal AI module suite: OpenAI + Anthropic providers,
site-building agents, DeepChat chatbot, CKEditor integration, content suggestions,
and image alt text.

## Prerequisites

Create a `keys/` directory in the project root and add your API key files before
applying the recipe. The recipe imports key config entities that reference these files.

```bash
mkdir -p keys
echo '/keys/' >> .gitignore
nano keys/openai.key      # paste your OpenAI API key
nano keys/anthropic.key   # paste your Anthropic API key
```

Each file should contain only the raw API key with no trailing newline.

## Apply the Recipe

```bash
ddev exec drush recipe ../recipes/drupal_playground_ai
ddev exec drush cr
```

## Post-Install Manual Steps

### AI Provider Model Settings

Optionally limit which models are available for each operation type:

```bash
ddev launch /admin/config/ai/settings
```

### AI Logging

Enable logging of AI requests and responses with contextual information:

```bash
ddev launch /admin/config/ai/logging/settings
ddev launch /admin/config/ai/logging/collection
```

### AI Observability

Logs AI requests to the Drupal Logger and OpenTelemetry:

```bash
ddev launch /admin/config/ai/observability
```

## Developer Tools

```bash
ddev launch /admin/config/ai/explorers        # AI API Explorer
ddev launch /admin/config/ai/agents/explorer  # AI Agents Explorer
```

## Additional Resources

- [AI module documentation](http://project.pages.drupalcode.org/ai/)
- [Drupal AI Initiative](https://www.drupal.org/about/starshot/initiatives/ai)
- [Video walkthrough on YouTube](https://www.youtube.com/watch?v=hptyElqmo6Q)
- [Drupal AI project page on Drupal.org](https://www.drupal.org/project/ai)
- [Source article on Drupalize.me](https://drupalize.me/blog/drupal-ai-how-set-it-and-try-it-out)
- [Drupal AI Ecosystem Part 1: Setup and AI CKEditor Configuration](https://opensenselabs.com/blog/drupal-ai-series/ai-module-setup-and-ckeditor)
- [AI in Drupal: a focused guide to practical implementation](https://www.qed42.com/insights/ai-in-drupal-a-focused-guide-to-practical-implementation)
