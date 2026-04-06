/**
 * @file
 * Plugin report behaviors.
 */

(function ($, Drupal, debounce, once) {
  /**
   * Filters the plugin report table by a text input search string.
   *
   * The text input will have the selector `input.plugin-report-filter-text`.
   *
   * The target element to do searching in will be in the selector
   * `input.plugin-report-filter-text[data-element]`
   *
   * The text source where the text should be found will have the selector
   * `.plugin-report-filter-text-source`
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the plugin report filtering.
   */
  Drupal.behaviors.pluginReportFilterByText = {
    attach(context, settings) {
      once(
        'plugin-report-filter-text',
        'input.plugin-report-filter-text',
        context,
      ).forEach((input) => {
        const $input = $(input);
        const $table = $($input.attr('data-element'));

        if (!$table.length) {
          return;
        }

        const $filterRows = $table.find('tbody tr');

        /**
         * Filters the plugin list.
         *
         * @param {jQuery.Event} e
         *   The jQuery event for the keyup event that triggered the filter.
         */
        function filterPluginList(e) {
          const query = e.target.value.toLowerCase();

          /**
           * Shows or hides the plugin entry based on the query.
           *
           * @param {number} index
           *   The index in the loop, as provided by `jQuery.each`
           * @param {HTMLElement} row
           *   The table row element.
           */
          function togglePluginEntry(index, row) {
            const textMatch = row.textContent.toLowerCase().includes(query);
            $(row).toggle(textMatch);
          }

          // Filter if the length of the query is at least 2 characters.
          if (query.length >= 2) {
            $filterRows.each(togglePluginEntry);
            Drupal.announce(
              Drupal.formatPlural(
                $table.find('tbody tr:visible').length,
                '1 item is available in the modified list.',
                '@count items are available in the modified list.',
              ),
            );
          }
          else {
            $filterRows.each(function () {
              $(this).show();
            });
          }
        }

        $input.on('keyup', debounce(filterPluginList, 200));

        // Detect when the search clear button is clicked. The clear button does
        // not fire a keyup event, so we listen for a click when a value exists
        // and check in the next tick whether it was cleared.
        // @see https://adamyonk.com/posts/2025-01-03-html-search-input/
        $input.on('click', ({ target }) => {
          if (target.value) {
            setTimeout(() => {
              if (!target.value) {
                $filterRows.each(function () {
                  $(this).show();
                });
              }
            }, 0);
          }
        });
      });
    },
  };
})(jQuery, Drupal, Drupal.debounce, once);
