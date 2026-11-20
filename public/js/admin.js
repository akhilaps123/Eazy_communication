/*
 * Eazy Communication — admin panel behaviour (admin pages only).
 * Progressive enhancement: without JavaScript every field stays visible and
 * forms work normally. Hiding a field never removes it from the submission.
 */
(function () {
  'use strict';

  // ---------------------------------------------------------------------------
  // data-show-when="field=value1,value2" — show an element only when the named
  // form field (radio, select or input) has one of the listed values.
  // ---------------------------------------------------------------------------
  function fieldValue(form, name) {
    var fields = form.querySelectorAll('[name="' + name + '"]');
    for (var i = 0; i < fields.length; i++) {
      var f = fields[i];
      if (f.type === 'radio' || f.type === 'checkbox') {
        if (f.checked) return f.value;
      } else {
        return f.value;
      }
    }
    return '';
  }

  function applyConditions(form) {
    form.querySelectorAll('[data-show-when]').forEach(function (el) {
      var rule = el.getAttribute('data-show-when').split('=');
      var allowed = (rule[1] || '').split(',');
      el.hidden = allowed.indexOf(fieldValue(form, rule[0])) === -1;
    });
  }

  document.querySelectorAll('form').forEach(function (form) {
    if (!form.querySelector('[data-show-when]')) return;
    applyConditions(form);
    form.addEventListener('change', function () { applyConditions(form); });
  });

  // ---------------------------------------------------------------------------
  // Country picker: search box, "N selected" counter, selected highlighting.
  // ---------------------------------------------------------------------------
  document.querySelectorAll('[data-country-picker]').forEach(function (picker) {
    var search = picker.querySelector('[data-country-search]');
    var counter = picker.querySelector('[data-country-count]');
    var empty = picker.querySelector('[data-country-empty]');
    var options = picker.querySelectorAll('.adm-country-option');

    function refresh() {
      var selected = 0;
      options.forEach(function (option) {
        var checked = option.querySelector('input').checked;
        option.classList.toggle('is-selected', checked);
        if (checked) selected++;
      });
      if (counter) counter.textContent = selected + ' selected';
    }

    function filter() {
      var q = (search.value || '').trim().toLowerCase();
      var shown = 0;
      options.forEach(function (option) {
        var match = q === '' || option.getAttribute('data-country-name').indexOf(q) !== -1;
        option.hidden = !match;
        if (match) shown++;
      });
      if (empty) empty.hidden = shown !== 0;
    }

    if (search) {
      search.hidden = false;   // only useful with JavaScript
      search.addEventListener('input', filter);
      search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    }
    picker.addEventListener('change', refresh);
    refresh();
  });

  // ---------------------------------------------------------------------------
  // Provider bundle picker (create plan): searches available, unused bundles
  // and stores the chosen id. Without JavaScript the admin picks a bundle from
  // the Provider Bundles page ("Create plan" button) instead.
  // All text is inserted with textContent (never as HTML).
  // ---------------------------------------------------------------------------
  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  }

  document.querySelectorAll('[data-bundle-picker]').forEach(function (picker) {
    var url = picker.getAttribute('data-search-url');
    var search = picker.querySelector('[data-bundle-search]');
    var results = picker.querySelector('[data-bundle-results]');
    var hidden = picker.querySelector('[data-bundle-id]');
    var card = picker.querySelector('[data-bundle-card]');
    var timer = null;
    var sequence = 0;
    if (!url || !search || !results || !hidden || !card) return;

    search.hidden = false;

    function countries(list) {
      if (!list.length) return '';
      return list.slice(0, 5).join(', ') + (list.length > 5 ? ' +' + (list.length - 5) : '');
    }

    function showSelected(bundle) {
      card.textContent = '';
      card.classList.remove('is-invalid');
      var dl = el('dl', 'adm-dl adm-bundle-summary');
      [
        ['Bundle code', bundle.code],
        ['Description', bundle.description || '—'],
        ['Countries', countries(bundle.countries) || '—'],
        ['Data allowance', bundle.data],
        ['Validity', bundle.validity],
        ['Speed', bundle.speed || '—'],
        ['Provider cost', bundle.cost],
        ['Source', bundle.source],
      ].forEach(function (row) {
        dl.appendChild(el('dt', null, row[0]));
        dl.appendChild(el('dd', null, row[1]));
      });
      card.appendChild(dl);
    }

    function showResults(list) {
      results.textContent = '';
      if (!list.length) {
        results.appendChild(el('p', 'adm-hint', 'No available, unused bundle matches this search.'));
      }
      list.forEach(function (bundle) {
        var option = el('button', 'adm-bundle-option');
        option.type = 'button';
        option.appendChild(el('span', 'adm-code', bundle.code));
        option.appendChild(el('small', 'adm-cell-sub',
          [countries(bundle.countries), bundle.data, bundle.validity, bundle.cost, bundle.source].filter(Boolean).join(' · ')));
        option.addEventListener('click', function () {
          hidden.value = bundle.id;
          showSelected(bundle);
          results.hidden = true;
          search.value = '';
        });
        results.appendChild(option);
      });
      results.hidden = false;
    }

    function load() {
      var mine = ++sequence;
      fetch(url + '?q=' + encodeURIComponent(search.value.trim()), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      }).then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      }).then(function (json) {
        if (mine === sequence) showResults(json.data || []);
      }).catch(function () {
        if (mine !== sequence) return;
        results.textContent = '';
        results.appendChild(el('p', 'adm-error', 'Could not load provider bundles. Please try again.'));
        results.hidden = false;
      });
    }

    search.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(load, 250);
    });
    search.addEventListener('focus', function () { if (results.hidden) load(); });
    search.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') e.preventDefault();
      if (e.key === 'Escape') results.hidden = true;
    });
  });
})();
