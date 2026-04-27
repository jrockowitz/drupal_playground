/* eslint-disable strict, no-undef, no-use-before-define */

/**
 * @file
 * AI Schema.org JSON-LD copy-to-clipboard behavior.
 *
 * @param {object} Drupal
 *   The Drupal global object.
 * @param {Function} once
 *   The once utility function.
 */

((Drupal, once) => {
  /**
   * AI Schema.org JSON-LD copy button.
   *
   * @type {object}
   */
  Drupal.behaviors.aiSchemaDotOrgJsonLdCopy = {
    attach: function attach(context) {
      once(
        "ai-schemadotorg-jsonld-copy",
        ".ai-schemadotorg-jsonld-copy-button",
        context,
      ).forEach((button) => {
        const { fieldName } = button.dataset;
        let checkmarkTimeout = null;

        if (!button || !fieldName) {
          return;
        }

        const textarea = context.querySelector
          ? context.querySelector(
              `[name="${fieldName}[0][value]"], textarea[data-drupal-selector*="${fieldName}"]`,
            )
          : null;

        button.addEventListener("click", (event) => {
          const value = textarea ? textarea.value : "";
          if (window.navigator.clipboard && value) {
            const closeTag = `</script>`;
            window.navigator.clipboard.writeText(
              `<script type="application/ld+json">\n${value}\n${closeTag}`,
            );
          }

          showCheckmark();
          Drupal.announce(Drupal.t("JSON-LD copied to clipboard…"));
          event.preventDefault();
        });

        function showCheckmark() {
          let checkmark = button.nextElementSibling;
          if (
            !checkmark ||
            !checkmark.classList.contains(
              "ai-schemadotorg-jsonld-copy-checkmark",
            )
          ) {
            checkmark = document.createElement("span");
            checkmark.className = "ai-schemadotorg-jsonld-copy-checkmark";
            checkmark.textContent = "✓";
            button.insertAdjacentElement("afterend", checkmark);
          }

          if (checkmarkTimeout) {
            clearTimeout(checkmarkTimeout);
          }

          checkmarkTimeout = setTimeout(() => {
            checkmark.remove();
            checkmarkTimeout = null;
          }, 1500);
        }
      });
    },
  };
})(Drupal, once);
