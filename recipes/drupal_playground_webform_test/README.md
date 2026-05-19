# Drupal Playground Webform Test

Provides the dependency layer for the local Drupal Playground Webform test
preset.

The `ddev install webform-test` command applies the Webform setup recipe first
and then enables the test modules directly with Drush. That sequence matches
the behavior that works reliably with Webform's test extensions in this local
development environment.

## Installation

```shell
ddev install webform-test
```
