<!-- cspell:ignore Bonnici streamable rockowij modelcontextprotocol -->
# Drupal Playground AI MCP Recipe

Installs and configures the `drupal/mcp_server` module as a local proof of
concept for exposing Drupal Playground through the Model Context Protocol
(MCP).

This recipe intentionally uses `drupal/mcp_server`, `drupal/tool`,
`drupal/tool_belt`, and Tool Explorer. It does not install `drupal/mcp`,
`drupal/mcp_tools`, OAuth companion modules, or governance modules. The goal is
to prove that Drupal can start an MCP server, negotiate MCP capabilities with a
client, expose recipe-provided prompt configuration, make Tool Belt tools
available through Drupal's Tool API and Tool Explorer, and expose enabled Tool
Belt tools to MCP clients.

## Research Summary

The current MCP ecosystem around Drupal is moving quickly. The
`drupal/mcp_server` project page describes a configuration-driven MCP server
with STDIO and HTTP transports, prompt/resource support, Tool API integration,
and OAuth support. The current 2.x code has since split some capabilities into
companion projects, leaving `drupal/mcp_server` as the core runtime: MCP
protocol handling, Drush STDIO transport, HTTP transport, prompt config
entities, resource provider plugins, resource template plugins, and
attribute-discovered MCP tool plugins.

The older `drupal/mcp` module is still stable, but its project page says it is
being merged with MCP Server and recommends `drupal/mcp_server` for Drupal MCP
server implementations. Several public setup articles still refer to
`drupal/mcp`, optional submodules, or a Docker-based bridge. Those articles are
useful for local-development and security recommendations, but they should not
be copied directly into this recipe because this recipe targets
`drupal/mcp_server` only.

Useful findings from the research:

- Use STDIO first for a local DDEV proof of concept.
- Use HTTP only after the basic server works, because route permissions,
  session authentication, TLS, and host access add more moving parts.
- Lock down access explicitly. Do not grant MCP endpoint access broadly.
- Test with MCP Inspector before wiring the server into Codex.
- Use Tool Belt tools for local Tool API experiments, and expose enabled Tool
  Belt operations to MCP clients through a local POC bridge.

References:

