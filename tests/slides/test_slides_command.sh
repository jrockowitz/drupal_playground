#!/bin/bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
command_path="${project_root}/.ddev/commands/host/slides"
deck_path="${project_root}/slides/vibing-webform/index.html"
package_json_path="${project_root}/package.json"
tmpdir="$(mktemp -d)"
trap 'rm -rf "${tmpdir}"' EXIT

assert_contains() {
  local file_path="$1"
  local expected_text="$2"

  if ! grep -Fq "$expected_text" "$file_path"; then
    echo "Expected to find '${expected_text}' in ${file_path}" >&2
    exit 1
  fi
}

assert_file_exists() {
  local file_path="$1"

  if [ ! -f "$file_path" ]; then
    echo "Expected file to exist: ${file_path}" >&2
    exit 1
  fi
}

echo "Check that the slides command exists."
assert_file_exists "${command_path}"

echo "Check that the webform deck entrypoint exists."
assert_file_exists "${deck_path}"

echo "Check that the root npm package exists."
assert_file_exists "${package_json_path}"

echo "Check that the slides command requires a directory argument."
if bash "${command_path}" >"${tmpdir}/missing.stdout" 2>"${tmpdir}/missing.stderr"; then
  echo "Expected slides command without arguments to fail." >&2
  exit 1
fi
assert_contains "${tmpdir}/missing.stderr" "Usage: ddev slides <directory>"

echo "Check that the slides command rejects unknown directories."
if bash "${command_path}" unknown-deck >"${tmpdir}/unknown.stdout" 2>"${tmpdir}/unknown.stderr"; then
  echo "Expected slides command with an unknown directory to fail." >&2
  exit 1
fi
assert_contains "${tmpdir}/unknown.stderr" "Unknown slides directory: unknown-deck"

echo "Check that the slides command reports missing reveal.js dependencies."
if SLIDES_FORCE_MISSING_REVEAL=1 bash "${command_path}" vibing-webform >"${tmpdir}/reveal.stdout" 2>"${tmpdir}/reveal.stderr"; then
  echo "Expected slides command with missing reveal.js dependencies to fail." >&2
  exit 1
fi
assert_contains "${tmpdir}/reveal.stderr" "Missing npm dependency: reveal.js"
assert_contains "${tmpdir}/reveal.stderr" "npm install"

echo "Check that the slides command resolves the vibing-webform deck."
if ! SLIDES_TEST_MODE=1 bash "${command_path}" vibing-webform >"${tmpdir}/deck.stdout" 2>"${tmpdir}/deck.stderr"; then
  cat "${tmpdir}/deck.stderr" >&2
  echo "Expected slides command in test mode to succeed." >&2
  exit 1
fi
assert_contains "${tmpdir}/deck.stdout" "Deck directory:"
assert_contains "${tmpdir}/deck.stdout" "slides/vibing-webform"
assert_contains "${tmpdir}/deck.stdout" "__slides_vendor__/reveal.js"

echo "Check that the deck references the shared reveal.js alias."
assert_contains "${deck_path}" "/__slides_vendor__/reveal.js/dist/reveal.css"
assert_contains "${deck_path}" "/__slides_vendor__/reveal.js/dist/reveal.js"

echo "Check that the first slide exposes the PDF export link."
assert_contains "${deck_path}" "?print-pdf"
assert_contains "${deck_path}" "Download PDF"
assert_contains "${deck_path}" "target=\"_blank\""
assert_contains "${deck_path}" "pdf-export-link"
assert_contains "${deck_path}" "window.print()"
assert_contains "${deck_path}" "pdf-export-view"

echo "Slides command test passed."
