# Telephone Filter

A Drupal 10/11 text format filter plugin that converts US phone numbers in HTML
text into clickable `tel:` links.

## Requirements

- Drupal core 10.3.x or 11.x
- PHP 8.3+

## Installation

```bash
composer require drupal/telephone_filter
drush en telephone_filter
```

Enable the filter on a text format at `/admin/config/content/formats`.

## Features

- Detects numeric formats: `888-888-8888`, `888.888.8888`, `(888) 888-8888`.
- Detects vanity formats: `800-FLOWERS`, `800-ASK-HELP`.
- Configurable list of allowed area codes — leave blank to link all phone numbers.
- Skips numbers already inside `<a>` tags — no double-wrapping.
- Safe DOM-based HTML manipulation via `DOMDocument`.

## Usage

1. Navigate to **Administration → Configuration → Content authoring → Text formats and editors**.
2. Edit a text format and enable **Telephone filter**.
3. Optionally enter allowed area codes (one per line) in the filter's settings. Leave blank to link all phone numbers.
4. Save the format.
