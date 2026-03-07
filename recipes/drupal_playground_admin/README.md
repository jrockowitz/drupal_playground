# Drupal Playground Admin

Sets up Gin admin theme with Navigation, Dashboard, and Coffee.

## Applied Patches

### Core: Stop copying block configuration from active theme when enabling a new theme

https://www.drupal.org/project/drupal/issues/3105597

When a new theme is enabled, Drupal automatically copies block configuration from
the currently active theme. This is problematic because themes define their own
regions, so copied blocks may not fit properly — admin themes in particular suffer
when blocks from a frontend theme are copied across. This patch is especially
relevant to the Recipes initiative, which requires predictable block configuration.

Status: Needs work. Community is exploring skipping block copying during recipe
application by properly maintaining configuration sync state.

---

### Core: Persist is_syncing across container rebuilds

https://www.drupal.org/project/drupal/issues/3572171

Installing an admin theme triggers container rebuilds that reset the `is_syncing`
flag, breaking logic that depends on it. This causes unwanted block creation during
configuration synchronisation — a regression introduced by
[#3182716: block_theme_initialize should not create blocks during config sync](https://www.drupal.org/project/drupal/issues/3182716). The fix
persists `is_syncing` across container rebuilds by consolidating shared logic into
`DrupalKernel` so both theme and module installers handle it consistently.

Status: Reviewed & tested by the community (RTBC).

---

### Coffee: Add Navigation TopBar integration for Gin toolbar

https://www.drupal.org/project/coffee/issues/3535874

The Coffee module's quick-navigation launcher doesn't integrate with Drupal's
Navigation Top Bar introduced in 11.2.0. With Gin 5.0+ and the Navigation module
becoming standard, users expect Coffee to appear in the top bar. The fix introduces
a `TopBarItem` plugin that registers a Coffee button in the Tools region, visible
only to users with the appropriate permission.

Status: Needs review. Merge request !19 is functional on Drupal 11.3 + Gin 5.0.3,
with open discussion around button placement and accessibility.
