# AI Devel Cache

`ai_devel_cache` is a development-only companion to the [AI module](https://www.drupal.org/project/ai) that transparently caches AI provider responses on the local filesystem. When the same request is sent twice, the second call returns the cached response and no request is made to the provider.

The intended use case is local development workflows — recipes, content imports, search re-indexes, automator runs — that repeatedly issue the same calls. Without this module each rerun bills against your provider account; with it, only the first run does.

By default the module caches embeddings, moderation, image, speech, and chat requests issued by automators, AI CKEditor, AI Translate, AI Content Suggestions, etc. Chat requests from interactive chat UIs (AI Assistant API, DeepChat blocks, `ai_rag_search_chat`) are skipped via a configurable tag deny-list — see [Settings](#settings).

> [!WARNING]
> This module is **not** intended for production. It writes complete prompts and responses to the system temporary directory as plain files. Install it only on local or development environments.

## How it works

The AI module's `ProviderProxy` fires two events around every request:

- `Drupal\ai\Event\PreGenerateResponseEvent`
- `Drupal\ai\Event\PostGenerateResponseEvent`

This module subscribes to both:

- On the pre-event it computes a SHA-256 hash of the request (provider, operation type, model, normalized input payload, and every configuration key except a small deny-list of ephemeral values such as API keys and request identifiers) and looks for a matching cache file. If found, the cached `OutputInterface` is set on the event via `setForcedOutputObject()` and the provider is never called.
- On the post-event (only fires on a miss) the returned `OutputInterface` is serialized to disk under the same hash, along with a `.json` sidecar describing the request — provider, operation type, model, tags, truncated input preview, timestamp — for hand inspection.

No core or contrib code is decorated or patched.

## Installation

```bash
composer require drupal/ai_devel_cache
ddev drush pm:install ai_devel_cache
```

## Storage

Cache files are written to:

```
<sys_get_temp_dir()>/drupal_ai_devel_cache/
```

The directory is `chmod`-ed to `0700` after creation so prompts and responses are not world-readable on shared dev hosts. Each cached request produces two files keyed by the SHA-256 hash: `<hash>.bin` (the serialized `OutputInterface`) and `<hash>.json` (the metadata sidecar).

Uninstall the module to disable caching.

### Overriding the cache directory

The directory's subfolder name is read from the `ai_devel_cache_directory_name` key in `settings.php`. Override it to isolate environments (for example to keep automated tests from trampling a developer's local cache):

```php
// settings.local.php
$settings['ai_devel_cache_directory_name'] = 'drupal_ai_devel_cache_test';
```

The kernel and functional tests use this override automatically so test runs write to `<sys_get_temp_dir()>/drupal_ai_devel_cache_test/` rather than the real cache.

## Report

Visit **Administration → Configuration → AI → Safety & Compliance → AI Devel Cache** (`/admin/config/ai/devel-cache`) to see every cached response — when it was cached, provider, model, operation type, tags, input preview, payload size, and a truncated hash. The page paginates 50 entries at a time and offers a one-click "Clear cache" button.

A collapsible **Cache summary** section at the top shows entry count, total size on disk, the cache directory path, and the oldest/newest `cached_at` timestamps.

## Settings

The **Settings** tab at `/admin/config/ai/devel-cache/settings` exposes one option:

- **Uncacheable chat tags** — chat requests whose `getTags()` intersect this list are never cached. Non-chat operations are always cached regardless of tags. Defaults:
  - `ai_assistant_api_assistant_message` — AI Assistant API responses (powers DeepChat blocks and any assistant-driven chat UI)
  - `mises_chat` — `ai_rag_search_chat`

Add tags here to keep additional interactive chat callers off the cache; remove them to cache their replies too.

## Drush

```bash
# List every cached response.
ddev drush ai-devel-cache:list

# Delete every cached response.
ddev drush ai-devel-cache:clear
```

## Service

The cache backend is exposed as the `ai_devel_cache.manager` service, which implements `\Drupal\ai_devel_cache\AiDevelCacheManagerInterface` and provides `get()`, `set()`, `clear()`, `list()`, and `directory()`. There is no plugin system — if the project ever needed a second backend it would gain one then, not before.

## What is excluded

- Chat requests carrying any tag in **Uncacheable chat tags** (see [Settings](#settings)). Chat responses are non-deterministic; serving a stale completion to an interactive UI would mask real provider behavior. Automator and other server-side chat callers do not carry these tags and are cached normally.
- Streaming responses (`ChatInput::isStreamedOutput() === TRUE`) are passed through unchanged.
- Requests whose input cannot be normalized to a stable representation are passed through unchanged.

## License

GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt).
