# Per-module OOP hook conversion workflow

Use this reference for one main-project module. Apply the same convention to
Schema.org Blueprints Experimental only after the main-project pilot is
approved, and keep Experimental in separate linked merge-request work.

## 1. Establish a clean implementation base

- Review and retain the issue note's raw Rector POC evidence.
- Require explicit maintainer approval before discarding the generated POC.
- Begin production conversion from clean `1.0.x` on an approved issue branch.
- Keep one module as the active implementation scope.

## 2. Classify the module

Inventory every function in the module's `*.module`, `*.inc`, and `*.install`
files and classify it as:

- supported runtime hook to convert;
- required procedural install/update/meta hook;
- registered callback that may remain callable by name;
- procedural helper;
- hook-order implementation requiring an ordering attribute;
- API documentation rather than an implementation.

Trace hook calls into managers, builders, interfaces, tests, and other modules.
Lexical caller counts are leads, not proof that a service is private or public.

## 3. Decide class and service boundaries

- Prefer one cohesive hook class for a small submodule.
- Split only when responsibilities or dependency sets are materially distinct.
- Keep public or independently useful managers/builders and inject their
  interfaces.
- Move a pass-through service method into the hook class only after verifying
  callers and receiving approval for any public service/interface change.
- Keep hook input adaptation and orchestration in the hook class.
- Use `StringTranslationTrait` only for hook-local strings; inject a translator
  when the selected design or testing boundary requires it.
- Do not keep Rector's explicit hook-service YAML when Drupal's automatic
  registration and autowiring suffice.

## 4. Preserve ordering and behavior

Record existing ordering before removing procedural implementations. Express
first/last or relative ordering with Drupal's `Hook`, `ReorderHook`, and order
objects. Add a regression test that fails if the order changes.

The focal-point POC failure is the known sentinel: an image field must retain
the `image_focal_point` widget rather than being overwritten by `image_image`.

## 5. Implement and optimize discovery

- Add the final hook class and injected constructor dependencies.
- Move supported runtime hook bodies or delegate to retained services.
- Remove converted procedural implementations; do not add legacy wrappers.
- Preserve required procedural functions and named callbacks.
- Add `MODULE.skip_procedural_hook_scan: true` only when the module contains no
  procedural runtime hook that Drupal must discover.
- Keep `*.api.php` definitions as documentation and update examples only when
  needed to explain the project convention.

## 6. Per-module definition of done

- Every function is classified and the tracker is updated.
- Behavioral and ordering tests cover the converted hooks.
- Hook classes contain no static `\Drupal` lookup.
- Public service IDs/interfaces are preserved or separately approved.
- No converted procedural implementation or `#[LegacyHook]` remains.
- Procedural-scan eligibility is documented and correctly configured.
- Container rebuilding succeeds.
- Targeted PHPUnit and target-scoped code review pass, apart from documented
  baseline limitations unrelated to the module.
- `git diff --check` passes.
- The maintainer reviews the complete module diff before commit.

## 7. Batch and publication policy

Use one reviewed commit per module. A merge-request batch should normally hold
four to eight related, individually completed modules. Use dedicated merge
requests for the pilot, ordering-sensitive work, the base module, and the
Experimental project. Run the complete main-project suite before requesting
review of each main-project batch.
