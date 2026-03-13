Spec Guidelines: Lessons from telephone_filter Implementation

Context

After generating the telephone_filter module from the spec, several corrections were
required before the code passed PHPCS, PHPStan (level 5), and unit tests. Each
correction traces to a gap or inaccuracy in the spec. The following guidelines capture
what the spec should have stated — or stated more precisely — so that a future agent can
transcribe spec code to passing code in one shot.

 ---
Issues Encountered & Proposed Guidelines

1. Method signatures when overriding untyped interface methods

What happened: The spec showed process(string $text, string $langcode) and
tips(bool $long = FALSE): TranslatableMarkup. PHP enforces LSP on method signatures:
you cannot narrow parameter types when the parent/interface declares none. Both methods
caused fatal errors at class load time.

Corrections applied:
- process($text, $langcode): FilterProcessResult — parameters untyped to match
  FilterInterface::process($text, $langcode)
- tips($long = FALSE): string — parameter untyped; return type changed from
  TranslatableMarkup to string, with a (string) cast on the return value

Guideline to add:

PHP method signature compatibility. When a method overrides one declared in a
parent class or interface without type hints, the child implementation must not
add parameter type hints. Return types may be narrowed (covariant). Always verify the
parent/interface signature before writing the override signature.

For FilterBase / FilterInterface, the following methods are declared without
parameter types and must be overridden without them:
- process($text, $langcode) — return type FilterProcessResult is fine
- tips($long = FALSE) — return type string is fine; cast TranslatableMarkup
  with (string)
- prepare($text, $langcode) — return type string is fine

 ---
2. Calling parent::submitConfigurationForm() — method does not exist

What happened: The spec said "call parent first, then overwrite area_codes".
FilterBase extends PluginBase, neither of which implements
submitConfigurationForm(). Calling parent::submitConfigurationForm() threw a
fatal Call to undefined method error.

Correction applied: Removed the parent call. The method reads the raw textarea
value and sets $this->settings['area_codes'] directly — no parent delegation needed.

Guideline to add:

Verify parent method existence before parent:: calls. Only call
parent::methodName() if you have confirmed the parent class actually defines that
method. For filter plugins, FilterBase provides defaultConfiguration(),
setConfiguration(), getConfiguration(), settingsForm(), prepare(), and
tips() — nothing else. validateConfigurationForm() and
submitConfigurationForm() are not defined in FilterBase; implementations should
not call parent:: for these.

 ---
3. Hook function return types — match what the code actually returns

What happened: telephone_filter_help() was typed TranslatableMarkup|string.
The match expression returns either (string) t(...) (a string) or '' (a
string). PHPStan level 5 flagged TranslatableMarkup as never returned and reported
an error.

Correction applied: Return type changed to string.

Guideline to add:

Hook return types must reflect actual return values. When t() output is cast
with (string), the return type is string, not TranslatableMarkup. Do not
include union members that are never actually returned — PHPStan level 5 flags them
as errors.

 ---
4. Unit tests that call $this->t() need a string translation stub

What happened: validateConfigurationForm() calls $this->t() to build the error
message. In unit tests the Drupal container is not initialized, so $this->t() throws
ContainerNotInitializedException. The spec's createFilter() helper did not set up
string translation.

Correction applied: Added $filter->setStringTranslation($this->getStringTranslationStub()) to
createFilter() in TelephoneFilterValidationTest.

Guideline to add:

Unit tests for filter plugins that call $this->t(). Any test that exercises a
code path calling $this->t() must call
$filter->setStringTranslation($this->getStringTranslationStub()) immediately
after instantiating the filter. UnitTestCase provides getStringTranslationStub()
for this purpose. Without it, any validation or error-message path will throw
ContainerNotInitializedException.

Update createFilter() helpers accordingly:
private function createFilter(): TelephoneFilter {
$filter = new TelephoneFilter(...);
$filter->setStringTranslation($this->getStringTranslationStub());
return $filter;
}

 ---
5. PHPUnit test environment — phpunit.xml and drupal/core-dev

What happened: The project had no phpunit.xml and vendor/bin/phpunit did not
exist (no dev dependencies installed). The spec listed the test run command but gave no
setup instructions.

