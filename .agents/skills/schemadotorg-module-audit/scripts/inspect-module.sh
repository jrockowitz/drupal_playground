#!/usr/bin/env bash

set -eu

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 <base|submodule>" >&2
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

if [ ! -d "$target" ] || [ ! -f "$info_file" ]; then
  echo "Module not found: $module_name" >&2
  exit 66
fi

find_files() {
  pattern=$1
  if [ "$module_name" = schemadotorg ]; then
    find "$target" -path "$target/modules" -prune -o -type f -name "$pattern" -print
  else
    find "$target" -type f -name "$pattern"
  fi
}

count_files() {
  find_files "$1" | wc -l | tr -d ' '
}

has_file() {
  pattern=$1
  if find_files "$pattern" | grep -q .; then
    echo yes
  else
    echo no
  fi
}

has_config() {
  if [ -d "$target/config" ] && find "$target/config" -type f -name '*.yml' | grep -q .; then
    echo yes
  else
    echo no
  fi
}

echo "project_root: $project_root"
echo "checkout: $checkout"
echo "branch: $(git -C "$checkout" branch --show-current)"
echo "checkout_status:"
git -C "$checkout" status --short
echo "module: $module_name"
echo "target: $target"
echo "info_file: $info_file"
echo "readme: $(has_file README.md)"
echo "php_files: $(count_files '*.php')"
echo "test_php_files: $(find "$target/tests" -type f -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "routes: $(has_file '*.routing.yml')"
echo "permissions: $(has_file '*.permissions.yml')"
echo "configuration: $(has_config)"
echo "config_schema: $(has_file '*.schema.yml')"
echo "libraries: $(has_file '*.libraries.yml')"
echo "metadata:"
sed -n '1,100p' "$info_file"
