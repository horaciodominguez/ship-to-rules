/**
 * Destination Shop — frontend combobox + bar UX
 */
(function () {
  'use strict';

  function initCombobox(root) {
    var toggle = root.querySelector('[data-ds-combobox-toggle]');
    var panel = root.querySelector('[data-ds-combobox-panel]');
    var search = root.querySelector('[data-ds-combobox-search]');
    var list = root.querySelector('[data-ds-combobox-list]');
    var empty = root.querySelector('[data-ds-combobox-empty]');
    var input = root.querySelector('[data-ds-combobox-input]');
    var flagEl = root.querySelector('[data-ds-combobox-flag]');
    var labelEl = root.querySelector('[data-ds-combobox-label]');
    if (!toggle || !panel || !list || !input) return;

    function open() {
      root.classList.add('is-open');
      panel.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
      if (search) {
        search.value = '';
        filter('');
        search.focus();
      }
    }

    function close() {
      root.classList.remove('is-open');
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    }

    function filter(q) {
      q = (q || '').toLowerCase().trim();
      var visible = 0;
      list.querySelectorAll('li[role="option"]').forEach(function (li) {
        var name = (li.getAttribute('data-name') || li.textContent || '').toLowerCase();
        var value = (li.getAttribute('data-value') || '').toLowerCase();
        var show = !q || name.indexOf(q) !== -1 || value.indexOf(q) !== -1;
        li.hidden = !show;
        if (show) visible++;
      });
      if (empty) empty.hidden = visible > 0;
    }

    function select(li) {
      var value = li.getAttribute('data-value') || '';
      var name = li.getAttribute('data-name') || li.textContent.trim();
      var flag = li.getAttribute('data-flag') || (value ? '•' : '🌐');
      input.value = value;
      if (flagEl) flagEl.textContent = flag;
      if (labelEl) labelEl.textContent = name;
      list.querySelectorAll('li[role="option"]').forEach(function (el) {
        el.setAttribute('aria-selected', el === li ? 'true' : 'false');
      });
      close();
      toggle.focus();
    }

    toggle.addEventListener('click', function () {
      if (panel.hidden) open();
      else close();
    });

    if (search) {
      search.addEventListener('input', function () {
        filter(search.value);
      });
      search.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          close();
          toggle.focus();
        }
      });
    }

    list.addEventListener('click', function (e) {
      var li = e.target.closest('li[role="option"]');
      if (li) select(li);
    });

    document.addEventListener('click', function (e) {
      if (!root.contains(e.target)) close();
    });

    toggle.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        open();
      }
    });
  }

  function initBars() {
    document.querySelectorAll('[data-ds-bar]').forEach(function (form) {
      var combo = form.querySelector('[data-ds-combobox]');
      if (combo) initCombobox(combo);

      form.addEventListener('submit', function () {
        form.classList.add('is-loading');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBars);
  } else {
    initBars();
  }
})();
