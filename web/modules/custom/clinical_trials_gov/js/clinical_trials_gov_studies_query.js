(function (Drupal, once) {
  function splitValues(value) {
    return value
      .split(/\||,|\n/)
      .map((item) => item.trim())
      .filter((item) => item !== '');
  }

  function syncTextarea(textarea, state) {
    textarea.value = state.values.join('|');
  }

  function renderChips(wrapper, textarea, state) {
    wrapper.querySelectorAll('.clinical-trials-gov-studies-query__chip').forEach((chip) => {
      chip.remove();
    });

    state.values.forEach((value, index) => {
      const chip = document.createElement('span');
      chip.className = 'clinical-trials-gov-studies-query__chip';

      const label = document.createElement('span');
      label.textContent = value;
      chip.appendChild(label);

      const removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.className = 'clinical-trials-gov-studies-query__chip-remove';
      removeButton.setAttribute('aria-label', Drupal.t('Remove @value', { '@value': value }));
      removeButton.textContent = '×';
      removeButton.addEventListener('click', () => {
        state.values.splice(index, 1);
        syncTextarea(textarea, state);
        renderChips(wrapper, textarea, state);
      });
      chip.appendChild(removeButton);

      wrapper.insertBefore(chip, wrapper.querySelector('.clinical-trials-gov-studies-query__editor'));
    });
  }

  function addValues(wrapper, textarea, state, values) {
    values.forEach((value) => {
      if (!state.values.includes(value)) {
        state.values.push(value);
      }
    });
    syncTextarea(textarea, state);
    renderChips(wrapper, textarea, state);
  }

  Drupal.behaviors.clinicalTrialsGovStudiesQuery = {
    attach(context) {
      once('clinical-trials-gov-studies-query-multivalue', 'textarea[data-clinical-trials-gov-multi-value="true"]', context).forEach((textarea) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'clinical-trials-gov-studies-query__multivalue';

        const state = {
          values: splitValues(textarea.value),
        };

        const editor = document.createElement('input');
        editor.type = 'text';
        editor.className = 'clinical-trials-gov-studies-query__editor';
        editor.placeholder = Drupal.t('add-multiple ↩');

        editor.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            const values = splitValues(editor.value);
            if (values.length > 0) {
              addValues(wrapper, textarea, state, values);
              editor.value = '';
            }
          }
        });

        editor.addEventListener('blur', () => {
          const values = splitValues(editor.value);
          if (values.length > 0) {
            addValues(wrapper, textarea, state, values);
            editor.value = '';
          }
        });

        textarea.classList.add('clinical-trials-gov-studies-query__source');
        textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);
        wrapper.appendChild(editor);
        renderChips(wrapper, textarea, state);
      });

      once('clinical-trials-gov-studies-query-fill', '.clinical-trials-gov-studies-query__fill-value', context).forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
          event.preventDefault();
          const formItem = trigger.closest('.js-form-item, .form-item');
          if (!formItem) {
            return;
          }

          const target = formItem.querySelector('textarea, input, select');
          if (!target) {
            return;
          }

          const value = trigger.getAttribute('data-clinical-trials-gov-value') || '';
          if (target.matches('textarea[data-clinical-trials-gov-multi-value="true"]')) {
            const wrapper = formItem.querySelector('.clinical-trials-gov-studies-query__multivalue');
            const editor = wrapper ? wrapper.querySelector('.clinical-trials-gov-studies-query__editor') : null;
            const state = {
              values: splitValues(target.value),
            };
            addValues(wrapper, target, state, splitValues(value));
            if (editor) {
              editor.focus();
            }
            return;
          }

          target.value = value;
          target.dispatchEvent(new Event('change', { bubbles: true }));
          target.focus();
        });
      });
    },
  };
})(Drupal, once);
