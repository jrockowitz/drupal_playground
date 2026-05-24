# Vibing Webform

## Local Preview

Run the slide deck locally with:

```bash
npm install
ddev slides vibing-webform
```

For the live Drupal demo links in the presentation, prepare the site with:

```bash
ddev install webform-setup
```

## Part 1: Vibing Webform as a Module (20 min)

---

### Slide: Vibing with AI

- AI makes it easier to build web forms
- AI helps you research Webform features
- AI can generate custom features for Webform

---

### Slide: Vibing the Webform UI

- Easy to get started
- Lots of help available
- Huge ecosystem of contributed modules

---

### Slide: End-to-End Demo

Build a feedback form from scratch in 5 minutes.

1. Create a feedback form with email and feedback textarea
2. Test the form via the test tab
3. Confirm results in submissions
4. Place the form as a block on the website
5. Submit the form as a block, show results per page
6. Create a Webform node for dedicated topic feedback
7. Set up an email handler
8. Configure a remote post handler

---

### Slide: Elements

- Textfield, Email, URL, Number, Tel, Password, Hidden
- Checkboxes, Radio buttons, Select, Combobox, Autocomplete
- Textarea, Markup, HTML, Processed text
- Details, Fieldset, Container, Columns, Tabs, Accordion
- Table, Table select, Image select, Signature pad
- File upload, Managed file
- Rating, Likert, Range, Color
- Date, Time, DateTime, Datelist, Week, Month, Year
- Terms of service, Same-as checkbox
- Composite (name, address, contact, link), Custom composites
- Computed elements (Twig, tokens)
- Prepopulation via URL and tokens
- Conditional visibility and logic
- Validation (pattern, regex, custom)
- Input masks
- Help text, descriptions, private notes
- Custom element plugins

---

### Slide: Handlers

- Email (HTML/plain text, Twig templating)
- Remote post (JSON, form data)
- Action (change submission status)
- Settings (conditional overrides)
- Debug
- Log
- Handler conditions (trigger based on values)
- Custom handler plugin API

---

### Slide: Integrations (Contributed Modules)

- Salesforce, HubSpot
- Mailchimp, newsletter subscribe
- Stripe, payment processing
- Google Sheets
- Slack
- Jira
- SMS
- Entity generation (create nodes from submissions)
- Views integration
- Rules integration
- Zapier/webhooks via remote post

---

### Slide: Submissions Management

- Admin table with customizable columns
- View, edit, delete, resend
- Flagging, starring, locking
- Admin notes and annotations
- Audit logs
- CSV/TSV export (customizable columns)
- Submission purging (scheduled, manual)
- Submission limits (per form, per user, per entity)
- Draft and autosave
- Confirmation (page, redirect, inline message)
- Serial numbers
- Tokens for anonymous edit/view links

---

### Slide: Access and Security

- Role-based access per form (create, view, update, delete, purge)
- Submission access per role and per user
- Element-level access control
- CAPTCHA and reCAPTCHA
- Honeypot
- Antibot, CleanTalk
- Closed forms with configurable messages
- Scheduled open/close dates
- IP address tracking

---

### Slide: Display and Theming

- Multiple display modes (form, table, HTML)
- Customizable confirmation page
- ShareJS embed (third-party sites)
- Block placement
- Webform as entity reference field
- Source entity token support
- Custom CSS per form
- Custom JavaScript per form
- Theme hook suggestions
- Element format overrides
- Print-friendly submission view

---

## Part 2: Vibing Webform with AI (20 min)

---

### Demo 1: Ask AI for the Right Prompt

Ask Claude: "Give me the prompt I should use to build a Webform in Drupal for an event registration."

Show the generated prompt.

---

### Demo 2: Generate Form Structure

Use the refined prompt to generate a full form outline — table of fields, elements, and guidance.

**Caveat:** AI doesn't fully understand your codebase. Output is a strong starting point, not production-ready.

---

### Demo 3: Export to Recipe

Use Claude Code to export the Webform into a Drupal Recipe.

---

### Demo 4: Generate a Custom Handler

Show the prompt and result for generating a custom handler that posts submissions to a Google Spreadsheet.

**Goal:** Generate custom code that is direct for the use case and as simple as possible.

---

### Slide: Tips and Tricks with AI

- Ask Claude for help
- Always write tests
- Use planning

---

## Q&A (5 min)
