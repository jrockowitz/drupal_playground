---
name: drupalorg-project-logo-prompt
description: Generate image-generation prompts for a Drupal module or theme logo based on the project README.md and Drupal.org project page.
---

# Drupal.org Project Logo Prompt Generator

Generate one or more image-generation prompts that can be pasted into an AI image generator (e.g. NanoBanana, Midjourney, DALL·E, Stable Diffusion) to produce logo candidates for a Drupal module or theme.

## When to Use

Use this skill when the user:

- Says "generate a logo prompt for my module"
- Says "create a logo prompt for [module name]"
- Wants ideas for a Drupal project logo
- Asks "what should my module logo look like?"
- Wants to submit a logo to Drupal.org Project Browser

## Logo Specifications (for context)

Drupal.org project logos must meet these requirements:

- **Size:** 512×512 pixels (square)
- **Format:** PNG, no animations, no rounded corners baked into the PNG
- **File size:** 10 KB or less (use pngquant at ~80% quality)
- **Filename:** `logo.png` in the root of the default Git branch
- **SVG:** also provide `logo_svg.txt` for the vector version
- **No module name in the image** unless there is strong branding justification
- Used in Project Browser, drupalcode.org project avatar, and drupal.org project page

## Steps

### 1. Identify the project

Parse $ARGUMENTS for the Drupal project machine name (e.g. `webform`, `views`, `token`).

If no argument is given, ask the user: "What is the Drupal.org machine name for your project (e.g. `webform`)?"

### 2. Gather project context

Collect information from two sources in parallel:

**A. Local README.md**
Look for a README.md in the following locations (check all, use the first that exists):
- `README.md`
- `web/modules/custom/*/README.md` matching the project name
- `web/modules/contrib/*/README.md` matching the project name
- `web/themes/custom/*/README.md` matching the project name

If found, extract: purpose, key features, metaphors used, and tone.

**B. Drupal.org project page**
Fetch `https://www.drupal.org/project/$PROJECT_NAME` and extract:
- Project description / tagline
- Key features listed on the page
- Any visual metaphors or domain language used

### 3. Synthesize understanding

From the gathered context, identify:

- **Core concept:** What does the module fundamentally do? (e.g. "manages webforms", "provides token replacement", "controls entity access")
- **Domain metaphors:** What real-world objects or concepts map to this? (e.g. forms → paper/clipboard, tokens → puzzle pieces/keys, access → lock/gate)
- **Tone:** Professional/enterprise? Developer tool? Content-creator-friendly? Playful?
- **Drupal ecosystem cues:** Does it integrate with Views, Fields, Paragraphs, Layout Builder, etc.?

### 4. Generate 3 distinct logo prompts

Produce **3 image-generation prompts**, each representing a different visual direction:

1. **Conceptual/Abstract** — A metaphor-driven icon using geometric shapes and the module's core concept
2. **Literal/Object** — A clean flat icon of a real-world object that represents the module's function
3. **Expressive/Character** — A slightly more playful or distinctive take that could stand out in a project browser grid

Each prompt should follow this structure:

```
[DIRECTION NAME]

Prompt:
[The full image generation prompt]

Style notes:
[2–3 sentences on the visual approach and why it fits the module]
```

### 5. Prompt engineering guidelines

When writing each prompt, apply these principles:

- **Start with the subject:** Lead with the icon object, not style adjectives
- **Specify flat/icon style:** Use phrases like "flat vector icon", "minimal icon design", "clean SVG-style icon"
- **Constrain the palette:** Suggest 2–3 colours maximum; optionally reference Drupal blue (`#0678be`) as an accent if appropriate
- **Square composition:** Mention "512x512", "square format", or "centered composition with padding"
- **No text:** Always include "no text, no letters, no words" unless the user confirms the module has strong logo-text branding
- **No rounded corners:** Specify "sharp edges" or "not rounded" when relevant to the container shape
- **High contrast:** "suitable for small sizes", "readable at 64px"
- **Negative space:** Encourage "clean white or transparent background"

### 6. Output format

Present the output as:

```
# Logo prompt ideas for: [PROJECT NAME] ([machine name])

Project Page: https://www.drupal.org/project/<PROJECT NAME>
Project README.md: https://git.drupalcode.org/project/<PROJECT NAME>

## What the module does
[2–3 sentence summary of the module's purpose derived from README.md + project page]

## Core visual concept
[1–2 sentences identifying the metaphors and tone you're working with]

---

### Option 1: Conceptual/Abstract
...

### Option 2: Literal/Object
...

### Option 3: Expressive/Character
...

---

## Tips for generating
- Paste each prompt directly into your image generator of choice
- Generate 4–8 variations per prompt and pick the strongest
- Aim for 512×512px PNG output; compress with pngquant at 80% quality to hit the 10 KB limit
- Save as `logo.png` in the root of your module's Git repository
```

## Example Output (for reference only)

For a module called "Token" (machine name: `token`):

Project Page: https://www.drupal.org/project/token
Project README.md: https://git.drupalcode.org/project/token

> **Option 1: Conceptual/Abstract**
> Prompt: "Flat vector icon of an interlocking puzzle piece with a small bracket symbol cutout, Drupal blue (#0678be) and white, minimal icon style, centered on transparent background, no text, 512x512, sharp edges, readable at small sizes"

> **Option 2: Literal/Object**
> Prompt: "Flat icon of a single coin or medallion with a bracket symbol embossed on it, two-colour palette of blue and light grey, clean icon design, square format with padding, transparent background, no letters or words"

> **Option 3: Expressive/Character**
> Prompt: "Minimal icon of a key made from angle brackets < >, bold line weight, coral and navy colour palette, slightly playful geometric style, centered with whitespace, no text, 512x512 PNG format"
