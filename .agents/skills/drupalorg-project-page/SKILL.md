---
name: drupalorg-project-page
description: Generate or improve Drupal.org project page HTML markup using the bluecheese theme's CSS classes and established page conventions.
---

# Drupal.org Project Page

Generate or improve Drupal.org project page HTML markup using the bluecheese theme's CSS component vocabulary and established page conventions.

## When to Use

Use this skill when the user:

- Says "create a project page for my module"
- Says "write my drupal.org page" or "write a drupal.org project page"
- Says "improve this project page markup" or "update my drupal.org page"
- Shares existing project page HTML and asks for improvements
- Wants to add or restructure sections of a Drupal.org project page

## Bluecheese CSS Component Reference

Drupal.org uses the "bluecheese" theme. These are the CSS classes available for project page body content:

| Component | Class / Pattern | Notes |
|---|---|---|
| Primary CTA button | `<a class="action-button">` | Use for main calls to action |
| Secondary button | `<a class="secondary-button">` | Subtler CTA; prefer over `.action-button` for non-primary CTAs in new pages |
| Inline link button | `<a class="link-button">` | Inline link styled as a button |
| Info callout | `<div class="note">` | General info box; also used for maintainer cards and screenshot wrappers |
| Version callout | `<div class="note-version">` | Version-specific notes, changelog entries |
| Warning callout (legacy) | `<div class="note-warning">` | Deprecated — use `.help` in new pages |
| Help callout | `<div class="help">` | Replaces `.note-warning` in new templates |
| Status: deprecated | `<div class="deprecated">` | Marks a page or section as deprecated |
| Status: incomplete | `<div class="incomplete">` | Marks content as incomplete |
| Status: out of date | `<div class="out-of-date">` | Marks content as out of date |
| Code block | `<div class="codeblock">` | Inline or block code display |
| Grid/table layout | `<table class="views-view-grid" width="100%">` | Multi-column layouts; use for feature grids, support cards, sponsor tiers |
| Floated maintainer card | `<table class="views-view-grid" width="160" align="left">` + `<br clear="both"/>` | Float a narrow card left; always follow with `<br clear="both"/>` |
| Centered block | `<p align="center">` or `<div align="center">` | Centers action buttons and sponsor logos |

## Page Structure

Build sections in this order. All sections are optional except the intro paragraph.

### 1. Intro Paragraph + Optional Video Button

```html
<p>[SHORT_DESCRIPTION]. [EXPANDING_DETAIL].</p>
<br/>
<p align="center"><a class="action-button" href="https://youtu.be/[VIDEO_ID]">&#9654; Watch an introduction to [MODULE_NAME]</a></p>
```

### 2. Screenshot Gallery (2-column)

```html
<table class="views-view-grid" width="100%">
  <tr>
    <td width="50%"><div class="note"><a href="/files/[URL]" class="colorbox" data-colorbox-gallery="gallery-node-[NODE_ID]" rel="nofollow"><img src="/files/[URL]" alt="[ALT]" /><br/><strong>[CAPTION]</strong></a></div></td>
    <td width="50%"><div class="note"><a href="/files/[URL]" class="colorbox" data-colorbox-gallery="gallery-node-[NODE_ID]" rel="nofollow"><img src="/files/[URL]" alt="[ALT]" /><br/><strong>[CAPTION]</strong></a></div></td>
  </tr>
</table>
```

### 3. Features

```html
<h2>Features</h2>
<blockquote>[ONE_SENTENCE_SUMMARY].</blockquote>
<table class="views-view-grid" width="100%">
  <tr>
    <td width="50%">
      <strong>[CATEGORY]</strong>
      <ul>
        <li>[FEATURE]</li>
      </ul>
    </td>
    <td width="50%">
      <strong>[CATEGORY]</strong>
      <ul>
        <li>[FEATURE]</li>
      </ul>
    </td>
  </tr>
</table>
```

Use a 4-column grid (4× `<td width="25%">`) when there are more than two feature categories.

### 4. Getting Involved / Support Options (3-column `.note` cards)

```html
<h2>Getting involved and support options</h2>
<table class="views-view-grid">
  <tr>
    <td width="33%">
      <div class="note">
        <h3>Get involved</h3>
        <p>...</p>
        <p align="center"><a class="action-button" href="https://www.drupal.org/contribute">Contribute</a></p>
        <p><em>Free for all</em></p>
      </div>
    </td>
    <td width="33%">
      <div class="note">
        <h3>Fund development</h3>
        <p>...</p>
        <p align="center"><a class="action-button" href="https://opencollective.com/[SLUG]">Fund</a></p>
        <p><em>Starting at $[AMOUNT] a month</em></p>
      </div>
    </td>
    <td width="33%">
      <div class="note">
        <h3>Professional support</h3>
        <p>...</p>
        <p align="center"><a class="action-button" href="[URL]">Contact</a></p>
        <p><em>[PRICING]</em></p>
      </div>
    </td>
  </tr>
</table>
```

