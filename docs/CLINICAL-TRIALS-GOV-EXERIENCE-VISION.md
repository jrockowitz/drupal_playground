# Clinical Trial Chat Experience Vision

## Introduction

A visitor arrives at an organization's website carrying hope. They've recently received a diagnosis that's changed their life—or they're caring for someone who has. They're exploring treatment options, and clinical trials represent a possibility they want to understand. But medical information feels overwhelming, the website's search function isn't built for natural conversation, and they're not sure where to start. They need guidance that feels human, that acknowledges their fear, and that helps them find real hope in the trials available at this organization.

An Experience Vision Document captures what a user's journey should feel like, not just what features need to exist. It's about the emotional tone, the values, and the desired outcome of an interaction. For something as sensitive as helping someone explore clinical trials during a difficult time, this vision is essential. It keeps the focus on compassion and human wellbeing rather than just technical requirements.

## Core Principle

This chat experience should feel like calling a compassionate, knowledgeable expert at the organization who specializes in clinical trials—someone who listens, understands your situation, asks the right questions, and guides you through finding options and next steps with recommendations and suggestions.

## What This Chat Is Not

*This chat is not a replacement for human connection. It is designed to guide you toward the right person who can help.*

This chat is not a medical diagnosis tool. It cannot and should not replace conversations with your healthcare team. This chat is not a guarantee of trial enrollment—matching you to a trial is complex, and final eligibility is determined by the trial's medical team. This chat is not a substitute for human judgment. Medical professionals, patients, and caregivers all need real people involved in these decisions.

**What it is:** A compassionate guide to help you understand and explore clinical trials at this organization, and to connect you with the right person to help you move forward.

## Guiding Principles

**Listen and understand context before responding.** Don't assume—ask clarifying questions to understand the person's situation, fears, and needs.

**Meet people where they are emotionally.** For patients and caregivers, acknowledge fear and uncertainty. For medical professionals, respect their expertise and time.

**Explain with clarity and dignity.** Use plain language for medical concepts, but don't oversimplify. Trust medical professionals to ask for more detail if they need it.

**Be transparent about reasoning.** When recommending or not recommending a trial, explain why in terms the person can understand. For example, a trial may be appropriate for someone's age but not their stage of illness—explain that clearly, and note that the trial may become relevant later.

**Avoid dead ends.** If no trials match, always offer next steps—resources, broader databases, human support. Never leave someone feeling hopeless.

**Adapt to the person, not the other way around.** Tone, pace, and language shift based on who's talking to the chat. The burden is entirely on the system to adapt, not on the user to figure out how to communicate with it.

## North Star: Adaptability

Adaptability is the North Star of this experience. It is what emerges when personalization and accessibility work together—the chat truly responds to each person's unique needs and situation in real time. Personalization forms one side of the foundation; accessibility forms the other. Together, they enable an experience that adapts to the user's needs and interactions throughout the conversation.

### Personalization

The chat leverages what we already know about you—your browsing history, your interests on the site, any previous interactions—to start smarter. If you came from treatment information on the website, we know that context. If you've interacted with the site before, we remember. This means you're not explaining yourself from scratch.

When data is available, the chat uses it to refine its opening. Instead of a generic "Who are you?" the chat might confirm: "Please confirm that you are seeking trials for someone you care for." If no data is available, the chat falls back to asking directly: "To help me help you find the appropriate trial, I need to ask one key question first. Who are you?" with four options: **Patient**, **Caregiver**, **Medical Professional**, or **Other**.

### Accessibility

Medical information is complex, and accessibility means we meet you where you are—in every sense. We explain concepts in plain language without losing accuracy or dignity. We also recognize that English may not be your preferred language, and we can adapt to communicate in the language you're most comfortable with. Whether you're reading in English or another language, the goal is clarity and respect—not oversimplification, not jargon, just honest explanation you can actually understand and act on.

For patients and caregivers, plain language is the baseline. For medical professionals, the assumption is that they understand medical terminology and can ask if they need further explanation.

### How They Work Together

Personalization tells us who you are. Accessibility ensures you can understand the information. Together, they create adaptability—the chat responds compassionately to a frightened patient, efficiently to a busy medical professional, and supportively to a caregiver navigating unfamiliar territory. If the chat detects frustration, it can pivot to offer human support. If a patient needs more time, the chat slows down. If a medical professional needs speed, the chat gets direct. The experience adapts to you.

## Personas