- [MCP Server on Drupal.org](https://www.drupal.org/project/mcp_server)
- [Model Context Protocol on Drupal.org](https://www.drupal.org/project/mcp)
- [Drupal MCP documentation](https://drupalmcp.io/en/mcp-server/setup-configure/)
- [Bonnici: Turn Your Drupal Site Into an MCP Server](https://bonnici.co.nz/blog/drupal-mcp-server-ai-integration)
- [Acquia: Bridging Drupal and AI with MCP](https://docs.acquia.com/acquia-cloud-platform/help/96496-bridging-drupal-and-ai-developers-guide-model-context-protocol-mcp)
- [MCP Inspector documentation](https://modelcontextprotocol.io/docs/tools/inspector)
- [Codex MCP documentation](https://developers.openai.com/codex/mcp)

## Planned Recipe Scope

The recipe should create a minimal, working MCP server POC.

Planned files:

- `composer.json` declares the recipe and requires `drupal/mcp_server:2.x-dev`,
  `drupal/tool:^1.0@beta`, and `drupal/tool_belt:^1.0@alpha`.
- `recipe.yml` installs `mcp_server`, `tool`, `tool_explorer`, `tool_belt`,
  `tool_belt_content`, `tool_belt_content_translation`, `tool_belt_entity`,
  `tool_belt_system`, `tool_belt_user`, and
  `drupal_playground_ai_mcp_tool_bridge`, imports/configures MCP settings,
  installs Tool API support, and grants MCP access only to the administrator
  role.
- `config/mcp_server.settings.yml` sets a local server name and keeps the
  module's conservative defaults.
- `config/mcp_server.mcp_prompt_config.codex_drupal_orientation.yml` provides a
  simple discoverable prompt so MCP clients can prove prompt discovery works
  and understand the local Tool Belt bridge.
- `web/modules/custom/drupal_playground_ai_mcp_tool_bridge/` exposes enabled
  Tool Belt operations as MCP tools and keeps an atomic content creation helper.
- `README.md` documents installation, verification, Codex setup, and current
  limitations.

## Install

Install the recipe dependencies and apply the recipe:

```bash
ddev composer update drupal/drupal_playground_ai_mcp drupal/mcp_server drupal/tool drupal/tool_belt --with-all-dependencies
ddev exec drush recipe ../recipes/drupal_playground_ai_mcp
ddev drush cr
```

The project install command also supports the recipe directly:

```bash
ddev install mcp
```

The full AI preset applies this recipe too:

```bash
ddev install ai
```

If this recipe is later added to Composer as a local recipe package, the first
command can be replaced with the normal recipe dependency installation flow
used by the rest of this project.

## Expected Configuration

The recipe should configure:

- Server name: `Drupal Playground MCP Server`
- Server version: `1.0.0`
- Pagination limit: `50`
- Pending request poll interval: `300` milliseconds
- Sampling timeout: `25` seconds
- Elicitation timeout: `600` seconds

The HTTP route is provided by `mcp_server.handle`. The current 2.x code changes
the route path from the project-page example `/_mcp` to `/mcp` through the
`mcp_server.base_path` service parameter.

## Permissions

The `access mcp server` permission should be granted only to trusted local
administrators for this POC.

Do not grant MCP access to anonymous or broadly authenticated users. MCP is an
agent-facing interface, and future tools/resources may expose sensitive Drupal
state or perform privileged operations.

## Codex Setup

Codex supports STDIO MCP servers and streamable HTTP MCP servers through
`config.toml`. Project-scoped configuration can live in
`.codex/config.toml` after the project is trusted.

This repository ships the following default project-scoped Codex MCP
configuration in `.codex/config.toml`:

`drupal/tool_belt` is installed for Tool API content, entity, system, user, and
translation tools. The local `drupal_playground_ai_mcp_tool_bridge` module
dynamically exposes enabled `tool_belt:*` Tool API plugins to Codex through MCP
using MCP-safe names such as
`tool_belt_dynamic.tool_belt__entity_list`.

`tool_explorer` is enabled so administrators can browse and manually execute
Tool API tools at `/admin/config/tool/explorer`. This is useful for reviewing
Tool Belt discovery and trying content tools. Codex should use the MCP bridge
tools rather than the Tool Explorer UI.

### Recommended: STDIO Through DDEV

Use the default STDIO server first for local development:

```toml
[mcp_servers.drupal_playground]
command = "ddev"
args = ["exec", "vendor/bin/drush", "mcp:server"]
cwd = "/Users/rockowij/Sites/drupal_ai"
startup_timeout_sec = 20
tool_timeout_sec = 60
```

This starts Drupal's MCP server through Drush inside DDEV. The configured
defaults give the server 20 seconds to start and allow individual MCP calls to
run for up to 60 seconds. This project's DDEV version runs `ddev exec` in raw
mode by default, which works for JSON-RPC over standard input and output.

### Secondary: HTTP

After STDIO works, HTTP can be tested with:

```toml
[mcp_servers.drupal_playground_http]
url = "https://drupal-playground.ddev.site/mcp"
startup_timeout_sec = 20
tool_timeout_sec = 60
```

HTTP may require additional Drupal authentication handling. For a local POC,
STDIO is expected to be the lower-friction path.

### Can Codex Create Drupal Nodes?

Yes, through the local Tool Belt bridge. The recipe exposes dynamic Tool Belt
tools such as:

- `tool_belt_content_field_definitions`
- `tool_belt_content_create_entity`
- `tool_belt_dynamic.tool_belt__entity_list`
- `tool_belt_dynamic.tool_belt__entity_load_by_id`
- `tool_belt_dynamic.tool_belt__entity_delete`
- `tool_belt_dynamic.tool_belt__system_status`

Codex can first inspect a bundle's field value schema, then call
`tool_belt_content_create_entity` with `entity_type_id`, `bundle`,
`base_fields`, and `field_values`. The bridge uses Tool Belt's
`entity_stub`, `field_set_value`, and `entity_save` tools internally.
Omit `field_values` when no configurable fields need to be set, or pass it as
a JSON object keyed by field machine name.

Dynamic tools that accept saved entities use MCP entity references like:

```json
{
  "entity": {
    "entity_type_id": "node",
    "entity_id": 2
  }
}
```

## Verification

Check that the module is enabled:

```bash
ddev drush pml --status=enabled | rg 'mcp_server|tool|tool_explorer|tool_belt|drupal_playground_ai_mcp_tool_bridge'
```

Check the Tool Explorer route:

```bash
ddev drush ev '$route = \Drupal::service("router.route_provider")->getRouteByName("tool_explorer.list"); echo $route->getPath() . "\n";'
```

Check the HTTP route:

```bash
ddev drush ev '$route = \Drupal::service("router.route_provider")->getRouteByName("mcp_server.handle"); echo $route->getPath() . "\n";'
```

Check the installed settings:

```bash
ddev drush config:get mcp_server.settings
```

Run MCP Inspector against the STDIO server:

```bash
npx @modelcontextprotocol/inspector ddev exec vendor/bin/drush mcp:server
```

The Inspector should open a local browser UI. Use it to verify:

- The server initializes successfully.
- Capability negotiation completes.
- The prompts tab lists the recipe-provided orientation prompt.
- The tools tab lists the static helper tools and dynamic
  `tool_belt_dynamic.tool_belt__*` tools.
- Drupal logs do not show MCP server errors.

View MCP-related Drupal logs with:

```bash
ddev drush watchdog:show --filter=mcp_server
```

## Steps to Review Codex Support

Use these steps to confirm that Codex can be wired to the Drupal MCP proof of
concept after the recipe is implemented and applied.

1. Confirm the recipe scope is still limited to `drupal/mcp_server`,
   `drupal/tool`, `drupal/tool_belt`, and Tool Explorer.
2. Confirm the module is enabled and the `mcp_server.settings` values match the
   expected local POC configuration.
3. Confirm `access mcp server` is granted only to trusted administrator users.
4. Confirm the `mcp_server.handle` route reports the `/mcp` path.
5. Run MCP Inspector with the STDIO command and confirm initialization,
   capability negotiation, and prompt discovery succeed.
6. Add the STDIO `mcp_servers.drupal_playground` block to Codex
   `config.toml`.
7. Start a fresh Codex session from `/Users/rockowij/Sites/drupal_ai`.
8. Use `/mcp` in the Codex CLI or MCP settings in the Codex app/IDE extension
   to confirm the Drupal Playground server is enabled.
9. Ask Codex to list available MCP prompts and tools from the Drupal Playground
   server.
10. Confirm Codex sees the orientation prompt, `tool_belt_content_create_entity`,
    and dynamic `tool_belt_dynamic.tool_belt__*` tools.
11. Confirm Drupal administrators can open Tool Explorer at
    `/admin/config/tool/explorer`.
12. Confirm Tool Explorer lists Tool Belt content tools such as entity stub and
    entity save.
13. Ask Codex to create a published content entity through
    `tool_belt_content_create_entity`, using the field definition tool first if
    field value shape is unclear.
14. Check Drupal watchdog logs for `mcp_server` errors after the Codex
    connection attempt.
15. Record any client-side connection failure with the exact Codex config block,
    DDEV status, Inspector result, and Drupal watchdog output.

## Current Limitations

This POC proves the MCP runtime, client connection path, prompt discovery, Tool
Explorer UI access, Tool Belt installation, and an MCP-callable Tool Belt
bridge. It is intentionally broad for a local POC and should not be exposed
outside trusted local development.

To make Codex do useful Drupal work through MCP later, add one of the following
in a separate recipe or custom module:

- Custom resource provider plugins for read-only Drupal context.
- Additional local bridge tools or a companion Tool API bridge project if the
  project decides to expose more Tool API tools through MCP.
- A governance or OAuth layer before exposing MCP beyond local development.

## Implementation Plan

1. Create the recipe package metadata in `composer.json`.
2. Add `recipe.yml` with `mcp_server` installation and conservative config.
3. Add minimal MCP settings config.
4. Add a single orientation prompt config entity for discoverability testing.
5. Update `docs/CLAUDE-CODE-MCP.md` with the Drupal Playground-specific Codex
   setup and the current `drupal/mcp_server` limitations.
6. Apply the recipe locally.
7. Verify Drush, route, config, and MCP Inspector behavior.
8. Keep any future tool/resource exposure out of this recipe unless explicitly
   requested.
