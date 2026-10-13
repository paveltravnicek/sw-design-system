/* ================================================================
   SW Design System — runtime

   Loaded once on every page. Safe to load twice, safe to re-run.

   Two rules this file must keep. Both were broken once and each cost
   a day, so they are written down:

   RULE 1 — Never leave an element hidden that only JS can un-hide.
     Anything this script hides, it must also own the job of showing
     again. Content is visible by default; hiding is opt-in, per
     element, and released as soon as the element reveals. Earlier
     versions hid every .sw-reveal-item via a class on <html> that
     stayed up for the life of the page — so when LiveCanvas
     re-rendered a block from its source after an edit, the fresh
     nodes matched the hiding rule, no observer knew about them, and
     the whole component vanished for good.

   RULE 2 — Never write runtime state into the DOM.
     A page builder saves the page by reading the DOM, so every class
     or attribute this script adds can end up in the database. The
     old per-element guard data-sw-init="1" did exactly that: once
     saved into a page, it permanently disabled the parallax on it.
     Per-element state lives in the WeakSets below instead. The one
     class we do add (.sw-rv) is removed again on reveal, and any
     copy that got saved anyway is stripped at startup.

   What it does:
     - Reveal      : fades .sw-reveal-item children of [data-sw-reveal]
                     in as they scroll into view.
     - Parallax    : [data-sw-parallax] tilts .sw-browser / .sw-phone
                     with the pointer. Desktop + fine pointer only.
     - Accordion   : [data-sw-accordion] with .sw-faq-item children.
     - Navbar offs.: measures .navbar into --sw-navbar-h for sticky.
     - Slider      : .sw-testimonials-slider arrows + edge fades.
   ================================================================ */
