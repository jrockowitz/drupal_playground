# Clinical Trials Recipe Milvus Chat

Adds the Olivero DeepChat user interface for the Clinical Trials Milvus experience.

## Prerequisite

This recipe layers on top of `clinical_trials_gov_recipe_milvus`.
For the intended DDEV preset workflow, run the Milvus backend recipe first so the
assistant, AI Search index, and Milvus provider already exist before this UI layer
is added. The underlying recipe itself declares
`clinical_trials_gov_recipe_milvus` as a recipe dependency, but the
`ddev install trials-chat` preset is still a layering step after
`trials-milvus`; it does not run the AI preset or backend bootstrap, import, or
indexing path by itself.

Apply the backend setup first:

```bash
ddev install trials-milvus
```

Then apply the chat UI:

```bash
ddev install trials-chat
```

For a fresh layered install, you can also run:

```bash
ddev install trials-milvus trials-chat
```

If you also want the hybrid Elasticsearch `/trials` page, use:

```bash
ddev install trials-elastic trials-milvus trials-chat
```

## What This Recipe Adds

- The Olivero-specific DeepChat interface for the Clinical Trials assistant
- Olivero-specific Asset Injector CSS for the chat experience
- Olivero-specific Asset Injector JavaScript for the chat experience
- The Olivero DeepChat block placement on `/trials` for the Clinical Trials Milvus assistant
- The anonymous `access deepchat api` permission needed for the public Olivero chat experience

## Layered Usage

- Use `ddev install trials-milvus` to build the backend and assistant layer first.
- Use `ddev install trials-chat` afterward to add the visible Olivero chat interface on `/trials`.
- Use `ddev install trials-milvus trials-chat` on a fresh site when you want both layers in one install flow.
- Use `ddev install trials-elastic trials-milvus trials-chat` when you want the Elasticsearch `/trials` page plus the Milvus-backed Olivero chat UI.

## Notes

- This recipe is additive and does not rebuild the Milvus index or replace the backend recipe.
- This recipe is Olivero-specific and imports Olivero-specific block and Asset Injector configuration.
- This recipe does not install the assistant or indexing backend by itself.
