
# DDEV-COMMANDS.md: DDEV Command Reference for Drupal

A practical quick-reference for working with Drupal projects using DDEV.

---

# Project Setup

```bash
# Configure a new project
ddev config

# Start the environment
ddev start

# Import an existing database
ddev import-db --file=dump.sql.gz

# Import a files directory
ddev import-files --src=./files
```

---

# Environment Management

```bash
ddev start                   # Start the environment
ddev stop                    # Stop (preserves data)
ddev restart                 # Restart containers
ddev restart --build         # Rebuild containers and restart
ddev poweroff                # Stop ALL DDEV projects
ddev delete                  # Delete environment (removes containers + DB!)
ddev delete -O               # Delete without creating a snapshot first
ddev describe                # Show URLs, ports, DB info
ddev launch                  # Open site in default browser
ddev list                    # List all DDEV projects
```

---

# Composer

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

# Drupal / Drush

```bash
ddev drush status          # Show Drupal status
ddev drush cr              # Clear caches
ddev drush cim -y          # Import configuration
ddev drush cex -y          # Export configuration
ddev drush uli             # Generate one-time login link
ddev drush updb -y         # Run database updates
```

Tip: Many developers create a shortcut:

```bash
alias drush='ddev drush'
```

---

# Database Operations

```bash
ddev snapshot                          # Quick backup snapshot
ddev snapshot --name=before-update     # Named snapshot
ddev snapshot restore                  # Restore most recent snapshot
ddev snapshot restore --latest         # Restore latest (explicit)
ddev snapshot list                     # List all snapshots

ddev import-db --file=dump.sql.gz      # Import DB from file
ddev export-db --file=dump.sql.gz      # Export DB to file

ddev mysql                             # Open MySQL CLI
```

---

# Container Access & Logs

```bash
ddev ssh                               # SSH into web container
ddev ssh -s db                         # SSH into DB container

ddev exec <command>                    # Run command in web container
ddev exec -s web <command>             # Run command in web service
ddev exec -s db <command>              # Run command in db service

ddev logs                              # View container logs
ddev logs -f                           # Follow logs in real time
ddev logs -f -s web                    # Follow web container logs
ddev logs -f -s db                     # Follow DB container logs
```

---

# Debugging

```bash
ddev xdebug on                         # Enable Xdebug
ddev xdebug off                        # Disable Xdebug
ddev xdebug status                     # Check Xdebug status
```

Performance tip: keep Xdebug disabled unless actively debugging.

---

# Code Quality

```bash
# Drupal coding standards
ddev exec vendor/bin/phpcs --standard=Drupal   --extensions=php,inc,module,install,info,yml src/

# Drupal best practices
ddev exec vendor/bin/phpcs --standard=DrupalPractice   --extensions=php,inc,module,install,info,yml src/

# Auto-fix coding standard violations
ddev exec vendor/bin/phpcbf --standard=Drupal   --extensions=php,inc,module,install,info,yml src/
```

---

# Testing

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
ddev exec -d /var/www/html/web   "../vendor/bin/phpunit -c ./core/phpunit.xml.dist   ./core/modules/migrate/tests/src/Functional/process/DownloadFunctionalTest.php"

# FunctionalJavascript test
ddev exec -d /var/www/html/web   "../vendor/bin/phpunit -c ./core/phpunit.xml.dist   ./core/modules/syslog/tests/src/FunctionalJavascript/SyslogTest.php"
```

---

# Add-on Management

```bash
ddev add-on search <term>              # Search available add-ons
ddev add-on get <name>                 # Install an add-on
ddev add-on list                       # List installed add-ons
ddev add-on remove <name>              # Remove an add-on
```

---

# File Synchronization (Mutagen)

```bash
ddev mutagen status                    # Check sync status
ddev mutagen reset                     # Reset sync cache
ddev mutagen pause                     # Pause file sync
ddev mutagen resume                    # Resume file sync
```

Mutagen is used by DDEV on macOS to improve filesystem performance.

---

# Reclaim Docker Disk Space

Docker images, build layers, and synchronization caches can consume significant disk space over time when working with many DDEV projects.

## Reset Mutagen Cache

Mutagen synchronization caches may grow large. Resetting the cache can reclaim disk space without affecting project data.

```bash
ddev mutagen reset
```

## Remove Unused DDEV Docker Images

Old Docker images may accumulate as DDEV updates project environments.

```bash
ddev delete images
```

This removes unused images and they will automatically download again when needed.

## Optional Global Docker Cleanup

These commands clean unused Docker resources across all projects.

```bash
docker system prune
docker image prune -a
docker builder prune
```

Use caution since these commands affect all Docker environments.

---

# Troubleshooting

```bash
# Won't start — full reset
ddev poweroff && ddev start

# Check project status
ddev describe
ddev list

# Check DDEV version
ddev --version

# Run diagnostic checks
ddev debug test
ddev debug dockercheck

# Display Docker disk usage
docker system df
```

---

# Useful Maintenance Commands

```bash
# Rebuild containers
ddev restart --build

# Stop all running projects
ddev poweroff

# Upgrade DDEV
ddev self-upgrade
```
