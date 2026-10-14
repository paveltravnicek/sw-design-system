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
     - Header      : .sw-header hides on scroll down, returns on up.
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

  /* True when movement is unwanted — either the visitor's own system
     setting, or the Motion switch in this plugin's admin, which reaches
     us as --sw-motion: 0. Checked on every boot rather than cached, so a
     settings change takes effect on the next page load with no stale
     copy of the answer sitting around. */
  function motionOff() {
    if (noMotion.matches) { return true; }
    try {
      return getComputedStyle(H).getPropertyValue('--sw-motion').trim() === '0';
    } catch (e) {
      return false;
    }
  }
  var parallaxOK = window.matchMedia('(min-width: 992px) and (pointer: fine)');
  var hoverOK    = window.matchMedia('(hover: hover) and (pointer: fine)');

  /* Per-element state — see RULE 2. Nothing here touches the DOM. */
  var wired   = new WeakSet();   // widgets already given their listeners
  var pending = new WeakSet();   // reveal items the observer is waiting on
  var shown   = new WeakSet();   // reveal items already dealt with
  var io      = null;
  var idleIO        = null;        // pauses the looping decorations off screen
  var watchedStages = new WeakSet();

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
    if (!containers.length || EDITOR || motionOff() ||
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
          if (!parallaxOK.matches || motionOff()) return;
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

    /* The header is fixed, so it holds no space of its own and the body
       reserves it instead (see body:has(.sw-header) in the CSS). Measure
       it rather than trusting the fallback: the bar's height moves with
       the logo, the font and the breakpoint. Measured with the bar in
       its resting state — never while it is tucked away — so a stale
       value can't leave a gap at the top of the page. */
    var bar = D.querySelector('.sw-header');
    if (bar && !bar.classList.contains('sw-header--hidden')
           && !bar.classList.contains('sw-header--stuck')) {
      H.style.setProperty('--sw-header-h', Math.ceil(bar.getBoundingClientRect().height) + 'px');
    }
  }

  /* ----------------------------------------------------------------
     Sticky header that steps out of the way

     Scrolling down past the first screenful slides the bar up out of
     sight; the first upward move brings it straight back. Transform
     only — the bar keeps its space in the layout and is painted from
     the first frame, so none of this touches the page's paint metrics.

     It stays put whenever hiding it would take something away from
     the visitor: the mobile menu or a dropdown is open, focus is
     inside it (keyboard users tab into the nav), or we are near the
     top of the page.

     RULE 1 is honoured twice over: the bar is only ever moved by a
     transform it can always have taken off again, and if anything in
     here throws the bar is put straight back — without detaching, which
     is the mistake the first version made. One hiccup used to kill the
     behaviour for the rest of the visit, silently.

     Deliberately ONE listener on the window for the whole page, which
     looks the bar up as it goes, instead of a listener armed per
     element at boot. The per-element version depended on the header
     being in the DOM at exactly the moment the runtime booted and on a
     WeakSet remembering it afterwards, and on a real site it ended up
     never arming at all. Nothing here cares when the header appears,
     whether a builder swaps it for a fresh node, or how many times
     this file is re-run.
     ---------------------------------------------------------------- */
  var MIN_MOVE  = 6;        // ignore sub-pixel jitter and rubber-banding
  var headerY   = 0;
  var headerBar = null;
  var headerTicking = false;
  var headerListening = false;

  function currentHeader() {
    // Cached, but re-queried the moment the cached node leaves the page.
    if (headerBar && headerBar.isConnected) { return headerBar; }
    headerBar = D.querySelector('.sw-header');
    return headerBar;
  }

  function headerShow(bar) { bar.classList.remove('sw-header--hidden'); }

  function headerHeldOpen(bar) {
    // Bootstrap marks both the collapsed menu and an open dropdown with
    // .show, so one query covers both. ":focus-visible" rather than
    // plain focus is the difference that matters: clicking the hamburger
    // leaves the button focused, and testing for focus meant that after
    // one tap the bar never hid again. Guarded — the selector throws
    // where it is unsupported.
    if (bar.querySelector('.navbar-collapse.show, .dropdown-menu.show')) { return true; }
    try { return !!bar.querySelector(':focus-visible'); }
    catch (e) { return false; }
  }

  function headerApply() {
    headerTicking = false;
    var bar = currentHeader();
    if (!bar || EDITOR) { return; }

    try {
      var y = window.pageYOffset || (D.scrollingElement ? D.scrollingElement.scrollTop : 0) || 0;
      var h = bar.offsetHeight || 0;

      bar.classList.toggle('sw-header--stuck', y > 4);

      if (y <= h || headerHeldOpen(bar)) { headerShow(bar); headerY = y; return; }

      var moved = y - headerY;
      if (Math.abs(moved) < MIN_MOVE) { return; }
      if (moved > 0) { bar.classList.add('sw-header--hidden'); } else { headerShow(bar); }
      headerY = y;
    } catch (e) {
      // Put it back and carry on. Never stop listening.
      headerShow(bar);
    }
  }

  function onHeaderScroll() {
    if (headerTicking) { return; }
    headerTicking = true;
    if (window.requestAnimationFrame) {
      window.requestAnimationFrame(headerApply);
      // rAF does not run in a background tab, and a pending frame would
      // otherwise leave headerTicking stuck true forever.
      window.setTimeout(function () { if (headerTicking) { headerApply(); } }, 250);
    } else {
      headerApply();
    }
  }

  function initHeaders(root) {
    if (headerListening || EDITOR) { return; }
    if (!D.querySelector('.sw-header')) { return; }   // nothing to drive yet
    headerListening = true;
    headerY = window.pageYOffset || 0;
    window.addEventListener('scroll', onHeaderScroll, { passive: true });
    // On the document, not the bar: a builder replacing the header must
    // not take the listener with it.
    D.addEventListener('focusin', function () {
      var bar = currentHeader();
      if (bar) { headerShow(bar); }
    });
    D.addEventListener('click', function () { window.setTimeout(headerApply, 0); });
    headerApply();
  }

  /* ----------------------------------------------------------------
     Pause the never-ending animations while they are off screen

     Two of the decorations loop forever: the gallery cards drifting up
     and down, and the screenshots panning inside the device mockups.
     Left alone they keep the compositor — and, for the panning ones,
     the painter — busy for as long as the tab is open, whether or not
     anybody can see them. On a long page that is most of the time.

     The switch is the same --sw-scroll-play custom property the admin's
     Motion setting uses, set on the element rather than globally. That
     matters: these elements carry two animations, and setting
     animation-play-state directly would also freeze the entrance one
     mid-flight. Only "paused" is ever written, and leaving the viewport
     removes it again, so a site with Motion switched off globally stays
     switched off.
     ---------------------------------------------------------------- */
  function initIdleAnimations(root) {
    if (!('IntersectionObserver' in window)) { return; }

    var stages = root.querySelectorAll('.sw-hero-gallery, .sw-hero-stage');
    if (!stages.length) { return; }

    if (!idleIO) {
      idleIO = new IntersectionObserver(function (entries) {
        for (var i = 0; i < entries.length; i++) {
          var el = entries[i].target;
          if (entries[i].isIntersecting) {
            el.style.removeProperty('--sw-scroll-play');
          } else {
            el.style.setProperty('--sw-scroll-play', 'paused');
          }
        }
      }, { rootMargin: '200px' });
    }

    for (var s = 0; s < stages.length; s++) {
      if (watchedStages.has(stages[s])) { continue; }
      watchedStages.add(stages[s]);
      idleIO.observe(stages[s]);
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
    initHeaders(scope);
    initIdleAnimations(scope);
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
              '[data-sw-accordion],' +
              '.sw-hero-gallery, .sw-hero-stage, .sw-header';

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
    version: '2.34.0',
    editor: EDITOR,
    init: boot
  };
})();
