ARG BASE_IMAGE
FROM $BASE_IMAGE
USER root

# PHP code quality tools — installed globally via Composer so they
# are independent of the project's own composer.json.
# Tools land in /usr/local/composer/vendor/bin/ and are symlinked
# into /usr/local/bin/ for PATH-transparent access.
ENV COMPOSER_HOME=/usr/local/composer
# phpcompatibility/php-compatibility only supports phpcs ^3.x, not ^4.x.
# Remove it from the global install so phpcs and drupal/coder can upgrade to
# 4.x/9.x to match Drupal 11 CI. It is re-installed alongside its own phpcs 3.x
# in /usr/local/phpcompat for use by the phpcompat command.
RUN composer global config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true \
    && composer global remove --no-interaction phpcompatibility/php-compatibility \
    && composer global require --no-interaction \
      squizlabs/php_codesniffer:^4.0 \
      drupal/coder:^9.0 \
      phpstan/phpstan \
      mglaman/phpstan-drupal \
      phpmd/phpmd \
      palantirnet/drupal-rector \
    && ln -sf /usr/local/composer/vendor/bin/phpcs   /usr/local/bin/phpcs \
    && ln -sf /usr/local/composer/vendor/bin/phpcbf  /usr/local/bin/phpcbf \
    && ln -sf /usr/local/composer/vendor/bin/phpstan /usr/local/bin/phpstan \
    && ln -sf /usr/local/composer/vendor/bin/phpmd   /usr/local/bin/phpmd \
    && ln -sf /usr/local/composer/vendor/bin/rector  /usr/local/bin/rector \
    && mkdir -p /usr/local/phpcompat \
    && composer -d /usr/local/phpcompat init --no-interaction --name=internal/phpcompat \
    && composer -d /usr/local/phpcompat config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true \
    && composer -d /usr/local/phpcompat require --no-interaction phpcompatibility/php-compatibility \
    # phpunit — isolated to avoid symfony/* / nikic/php-parser conflicts with
    # phpstan and rector. Version ^11.5 matches drupal/core-dev ^11.x.
    && mkdir -p /usr/local/phpunit \
    && composer -d /usr/local/phpunit init --no-interaction --name=internal/phpunit \
    && composer -d /usr/local/phpunit require --no-interaction phpunit/phpunit:^11.5 \
    && ln -sf /usr/local/phpunit/vendor/bin/phpunit /usr/local/bin/phpunit

# Node.js code quality tools — installed globally via npm.
#
# Versions and packages are aligned with the Drupal GitLab Templates
# package.json (https://www.drupal.org/project/gitlab_templates).
#
# eslint@^8.57.0 — ESLint v8 matches the template; v9 uses a
#   different flat-config format incompatible with Drupal core's
#   .eslintrc.* files.
# eslint-plugin-prettier / eslint-config-prettier / prettier —
#   the template runs Prettier checks inside the eslint job.
# eslint-plugin-yml@^1.19.1 — v2+ dropped ESLint 8 legacy config
#   support (plugin:yml/standard becomes a flat config object with
#   circular references that ESLint 8's schema validator rejects).
# cspell@^8.6.0 — spell-checking job in the template pipeline.
# stylelint-config-standard — base config used by Drupal core and contrib.
RUN npm install -g \
      stylelint \
      stylelint-config-standard \
      stylelint-prettier \
      eslint@^8.57.0 \
      eslint-config-prettier \
      eslint-plugin-prettier \
      eslint-plugin-yml@^1.19.1 \
      prettier \
      @babel/eslint-parser \
      @babel/core \
      cspell@^8.6.0
