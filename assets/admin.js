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
    var noSaveBar = ['presets', 'help', 'library', 'convert', 'usage'];

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
    /* `row` is the replacement class list for the wrapper. `cols` is tried
       in order against each direct child; the first match wins and '*'
       catches the rest. Both may be functions of the wrapper element, for
       grids whose column count depends on a modifier class. */
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
      },
      'sw-trust-grid': {
        row: 'row g-3',
        cols: function (grid) {
          var four = grid.classList.contains('sw-trust-grid--4');
          return [{ match: '*', col: 'col-12 col-sm-6 col-lg-' + (four ? '3' : '4') }];
        },
        strip: ['sw-trust-grid--3', 'sw-trust-grid--4'],
        equalHeight: true,
        note: 'Prvky důvěryhodnosti: mřížka .sw-trust-grid nahrazena Bootstrap row/col.'
      },
      'sw-process-grid': {
        row: 'row g-4',
        cols: [{ match: '*', col: 'col-12 col-md-6' }],
        equalHeight: true,
        note: 'Seznam kroků: mřížka .sw-process-grid nahrazena Bootstrap row/col.'
      },
      'sw-pricing-grid': {
        /* justify-content-center is what replaces the old grid hack that
           centred a lone third card across both columns. */
        row: 'row g-4 justify-content-center',
        cols: function (grid) {
          var four = grid.classList.contains('sw-pricing-grid--4');
          var base = 'col-12 col-md-6 col-xl-' + (four ? '3' : '4');
          return [
            // featured card goes first when the cards stack on mobile
            { match: '.sw-price-card--featured', col: base + ' order-first order-md-0' },
            { match: '*',                        col: base }
          ];
        },
        strip: ['sw-pricing-grid--4'],
        equalHeight: true,
        note: 'Ceník: mřížka .sw-pricing-grid nahrazena Bootstrap row/col.'
      },
      'sw-faq-layout': {
        row: 'row g-4 g-lg-5 align-items-start',
        cols: [
          { match: '.sw-faq-list',  col: 'col-12 col-lg-8' },
          { match: '.sw-faq-decor', col: 'col-12 col-lg-4 d-none d-lg-block' },
          { match: '*',             col: 'col-12' }
        ],
        note: 'Časté dotazy: mřížka .sw-faq-layout nahrazena Bootstrap row/col.'
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
          var cols = typeof rule.cols === 'function' ? rule.cols(grid) : rule.cols;
          var kids = Array.prototype.filter.call(grid.children, function (n) {
            return n.nodeType === 1;
          });

          kids.forEach(function (kid) {
            var colCls = null;
            for (var i = 0; i < cols.length; i++) {
              if (cols[i].match === '*' || kid.matches(cols[i].match)) {
                colCls = cols[i].col;
                break;
              }
            }
            if (!colCls) { return; }
            // Already wrapped by a previous run? Leave it be.
            if (/\bcol(-|$)/.test(kid.className)) { return; }

            var col = doc.createElement('div');
            col.className = colCls;

            /* role="listitem" has to sit on the direct child of the
               role="list", which is now the column — left on the card it
               would no longer be owned by the list. */
            if (kid.getAttribute('role') === 'listitem') {
              kid.removeAttribute('role');
              col.setAttribute('role', 'listitem');
            }
            /* CSS Grid stretched its items to equal height for free;
               inside a column a card has to ask for it. Only where the
               library's own markup does — a layout column must not get it. */
            if (rule.equalHeight && !kid.classList.contains('h-100')) {
              kid.classList.add('h-100');
            }

            /* Keep the output readable: reuse the indentation the card
               already sits at, one level deeper inside the new column. */
            var pad = (kid.previousSibling && kid.previousSibling.nodeType === 3)
              ? (kid.previousSibling.nodeValue.match(/[\t ]*$/) || [''])[0]
              : '';
            grid.insertBefore(col, kid);
            col.appendChild(doc.createTextNode('\n' + pad + '\t'));
            col.appendChild(kid);
            col.appendChild(doc.createTextNode('\n' + pad));
          });

          grid.classList.remove(cls);
          (rule.strip || []).forEach(function (c) { grid.classList.remove(c); });
          rule.row.split(' ').forEach(function (c) { grid.classList.add(c); });
          count(rule.note);
        });
      });

      /* Anything grid-shaped we don't have a rule for. Saying "nothing to
         convert" about markup we simply don't recognise would be a lie,
         and an expensive one — it reads as "this section is fine". */
      root.querySelectorAll('[class]').forEach(function (el) {
        el.classList.forEach(function (c) {
          if (!/^sw-.*-(grid|layout)$/.test(c)) { return; }
          if (GRIDS[c]) { return; }
          count('Pozor: našel jsem mřížku .' + c + ', pro kterou nemám pravidlo — nechal jsem ji beze změny. Dej vědět a doplním ji.');
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

      /* --- 5. version stamp ------------------------------------
         data-sw-c tells the usage screen exactly what a section is and
         which revision of the markup it carries — without it, all it can
         do is name the family. Stamped only where the family identifies
         one component: .sw-hero is shared by four, and a wrong stamp is
         worse than none, because the usage screen would then trust it. */
      var cat = hints.components || {};
      root.querySelectorAll('section[class]').forEach(function (sec) {
        if (sec.hasAttribute('data-sw-c')) {
          // Already stamped: bring the revision up to date, since this
          // conversion is what makes the markup current.
          var cur = (sec.getAttribute('data-sw-c').split('@')[0] || '').trim();
          if (cat[cur] && sec.getAttribute('data-sw-c') !== cur + '@' + cat[cur].version) {
            sec.setAttribute('data-sw-c', cur + '@' + cat[cur].version);
            count('Razítko .' + cur + ' zvednuto na @' + cat[cur].version + '.');
          }
          return;
        }

        var classes = sec.className.split(/\s+/);
        var hits = Object.keys(cat).filter(function (id) {
          return cat[id].section && classes.indexOf(cat[id].section) !== -1;
        });

        /* Same narrowing the usage screen does: the distinguishing class
           first, then the number of blocks. Both values are computed from
           the component files server-side (SWDS_Usage::catalogue), so
           only the two lines of matching live here. */
        if (hits.length > 1) {
          var byMark = hits.filter(function (id) {
            return cat[id].mark && sec.querySelector('.' + cat[id].mark);
          });
          if (byMark.length === 1) {
            hits = byMark;
          } else {
            if (byMark.length) { hits = byMark; }
            var blocks = sec.querySelectorAll('.lc-block').length;
            var byCard = hits.filter(function (id) { return Number(cat[id].cards) === blocks; });
            if (byCard.length === 1) { hits = byCard; }
          }
        }

        if (hits.length === 1) {
          sec.setAttribute('data-sw-c', hits[0] + '@' + cat[hits[0]].version);
          count('Doplněno razítko data-sw-c="' + hits[0] + '@' + cat[hits[0]].version + '" (podle něj pak obrazovka Použití pozná, co na stránce leží).');
        } else if (hits.length > 1) {
          count('Razítko jsem nedoplnil — tahle sekce může být ' + hits.join(' nebo ') +
                ' a z kódu se to nepozná. Dopiš prosím ručně data-sw-c="<ta správná>@' +
                cat[hits[0]].version + '" na <section>.');
        }
      });

      /* --- 6. tidy up ------------------------------------------ */
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

    /* ==============================================================
       Usage screen → converter

       Fetches one component occurrence's markup and drops it into the
       converter, already converted, so the comparison is in front of
       you in one click. Still nothing is written anywhere: the result
       goes back to the page only if you copy it there yourself.
       ============================================================== */
    $(document).on('click', '.swds-usage-load', function () {
      var btn = this;
      var $btn = $(btn);
      if ($btn.prop('disabled')) { return; }

      var orig = btn.textContent;
      $btn.prop('disabled', true);
      btn.textContent = 'Načítám…';

      $.getJSON(SWDS_ADMIN.ajax, {
        action: 'swds_section',
        nonce:  SWDS_ADMIN.nonce,
        post:   $btn.data('post'),
        index:  $btn.data('index')
      }).done(function (res) {
        if (!res || !res.success) {
          btn.textContent = 'Nepovedlo se';
          return;
        }
        $in.val(res.data.html);
        activate('convert');
        $('#swds-conv-run').trigger('click');
        $('html, body').animate({ scrollTop: $('#swds-conv-in').offset().top - 60 }, 200);
      }).fail(function (xhr) {
        var msg = (xhr && xhr.responseJSON && xhr.responseJSON.data) || 'Nepovedlo se';
        btn.textContent = String(msg).slice(0, 40);
      }).always(function () {
        setTimeout(function () {
          btn.textContent = orig;
          $btn.prop('disabled', false);
        }, 1800);
      });
    });
  });
})(jQuery);
