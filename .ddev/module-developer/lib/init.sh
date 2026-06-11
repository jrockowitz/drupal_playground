#!/usr/bin/env bash
#ddev-generated

# Shared initialization sourced by every command in commands/web/ as the first
# thing it does, before it reads "$@", applies defaults, or parses arguments:
#
#   # shellcheck source=../../module-developer/lib/init.sh
#   source /mnt/ddev_config/module-developer/lib/init.sh
#
# Because this file is sourced with no arguments, "$@" here is the *caller's*
# positional parameters, and the top-level `set --` below rewrites the caller's
# "$@" in place. A command therefore gets host-path normalization for free with a
# single source line, with no per-command boilerplate.
#
# init.sh currently normalizes absolute host-path arguments (see below) and is the
# designated home for any future shared global utilities the commands need.

# Locations of the add-on's bundled files inside the container. Provided as a
# convenience for commands and future helpers; overridable for testing.
: "${DDEV_MODULE_DEVELOPER_DIR:=/mnt/ddev_config/module-developer}"
: "${DDEV_MODULE_DEVELOPER_CONFIG_DIR:=${DDEV_MODULE_DEVELOPER_DIR}/config}"

# Convert absolute host-path arguments into their in-container equivalents.
#
# Commands run with `## HostWorkingDir: true`, which maps the host's current
# working directory into the container, but arguments are passed through verbatim:
# an absolute *host* path (e.g. /Users/me/Sites/project/web/modules/custom/foo)
# arrives inside the container unchanged and does not resolve, because the project
# is mounted at ${DDEV_APPROOT} (/var/www/html).
#
# This rewrites such arguments so callers — IDE external tools, file watchers, AI
# agents — can pass absolute host paths. The host project root is NOT known inside
# the container (DDEV exposes only the container ${DDEV_APPROOT}, never the host
# path), so a precise prefix swap is impossible. Instead this is host-root agnostic:
# it strips leading path components until the remainder resolves under
# ${DDEV_APPROOT}, matching the longest (most specific) resolvable suffix. This
# covers paths anywhere in the project (web/modules/..., top-level recipes/..., etc.).
#
# The rewrite is a no-op for relative arguments, flags, absolute paths that already
# exist in the container, and absolute paths whose tail does not resolve under
# ${DDEV_APPROOT}.
#
# Known limitations:
#   - Glob wildcards: arguments like /abs/path/**/*.css are left untouched because
#     the existence test cannot match a glob; pass those relative to the project root.
#   - Coincidental suffixes: because the match is by suffix, an absolute path that
#     does not exist in the container but whose trailing component happens to equal a
#     top-level project entry (e.g. /unrelated/web matches the docroot "web") is
#     rewritten to that entry rather than passed through. Longest-match minimizes
#     this, but for paths that are not inside the project, pass them relative or cd
#     to them instead of passing an unrelated absolute path.
#
# The rewritten argument list is returned in the
# DDEV_MODULE_DEVELOPER_NORMALIZED_ARGS array, which is unset after use below so it
# does not linger in the command's shell. (DDEV_MODULE_DEVELOPER_DIR/CONFIG_DIR are
# plain, unexported shell variables, so they are not inherited by the tool process.)
ddev_module_developer_normalize_host_paths() {
  DDEV_MODULE_DEVELOPER_NORMALIZED_ARGS=()

  local arg rel
  for arg in "$@"; do
    if [ "${arg#/}" != "${arg}" ] && [ ! -e "${arg}" ]; then
      rel="${arg}"
      while [ "${rel#*/}" != "${rel}" ]; do
        rel="${rel#*/}"
        if [ -e "${DDEV_APPROOT}/${rel}" ]; then
          arg="${DDEV_APPROOT}/${rel}"
          break
        fi
      done
    fi

    DDEV_MODULE_DEVELOPER_NORMALIZED_ARGS+=("${arg}")
  done
}

# Normalize the caller's positional arguments in place. Sourced with no arguments,
# so "$@" is the command's own argument list.
ddev_module_developer_normalize_host_paths "$@"
set -- "${DDEV_MODULE_DEVELOPER_NORMALIZED_ARGS[@]}"
unset DDEV_MODULE_DEVELOPER_NORMALIZED_ARGS
