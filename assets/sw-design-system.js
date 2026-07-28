/* ================================================================
   SW Design System — runtime
   Drop once on every page (enqueue from theme, or include in footer).
   Idempotent: safe if loaded multiple times or in LiveCanvas editor.

   What it does:
   - Reveal: any element with [data-sw-reveal] gets .sw-anim-on, then
     .is-visible when it enters the viewport. Children with class
     .sw-reveal-item fade up (with stagger via :nth-child in CSS).
   - Parallax: any element with [data-sw-parallax] becomes a 3D
     stage. It looks for .sw-browser and .sw-phone children and
     tilts them following the mouse. Desktop + fine pointer only.
   ================================================================ */
(() => {
  if (window.__swDS) return;       // guard against double init
  window.__swDS = true;

  const noMotion   = window.matchMedia('(prefers-reduced-motion: reduce)');
  const parallaxOK = window.matchMedia('(min-width: 992px) and (pointer: fine)');
  const hoverOK     = window.matchMedia('(hover: hover) and (pointer: fine)');

  /* ---------- Reveal on enter ----------
     Each [data-sw-reveal] container gets .sw-anim-on (hides its
     .sw-reveal-item children). We observe each ITEM individually and add
     .is-visible to its container-scope when the item scrolls into view, so
     items animate exactly when they personally reach the viewport —
     regardless of how tall the section is. */
  const revealEls = document.querySelectorAll('[data-sw-reveal]');
  if (revealEls.length) {
    if (noMotion.matches || !('IntersectionObserver' in window)) {
      revealEls.forEach(el => {
        el.classList.add('sw-anim-on', 'is-visible');
        el.querySelectorAll('.sw-reveal-item').forEach(i => i.classList.add('is-in'));
      });
    } else {
      const items = [];
      revealEls.forEach(el => {
        el.classList.add('sw-anim-on');
        el.querySelectorAll('.sw-reveal-item').forEach(i => items.push(i));
      });

      const io = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-in');
            obs.unobserve(entry.target);
          }
        });
      }, {
        root: null,
        threshold: 0,
        rootMargin: '0px 0px -12% 0px'  // reveal a bit before the very edge
      });

      items.forEach(i => io.observe(i));
    }
  }

  /* ---------- Parallax stages (any number per page) ---------- */
  document.querySelectorAll('[data-sw-parallax]').forEach(stage => {
    if (stage.dataset.swInit === '1') return;
    stage.dataset.swInit = '1';

    const browser = stage.querySelector('.sw-browser');
    const phone   = stage.querySelector('.sw-phone');
    if (!browser || !phone) return;

    const onMove = e => {
      if (!parallaxOK.matches || noMotion.matches) return;
      const r = stage.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width  - .5;
      const y = (e.clientY - r.top)  / r.height - .5;
      browser.style.transform =
        `rotateX(${7 - y * 4}deg) rotateY(${-10 + x * 6}deg) rotateZ(${1 + x * 1.2}deg) translateY(${-Math.abs(x) * 6}px)`;
      phone.style.transform =
        `rotateZ(${4 - x * 5}deg) translateZ(60px) translate(${x * 14}px, ${y * 10}px)`;
    };
    const reset = () => {
      browser.style.transform = '';
      phone.style.transform   = '';
    };

    stage.addEventListener('pointermove',  onMove);
    stage.addEventListener('pointerleave', reset);
    parallaxOK.addEventListener('change', e => { if (!e.matches) reset(); });
  });

  /* ---------- FAQ accordion ----------
     [data-sw-accordion] containing .sw-faq-item with a .sw-faq-trigger
     (button) and a .sw-faq-a panel. One open at a time. Height animated
     via max-height; accessible via aria-expanded. No Bootstrap needed. */
  document.querySelectorAll('[data-sw-accordion]').forEach(acc => {
    const items = acc.querySelectorAll('.sw-faq-item');

    const close = item => {
      const btn   = item.querySelector('.sw-faq-trigger');
      const panel = item.querySelector('.sw-faq-a');
      if (!btn || !panel) return;
      btn.setAttribute('aria-expanded', 'false');
      item.classList.remove('is-open');
      panel.style.maxHeight = '';
    };
    const open = item => {
      const btn   = item.querySelector('.sw-faq-trigger');
      const panel = item.querySelector('.sw-faq-a');
      if (!btn || !panel) return;
      btn.setAttribute('aria-expanded', 'true');
      item.classList.add('is-open');
      panel.style.maxHeight = panel.scrollHeight + 'px';
    };

    items.forEach(item => {
      const btn = item.querySelector('.sw-faq-trigger');
      if (!btn) return;
      btn.addEventListener('click', () => {
        const isOpen = item.classList.contains('is-open');
        items.forEach(close);              // one open at a time
        if (!isOpen) open(item);
      });
    });

    // Keep an open panel's height correct on resize.
    window.addEventListener('resize', () => {
      const openItem = acc.querySelector('.sw-faq-item.is-open .sw-faq-a');
      if (openItem) openItem.style.maxHeight = openItem.scrollHeight + 'px';
    });
  });

  /* ---------- Fixed/sticky navbar offset ----------
     Sticky elements (see .sw-journey-sticky) need to stop below a fixed
     header instead of sliding under it. Measuring the navbar's real
     height at runtime — instead of a hardcoded pixel value in CSS —
     means this keeps working if the header's height ever changes
     (responsive breakpoints, or a future menu redesign), without
     needing a CSS edit every time. Looks for .navbar; if none is
     found, --sw-navbar-h stays 0 and sticky elements just use their
     own base offset. */
  const updateNavbarOffset = () => {
    const nav = document.querySelector('.navbar');
    const h = nav ? Math.ceil(nav.getBoundingClientRect().height) : 0;
    document.documentElement.style.setProperty('--sw-navbar-h', h + 'px');
  };
  updateNavbarOffset();
  let navResizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(navResizeTimer);
    navResizeTimer = setTimeout(updateNavbarOffset, 150);
  });

  /* ---------- Testimonials slider ----------
     The track is a native scroll-snap container — it already works via
     touch/trackpad/keyboard with no JS at all. The arrow buttons are a
     progressive-enhancement convenience: nudge scrollLeft by one card's
     width, and auto-disable at each end. */
  document.querySelectorAll('.sw-testimonials-slider').forEach(slider => {
    const track = slider.querySelector('.sw-testimonials-track');
    const prev  = slider.querySelector('.sw-testimonials-arrow--prev');
    const next  = slider.querySelector('.sw-testimonials-arrow--next');
    if (!track || !prev || !next) return;

    const cardStep = () => {
      const card = track.querySelector('.sw-testimonial-card');
      if (!card) return track.clientWidth * .8;
      const style = getComputedStyle(track);
      return card.getBoundingClientRect().width + parseFloat(style.gap || '0');
    };

    prev.addEventListener('click', () => track.scrollBy({ left: -cardStep(), behavior: 'smooth' }));
    next.addEventListener('click', () => track.scrollBy({ left:  cardStep(), behavior: 'smooth' }));

    // Hover is tracked in JS (.is-hover), not left to CSS :hover — see
    // the note above .sw-testimonials-arrow.is-hover in the stylesheet.
    // Only wired up on devices with a real pointer (mouse/trackpad) —
    // touchscreens have no true "hover", and a tap can otherwise leave
    // the class looking stuck on since there's no mouse to leave with.
    if (hoverOK.matches) {
      [prev, next].forEach(btn => {
        btn.addEventListener('mouseenter', () => { if (!btn.disabled) btn.classList.add('is-hover'); });
        btn.addEventListener('mouseleave', () => btn.classList.remove('is-hover'));
      });
    }

    const updateArrows = () => {
      const max = track.scrollWidth - track.clientWidth - 1;
      const atStart = track.scrollLeft <= 4;
      const atEnd = max <= 0 || track.scrollLeft >= max - 4;
      prev.disabled = atStart;
      next.disabled = atEnd;
      // No fade toward a side with no more content to reveal — see the
      // .is-at-start/.is-at-end rules in the stylesheet.
      track.classList.toggle('is-at-start', atStart);
      track.classList.toggle('is-at-end', atEnd);
      // A click can disable a button while the mouse never actually
      // leaves it — clear the hover class right here too, instead of
      // waiting on a mouseleave event that may or may not still fire.
      if (prev.disabled) prev.classList.remove('is-hover');
      if (next.disabled) next.classList.remove('is-hover');
    };
    updateArrows();

    let scrollTimer;
    track.addEventListener('scroll', () => {
      clearTimeout(scrollTimer);
      scrollTimer = setTimeout(updateArrows, 80);
    });
    window.addEventListener('resize', updateArrows);
  });
})();
