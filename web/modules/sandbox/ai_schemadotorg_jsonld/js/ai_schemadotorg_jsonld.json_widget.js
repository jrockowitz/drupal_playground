/* eslint-disable strict, no-undef, no-use-before-define */

/**
 * @file
 * Adjusts the JSON editor widget for AI Schema.org JSON-LD settings.
 *
 * @param {jQuery} $
 *   jQuery.
 * @param {object} Drupal
 *   The Drupal global object.
 * @param {object} drupalSettings
 *   The Drupal settings object.
 * @param {Function} once
 *   The once utility function.
 */

(($, Drupal, drupalSettings, once) => {
  /**
   * Parses a JSON string, returning null on failure.
   *
   * @param {string} string
   *   The string to parse.
   * @return {object|null}
   *   The parsed JSON object, or null if parsing fails.
   */
  function parseJson(string) {
    try {
      return JSON.parse(string);
    } catch (exception) {
      return null;
    }
  }

  /**
   * Converts a CSS dimension string to pixels.
   *
   * @param {string} value
   *   The dimension string (e.g. '260px', '60vh').
   * @return {number}
   *   The dimension in pixels.
   */
  function parseDimension(value) {
    if (typeof value !== "string") {
      return 0;
    }

    if (value.endsWith("vh")) {
      return Math.round(window.innerHeight * (parseFloat(value) / 100));
    }

    if (value.endsWith("px")) {
      return parseFloat(value);
    }

    return parseFloat(value) || 0;
  }

  /**
   * Calculates the editor height based on content line count.
   *
   * @param {string} text
   *   The editor text content.
   * @param {number} defaultHeight
   *   The minimum/default height in pixels.
   * @param {number} maxHeight
   *   The maximum height in pixels.
   * @return {number}
   *   The calculated height in pixels.
   */
  function getEditorHeight(text, defaultHeight, maxHeight) {
    const lineCount = Math.max(text.split(/\r\n|\r|\n/).length, 1);
    const expandedHeight = defaultHeight + Math.max(lineCount - 12, 0) * 20;
    return Math.min(Math.max(expandedHeight, defaultHeight), maxHeight);
  }

  /**
   * Resizes the JSON editor container to fit its content.
   *
   * @param {jQuery} $editor
   *   The editor container jQuery element.
   * @param {object} jsonEditor
   *   The JSONEditor instance.
   * @param {object} settings
   *   The editor settings from drupalSettings.
   */
  function resizeEditor($editor, jsonEditor, settings) {
    const defaultHeight = parseDimension(settings.height || "260px");
    const maxHeight = parseDimension(settings.maxHeight || "60vh");
    const editorHeight = getEditorHeight(
      jsonEditor.getText(),
      defaultHeight,
      maxHeight,
    );

    $editor.css("height", `${editorHeight}px`);
    jsonEditor.resize();
  }

  /**
   * Attaches the JSON editor widget to matching textarea elements.
   *
   * @type {object}
   */
  Drupal.behaviors.json_widget = {
    attach(context) {
      $(
        once("ai-schemadotorg-json-widget", "[data-json-editor]", context),
      ).each((index, element) => {
        const $textarea = $(element);
        const editorId = $textarea.attr("data-json-editor");
        const settings = drupalSettings.json_field[editorId];
        const data = $textarea.val();
        const json = parseJson(data);

        if (settings || data) {
          const defaultHeight = settings.height || "260px";
          const $editor = $(
            `<div id="json-editor-${$textarea.attr(
              "name",
            )}" style="width:100%;height:${defaultHeight}"></div>`,
          );

          $textarea.addClass("js-hide");
          $textarea.after($editor);

          let jsonEditor;
          const instanceOptions = {
            onChange() {
              $textarea.val(jsonEditor.getText());
              resizeEditor($editor, jsonEditor, settings);
            },
          };
          if (settings.schema) {
            instanceOptions.schema = parseJson(settings.schema);
          }

          jsonEditor = new JSONEditor($editor[0], {
            ...settings,
            ...instanceOptions,
          });
          if (json) {
            jsonEditor.set(json);
          } else {
            jsonEditor.setText(data);
          }

          resizeEditor($editor, jsonEditor, settings);
        }
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once);
