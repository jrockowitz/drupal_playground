/**
 * @file
 * Plugin report behaviors.
 */

/**
 * Initializes plugin report behaviors.
 *
 * @param {object} Drupal
 *   The Drupal object.
 * @param {Function} debounce
 *   The Drupal debounce helper.
 * @param {Function} once
 *   The Drupal once helper.
 */
(function pluginReportBehavior(Drupal, debounce, once) {
  /**
   * Filters the plugin report table by a text input search string.
   *
   * The text input will have the selector `input.plugin-report-filter-text`.
   *
   * The target element to do searching in will be in the selector
   * `input.plugin-report-filter-text[data-element]`
   */
  Drupal.behaviors.pluginReportFilterByText = {
    attach(context) {
      once(
        'plugin-report-filter-text',
        'input.plugin-report-filter-text',
        context,
      ).forEach((input) => {
        const table = document.querySelector(input.dataset.element);

        if (!table) {
          return;
        }

        const filterRows = table.querySelectorAll('tbody tr');

        /**
         * Shows all rows in the table.
         */
        function showAllRows() {
          filterRows.forEach((row) => {
            row.classList.remove('is-hidden');
          });
        }

        /**
         * Filters the plugin list.
         *
         * @param {Event} e
         *   The event that triggered the filter.
         */
        function filterPluginList(e) {
          const query = e.target.value.toLowerCase();

          // Filter if the length of the query is at least 2 characters.
          if (query.length >= 2) {
            filterRows.forEach((row) => {
              const textMatch = row.textContent.toLowerCase().includes(query);
              row.classList.toggle('is-hidden', !textMatch);
            });
            const visibleCount = table.querySelectorAll(
              'tbody tr:not(.is-hidden)',
            ).length;
            Drupal.announce(
              Drupal.formatPlural(
                visibleCount,
                '1 item is available in the modified list.',
                '@count items are available in the modified list.',
              ),
            );
          } else {
            showAllRows();
          }
        }

        /**
         * Restores rows after the search input clear button is used.
         */
        function handleInputClear() {
          if (!input.value) {
            showAllRows();
          }
        }

        input.addEventListener('keyup', debounce(filterPluginList, 200));

        // Detect when the search clear button is clicked. The clear button does
        // not fire a keyup event, so we listen for a click when a value exists
        // and check in the next tick whether it was cleared.
        // @see https://adamyonk.com/posts/2025-01-03-html-search-input/
        input.addEventListener('click', ({ target }) => {
          if (target.value) {
            setTimeout(handleInputClear, 0);
          }
        });
      });
    },
  };
})(Drupal, Drupal.debounce, once);
