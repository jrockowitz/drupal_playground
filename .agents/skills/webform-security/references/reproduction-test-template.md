# Webform security reproduction test template

Replace `{placeholders}`. Keep the assertion that fits the vulnerability and
delete the other examples. The test must fail on vulnerable code and pass
unchanged after the fix.

## Browser test (default)

`tests/src/Functional/{Area}/Webform{Feature}{Behavior}Test.php`

```php
<?php

namespace Drupal\Tests\webform\Functional\{Area};

use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\webform\Entity\Webform;

/**
 * Tests {feature} {access|escaping|validation}.
 *
 * @group webform
 */
class Webform{Feature}{Behavior}Test extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['webform'];

  /**
   * Tests {feature} {behavior}.
   */
  public function test{Behavior}(): void {
    $assert_session = $this->assertSession();

    // {High-level description of the expected secure behavior.}
    $webform = Webform::create([
      'id' => 'test_{feature}',
      'title' => 'Test {feature}',
      'elements' => "{yaml}",
    ]);
    $webform->save();

    $allowed_account = $this->drupalCreateUser(['{permission}']);
    $attacker_account = $this->drupalCreateUser([]);

    /* ********************************************************************** */

    // Check legitimate use is allowed.
    $this->drupalLogin($allowed_account);
    $this->drupalGet('/webform/test_{feature}');
    $assert_session->statusCodeEquals(200);

    // Check {attacker path} is {denied|escaped|ignored}.
    $this->drupalLogin($attacker_account);
    $this->drupalGet('{path}');

    // Access control and data exposure.
    $assert_session->statusCodeEquals(403);

    // XSS and HTML injection.
    $assert_session->responseNotContains('<script>alert("webform-xss")</script>');
    $assert_session->responseContains('&lt;script&gt;alert("webform-xss")&lt;/script&gt;');

    // Redirect and destination handling.
    $this->drupalGet('{path}', [
      'query' => ['destination' => 'https://attacker.invalid/'],
    ]);
    $this->submitForm([], 'Submit');
    $assert_session->addressEquals('{expected internal path}');

    // Unauthorized changes.
    $this->drupalGet('{path}');
    $this->submitForm(['{field}' => '{value}'], '{button}');
    \Drupal::entityTypeManager()->getStorage('webform')->resetCache();
    $this->assertSame('{original}', Webform::load('test_{feature}')->get('{property}'));
  }

}
```

## JavaScript test (only when JavaScript is required)

`tests/src/FunctionalJavascript/{Area}/Webform{Feature}{Behavior}JavaScriptTest.php`

```php
<?php

namespace Drupal\Tests\webform\FunctionalJavascript\{Area};

use Drupal\Tests\webform\FunctionalJavascript\WebformWebDriverTestBase;

/**
 * Tests {feature} {behavior} JavaScript.
 *
 * @group webform_javascript
 */
class Webform{Feature}{Behavior}JavaScriptTest extends WebformWebDriverTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['{test_webform}'];

  /**
   * Tests {feature} {behavior}.
   */
  public function test{Behavior}(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // {High-level description of the expected secure behavior.}
    $this->drupalGet('/webform/{test_webform}');
    $assert_session->waitForElement('css', '{selector}');

    // Check legitimate behavior works.
    $page->{action}();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('css', '{selector}');

    // Check the marker payload is not executed.
    $this->assertFalse((bool) $this->getSession()->evaluateScript(
      'window.webformXssExecuted === true'
    ));
  }

}
```

Use a marker such as `<img src="x" onerror="window.webformXssExecuted = true">`
as the payload so execution can be detected without alerts.

## Checklist

- Fails on the vulnerable branch for the expected reason.
- Passes after the fix without changes.
- Lives in the feature folder; no security ID in names or comments.
- Comments describe secure behavior, not exploit steps.
- Fully exercises the reported path with harmless local fixtures or test
  endpoints. Does not contact live external systems or use secrets.
