/**
 * Destination Shop — admin helpers
 *
 * Destination checkboxes are UI only. A hidden JSON payload is synced on every
 * change and submitted even when WooCommerce disables inputs in inactive tabs.
 */
(function () {
  'use strict';

  function syncPayload(list, payload, count) {
    if (!payload) return;
    var ids = [];
    if (list) {
      list.querySelectorAll('.ds-dest-checkbox:checked').forEach(function (cb) {
        var id = parseInt(cb.value, 10);
        if (id) ids.push(id);
      });
    }
    payload.value = JSON.stringify(ids);
    payload.disabled = false;
    if (count) {
      count.textContent = ids.length + ' selected';
    }
  }

  function initProductChecklist() {
    var filter = document.querySelector('.ds-dest-filter');
    var list = document.querySelector('[data-ds-dest-list]');
    var count = document.querySelector('.ds-dest-count');
    var payload = document.querySelector('[data-ds-dest-payload]');
    if (!list || !payload) return;

    function refresh() {
      syncPayload(list, payload, count);
    }

    if (filter) {
      filter.addEventListener('input', function () {
        var q = filter.value.toLowerCase().trim();
        list.querySelectorAll('.ds-dest-item').forEach(function (item) {
          var name = item.getAttribute('data-name') || '';
          item.classList.toggle('is-hidden', q && name.indexOf(q) === -1);
        });
      });
    }

    list.addEventListener('change', refresh);
    refresh();
  }

  function initBulkPanel() {
    var panel = document.getElementById('ds-bulk-destinations');
    var selector = document.getElementById('bulk-action-selector-top');
    var selector2 = document.getElementById('bulk-action-selector-bottom');
    if (!panel) return;

    function sync() {
      var val = (selector && selector.value) || '';
      var val2 = (selector2 && selector2.value) || '';
      var show = val === 'ds_assign_destinations' || val2 === 'ds_assign_destinations';
      panel.hidden = !show;
    }

    if (selector) selector.addEventListener('change', sync);
    if (selector2) selector2.addEventListener('change', sync);

    var tablenav = document.querySelector('.tablenav.top');
    if (tablenav && panel.parentNode !== tablenav) {
      tablenav.appendChild(panel);
    }
    sync();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initProductChecklist();
    initBulkPanel();

    var form = document.getElementById('post');
    if (form) {
      form.addEventListener('submit', function () {
        var list = document.querySelector('[data-ds-dest-list]');
        var payload = document.querySelector('[data-ds-dest-payload]');
        var count = document.querySelector('.ds-dest-count');
        syncPayload(list, payload, count);

        document.querySelectorAll(
          '[data-ds-dest-payload], input[name="ds_product_destinations_nonce"], input[name="ds_product_destinations_posted"], .ds-dest-checkbox'
        ).forEach(function (el) {
          el.disabled = false;
        });
      });
    }
  });
})();
