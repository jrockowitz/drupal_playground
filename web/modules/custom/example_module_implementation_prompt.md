I have a Drupal module implementation plan in @path/to/spec.md.

Implement this module incrementally, pausing to ask me questions at each
stage before proceeding. Follow this workflow:

**Before writing any code:**
1. Read the full spec and summarize your implementation plan as ordered
   phases (e.g., module scaffold → service layer → plugin/form → routes →
   tests → config)
2. Identify any ambiguities, missing details, or decisions I need to make
   BEFORE you start — list them all at once and wait for my answers

**During implementation — pause and ask me before:**
- Starting each new phase
- Creating any file you're uncertain about (structure, naming, approach)
- Choosing between two valid Drupal patterns (e.g., service vs. static,
  hook vs. event subscriber)
- Adding anything not explicitly covered in the spec

**Format your checkpoint questions like this:**
---
✅ Completed: [what you just built]
🔜 Next phase: [what comes next]
❓ Questions before I proceed:
1. [specific question]
2. [specific question]
   Type "go" to proceed with defaults, or answer any questions above.
---

**After completing each phase**, show me the files created/modified and
any follow-up considerations before moving on.
