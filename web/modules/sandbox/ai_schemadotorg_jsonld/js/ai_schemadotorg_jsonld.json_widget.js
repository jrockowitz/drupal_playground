/**
 * @file
 * Adjusts the JSON editor widget for AI Schema.org JSON-LD settings.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  function parseJson(string) {
    try {
      return JSON.parse(string);
    }
    catch (exception) {
      return null;
    }
  }

  function parseDimension(value) {
    if (typeof value !== 'string') {
      return 0;
    }

    if (value.endsWith('vh')) {
      return Math.round(window.innerHeight * (parseFloat(value) / 100));
    }

    if (value.endsWith('px')) {
      return parseFloat(value);
    }

    return parseFloat(value) || 0;
  }

  function getEditorHeight(text, defaultHeight, maxHeight) {
    var lineCount = Math.max(text.split(/\r\n|\r|\n/).length, 1);
    var expandedHeight = defaultHeight + (Math.max(lineCount - 12, 0) * 20);
    return Math.min(Math.max(expandedHeight, defaultHeight), maxHeight);
  }

  function resizeEditor($editor, jsonEditor, settings) {
    var defaultHeight = parseDimension(settings.height || '260px');
    var maxHeight = parseDimension(settings.maxHeight || '60vh');
    var editorHeight = getEditorHeight(jsonEditor.getText(), defaultHeight, maxHeight);

    $editor.css('height', editorHeight + 'px');
    jsonEditor.resize();
  }

  Drupal.behaviors.json_widget = {
    attach: function (context) {
      $(once('ai-schemadotorg-json-widget', '[data-json-editor]', context))
        .each(function (index, element) {
          var $textarea = $(element);
          var editorId = $textarea.attr('data-json-editor');
          var settings = drupalSettings.json_field[editorId];
          var data = $textarea.val();
          var json = parseJson(data);

          if (settings || data) {
            var defaultHeight = settings.height || '260px';
            var $editor = $('<div id="json-editor-' + $textarea.attr('name') + '" style="width:100%;height:' + defaultHeight + '"></div>');

            $textarea.addClass('js-hide');
            $textarea.after($editor);

            var jsonEditor;
            var instanceOptions = {
              onChange: function () {
                $textarea.val(jsonEditor.getText());
                resizeEditor($editor, jsonEditor, settings);
              }
            };
            if (settings.schema) {
              instanceOptions.schema = parseJson(settings.schema);
            }

            jsonEditor = new JSONEditor($editor[0], Object.assign({}, settings, instanceOptions));
            if (json) {
              jsonEditor.set(json);
            }
            else {
              jsonEditor.setText(data);
            }

            resizeEditor($editor, jsonEditor, settings);
          }
        });
    }
  };

})(jQuery, Drupal, drupalSettings, once);