### 5. Getting the Most Out of the Module (h3 subsections)

Use `<h3>` headings with `<hr/>` separators between them. Common subsections:

- Discovering the module (getting started, docs, demo)
- Finding help (issue queue, Drupal Answers, Slack)
- Getting involved (contributing, open source)
- Funding ongoing development (Open Collective)
- Professional support (contact info)

Each subsection should end with a centered `.action-button` and then `<hr/>`.

### 6. About / Version History

```html
<h2>About the [MODULE_NAME] module</h2>
<div class="note-version">
  <h4>About [MODULE_NAME] for Drupal [VERSION]</h4>
  <p>...</p>
</div>
<div class="help">
  <h4>[UPGRADE/MIGRATION HEADING]</h4>
  <p>...</p>
</div>
```

### 7. Meet the Project Maintainers

Each maintainer gets a floated `.note` card. After each group (e.g. D10 maintainers, D7 maintainers), add `<br clear="both"/>`.

```html
<h2>Meet the Project Maintainers</h2>
<h4 class="clearfix">[MODULE_NAME] for Drupal [VERSION]</h4>
<table class="views-view-grid" width="160" align="left"><tr><td>
  <div class="note">
    <div><a href="/u/[USERNAME]"><img src="/files/styles/drupalorg_user_picture/public/user-pictures/[PICTURE_FILENAME]" width="160" alt="[FULL_NAME]"/></a></div>
    <div><strong>[FULL_NAME]</strong> (<a href="/u/[USERNAME]">[USERNAME]</a>)</div>
    <p>[BIO]</p>
  </div>
</td></tr></table>
<br clear="both"/>
```

### 8. Sponsors Section

```html
<hr/>
<div align="center">
  <h2>Thank you to the [MODULE_NAME] module's<br/>Open Collective Sponsors</h2>
  <h3>Impact Sponsors</h3>
  <table class="views-view-grid" width="25%"><tr>
    <td align="center">
      <img src="[LOGO_URL]" alt="[NAME]" />
      <div align="center"><a href="[URL]">[NAME]</a></div>
    </td>
  </tr></table>
  <a href="[OPENCOLLECTIVE_TIER_URL]" class="action-button">Become an Impact Sponsor</a>
  ...
</div>
<br clear="both"/>
```

## Information to Gather Before Generating

Ask the user for the following before generating the page:

1. **Module/project name** (human-readable) and **machine name** (e.g. `webform`)
2. **One-sentence description** of what the module does
3. **Key features**, grouped by category if there are many
4. **Screenshot URLs** (optional; `/files/...` paths on drupal.org, or full URLs)
5. **Maintainer username(s)** and profile picture filenames (from `/u/[username]`)
6. **Support channels**: issue queue (auto-generated from machine name), Slack channel name, Stack Exchange tags
7. **Funding/sponsorship links**: Open Collective slug, tier slugs
8. **Professional support contact URL** (if applicable)
9. **Intro video YouTube ID** (optional)

If the user provides existing markup to improve, extract what you can and ask only about what is missing.

## Tips and Common Mistakes

- **Always use `<br/>`** (XHTML self-closing), never `<br>`. The Drupal.org body field is XHTML-filtered.
- **Floated tables must always be followed by `<br clear="both"/>`.** Without it, subsequent content will wrap around the float in unexpected ways.
- **Use `align="center"` on `<p>` or `<div>`** wrapping action buttons — do not use CSS `text-align` inline styles.
- **This is a body snippet, not a full HTML document.** Never include `<html>`, `<head>`, or `<body>` tags.
- **Prefer `.secondary-button` over `.action-button`** for non-primary CTAs in new pages (e.g. "Learn more" links alongside a primary "Download" button).
- **`.note-warning` is legacy** — use `.help` in new templates.
- **`data-colorbox-gallery` attribute** on screenshot links requires the node ID of the project page. If the page doesn't exist yet, use a placeholder and note that the user must update it after publishing.
- **Do not add `<html>`, `<head>`, or `<body>` tags** — content is pasted into a body field inside the bluecheese theme.
- **The template file** at `.claude/skills/drupalorg-project-page/assets/project-page-template.html` (in this project) is a style guide showing each component with a working example.
