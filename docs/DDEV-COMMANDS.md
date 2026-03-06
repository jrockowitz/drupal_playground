# DDEV-COMMANDS.md: DDEV Command Reference for Drupal

## Environment Management

```bash
ddev start                   # Start the environment
ddev stop                    # Stop (preserves data)
ddev restart                 # Restart
ddev poweroff                # Stop ALL DDEV projects
ddev delete                  # Delete environment (removes containers + DB!)
ddev delete -O               # Delete without creating a snapshot first
ddev describe                # Show URLs, ports, DB info
ddev launch                  # Open site in default browser
```

---

## Composer

```bash
ddev composer install                  # Install dependencies
ddev composer require <package>        # Add a package
ddev composer update                   # Update all packages
ddev composer audit                    # Check for security advisories
ddev composer outdated                 # Show outdated packages

# Composer memory issues workaround
ddev exec php -d memory_limit=-1 /usr/local/bin/composer install
```

---

## Database Operations

```bash
ddev snapshot                          # Quick backup snapshot
ddev snapshot --name=before-update     # Named snapshot
ddev snapshot restore                  # Restore most recent snapshot
ddev snapshot restore --latest         # Restore latest (explicit)
ddev snapshot list                     # List all snapshots
ddev import-db --file=dump.sql.gz      # Import DB from file
ddev export-db --file=dump.sql.gz      # Export DB to file
```

---

## Container Access & Logs

```bash
ddev ssh                               # SSH into web container
ddev ssh -s db                         # SSH into DB container
ddev exec <command>                    # Run command in web container
ddev logs                              # View container logs
ddev logs -f                           # Follow logs in real time
ddev logs -f -s web                    # Follow web container logs
ddev logs -f -s db                     # Follow DB container logs
```

---

## Debugging

```bash
ddev xdebug on                         # Enable Xdebug
ddev xdebug off                        # Disable Xdebug
ddev xdebug status                     # Check Xdebug status
```

---

## Code Quality

```bash
# Drupal coding standards
ddev exec vendor/bin/phpcs --standard=Drupal \
  --extensions=php,inc,module,install,info,yml src/

# Drupal best practices
ddev exec vendor/bin/phpcs --standard=DrupalPractice \
  --extensions=php,inc,module,install,info,yml src/

# Auto-fix coding standard violations
ddev exec vendor/bin/phpcbf --standard=Drupal \
  --extensions=php,inc,module,install,info,yml src/
```

---

## Testing

```bash
# Run all tests
ddev exec vendor/bin/phpunit -v

# By test suite
ddev exec vendor/bin/phpunit --testsuite unit
ddev exec vendor/bin/phpunit --testsuite kernel
ddev exec vendor/bin/phpunit --testsuite functional

# Specific test class or method
ddev exec vendor/bin/phpunit --filter MyModuleTest

# Coverage report
ddev exec vendor/bin/phpunit -v --coverage-html coverage/
```

### Running Core Tests (with Selenium)

```bash
# Functional test (requires ddev-selenium-standalone-chrome add-on)
ddev exec -d /var/www/html/web \
  "../vendor/bin/phpunit -c ./core/phpunit.xml.dist \
  ./core/modules/migrate/tests/src/Functional/process/DownloadFunctionalTest.php"

# FunctionalJavascript test
ddev exec -d /var/www/html/web \
  "../vendor/bin/phpunit -c ./core/phpunit.xml.dist \
  ./core/modules/syslog/tests/src/FunctionalJavascript/SyslogTest.php"
```

---

## Add-on Management

```bash
ddev add-on search <term>              # Search available add-ons
ddev add-on get <name>                 # Install an add-on
ddev add-on list                       # List installed add-ons
ddev add-on remove <name>              # Remove an add-on
```

---

## Troubleshooting

```bash
# Won't start — full reset
ddev poweroff && ddev start

# Check status
ddev describe
ddev list                              # List all DDEV projects

# Check DDEV version and doctor
ddev --version
ddev debug                             # Debug info
```
