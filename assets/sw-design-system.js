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

  /* ---------- Reveal on enter ---------- */
  const revealEls = document.querySelectorAll('[data-sw-reveal]');
  if (revealEls.length && 'IntersectionObserver' in window && !noMotion.matches) {
    revealEls.forEach(el => el.classList.add('sw-anim-on'));
    const io = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: .18 });
    revealEls.forEach(el => io.observe(el));
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
})();
