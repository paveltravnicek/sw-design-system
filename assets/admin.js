/* SW Design System — admin UI */
(function ($) {
  'use strict';

  $(function () {
    // Color pickers
    if ($.fn.wpColorPicker) {
      $('.swds-color').wpColorPicker();
    }

    var $tabs    = $('.swds-tab');
    var $panels  = $('.swds-panel');
    var $actions = $('.swds-actions');
    // Panels where the Save/Reset bar makes no sense
    var noSaveBar = ['presets', 'help', 'library', 'convert'];

    function activate(tabKey) {
      $tabs.removeClass('swds-tab-active');
      $panels.removeClass('swds-panel-active');
      $('.swds-tab[data-tab="' + tabKey + '"]').addClass('swds-tab-active');
      $('.swds-panel[data-panel="' + tabKey + '"]').addClass('swds-panel-active');
      $actions.toggleClass('is-hidden', noSaveBar.indexOf(tabKey) !== -1);
      // remember tab across save-reloads
      try { sessionStorage.setItem('swdsTab', tabKey); } catch (e) {}
    }

    $tabs.on('click', function () {
      activate($(this).data('tab'));
    });

    // Restore last tab
    var saved = null;
    try { saved = sessionStorage.getItem('swdsTab'); } catch (e) {}
    if (saved && $('.swds-tab[data-tab="' + saved + '"]').length) {
      activate(saved);
    } else {
      activate($tabs.first().data('tab'));
    }

    // Copy buttons (library + anywhere with .swds-copy)
    $('.swds-copy').on('click', function () {
      var id = $(this).data('target');
      var el = document.getElementById(id);
      if (!el) { return; }
      el.select();
      el.setSelectionRange(0, 99999);
      var btn = this;
      var done = function () {
        var orig = btn.textContent;
        btn.textContent = 'Zkopírováno ✓';
        setTimeout(function () { btn.textContent = orig; }, 1500);
      };
      if (navigator.clipboard) {
        navigator.clipboard.writeText(el.value).then(done, function () {
          document.execCommand('copy'); done();
        });
      } else {
        document.execCommand('copy'); done();
      }
    });

    // Library: collapsible categories
    $(document).on('click', '[data-cat-toggle]', function () {
      $(this).closest('[data-cat]').toggleClass('is-open');
    });

    /* ==============================================================
       Converter — old section HTML → current shape

       Everything happens in this browser tab. Nothing is posted,
       nothing is saved, the page content is never touched: the user
       copies the result out and pastes it into LiveCanvas themselves.

       The lc-block / editable rules come from the JSON blob printed
       by SWDS_Library::lc_hints(), which reads them out of the
       bundled component files. So this code knows nothing about any
       particular component — add a component, and the converter
       learns its slots with no edit here.

       The layout rules below ARE specific, because they encode a
       one-off migration (CSS Grid wrappers → Bootstrap row/col) and
       there are only three of them.
       ============================================================== */
    var GRIDS = {
      'sw-hero-grid': {
        row: 'row g-4 g-lg-5 align-items-center',
        cols: [
          { match: '.sw-hero-copy', col: 'col-12 col-lg-5' },
          { match: '*',             col: 'col-12 col-lg-7' }
        ],
        note: 'Hero: mřížka .sw-hero-grid nahrazena Bootstrap row/col.'
      },
      'sw-content2img-grid': {
        row: 'row align-items-center g-4 g-lg-5',
        cols: [
          { match: '.sw-content2img-copy',  col: 'col-12 col-lg-5' },
          { match: '.sw-content2img-stack', col: 'col-12 col-lg-7' },
          { match: '*',                     col: 'col-12 col-lg-6' }
        ],
        note: 'Obsah + 2 obrázky: mřížka .sw-content2img-grid nahrazena Bootstrap row/col.'
      },
      'sw-hub-grid': {
        row: 'row g-4',
        cols: [
          { match: '.sw-hub-card--plain', col: 'col-12' },
          { match: '*',                   col: 'col-12 col-md-6' }
        ],
        note: 'Rozcestník: mřížka .sw-hub-grid nahrazena Bootstrap row/col.'
      }
    };

    function getHints() {
      var el = document.getElementById('swds-lc-hints');
      if (!el) { return { block: [], editable: {} }; }
      try { return JSON.parse(el.textContent); }
      catch (e) { return { block: [], editable: {} }; }
    }

    function convert(html) {
      var hints = getHints();
      var log = [];
      var count = function (key) {
        for (var i = 0; i < log.length; i++) {
          if (log[i].key === key) { log[i].n++; return; }
        }
        log.push({ key: key, n: 1 });
      };

      var doc = new DOMParser().parseFromString(
        '<div id="swds-conv-root">' + html + '</div>', 'text/html');
      var root = doc.getElementById('swds-conv-root');
      if (!root) { return { html: html, log: [{ key: 'HTML se nepodařilo zpracovat.', n: 0 }] }; }

      /* --- 1. runtime leftovers -------------------------------- */
      root.querySelectorAll('[data-sw-init]').forEach(function (el) {
        el.removeAttribute('data-sw-init');
        count('Odstraněn atribut data-sw-init (zablokoval by animaci hero sekce).');
      });
      ['sw-rv', 'sw-anim-on', 'is-visible'].forEach(function (c) {
        root.querySelectorAll('.' + c).forEach(function (el) {
          el.classList.remove(c);
          count('Odstraněna runtime třída .' + c + ' zapsaná do kódu.');
        });
      });
      root.querySelectorAll('.sw-reveal-item.is-in').forEach(function (el) {
        el.classList.remove('is-in');
        count('Odstraněna runtime třída .is-in zapsaná do kódu.');
      });

      /* --- 2. the dead wrapper class --------------------------- */
      root.querySelectorAll('.sw-container').forEach(function (el) {
        el.classList.remove('sw-container');
        el.classList.add('container');
        count('.sw-container → .container (pro .sw-container nikdy neexistovalo žádné CSS, sekce byla přes celou šířku).');
      });

      /* --- 3. CSS Grid wrappers → Bootstrap row/col ------------- */
      Object.keys(GRIDS).forEach(function (cls) {
        var rule = GRIDS[cls];
        root.querySelectorAll('.' + cls).forEach(function (grid) {
          var kids = Array.prototype.filter.call(grid.children, function (n) {
            return n.nodeType === 1;
          });

          kids.forEach(function (kid) {
            var colCls = null;
            for (var i = 0; i < rule.cols.length; i++) {
              var m = rule.cols[i];
              if (m.match === '*' || kid.matches(m.match)) { colCls = m.col; break; }
            }
            if (!colCls) { return; }
            // Already wrapped by a previous run? Leave it be.
            if (/\bcol(-|$)/.test(kid.className)) { return; }
            var col = doc.createElement('div');
            col.className = colCls;
            grid.insertBefore(col, kid);
            col.appendChild(kid);
          });

          grid.classList.remove(cls);
          rule.row.split(' ').forEach(function (c) { grid.classList.add(c); });
          count(rule.note);
        });
      });

      /* --- 4. LiveCanvas hooks, from the component library ------ */
      (hints.block || []).forEach(function (cls) {
        root.querySelectorAll('.' + cls).forEach(function (el) {
          if (el.classList.contains('lc-block')) { return; }
          el.classList.add('lc-block');
          count('Doplněna třída lc-block na .' + cls + ' (jinak ji v LiveCanvasu nejde vybrat jako blok).');
        });
      });

      Object.keys(hints.editable || {}).forEach(function (cls) {
        var mode = hints.editable[cls];
        root.querySelectorAll('.' + cls).forEach(function (el) {
          // Never override a choice already in the markup.
          if (el.hasAttribute('editable')) { return; }
          el.setAttribute('editable', mode);
          count('Doplněno editable="' + mode + '" na .' + cls + '.');
        });
      });

      /* --- 5. tidy up ------------------------------------------ */
      root.querySelectorAll('[class=""]').forEach(function (el) {
        el.removeAttribute('class');
      });

      return { html: root.innerHTML, log: log };
    }

    var $in  = $('#swds-conv-in');
    var $out = $('#swds-conv-out');
    var $rep = $('#swds-conv-report');

    $('#swds-conv-run').on('click', function () {
      var src = String($in.val() || '').trim();
      if (!src) {
        $out.val('');
        $rep.attr('hidden', true).empty();
        return;
      }

      var res = convert(src);
      $out.val(res.html);

      var html;
      if (!res.log.length) {
        html = '<p class="swds-conv-ok">Nic k převodu — tahle sekce už je v současné podobě.</p>';
      } else {
        html = '<p class="swds-conv-ok"><strong>Provedené změny:</strong></p><ul>';
        res.log.forEach(function (l) {
          html += '<li>' + $('<div>').text(l.key).html() +
                  (l.n > 1 ? ' <em>(' + l.n + '×)</em>' : '') + '</li>';
        });
        html += '</ul><p class="swds-conv-warn">Porovnejte výsledek, než ho vložíte zpět — nástroj mění strukturu, ne obsah, ale zkontrolovat se to vyplatí.</p>';
      }
      $rep.html(html).removeAttr('hidden');
    });

    $('#swds-conv-clear').on('click', function () {
      $in.val('');
      $out.val('');
      $rep.attr('hidden', true).empty();
    });
  });
})(jQuery);