Corrections applied:
- Copied web/core/phpunit.xml.dist to phpunit.xml at the project root; set
  SIMPLETEST_DB, SIMPLETEST_BASE_URL, and fixed the bootstrap path to
  web/core/tests/bootstrap.php
- Ran ddev composer require --dev "drupal/core-dev:^11" "phpunit/phpunit:^11.5.50" --with-all-dependencies

Guideline to add:

Test environment prerequisites. Before the unit test command will work, the
following must be in place:

1. phpunit.xml at the project root — copy from web/core/phpunit.xml.dist and set:
- bootstrap="web/core/tests/bootstrap.php"
- SIMPLETEST_DB=mysql://db:db@db/db
- SIMPLETEST_BASE_URL=https://drupal-playground.ddev.site
2. drupal/core-dev at the matching Drupal major version as a dev dependency:
   ddev composer require --dev "drupal/core-dev:^11" --with-all-dependencies

Include these steps in the module's Verification section.

 ---
6. PHPCS docblock rules — @return with PHPStan generic types

What happened: Drupal's PHPCS sniff (Drupal.Commenting.FunctionComment) does not
understand PHPStan-style generic types. @return array<string, array{string, string}>
was flagged as "Description for the @return value must be on the next line" — the sniff
interpreted <string, array{string, string}> as an inline description.

Correction applied: Replaced all complex generic return types in data-provider
docblocks with @return array followed by a short description on the next line.

Guideline to add:

@return tags — no PHPStan generics. Drupal's PHPCS sniff does not parse
generic type syntax. Use plain @return array (or @return string, etc.) for
return types in docblocks. If the data shape matters for documentation, describe it
in the doc comment body, not in the @return type expression. PHPStan can infer
generics from code; the docblock only needs to satisfy PHPCS.

 ---
7. PHPCS docblock rules — @param must precede @dataProvider / @covers

What happened: Drupal's Drupal.Commenting.DocComment sniff requires @param
tags to appear before annotations like @dataProvider and @covers. The spec placed
the annotations first, which produced "Parameter tags must be defined first in a doc
comment" errors on multiple test methods.

Correction applied: Moved all @param blocks above @dataProvider and @covers
in TelephoneFilterValidationTest.

Guideline to add:

Docblock tag order. Drupal PHPCS requires this ordering in method docblocks:
1. Long description (if any)
2. @param tags
3. @return tag
4. @throws tags
5. Annotation tags: @dataProvider, @covers, @group, etc.

Never place @param after @dataProvider or @covers.

 ---
8. PHPCS inline comment rules — one space after //

What happened: The spec's regex pattern explanation used alignment spaces inside
comments (//   \(?   — description). Drupal PHPCS requires exactly one space after
// in inline comments; extra leading spaces inside the comment text trigger
"Comment indentation error".

Correction applied: Rewrote the pattern explanation as single-space inline
comments (// Pattern: ...).

Guideline to add:

Inline comment style. Drupal PHPCS requires exactly one space between // and
the comment text. Do not use extra spaces for visual alignment inside inline
comments. If a multi-column explanation is needed, use a /* ... */ block comment
or restructure as prose sentences.

 ---
9. Line length — 80-character limit applies to comments too

What happened: Several comment lines and one description string in settingsForm()
exceeded 80 characters, producing PHPCS line-length warnings (which are reported as
errors in strict mode).

Guideline to add:

80-character line limit. Every line — code, comment, string — must stay within
80 characters. Long strings in form element '#description' keys should be split
across concatenation if needed. Long inline comments should be wrapped to the next
line.

 ---
10. strtr() key-value map — one entry per line

What happened: The spec's vanityToDigits() used a compact inline format for
the keypad map ('A' => '2', 'B' => '2', 'C' => '2', on one line). PHPCS's
array-formatting sniff required each key-value pair on its own line in a multi-line
array. phpcbf auto-fixed this, but the spec code was misleading.

Guideline to add:

Multi-line array formatting. In any array that spans multiple lines, every
element must be on its own line — including compact lookup tables. The Drupal PHPCS
sniff does not allow grouping multiple 'key' => 'value' pairs on a single line
within a multi-line array literal.
