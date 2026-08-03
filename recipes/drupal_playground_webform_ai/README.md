# Drupal Playground AI Webform Generator

Installs and configures the
[AI Webform Generator](https://www.drupal.org/project/ai_webform_generator).

## What it provides

AI Webform Generator lets a site builder describe a form in plain English and
uses the site's configured AI provider to create a Webform or update an
existing Webform. This recipe configures the generator but does not create or
modify a Webform.

## Prerequisite

Create `keys/openai.key` in the project root before installation. It must
contain only the raw OpenAI API key, with no trailing newline. The composed AI
recipe imports the Key configuration that reads this file.

## Installation

```bash
ddev install webform-ai
```

Warning: `ddev install` drops and recreates the local Drupal database. Do not
run it when you need to keep the current site's data.

## Defaults

The recipe composes the Drupal Playground AI and Webform setup recipes, then
installs `ai_webform_generator`. Its settings use `openai__gpt-5-nano` with a
temperature of `0.2`, a maximum of `4000` output tokens, and a per-user flood
limit of `20` requests per `3600` seconds.

## Browser review

1. Create a disposable contact Webform at `/admin/structure/webform/add` with
   required Name, Email, Subject, and Message fields.
2. Open `/admin/structure/webform/ai-generator` as the administrator and
   select that Webform.
3. Enter the following prompt exactly: `Add a required telephone field,
   preserving all existing fields.`
4. Select **Generate Webform**.

The AI reports a successful update and redirects to the selected Webform.
Confirm that the original Name, Email, Subject, and Message fields and the
submit actions remain, and that an additional required telephone-number field
exists. The provider may choose a different field key or label, such as
**Phone** or **Phone number**, so review the field type and required setting
instead of matching a literal label.

## Module of the week

**Module of the Week — AI Webform Generator**

**Brief description**

AI Webform Generator enables site builders to create a Drupal Webform, or
update an existing one, from plain-English instructions. It sends the request
through the site's configured Drupal AI provider, validates the returned
Webform definition, and saves the resulting form. Review the generated change
before using the form.

**Module name / project name**

[AI Webform Generator](https://www.drupal.org/project/ai_webform_generator)
(`ai_webform_generator`)

**Brief history**

- Created on 2 July 2026 by `chaitanyadessai` (Chaitanya R Dessai).
- The current stable release is 1.0.2, released on 3 July 2026, and supports
  Drupal `^10 || ^11`.

**Maintainership**

- Appears actively maintained: Drupal.org lists an update on 24 July 2026.
- Maintainers: `zeeshan_khan` and `chaitanyadessai`.
- Security coverage: Yes. Stable releases are covered by Drupal's security
  advisory policy.
- Test coverage: Yes. Version 1.0.2 includes unit, kernel, and functional
  tests for prompt building, JSON validation, settings, route access, Webform
  building, and optional CAPTCHA elements.
- Documentation: Yes. The project page and module README cover requirements,
  configuration, usage, security considerations, and supported field types.
- Issues: 1 open issue, with 0 open bug reports (7 issues total).

**Usage statistics**

1 site reports using this module.

**Module features and usage**

- Creates complete Webforms and updates existing Webforms in place from
  natural-language prompts.
- Supports common Webform elements, including text, email, telephone, number,
  date, select, checkbox, radio, range, password, hidden, and managed-file
  elements.
- Validates the AI response before applying the Webform definition.
- Uses the existing Drupal AI provider configuration; API keys are not stored
  in this module's configuration.
- Provides configurable model, temperature, output-token, and per-user request
  limits to balance output quality and provider spend.
- Requires trusted users with both the generator permission and ordinary
  Webform edit access when changing an existing form.

Project statistics and release details were checked on 3 August 2026; consult
the [Drupal.org project page](https://www.drupal.org/project/ai_webform_generator)
for current information.

## AI-Generated Assessment

- **Technical:** The module separates AI generation, prompt building, JSON
  validation, and Webform construction into Drupal services. It uses the
  site's configured Drupal AI provider, validates a limited allowlist of
  Webform element types before saving, and exposes model, temperature,
  output-token, and per-user request-limit settings.
- **Access and error handling:** Generation requires its own permission, and
  updating an existing Webform also requires normal Webform update access. A
  per-user flood limit constrains provider spend; failures are logged, with
  detailed upstream errors shown only to generator administrators.
- **Code quality:** Version 1.0.2 uses strict types and separates form,
  service, validation, and persistence responsibilities. It includes unit,
  kernel, and functional coverage for core behavior. This assessment is a
  code review of the released module, not a security audit.
- **Implementation:** The module creates new Webforms and updates supported
  fields of existing Webforms in place, but saves the generated definition
  immediately without a preview, diff, or approval screen.
- **Usefulness:** The module is useful for quickly drafting straightforward
  Webforms and iterating on common field changes when a site builder reviews
  the result. Complex, highly customized, or regulated forms need especially
  careful manual review before publication.
- **How to use it:** Configure a chat-capable provider, select an existing
  Webform or choose to create one, describe the fields and validation in plain
  English, submit the request, and then review the saved Webform. For example,
  create a disposable contact Webform and ask the generator to add a required
  telephone field while preserving the existing fields.
- **AI-generated source code:** The module's runtime use of AI and its code
  style cannot establish whether its source was AI-generated or AI-assisted.
  Its public project metadata does not make an authorship claim, so this is
  unknown.
- **Possible improvements:** Add a preview/diff and explicit approval before
  saving; broaden support for advanced Webform structures and handlers; add
  optional, privacy-conscious prompt and response audit logs; and expand
  regression coverage for complex Webform updates.
- **Next steps for adopters:** Restrict generation to trusted roles, begin
  with a low request limit, test representative prompts outside production,
  and review every generated field, validation rule, confirmation message, and
  permission before publishing.

*This README was AI-generated with Codex, based on GPT-5.*
