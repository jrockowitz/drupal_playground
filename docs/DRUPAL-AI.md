# DRUPAL-AI

## Overview

Drupal's AI ecosystem is built around the **AI (Artificial Intelligence) module** (`drupal/ai`), a unified framework for integrating AI services into Drupal 10 and 11 sites. Rather than tying you to a single vendor, the module provides an **abstraction layer** — similar to how Drupal handles database or OAuth providers — so you can swap between AI providers (OpenAI, Anthropic, AWS Bedrock, Google Vertex, Hugging Face, and many others) without rewriting your integration code.

## The Drupal AI Initiative

Launched in June 2025, the **Drupal AI Initiative** is a coordinated, sponsor-funded effort to accelerate AI development across the Drupal ecosystem. The initiative grew out of the organic community work on 290+ AI-related modules and aims to channel that energy into a shared product vision with dedicated leadership, funding, and strategic direction.

The initiative is led by representatives from founding member agencies (FreelyGive, Dropsolid, 1xINTERNET, Salsa Digital, Acquia) and guided by Dries Buytaert. It emphasizes **human-AI collaboration** — amplifying expertise rather than replacing it.

## Key Components of the AI Module

- **AI Core** — The provider abstraction layer. Connect to any supported AI service and swap models as needed.
- **AI Automators** — Populate and transform any Drupal field using AI. Chain prompts together for complex workflows.
- **AI Explorer** — An admin area for testing prompts and exploring text generation capabilities.
- **AI CKEditor** — An AI assistant embedded in CKEditor 5 for writing assistance, spelling correction, translation, and summarization.
- **AI Content** — Tools for adjusting tone, summarizing body text, suggesting taxonomy terms, and checking moderation.
- **AI Translate** — One-click AI-powered translations for multilingual sites.
- **AI Search (Experimental)** — Semantic search and LLM-powered chatbot for exploring site content.
- **AI Agents** — A framework for building text-to-action agents that can manipulate Drupal configuration and content.
- **AI Logging** — Log AI requests and responses for observability.

## Basic Setup

1. Install the AI module and a provider module (e.g., `provider_openai`) via Composer.
2. Store your API key securely using the **Key** module.
3. Navigate to **Configuration → AI → Provider settings**, select your provider, assign your key, and save.
4. The module auto-configures the provider as the default for various AI tasks.
5. Enable the submodules you need (e.g., `ai_content`, `ai_chatbot`, `ai_agents`, `ai_ckeditor`).

## Links & Resources

- [AI (Artificial Intelligence) module for Drupal](http://project.pages.drupalcode.org/ai/)
- [Drupal AI Initiative](https://www.drupal.org/about/starshot/initiatives/ai)
- [Video walkthrough on YouTube](https://www.youtube.com/watch?v=hptyElqmo6Q)
- [Drupal AI project page on Drupal.org](https://www.drupal.org/project/ai)
- [Source article on Drupalize.me](https://drupalize.me/blog/drupal-ai-how-set-it-and-try-it-out)
- [Drupal AI Ecosystem Part 1: Setup and AI CKEditor Configuration](https://opensenselabs.com/blog/drupal-ai-series/ai-module-setup-and-ckeditor)
- [AI in Drupal: a focused guide to practical implementation](https://www.qed42.com/insights/ai-in-drupal-a-focused-guide-to-practical-implementation)
- [https://www.talkingDrupal.com/538](https://www.talkingDrupal.com/538)
