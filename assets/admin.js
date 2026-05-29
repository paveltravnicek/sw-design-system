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
    var noSaveBar = ['presets', 'help', 'library'];

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
  });
})(jQuery);
