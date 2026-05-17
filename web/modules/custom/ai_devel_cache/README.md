# AI Devel Cache

`ai_devel_cache` is a development-only companion to the [AI module](https://www.drupal.org/project/ai) that transparently caches AI provider responses on the local filesystem. When the same prompt is sent twice, the second call returns the cached response and no request is made to the provider.

The intended use case is local development workflows that re-run AI Automators or other AI-driven recipes repeatedly. Without this module each rerun bills against your provider account; with it, only the first run does.

> [!WARNING]
> This module is **not** intended for production. It writes complete prompts and responses to the system temporary directory as plain files. Install it only on local or development environments.

## How it works

The AI module's `ProviderProxy` fires two events around every request:

- `Drupal\ai\Event\PreGenerateResponseEvent`
- `Drupal\ai\Event\PostGenerateResponseEvent`

This module subscribes to both:

- On the pre-event it computes a SHA-256 hash of the request (provider, operation type, model, input payload, deterministic configuration keys) and looks for a matching cache file. If found, the cached `OutputInterface` is set on the event via `setForcedOutputObject()` and the provider is never called.
- On the post-event (only fires on a miss) the returned `OutputInterface` is serialized to disk under the same hash, along with a `.json` sidecar describing the request for hand inspection.

No core or contrib code is decorated or patched.

## Installation

```bash
composer require drupal/ai_devel_cache
ddev drush pm:install ai_devel_cache
```

## Configuration

The module is zero-configuration. Cache files are written to:

```
<sys_get_temp_dir()>/drupal_ai_devel_cache/
```

To clear the cache, delete the directory. Uninstall the module to disable caching.

## What is excluded

- Chat completions (`operation_type === 'chat'`) are not cached. Chat responses are non-deterministic enough that serving a stale completion would mask real provider behavior; cache embeddings, moderation, and image operations instead.
- Streaming responses (`ChatInput::isStreamedOutput() === TRUE`) are passed through unchanged.
- Requests whose input cannot be normalized to a stable representation are passed through unchanged.

## License

GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt).
