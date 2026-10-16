# GitLab Webform Security Queue

Browser snapshot: {YYYY-MM-DD}. The open Project-webform search returned {total}
work items: {active} in this active tracker and {excluded} excluded D7ES backports.
{Summary of security status counts.} Local priority is provisional, not an official
risk assessment.

Work oldest first: begin with [{security-id}]({security-id}.md), whose next action is
{next action}. Continue down the queue by creation date. Interrupt the order only for a
`Needs …` or `Needs attention` label, a newly validated high-impact issue, or a
`Security risk::…` label.

{Note which response, risk, or advisory labels appear in this snapshot, if any.}

## Category overview

Categories describe reported failure types and remain provisional until reproduction.
They organize triage; they do not determine how many advisories are needed.

| Primary category | Issues |
| --- | ---: |
| XSS and HTML injection | {n} |
| Access control and data exposure | {n} |
| Unauthorized changes | {n} |
| File inclusion and path access | {n} |
| Outbound requests and SSRF | {n} |
| Unsafe template or configuration execution | {n} |
| Redirect and destination handling | {n} |

Candidate advisory groups: {unassigned, or groups with linked issues}.

## Queue

Ordered by GitLab creation time, oldest to newest.

| Order | Created | Issue | Reported area | Primary category | GitLab status | Local priority | Next action |
| ---: | --- | --- | --- | --- | --- | --- | --- |
| 1 | {YYYY-MM-DD} | [{security-id}]({security-id}.md) | {area} | {category} | {status} | {P1–P3} | {next action} |

## Existing local work

- [{security-id}]({security-id}.md): {Branches, commits, MRs, and pending reviews.}

## Queue scope and sources

- Excluded: {security-ids} (`webform: [D7ES] Backport of …`), per maintainer
  instruction.
- Closed: {security-ids with closed date; notes retained}.
- [Open Project-webform queue, oldest first](https://git.drupalcode.org/search?group_id=183118&scope=issues&search=%22Project-webform%22&sort=created_asc&state=opened)
  and [security label definitions](https://git.drupalcode.org/groups/security/-/labels).
- Open each issue note for its exact GitLab label names, response blocker,
  confidence, and private source link.
