# Drupal Playground AI MCP Tool Bridge

This local proof-of-concept module exposes Tool Belt tools through Drupal MCP
Server so Codex can discover and call them over MCP.

## Why This Bridge Exists

`drupal/mcp_server`, `drupal/tool`, and `drupal/tool_belt` solve adjacent
parts of the same problem, but they do not automatically wire themselves
together.

`drupal/mcp_server` provides the MCP runtime: STDIO and HTTP transports,
prompt discovery, MCP tool registration, and the server that Codex connects to.

`drupal/tool` provides Drupal's Tool API: a plugin system for defining
executable Drupal tools.

`drupal/tool_belt` provides useful Tool API plugins, including entity listing,
entity loading, field definitions, entity save, system status, user operations,
bundle operations, and field operations.

The missing link is that Tool Belt tools are Tool API plugins, not MCP tools.
Codex only sees tools registered with `mcp_server` as MCP tools. Without this
bridge, Codex can connect to Drupal's MCP server and discover MCP prompts, but
it does not automatically see or call Tool Belt's `tool_belt:*` plugins through
MCP.

## What The Bridge Does

The bridge translates Tool Belt plugins into Codex-callable MCP tools.

- Discovers enabled Tool Belt tools from Drupal's Tool API manager.
- Creates MCP tool definitions with names such as
  `tool_belt_dynamic.tool_belt__entity_list`.
- Converts Tool API input definitions into MCP JSON schemas.
- Adapts MCP entity references into Drupal entity objects.
- Normalizes Drupal and Tool API outputs into JSON-safe MCP structured content.
- Adds `tool_belt_content_create_entity` as an atomic content creation helper.

The content creation helper exists because raw Tool Belt content creation is a
multi-step workflow: create an unsaved entity, set field values, and save the
entity. The helper keeps that workflow easier for Codex to call in a local POC.

## Entity Arguments

Dynamic tools that need an existing entity accept an MCP-safe entity reference:

```json
{
  "entity": {
    "entity_type_id": "node",
    "entity_id": 4
  }
}
```

The bridge loads that reference into the Drupal entity object expected by the
underlying Tool Belt plugin.

## Local POC Scope

This module is intentionally broad because it is for trusted local development.
It exposes every enabled Tool Belt tool that starts with `tool_belt:*`.

Do not expose this bridge to untrusted users or remote clients without adding a
governance layer, narrower allowlists, and dedicated permission checks for the
operations you want to permit.
