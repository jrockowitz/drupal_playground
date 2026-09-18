#!/usr/bin/env bash

set -eu

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 <base|schemadotorg_submodule|submodule>" >&2
  exit 64
fi

requested_module=${1#schemadotorg_}
case "$requested_module" in
  base)
    module_name=schemadotorg
    ;;
  *[!a-z0-9_]*|'')
    echo "Invalid module name: $1" >&2
    exit 64
    ;;
  *)
    module_name="schemadotorg_${requested_module}"
    ;;
esac

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_root=$(CDPATH= cd -- "$script_dir/../../../.." && pwd)
checkout="$project_root/web/modules/sandbox/schemadotorg"

if [ "$module_name" = schemadotorg ]; then
  target="$checkout"
  info_file="$checkout/schemadotorg.info.yml"
else
  target="$checkout/modules/$module_name"
  info_file="$target/$module_name.info.yml"
fi

if ! command -v rg >/dev/null 2>&1; then
  echo "rg is required." >&2
  exit 69
fi

if [ ! -d "$checkout" ]; then
  echo "Schema.org checkout not found: $checkout" >&2
  exit 66
fi

if [ ! -d "$target" ] || [ ! -f "$info_file" ]; then
  echo "Module not found: $module_name" >&2
  exit 66
fi

search_source_files() {
  pattern=$1
  find "$target" -maxdepth 1 -type f \
    \( -name '*.module' -o -name '*.inc' -o -name '*.install' \) \
    -exec rg -n "$pattern" {} + 2>/dev/null || true
}

search_hook_classes() {
  pattern=$1
  if [ -d "$target/src/Hook" ]; then
    rg -n "$pattern" "$target/src/Hook" -g '*.php' || true
  fi
}

search_module_code() {
  pattern=$1
  find "$target" -maxdepth 1 -type f \
    \( -name '*.php' -o -name '*.module' -o -name '*.inc' \
       -o -name '*.install' -o -name '*.yml' \) \
    ! -name '*.api.php' \
    -exec rg -n "$pattern" {} + 2>/dev/null || true
  if [ -d "$target/src" ]; then
    rg -n "$pattern" "$target/src" -g '*.php' || true
  fi
}

echo "project_root: $project_root"
echo "project_status:"
git -C "$project_root" status --short
echo "checkout: $checkout"
echo "branch: $(git -C "$checkout" branch --show-current)"
if upstream=$(git -C "$checkout" rev-parse --abbrev-ref --symbolic-full-name '@{upstream}' 2>/dev/null); then
  counts=$(git -C "$checkout" rev-list --left-right --count '@{upstream}...HEAD')
  read -r behind ahead <<< "$counts"
  echo "upstream: $upstream"
  echo "upstream_behind: $behind"
  echo "upstream_ahead: $ahead"
else
  echo "upstream: (none)"
fi
echo "checkout_status:"
git -C "$checkout" status --short
echo "module: $module_name"
echo "target: $target"
echo "info_file: $info_file"

echo "procedural_hooks_callbacks_and_helpers:"
search_source_files '^function[[:space:]]+[A-Za-z0-9_]+'

echo "callback_registrations:"
search_module_code '#(ajax|after_build|element_validate|process|submit|validate)|allowed_values_function|value_callback'

echo "install_update_functions:"
find "$target" -maxdepth 1 -type f -name '*.install' \
  -exec rg -n '^function[[:space:]]+[A-Za-z0-9_]+' {} + 2>/dev/null || true

echo "oop_hooks:"
search_hook_classes '#\[(Hook|ReorderHook|RemoveHook)'

echo "legacy_wrappers:"
search_source_files '#\[LegacyHook\]'

echo "static_drupal_lookups_in_hook_classes:"
search_hook_classes '\\Drupal::'

echo "ordering:"
search_source_files 'module_implements_alter|LegacyModuleImplementsAlter'
search_hook_classes 'order:|ReorderHook'

echo "hook_service_registrations:"
find "$target" -maxdepth 1 -type f -name '*.services.yml' \
  -exec rg -n 'Hook\\|skip_procedural_hook_scan' {} + 2>/dev/null || true

echo "service_and_interface_references:"
search_module_code '\\Drupal::|[A-Za-z_][A-Za-z0-9_]*Interface|#\[Autowire'

echo "static_calls_to_trace_for_hidden_dependencies:"
search_module_code '[A-Z][A-Za-z0-9_]*::[A-Za-z_][A-Za-z0-9_]*\('

echo "interfaces:"
if [ -d "$target/src" ]; then
  find "$target/src" -type f -name '*Interface.php' -print | sort
fi

echo "tests:"
if [ -d "$target/tests/src" ]; then
  find "$target/tests/src" -type f -name '*Test.php' -print | sort
fi

echo "procedural_scan_configuration:"
find "$target" -maxdepth 1 -type f -name '*.services.yml' \
  -exec rg -n 'skip_procedural_hook_scan' {} + 2>/dev/null || true