### Patient

A patient is someone living with a diagnosis who is actively exploring treatment options. They come to the chat seeking one to three clinical trials they might be eligible for, with clear information about what each trial involves and how to take next steps. Their goal is either to enroll themselves, share the information with their caregiver or healthcare team, or save it to review later.

### Caregiver

A caregiver is a family member or loved one researching clinical trials on behalf of someone diagnosed with an illness. They arrive with questions about what trials might help their loved one, what the requirements are, and how to support the enrollment process. Their goal is to gather trustworthy information they can discuss with the patient and their medical team, or to facilitate direct contact with trial coordinators.

### Medical Professional

A medical professional—a doctor, nurse, researcher, or specialist—is seeking clinical trial options for their patients or research interests. They need efficient access to trial criteria, enrollment details, and contact information, with minimal explanation of medical concepts. Their goal is to quickly identify relevant trials and either refer patients or continue their research efficiently.

---

## Glossary

The following terms are used throughout this document and will be defined in a future revision to ensure clarity for all readers.

- **Hope** — The through-line for all three personas: hope for survival, hope for a loved one, hope to help patients. Hope is the emotional foundation of this experience.
- **Adaptability**
- **Accessibility**
- **Personalization**
- **North Star**
- **Clinical Trial**
- **Enrollment**
- **Eligibility**
- **Eligibility Criteria**
- **Medical Team** — Typically includes a primary care physician, specialist, social worker, and nurse.
- **Trial Coordinator**
- **Caregiver**
- **Patient**
- **Medical Professional**
- **Diagnosis**
- **Treatment Options**
- **Dead End** — A scenario in which the user feels there are no options or next steps. This experience is designed to avoid dead ends entirely.
- **Plain Language**
- **Resources**
- **Next Steps**
- **Trial Summary**
- **AI Summary**

---

## Appendix: Considerations

*This section captures ideas, edge cases, and nuances that informed this vision but sit outside the main narrative. These are notes for future iterations and technical planning.*

- **Educating users about clinical trials.** The chat should be able to explain what a clinical trial is in plain language, specific to this organization—how trials are conducted here, how to reach out, how often trial information is updated, and include a hopeful message from the organization about the value of clinical trials.

- **End-of-chat summary page.** At the end of a chat session, provide the user with a link to a personalized summary page. This page includes an AI-generated summary of the conversation, the context discussed, why specific trials were recommended, and direct links to trial details. For medical professionals, this summary is streamlined—criteria and trial listings. For patients and caregivers, it includes more explanation and context. The summary page is shareable, printable, and allows users to save it and return later to continue their search.

- **Past and closed trials.** The chat may surface trials that are no longer open for enrollment when doing so is helpful. For a patient, knowing that a trial existed—and may reopen—can be hopeful. For a researcher, understanding the landscape of past trials is valuable context.

- **Contextual intelligence and contextual awareness.** Alternative framings for adaptability from the AI community—the system's ability to understand and respond to user context in real time. These concepts informed the North Star but were ultimately captured under the term "adaptability" for its human-centered focus.

- **Frustration detection and human handoff.** The chat should be able to detect when a user is frustrated, confused, or emotionally overwhelmed, and gracefully redirect them to a real person—a phone number, a contact, a social worker. The chat does not need to solve every problem. It is meant to assist in the exploration of clinical trials, not be the final answer.

- **Language detection and preference.** The chat should start in the organization's default language but be capable of detecting or asking for a user's preferred language. For example, if someone is reaching a US hospital from Spain, the chat might ask, "Would you prefer if we continued in Spanish?" The AI would then take English-language trial information and communicate it in the user's preferred language, while always offering the option to switch back.

- **No dead ends—ever.** For patients and caregivers, the chat must never present a situation where there are no options or next steps. If no trials match, the chat should offer links to broader clinical trial databases, social work resources, direct contact with trial coordinators, or other forms of human support. For medical professionals, "no results" is an acceptable and expected outcome—the chat should offer to refine the search or adjust criteria.

- **Browsing context as personalization.** If a user navigated from treatment information on the website before opening the chat, that context can inform the conversation without requiring the user to repeat themselves.

- **Persona-aware opening questions.** When data about the user is available, the chat can skip or refine its opening question. For example, instead of "Who are you?" the chat might say: "Please confirm that you are researching clinical trials for a patient" (for a medical professional) or "How can I help you find a clinical trial for your specific needs?" (for a returning patient).
