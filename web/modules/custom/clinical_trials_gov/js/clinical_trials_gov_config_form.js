/**
 * @file
 * Keeps field group checkboxes in sync with selected child metadata fields.
 */

/**
 * Registers ClinicalTrials.gov config form behaviors.
 *
 * @param {Drupal} Drupal
 *   The Drupal behavior registry.
 * @param {Function} once
 *   The Drupal once helper.
 */
(function clinicalTrialsGovConfigFormBehavior(Drupal, once) {
  /**
   * Returns the metadata path for a table row.
   *
   * @param {HTMLTableRowElement} row
   *   The field-mapping table row.
   *
   * @return {string}
   *   The metadata path, or an empty string when unavailable.
   */
  function getRowPath(row) {
    const pathElement = row.querySelector("td:nth-child(3) small");
    return pathElement ? pathElement.textContent.trim() : "";
  }

  /**
   * Returns the nearest ancestor metadata path present in the table.
   *
   * @param {string} path
   *   The metadata path to inspect.
   * @param {Map<string, object>} items
   *   The row data keyed by metadata path.
   *
   * @return {string}
   *   The nearest ancestor path, or an empty string when unavailable.
   */
  function getParentPath(path, items) {
    let candidatePath = path;
    let lastDot = candidatePath.lastIndexOf(".");

    while (lastDot !== -1) {
      candidatePath = candidatePath.slice(0, lastDot);
      if (items.has(candidatePath)) {
        return candidatePath;
      }
      lastDot = candidatePath.lastIndexOf(".");
    }

    return "";
  }

  /**
   * Updates one parent checkbox from its direct child checkbox states.
   *
   * @param {string} path
   *   The metadata path for the parent row.
   * @param {Map<string, object>} items
   *   The row data keyed by metadata path.
   */
  function updateParentCheckbox(path, items) {
    const item = items.get(path);
    if (!item || item.childPaths.length === 0 || !item.checkbox) {
      return;
    }

    item.checkbox.checked = item.childPaths.some((childPath) => {
      const childItem = items.get(childPath);
      return Boolean(
        childItem && childItem.checkbox && childItem.checkbox.checked,
      );
    });
  }

  /**
   * Updates all ancestor checkboxes for a changed row.
   *
   * @param {string} path
   *   The metadata path for the changed row.
   * @param {Map<string, object>} items
   *   The row data keyed by metadata path.
   */
  function updateAncestorCheckboxes(path, items) {
    let currentPath = path;
    let parentPath = getParentPath(currentPath, items);

    while (parentPath) {
      updateParentCheckbox(parentPath, items);
      currentPath = parentPath;
      parentPath = getParentPath(currentPath, items);
    }
  }

  /**
   * Initializes the row hierarchy for one field-mapping table.
   *
   * @param {HTMLTableElement} table
   *   The field-mapping table element.
   *
   * @return {Map<string, object>}
   *   The row data keyed by metadata path.
   */
  function buildItems(table) {
    const items = new Map();
    const rows = table.querySelectorAll("tr");

    rows.forEach((row) => {
      const path = getRowPath(row);
      const checkbox = row.querySelector('input[type="checkbox"]');

      if (!path || !checkbox) {
        return;
      }

      items.set(path, {
        checkbox,
        childPaths: [],
      });
    });

    items.forEach((item, path) => {
      const parentPath = getParentPath(path, items);
      if (!parentPath) {
        return;
      }

      const parentItem = items.get(parentPath);
      if (parentItem) {
        parentItem.childPaths.push(path);
      }
    });

    return items;
  }

  /**
   * Sets the initial checked state for parent field group checkboxes.
   *
   * @param {Map<string, object>} items
   *   The row data keyed by metadata path.
   */
  function initializeParentCheckboxes(items) {
    const paths = Array.from(items.keys()).sort(
      (left, right) => right.split(".").length - left.split(".").length,
    );

    paths.forEach((path) => {
      updateParentCheckbox(path, items);
    });
  }

  Drupal.behaviors.clinicalTrialsGovConfigForm = {
    /**
     * Attaches parent-child checkbox syncing for the config form.
     *
     * @param {HTMLElement} context
     *   The current DOM context.
     */
    attach(context) {
      once(
        "clinical-trials-gov-config-form",
        ".clinical-trials-gov-table",
        context,
      ).forEach((table) => {
        const items = buildItems(table);

        initializeParentCheckboxes(items);

        table.addEventListener("change", (event) => {
          const checkbox = event.target;

          if (
            !(checkbox instanceof HTMLInputElement) ||
            checkbox.type !== "checkbox"
          ) {
            return;
          }

          const row = checkbox.closest("tr");
          if (!(row instanceof HTMLTableRowElement)) {
            return;
          }

          const path = getRowPath(row);
          if (!path) {
            return;
          }

          updateAncestorCheckboxes(path, items);
        });
      });
    },
  };
})(Drupal, once);
