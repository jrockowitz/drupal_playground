# DDEV-SETUP.md: Installing DDEV for Drupal on macOS

## Prerequisites

### 1. Install a Docker Provider

DDEV requires a Docker provider. **[OrbStack](https://orbstack.dev/)** is the recommended provider for macOS — easiest to install, most performant, and very popular with DDEV users. Note that OrbStack is not open-source and is not free for professional use.

```bash
brew install orbstack
```

Or [download OrbStack directly](https://orbstack.dev/download).

After installing, launch OrbStack from your Applications folder. If migrating from Docker Desktop, OrbStack will offer to migrate your existing containers, volumes, and images automatically.

**Verify Docker is running:**

```bash
docker ps
```

> **Alternatives:** For a free, open-source Docker provider, [Lima](https://lima-vm.io/) and [Colima](https://github.com/abiosoft/colima) are also supported. See the [DDEV Docker Installation docs](https://docs.ddev.com/en/stable/users/install/docker-installation/#macos) for all macOS options.

### 2. Install DDEV

```bash
brew install ddev/ddev/ddev
```

**Verify installation:**

```bash
ddev --version
```

### 3. Updating DDEV

```bash
# Update DDEV to the latest version
brew upgrade ddev

# Download updated container images
ddev utility download-images

# Restart any running projects
ddev restart
```

---

## Creating a New Drupal Project

```bash
# Create and enter project directory
mkdir drupal_playground && cd drupal_playground

# Configure DDEV for Drupal
ddev config --project-type=drupal --docroot=web --php-version=8.3

# Start the DDEV environment
ddev start

# Create a Drupal project via Composer (latest Drupal 11)
ddev composer create-project "drupal/recommended-project:^11"

# Or pin a specific minor version:
# ddev composer create-project "drupal/recommended-project:~11.2.0"

# Install Drush
ddev composer require drush/drush

# Install Drupal
ddev drush site:install --account-name=admin --account-pass=admin -y

# Launch the site in your browser
ddev launch
```

---

## Resources

- **DDEV Official Docs:** https://ddev.readthedocs.io
- **DDEV Quickstart:** https://docs.ddev.com/en/latest/users/quickstart/
- **DDEV Docker Providers (macOS):** https://docs.ddev.com/en/stable/users/install/docker-installation/#macos
- **OrbStack:** https://orbstack.dev/
