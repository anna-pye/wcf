/**
 * @file
 * Accessible homepage hero carousel (vanilla JS, no dependencies).
 */
(function (Drupal, once) {
  'use strict';

  const SWIPE_THRESHOLD_PX = 48;
  const AUTOPLAY_MS = 8000;

  /**
   * Activates a slide by index.
   *
   * @param {HTMLElement[]} slides
   * @param {HTMLElement} track
   * @param {number} index
   */
  function goToSlide(slides, track, index) {
    const total = slides.length;
    if (total === 0) {
      return;
    }
    const next = ((index % total) + total) % total;
    slides.forEach((slide, i) => {
      const active = i === next;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', active ? 'false' : 'true');
      slide.tabIndex = active ? 0 : -1;
    });
    track.dataset.activeIndex = String(next);
    const status = track.closest('.hero-slider')?.querySelector('[data-hero-slider-status]');
    if (status) {
      status.textContent = Drupal.t('Slide @current of @total', {
        '@current': next + 1,
        '@total': total,
      });
    }
    return next;
  }

  /**
   * Clears an autoplay interval safely.
   *
   * @param {{ timer: number|null }} state
   */
  function clearAutoplay(state) {
    if (state.timer !== null) {
      window.clearInterval(state.timer);
      state.timer = null;
    }
  }

  /**
   * Starts autoplay if allowed.
   *
   * @param {HTMLElement} root
   * @param {HTMLElement} track
   * @param {HTMLElement[]} slides
   * @param {{ timer: number|null, current: number, paused: boolean, reducedMotion: boolean }} state
   */
  function startAutoplay(root, track, slides, state) {
    clearAutoplay(state);
    if (state.reducedMotion || state.paused || slides.length <= 1) {
      return;
    }
    state.timer = window.setInterval(() => {
      state.current += 1;
      state.current = goToSlide(slides, track, state.current);
    }, AUTOPLAY_MS);
  }

  /**
   * Initializes one hero slider root element.
   *
   * @param {HTMLElement} root
   * @param {AbortSignal} signal
   */
  function initHeroSlider(root, signal) {
    const track = root.querySelector('[data-hero-slider-track]');
    if (!track) {
      return;
    }
    const slides = Array.from(track.querySelectorAll('.hero-slide'));
    if (slides.length <= 1) {
      slides.forEach((slide) => {
        slide.classList.add('is-active');
        slide.setAttribute('aria-hidden', 'false');
      });
      const controls = root.querySelector('.hero-slider__controls');
      if (controls) {
        controls.hidden = true;
      }
      return;
    }

    const state = {
      current: 0,
      timer: null,
      paused: false,
      reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    };

    state.current = goToSlide(slides, track, state.current);

    const prev = root.querySelector('[data-hero-slider-prev]');
    const next = root.querySelector('[data-hero-slider-next]');

    const step = (delta) => {
      state.current += delta;
      state.current = goToSlide(slides, track, state.current);
    };

    prev?.addEventListener('click', () => step(-1), { signal });
    next?.addEventListener('click', () => step(1), { signal });

    root.addEventListener(
      'keydown',
      (event) => {
        if (event.key === 'ArrowLeft') {
          event.preventDefault();
          step(-1);
        }
        if (event.key === 'ArrowRight') {
          event.preventDefault();
          step(1);
        }
      },
      { signal },
    );

    root.addEventListener(
      'mouseenter',
      () => {
        state.paused = true;
        clearAutoplay(state);
      },
      { signal },
    );

    root.addEventListener(
      'mouseleave',
      () => {
        state.paused = false;
        if (!root.contains(document.activeElement)) {
          startAutoplay(root, track, slides, state);
        }
      },
      { signal },
    );

    root.addEventListener(
      'focusin',
      () => {
        state.paused = true;
        clearAutoplay(state);
      },
      { signal },
    );

    root.addEventListener(
      'focusout',
      (event) => {
        if (root.contains(event.relatedTarget)) {
          return;
        }
        state.paused = false;
        startAutoplay(root, track, slides, state);
      },
      { signal },
    );

    let touchStartX = null;
    root.addEventListener(
      'touchstart',
      (event) => {
        if (event.touches.length !== 1) {
          return;
        }
        touchStartX = event.touches[0].clientX;
      },
      { signal, passive: true },
    );

    root.addEventListener(
      'touchend',
      (event) => {
        if (touchStartX === null || event.changedTouches.length !== 1) {
          touchStartX = null;
          return;
        }
        const deltaX = event.changedTouches[0].clientX - touchStartX;
        touchStartX = null;
        if (Math.abs(deltaX) < SWIPE_THRESHOLD_PX) {
          return;
        }
        step(deltaX < 0 ? 1 : -1);
      },
      { signal, passive: true },
    );

    signal.addEventListener('abort', () => clearAutoplay(state));

    startAutoplay(root, track, slides, state);
  }

  Drupal.behaviors.wcfHeroSlider = {
    attach(context) {
      once('wcf-hero-slider', '[data-hero-slider]', context).forEach((root) => {
        const controller = new AbortController();
        root.wcfHeroSliderController = controller;
        initHeroSlider(root, controller.signal);
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      const roots = once.remove('wcf-hero-slider', '[data-hero-slider]', context);
      roots.forEach((root) => {
        root.wcfHeroSliderController?.abort();
        delete root.wcfHeroSliderController;
      });
    },
  };
})(Drupal, once);
