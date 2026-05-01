# Drupal AI-Powered Search: Search API, Elasticsearch & Vectors

## Search API

Search API is a Drupal module that sits between your content and the search backend. It handles index creation, field mapping, data processing, and query building. Search results are displayed via Views, where you select the backend for each view. It's not truly decoupled—switching backends means reconfiguring Views and adjusting backend-specific tuning. It reduces friction compared to writing raw queries, but you're still tethered to your backend choice in practice.

## Elasticsearch

Elasticsearch and Apache Solr are both built on **Lucene**, the same underlying search library, but they are separate projects—cousins, not parent-child. They share core concepts (inverted indexes, analyzers, tokenization) but have their own architectures, APIs, and feature sets.

**Drupal integration:** Several connector modules exist (Search API Elasticsearch, Search API Elasticsearch Client, Elasticsearch Connector). Use whatever modules align with the client's existing Elasticsearch setup. DDEV can spin up Elasticsearch as a Docker service alongside Drupal.

## Vectors & Semantic Search

A **vector** (embedding) is a list of numbers (e.g., 768 floats) that represents the semantic meaning of text. An AI model converts text passages into vectors where similar content produces numerically similar vectors—matching by meaning, not just keywords. Elasticsearch natively supports vector fields, so you can store embeddings and run semantic search alongside keyword search (hybrid search) without a separate vector database.

**Other vector databases** (Milvus, Weaviate, Pinecone) exist but are unnecessary if Elasticsearch is already in place.

## RAG: Retrieval Augmented Generation

**RAG** is the pattern that ties it all together:

1. **User asks a question** in natural language (e.g., "Find clinical trials for a 35-year-old male with stage 3 prostate cancer")
2. **Retrieve** — The question is converted to a vector and used to search Elasticsearch for semantically relevant nodes. Hybrid search (keyword + vector similarity) generally delivers the best results.
3. **Augment** — The full text of the matched nodes is passed as context to the LLM. Vectors are only the retrieval mechanism; the LLM reads the actual text.
4. **Generate** — The LLM produces a natural language response grounded in your real data, e.g., a layman-friendly summary with eligibility criteria and contact info rather than raw clinical research data.

The LLM cannot call Elasticsearch directly. You need an intermediary—a Drupal controller, agent, or tool—that translates natural language into structured ES queries (filtering by age, diagnosis, stage, etc.) and feeds results to the LLM.

## Drupal Community: What People Are Doing

- **AI Search module** (drupal.org) — Semantic search with vector databases and embeddings, built on Search API.
- **AI Search Block module** (drupal.org) — Natural language content queries with streamed real-time responses. Built on the Drupal AI module.
- **Hybrid search** (keyword + vector) is the emerging consensus for best results.
- University of Edinburgh has published experiments with Drupal AI-boosted search and AI assistants.

## Local Experiment Setup (DDEV)

**Goal:** Index a set of nodes, ask questions in natural language via a chat interface, and get well-formed answers grounded in actual content.

**Stack:** DDEV → Drupal → Search API → Elasticsearch (with vector fields) → LLM (Claude API or Drupal AI module)

**Key decisions to pin down:**
- Which Elasticsearch connector modules does the client use?
- Embedding model choice (impacts performance, cost, and domain relevance)
- How to chunk longer content for accurate semantic capture
- Building the intermediary that translates natural language queries into structured ES queries
