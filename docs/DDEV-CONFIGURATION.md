# DDEV-CONFIGURATION.md: Configuring DDEV for Drupal

DDEV stores its configuration in `.ddev/config.yaml`. This file is created by `ddev config` and can be edited directly.

## Core Configuration

```yaml
# .ddev/config.yaml
type: drupal
docroot: web
php_version: "8.3"
webserver_type: nginx-fpm
router_http_port: "80"
router_https_port: "443"
xdebug_enabled: false
additional_hostnames: []
additional_fqdns: []

# Drupal-specific settings
disable_settings_management: false
web_environment:
  - DRUSH_OPTIONS_URI=https://my_project.ddev.site
```

---

## Extended Configuration Options

Add any of these to `.ddev/config.yaml`:

```yaml
# Node.js version (uses `n` under the hood)
nodejs_version: "22"

# Add extra Debian packages to the web container
webimage_extra_packages: [php8.2-tidy]

# Lifecycle hooks — run tasks on ddev start, import, etc.
hooks:
  post-start:
    - exec: "drush cr"
    - exec: "drush updatedb -y"
  post-import-db:
    - exec: "drush cr"
    - exec: "drush uli"
```

### Useful Hook Events

| Event             | Fires when...                        |
|-------------------|--------------------------------------|
| `pre-start`       | Before containers start              |
| `post-start`      | After containers are running         |
| `pre-stop`        | Before containers stop               |
| `post-stop`       | After containers stop                |
| `post-import-db`  | After `ddev import-db` completes     |
| `post-import-files` | After `ddev import-files` completes |

---

## Override Files

You can extend or override `config.yaml` using additional `config.*.yaml` files. These are loaded in lexicographic order and merged.

| File | Purpose |
|------|---------|
| `config.local.yaml` | Local, developer-specific settings (gitignored by default) |
| `config.*.local.yaml` | Also gitignored — any file matching this pattern |
| `config.selenium-standalone-chrome.yaml` | Added by the Selenium add-on |

Use `override_config: true` in a `config.*.yaml` to **replace** values instead of merging them.

---

## Custom PHP Configuration

Create `.ddev/php/php.ini` for PHP overrides:

```ini
; .ddev/php/php.ini
memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
```

---

## Custom Nginx Configuration

Place custom Nginx config in `.ddev/nginx_full/` or use `.ddev/nginx/` for snippets that are included in the default config.

---

## Environment Variables

Set environment variables via `web_environment` in `config.yaml`:

```yaml
web_environment:
  - DRUSH_OPTIONS_URI=https://my_project.ddev.site
  - MY_API_KEY=abc123
  - ENVIRONMENT=local
```

Or use a `.ddev/.env` file for secrets you don't want in version control.

---

## Add-ons

DDEV supports add-ons that extend your environment. Browse all available add-ons at the [DDEV Add-on Registry](https://addons.ddev.com/) or search from the CLI:

```bash
ddev add-on search <term>
```

### Selenium (Headless Chrome for Testing)

**Repository:** [ddev/ddev-selenium-standalone-chrome](https://github.com/ddev/ddev-selenium-standalone-chrome)

Provides a headless Chrome browser via Selenium for Functional and FunctionalJavascript tests.

```bash
ddev add-on get ddev/ddev-selenium-standalone-chrome
ddev restart
```

**Prerequisites for Drupal testing:**

```bash
ddev composer require drupal/core-dev --dev
```

**Running tests with Selenium:**

```bash
# Functional test
ddev exec -d /var/www/html/web \
  "../vendor/bin/phpunit -c ./core/phpunit.xml.dist \
  ./core/modules/migrate/tests/src/Functional/process/DownloadFunctionalTest.php"

# FunctionalJavascript test
ddev exec -d /var/www/html/web \
  "../vendor/bin/phpunit -c ./core/phpunit.xml.dist \
  ./core/modules/syslog/tests/src/FunctionalJavascript/SyslogTest.php"
```

**Notes:**
- Commit `.ddev/` to version control after installing add-ons.
- Remove `#ddev-generated` from generated config files to prevent overwriting on updates.
- Re-run the `ddev add-on get` command after changing `name`, `additional_hostnames`, `additional_fqdns`, or `project_tld`.

### Other Useful Add-ons

| Add-on | Purpose |
|--------|---------|
| `ddev/ddev-redis` | Redis caching backend |
| `ddev/ddev-elasticsearch` | Elasticsearch for Search API |
| `ddev/ddev-memcached` | Memcached caching |
| `ddev/ddev-varnish` | Varnish reverse proxy |
| `ddev/ddev-browsersync` | Live browser reloading |

Install any of these with `ddev add-on get <name>`.

---

## Resources

- **DDEV Config Options:** https://docs.ddev.com/en/stable/users/configuration/config/
- **DDEV Hooks:** https://docs.ddev.com/en/stable/users/configuration/hooks/
- **DDEV Add-on Registry:** https://addons.ddev.com/
- **Using DDEV Add-ons:** https://docs.ddev.com/en/stable/users/extend/using-add-ons/
