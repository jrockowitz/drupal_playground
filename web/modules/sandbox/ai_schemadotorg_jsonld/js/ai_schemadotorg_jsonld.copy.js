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
        ".ai-schemadotorg-jsonld-copy",
        context,
      ).forEach((container) => {
        const button = container.querySelector(
          ".ai-schemadotorg-jsonld-copy-button",
        );
        const message = container.querySelector(
          ".ai-schemadotorg-jsonld-copy-message",
        );
        const fieldName = button ? button.dataset.fieldName : null;

        if (!button || !message || !fieldName) {
          return;
        }

        const textarea = context.querySelector
          ? context.querySelector(
              `[name="${fieldName}[0][value]"], textarea[data-drupal-selector*="${fieldName}"]`,
            )
          : null;

        message.addEventListener("transitionend", hideMessage);

        button.addEventListener("click", (event) => {
          const value = textarea ? textarea.value : "";
          if (window.navigator.clipboard && value) {
            const closeTag = `</script>`;
            window.navigator.clipboard.writeText(
              `<script type="application/ld+json">\n${value}\n${closeTag}`,
            );
          }

          showMessage();
          Drupal.announce(Drupal.t("JSON-LD copied to clipboard…"));
          event.preventDefault();
        });

        function showMessage() {
          message.style.display = "inline-block";
          setTimeout(() => {
            message.style.opacity = "0";
          }, 1500);
        }

        function hideMessage() {
          message.style.display = "none";
          message.style.opacity = "1";
        }
      });
    },
  };
})(Drupal, once);
