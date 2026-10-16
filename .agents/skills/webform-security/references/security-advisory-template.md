# Webform security advisory draft

For private issues labeled `Security advisory::needed`. Start from the
issue's drupalbot draft link; one advisory per vulnerability, per
[Drupal's guide][guide]. The live form wins if fields differ.

## Fields

- **Project:** Webform (confirm the auto-filled reference).
- **Security risk:** Use the form's calculator after validating impact and
  prerequisites. Local priority is not the official score. A proof of
  concept committed only to the private security fork is not public and
  does not by itself justify `E:Proof`.
- **Vulnerability:** `[Brief, high-level type]`
- **Description:**

  The Webform module enables site builders to create forms and collect
  submissions.

  `[Affected feature and impact, high level.]`

  `[Confirmed mitigating conditions, if any.]`

- **Solution:**

  Install the latest applicable security release:

  - Webform 6.3.x: `[fixed release or "not affected"]`
  - Webform 6.2.x: `[fixed release or "not affected"]`

  `[Confirmed workaround, or omit.]`

- **Reported by:** `[Original reporter]`
- **Fixed by:** `[Authors, reviewers, testers of the fix]`
- **Coordinated by:** `[Security team members who guided triage, review,
  or release]`

Use verified Drupal.org usernames, not display names, for all credits.
Exclude bots and system notes. If a person has two plausible usernames,
list both with a note rather than guessing.

Click the form's Update buttons to generate credit markup, then save before
editing contribution fields. Leave affected versions, fixed-in releases,
and CVE ID to the security team. Mark unknowns visibly; never guess
versions, risk, or credits.

[guide]: https://www.drupal.org/node/3627681
