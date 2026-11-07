# Steps to Review

Use this guidance when drafting or updating manual review steps for a Drupal.org
issue, including in local notes, GitLab, and maintainer comment drafts. Draft
steps for a human reviewer; do not claim that a step passed or failed unless the
result was observed or explicitly reported.

## Drafting

- For a new section, use a `Steps to review` heading and a numbered list.
- Write one action and its observable expected result per step. Identify the
  relevant page, control, content, or message; avoid vague results such as
  “works as expected.”
- Order steps as the reviewer will perform them. Include only prerequisites and
  actions needed to review the issue; assume the reviewer knows how to set up
  the module.
- For a bug fix, include reproduction before the fix and verification afterward
  when the behavior and steps are known and reproduction is practical and
  safe. Otherwise, state the known prerequisites and verify the fixed behavior.
- Reflect the issue's requirements and evidence. Do not invent behavior, paths,
  messages, or test data. Name a page or navigation route if its path is unknown.
- Use known site-relative paths, such as `/admin/structure/types`, instead of
  absolute site URLs. Do not guess unknown paths.
- Omit status markers and requirement IDs unless the existing issue uses them or
  the user requests them.

## Updating existing steps

Edit an existing section in place. Preserve its structure and useful checks
where practical, and preserve unrelated issue content. If the section boundaries
are ambiguous, ask before replacing content.

## Destination format

Draft in Markdown for local review and GitLab. Convert the steps to HTML when
preparing a Drupal.org ticket update. Preserve unrelated description content
and use the destination's supported markup. Obtain the maintainer's approval
before submitting any public update, as required by the parent skill.

## Example

```markdown
## Steps to review

1. Visit `/admin/structure/types` and select the relevant content type; confirm
   that its configuration page opens.
2. Change the setting described in the issue and save; confirm that the saved
   value appears when the page is reopened.
```

Use this example only as a format illustration; derive actual steps and
expected results from the issue.