(function () {
  'use strict';

  var D = document;
  var H = D.documentElement;

  /* If a previous copy of this file already set itself up, just re-run
     its init instead of building a second runtime alongside the first. */
  if (window.__swDS && typeof window.__swDS.init === 'function') {
    window.__swDS.init();
    return;
  }

  var noMotion   = window.matchMedia('(prefers-reduced-motion: reduce)');
  var parallaxOK = window.matchMedia('(min-width: 992px) and (pointer: fine)');
  var hoverOK    = window.matchMedia('(hover: hover) and (pointer: fine)');

  /* Per-element state — see RULE 2. Nothing here touches the DOM. */
  var wired   = new WeakSet();   // widgets already given their listeners
  var pending = new WeakSet();   // reveal items the observer is waiting on
  var shown   = new WeakSet();   // reveal items already dealt with
  var io      = null;

  /* ----------------------------------------------------------------
     Editor detection

     Inside a builder there is nothing to gain from entrance
     animations and plenty to lose: the preview re-renders on every
     keystroke, so anything we hide risks staying hidden, and anything
     we add to the DOM risks being saved into the page.

     Being in an iframe is treated as "editor" on its own. That covers
     LiveCanvas, the WordPress customizer preview and any other
     embed — none of which need the animation — without having to
     recognise each one. The explicit LiveCanvas checks are a second
     signal in case it ever previews outside an iframe.
     ---------------------------------------------------------------- */
  var inFrame = (function () {
    try { return window.self !== window.top; } catch (e) { return true; }
  })();

  function looksLikeLiveCanvas() {
    try {
      if (/[?&](lc-|lc_|livecanvas)/i.test(location.search)) return true;
      if (window.lc_version || window.livecanvas || window.LiveCanvas) return true;
      if (D.body && /\blc-(editor|editing|preview)\b/.test(D.body.className)) return true;
      if (inFrame) {
        // Same WordPress origin, so the parent document is readable.
        var pd = window.parent.document;
        if (pd && (pd.getElementById('lc-main') ||
                   pd.getElementById('previewiframe') ||
                   pd.querySelector('#lc-editor, .lc-editor, [id^="livecanvas"]'))) return true;
      }
    } catch (e) { /* cross-origin parent — not our editor */ }
    return false;
  }

  var EDITOR = inFrame || looksLikeLiveCanvas();

  /* ----------------------------------------------------------------
     Strip runtime state that got saved into the markup (RULE 2)
     ---------------------------------------------------------------- */
  function sanitize(root) {
    var i, n;

    // Left over from the version that used a DOM attribute as its
    // init guard. If this is still in a saved page, the widget it
    // belongs to never initialises.
    n = root.querySelectorAll('[data-sw-init]');
    for (i = 0; i < n.length; i++) n[i].removeAttribute('data-sw-init');

    // A reveal item can be saved mid-animation, still carrying .sw-rv.
    // Nobody is observing that copy, so it has to come off or the item
    // stays invisible — RULE 1.
    n = root.querySelectorAll('.sw-rv');
    for (i = 0; i < n.length; i++) {
      if (!pending.has(n[i])) n[i].classList.remove('sw-rv');
    }

    // Dead classes from older runtimes. Harmless (no CSS matches them)
    // but they clutter every saved page, so clear them where it is
    // free to do so — in the editor, whose next save writes the
    // cleaned-up markup back.
    if (EDITOR) {
      n = root.querySelectorAll('.sw-anim-on, .is-visible');
      for (i = 0; i < n.length; i++) n[i].classList.remove('sw-anim-on', 'is-visible');
      n = root.querySelectorAll('.sw-reveal-item.is-in');
      for (i = 0; i < n.length; i++) n[i].classList.remove('is-in');
    }
  }

  /* Is this element parked outside the visible box of a horizontally
     scrolling ancestor? The testimonials track is one: the cards beyond
     its right edge are clipped, so they never enter the page viewport
     and an observer watching for that would wait forever. Those cards
     must not be hidden — they would sit at opacity 0 until somebody
     happened to slide them in. The track has its own edge fade for
     exactly this job. */
  function parkedInScroller(el) {
    var n = el.parentElement;
    while (n && n !== D.body) {
      var ox = getComputedStyle(n).overflowX;
      if (ox === 'auto' || ox === 'scroll') {
        var a = el.getBoundingClientRect();
        var b = n.getBoundingClientRect();
        return a.left >= b.right - 1 || a.right <= b.left + 1;
      }
      n = n.parentElement;
    }
    return false;
  }

  /* ----------------------------------------------------------------
     Reveal on enter
     ---------------------------------------------------------------- */
  function initReveal(root) {
    var containers = root.querySelectorAll('[data-sw-reveal]');

    /* No animation wanted, not possible, or we are in a builder:
       drop the pre-paint flag and leave everything visible. */
    if (!containers.length || EDITOR || noMotion.matches ||
        !('IntersectionObserver' in window)) {
      H.classList.remove('sw-ds-reveal');
      return;
    }

    if (!io) {
      io = new IntersectionObserver(function (entries, obs) {
        for (var i = 0; i < entries.length; i++) {
          if (!entries[i].isIntersecting) continue;
          var el = entries[i].target;
          el.classList.remove('sw-rv');   // releasing the flag reveals it
          pending.delete(el);
          shown.add(el);
          obs.unobserve(el);
        }
      }, { root: null, threshold: 0, rootMargin: '0px 0px -12% 0px' });
    }

    /* Only the FIRST pass is allowed to hide anything.

       On that pass the pre-paint flag is still up, so the items are
       already hidden and claiming them changes nothing on screen. Any
       later pass means content appeared after load — a builder swapping
       a block, or an AJAX insert. Those items are shown as they are.

       This is deliberate and it is the thing that makes the disappearing
       bug structurally impossible rather than merely fixed. Hiding
       freshly inserted content is the only way an element can end up
       invisible with nobody watching it, and the editor detection above
       is a heuristic — if it ever misses, this rule still holds. The
       only thing given up is an entrance animation on content inserted
       after load, which has no flash to prevent in the first place. */
    var firstPass = H.classList.contains('sw-ds-reveal');

    for (var c = 0; c < containers.length; c++) {
      var items = containers[c].querySelectorAll('.sw-reveal-item');
      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        if (pending.has(item) || shown.has(item)) continue;

        if (!firstPass || parkedInScroller(item)) {
          item.classList.remove('sw-rv');   // in case it was copied in
          shown.add(item);
          continue;
        }
        item.classList.add('sw-rv');
        pending.add(item);
        io.observe(item);
      }
    }

    /* Every item found is now flagged individually, so the global
       pre-paint flag has done its job. Taking it off here is what makes
       RULE 1 hold: from this point on, a node that appears in the DOM
       without our per-item flag is visible. Runs in the same task as
       the loop above, so no frame is painted in between. */
    H.classList.remove('sw-ds-reveal');
  }

  /* ----------------------------------------------------------------
     Parallax stages
     ---------------------------------------------------------------- */
  function initParallax(root) {
    var stages = root.querySelectorAll('[data-sw-parallax]');
    for (var i = 0; i < stages.length; i++) {
      (function (stage) {
        if (wired.has(stage)) return;

        var browser = stage.querySelector('.sw-browser');
        var phone   = stage.querySelector('.sw-phone');
        if (!browser || !phone) return;
        wired.add(stage);

        var onMove = function (e) {
          if (!parallaxOK.matches || noMotion.matches) return;
          var r = stage.getBoundingClientRect();
          var x = (e.clientX - r.left) / r.width  - 0.5;
          var y = (e.clientY - r.top)  / r.height - 0.5;
          browser.style.transform =
            'rotateX(' + (7 - y * 4) + 'deg) rotateY(' + (-10 + x * 6) +
            'deg) rotateZ(' + (1 + x * 1.2) + 'deg) translateY(' + (-Math.abs(x) * 6) + 'px)';
          phone.style.transform =
            'rotateZ(' + (4 - x * 5) + 'deg) translateZ(60px) translate(' +
            (x * 14) + 'px, ' + (y * 10) + 'px)';
        };
        var reset = function () {
          browser.style.transform = '';
          phone.style.transform   = '';
        };

        stage.addEventListener('pointermove',  onMove);
        stage.addEventListener('pointerleave', reset);
        parallaxOK.addEventListener('change', function (e) { if (!e.matches) reset(); });
      })(stages[i]);
    }
  }

  /* ----------------------------------------------------------------
     FAQ accordion — one open at a time, height animated via max-height
     ---------------------------------------------------------------- */
  function initAccordions(root) {
    var accs = root.querySelectorAll('[data-sw-accordion]');
    for (var a = 0; a < accs.length; a++) {
      (function (acc) {
        if (wired.has(acc)) return;
        wired.add(acc);

        var items = acc.querySelectorAll('.sw-faq-item');

        var close = function (item) {
          var btn   = item.querySelector('.sw-faq-trigger');
          var panel = item.querySelector('.sw-faq-a');
          if (!btn || !panel) return;
          btn.setAttribute('aria-expanded', 'false');
          item.classList.remove('is-open');
          panel.style.maxHeight = '';
        };
        var open = function (item) {
          var btn   = item.querySelector('.sw-faq-trigger');
          var panel = item.querySelector('.sw-faq-a');
          if (!btn || !panel) return;
          btn.setAttribute('aria-expanded', 'true');
          item.classList.add('is-open');
          panel.style.maxHeight = panel.scrollHeight + 'px';
        };

        for (var i = 0; i < items.length; i++) {
          (function (item) {
            var btn = item.querySelector('.sw-faq-trigger');
            if (!btn) return;
            btn.addEventListener('click', function () {
              var wasOpen = item.classList.contains('is-open');
              for (var j = 0; j < items.length; j++) close(items[j]);
              if (!wasOpen) open(item);
            });
          })(items[i]);
        }

        window.addEventListener('resize', function () {
          var openPanel = acc.querySelector('.sw-faq-item.is-open .sw-faq-a');
          if (openPanel) openPanel.style.maxHeight = openPanel.scrollHeight + 'px';
        });
      })(accs[a]);
    }
  }

  /* ----------------------------------------------------------------
     Fixed/sticky navbar offset

     Sticky elements (see .sw-journey-sticky) need to stop below a fixed
     header instead of sliding under it. Measuring the navbar at runtime
     instead of hardcoding a pixel value means this survives a header
     redesign with no CSS edit. No .navbar on the page → stays 0.
     ---------------------------------------------------------------- */
  function updateNavbarOffset() {
    var nav = D.querySelector('.navbar');
    var h = nav ? Math.ceil(nav.getBoundingClientRect().height) : 0;
    H.style.setProperty('--sw-navbar-h', h + 'px');
  }

  /* ----------------------------------------------------------------
     Testimonials slider

     The track is a native scroll-snap container — it already works by
     touch, trackpad and keyboard with no JS. The arrows are a
     convenience on top: nudge scrollLeft by one card, auto-disable at
     each end.
     ---------------------------------------------------------------- */
  function initSliders(root) {
    var sliders = root.querySelectorAll('.sw-testimonials-slider');
    for (var s = 0; s < sliders.length; s++) {
      (function (slider) {
        if (wired.has(slider)) return;

        var track = slider.querySelector('.sw-testimonials-track');
        var prev  = slider.querySelector('.sw-testimonials-arrow--prev');
        var next  = slider.querySelector('.sw-testimonials-arrow--next');
        if (!track || !prev || !next) return;
        wired.add(slider);

        var cardStep = function () {
          var card = track.querySelector('.sw-testimonial-card');
          if (!card) return track.clientWidth * 0.8;
          var style = getComputedStyle(track);
          return card.getBoundingClientRect().width + parseFloat(style.gap || '0');
        };

        prev.addEventListener('click', function () {
          track.scrollBy({ left: -cardStep(), behavior: 'smooth' });
        });
        next.addEventListener('click', function () {
          track.scrollBy({ left: cardStep(), behavior: 'smooth' });
        });

        /* Hover is tracked in JS (.is-hover) rather than left to CSS
           :hover — see the note by .sw-testimonials-arrow.is-hover in
           the stylesheet. Only wired on devices with a real pointer:
           a touchscreen has no hover to leave with, so the class would
           look stuck on after a tap. */
        if (hoverOK.matches) {
          [prev, next].forEach(function (btn) {
            btn.addEventListener('mouseenter', function () {
              if (!btn.disabled) btn.classList.add('is-hover');
            });
            btn.addEventListener('mouseleave', function () {
              btn.classList.remove('is-hover');
            });
          });
        }

        var updateArrows = function () {
          var max = track.scrollWidth - track.clientWidth - 1;
          var atStart = track.scrollLeft <= 4;
          var atEnd = max <= 0 || track.scrollLeft >= max - 4;
          prev.disabled = atStart;
          next.disabled = atEnd;
          // No fade toward a side with nothing left to reveal — see the
          // .is-at-start / .is-at-end rules in the stylesheet.
          track.classList.toggle('is-at-start', atStart);
          track.classList.toggle('is-at-end', atEnd);
          // A click can disable a button while the mouse never leaves
          // it, so clear the hover class here rather than waiting on a
          // mouseleave that may never fire.
          if (prev.disabled) prev.classList.remove('is-hover');
          if (next.disabled) next.classList.remove('is-hover');
        };
        updateArrows();

        var scrollTimer;
        track.addEventListener('scroll', function () {
          clearTimeout(scrollTimer);
          scrollTimer = setTimeout(updateArrows, 80);
        });
        window.addEventListener('resize', updateArrows);
      })(sliders[s]);
    }
  }

  /* ----------------------------------------------------------------
     Boot / re-boot

     Everything above is idempotent and takes a root, so this can run
     as many times as the page changes shape. That is what keeps the
     widgets alive in a builder: after an edit replaces a block, the
     replacement gets its listeners back instead of going dead.
     ---------------------------------------------------------------- */
  function boot(root) {
    var scope = root || D;
    sanitize(scope);
    initReveal(scope);
    initParallax(scope);
    initAccordions(scope);
    initSliders(scope);
    updateNavbarOffset();
  }

  boot(D);

  /* Re-measure the navbar on resize (debounced — it fires a lot). */
  var navTimer;
  window.addEventListener('resize', function () {
    clearTimeout(navTimer);
    navTimer = setTimeout(updateNavbarOffset, 150);
  });

  /* ----------------------------------------------------------------
     Watch for content appearing after load

     A builder swapping a block, or anything loading markup by AJAX,
     produces nodes this runtime has never seen. Re-booting over them
     reconnects the widgets and — on a first-ever pass — arms the new
     reveal items. We only listen for childList: this script never
     inserts nodes, so there is no feedback loop.
     ---------------------------------------------------------------- */
  var WATCH = '[data-sw-reveal], .sw-reveal-item, [data-sw-parallax],' +
              '[data-sw-accordion], .sw-testimonials-slider';

  if ('MutationObserver' in window) {
    var rescanTimer;
    var rescan = function () {
      clearTimeout(rescanTimer);
      rescanTimer = setTimeout(function () { boot(D); }, 150);
    };

    new MutationObserver(function (records) {
      for (var i = 0; i < records.length; i++) {
        var added = records[i].addedNodes;
        for (var j = 0; j < added.length; j++) {
          var n = added[j];
          if (n.nodeType !== 1) continue;
          if ((n.matches && n.matches(WATCH)) ||
              (n.querySelector && n.querySelector(WATCH))) {
            rescan();
            return;
          }
        }
      }
    }).observe(H, { childList: true, subtree: true });
  }

  /* Public handle. Kept truthy because the inline head script checks
     window.__swDS to decide whether its failsafe needs to fire. */
  window.__swDS = {
    version: '2.20.9',
    editor: EDITOR,
    init: boot
  };
})();
