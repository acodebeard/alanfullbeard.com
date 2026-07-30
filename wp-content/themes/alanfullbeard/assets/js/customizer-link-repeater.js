(function (customize) {
  'use strict';

  const maximumLinks = 20;

  function parseLinks(value) {
    try {
      const links = JSON.parse(value);

      return Array.isArray(links) ? links : [];
    } catch (error) {
      return [];
    }
  }

  function initializeRepeater(root) {
    if (root.dataset.initialized === 'true') {
      return;
    }

    const rows = root.querySelector('.alanfullbeard-link-repeater__rows');
    const template = root.querySelector('.alanfullbeard-link-repeater__template');
    const addButton = root.querySelector('.alanfullbeard-link-repeater__add');
    const valueInput = root.querySelector('.alanfullbeard-link-repeater__value');

    if (!rows || !template || !addButton || !valueInput) {
      return;
    }

    root.dataset.initialized = 'true';
    let nextIndex = 0;

    function updateAddButton() {
      addButton.disabled = rows.children.length >= maximumLinks;
    }

    function synchronizeValue() {
      const links = Array.from(rows.querySelectorAll('.alanfullbeard-link-repeater__row')).map((row) => ({
        label: row.querySelector('[data-link-field="label"]').value.trim(),
        url: row.querySelector('[data-link-field="url"]').value.trim(),
      }));

      valueInput.value = JSON.stringify(links);
      valueInput.dispatchEvent(new Event('change', { bubbles: true }));
      updateAddButton();
    }

    function appendRow(link) {
      if (rows.children.length >= maximumLinks) {
        return null;
      }

      const fragment = template.content.cloneNode(true);
      const row = fragment.querySelector('.alanfullbeard-link-repeater__row');
      const rowIndex = nextIndex;
      const controlId = root.dataset.controlId.replace(/[^a-zA-Z0-9_-]/g, '-');

      nextIndex += 1;

      ['label', 'url'].forEach((fieldName) => {
        const input = row.querySelector(`[data-link-field="${fieldName}"]`);
        const label = input.closest('label');
        const inputId = `${controlId}-${fieldName}-${rowIndex}`;

        input.id = inputId;
        input.value = typeof link[fieldName] === 'string' ? link[fieldName] : '';
        label.htmlFor = inputId;
        input.addEventListener('input', synchronizeValue);
      });

      const removeButton = row.querySelector('.alanfullbeard-link-repeater__remove');

      removeButton.addEventListener('click', function () {
        row.remove();
        synchronizeValue();
      });

      rows.appendChild(fragment);
      updateAddButton();

      return row;
    }

    parseLinks(valueInput.value).forEach((link) => appendRow(link));

    addButton.addEventListener('click', function () {
      const row = appendRow({});

      if (row) {
        row.querySelector('[data-link-field="label"]').focus();
      }
    });

    updateAddButton();
  }

  customize.bind('ready', function () {
    document.querySelectorAll('.alanfullbeard-link-repeater').forEach(initializeRepeater);
  });
})(wp.customize);
