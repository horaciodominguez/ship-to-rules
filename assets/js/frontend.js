/**
 * Ship-To Rules — frontend combobox + context UX
 */
(function () {
  'use strict';

  function navigateToDestination(slug) {
    if (!window.STR_VARS) return;
    var url = new URL(window.location.href);
    if (slug) {
      url.searchParams.set(STR_VARS.queryVar, slug);
    } else {
      url.searchParams.set(STR_VARS.queryVar, '');
    }
    window.location.href = url.toString();
  }

  function initCombobox(root) {
    var toggle = root.querySelector('[data-str-combobox-toggle]');
    var panel = root.querySelector('[data-str-combobox-panel]');
    var search = root.querySelector('[data-str-combobox-search]');
    var list = root.querySelector('[data-str-combobox-list]');
    var empty = root.querySelector('[data-str-combobox-empty]');
    var input = root.querySelector('[data-str-combobox-input]');
    var flagEl = root.querySelector('[data-str-combobox-flag]');
    var labelEl = root.querySelector('[data-str-combobox-label]');
    var instant = root.hasAttribute('data-str-instant');
    if (!toggle || !panel || !list) return;

    function open() {
      root.classList.add('is-open');
      panel.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
      var host = root.closest('.str-context, .str-picker, .str-hint, .str-bar');
      if (host) {
        host.classList.add('str-has-open-combobox');
      }
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
      var host = root.closest('.str-context, .str-picker, .str-hint, .str-bar');
      if (host) {
        host.classList.remove('str-has-open-combobox');
      }
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

      if (instant) {
        navigateToDestination(value);
        return;
      }

      if (input) input.value = value;
      if (flagEl) flagEl.textContent = flag;
      if (labelEl) labelEl.textContent = name;
      list.querySelectorAll('li[role="option"]').forEach(function (el) {
        el.setAttribute('aria-selected', el === li ? 'true' : 'false');
      });
      close();
      toggle.focus();
    }

    // Force closed on init — theme CSS often overrides [hidden] when panel uses display:flex.
    close();

    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (root.classList.contains('is-open')) {
        close();
      } else {
        open();
      }
    });

    panel.addEventListener('click', function (e) {
      e.stopPropagation();
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
      if (!root.contains(e.target)) {
        close();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && root.classList.contains('is-open')) {
        close();
        toggle.focus();
      }
    });

    toggle.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        open();
      }
    });
  }

  function init() {
    document.querySelectorAll('[data-str-combobox]').forEach(initCombobox);
    document.querySelectorAll('[data-str-clear]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        navigateToDestination('');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
