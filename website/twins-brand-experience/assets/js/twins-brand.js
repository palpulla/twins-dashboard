(() => {
  'use strict';

  const focusables = root => [...root.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')]
    .filter(element => !element.closest('[hidden]'));
  const setExpanded = (button, value) => button.setAttribute('aria-expanded', String(value));

  function trapTab(event, root) {
    if (event.key !== 'Tab') return;
    const items = focusables(root);
    if (!items.length) return;
    const first = items[0], last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }

  function start() {
    const header = document.querySelector('[data-twins-header]');
    const menuTrigger = document.querySelector('.twins-brand-menu-trigger');
    const drawer = document.querySelector('#twins-brand-drawer');
    const drawerPanel = drawer?.querySelector('.twins-brand-drawer-panel');
    const drawerClose = drawer?.querySelector('.twins-brand-drawer-close');
    const booking = document.querySelector('[data-twins-booking-dialog]');
    const bookingPanel = booking?.querySelector('[role="dialog"]');
    const bookingClose = booking?.querySelector('[data-booking-close]');
    let drawerRestore = null;
    let bookingRestore = null;
    let savedOverflow = '';

    const lockScroll = () => {
      savedOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
    };
    const unlockScroll = () => { document.body.style.overflow = savedOverflow; };

    const closeDrawer = (restore = true) => {
      if (!drawer || drawer.hidden) return;
      drawer.hidden = true;
      drawer.setAttribute('aria-hidden', 'true');
      drawer.style.pointerEvents = 'none';
      if (menuTrigger) setExpanded(menuTrigger, false);
      unlockScroll();
      if (restore && drawerRestore instanceof HTMLElement) drawerRestore.focus();
    };
    const openDrawer = () => {
      if (!drawer || !menuTrigger || !drawerClose) return;
      drawerRestore = menuTrigger;
      drawer.hidden = false;
      drawer.setAttribute('aria-hidden', 'false');
      drawer.style.pointerEvents = '';
      setExpanded(menuTrigger, true);
      lockScroll();
      drawerClose.focus();
    };
    menuTrigger?.addEventListener('click', () => drawer?.hidden ? openDrawer() : closeDrawer());
    drawerClose?.addEventListener('click', () => closeDrawer());
    drawer?.addEventListener('pointerdown', event => { if (event.target === drawer) closeDrawer(); });
    drawer?.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); closeDrawer(); return; }
      trapTab(event, drawerPanel || drawer);
    });

    const closeBooking = (restore = true) => {
      if (!booking || booking.hidden) return;
      booking.hidden = true;
      booking.style.pointerEvents = 'none';
      unlockScroll();
      if (restore && bookingRestore instanceof HTMLElement) bookingRestore.focus();
    };
    const openBooking = trigger => {
      if (!booking || !bookingClose) return;
      const openedFromDrawer = Boolean(trigger.closest('#twins-brand-drawer'));
      bookingRestore = openedFromDrawer ? menuTrigger : trigger;
      if (openedFromDrawer) closeDrawer(false);
      booking.hidden = false;
      booking.style.pointerEvents = '';
      lockScroll();
      bookingClose.focus();
    };
    document.querySelectorAll('[data-twins-booking-open]').forEach(trigger => {
      trigger.addEventListener('click', () => openBooking(trigger));
    });
    bookingClose?.addEventListener('click', () => closeBooking());
    booking?.addEventListener('pointerdown', event => { if (event.target === booking) closeBooking(); });
    booking?.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); closeBooking(); return; }
      trapTab(event, bookingPanel || booking);
    });
    booking?.querySelector('[data-booking-finalize]')?.addEventListener('click', () => {
      const status = booking.querySelector('[data-booking-status]');
      if (status) status.hidden = false;
    });

    const navTriggers = [...document.querySelectorAll('.twins-brand-nav-trigger')];
    const closeNav = (except = null, restore = false) => {
      navTriggers.forEach(trigger => {
        if (trigger === except) return;
        const wasOpen = trigger.getAttribute('aria-expanded') === 'true';
        setExpanded(trigger, false);
        if (restore && wasOpen) trigger.focus();
      });
    };
    navTriggers.forEach(trigger => {
      const panel = trigger.nextElementSibling;
      const links = panel ? [...panel.querySelectorAll('a[href]')] : [];
      const open = (focusFirst = false) => {
        closeNav(trigger);
        setExpanded(trigger, true);
        if (focusFirst) links[0]?.focus();
      };
      const close = (restore = false) => {
        setExpanded(trigger, false);
        if (restore) trigger.focus();
      };
      trigger.addEventListener('click', () => {
        const expanded = trigger.getAttribute('aria-expanded') === 'true';
        if (expanded) close(); else open();
      });
      trigger.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown') { event.preventDefault(); open(true); }
        if (event.key === 'Escape') { event.preventDefault(); close(true); }
      });
      panel?.addEventListener('keydown', event => {
        const index = links.indexOf(document.activeElement);
        if (event.key === 'Escape') { event.preventDefault(); close(true); }
        if (event.key === 'ArrowDown' && index >= 0) { event.preventDefault(); links[(index + 1) % links.length]?.focus(); }
        if (event.key === 'ArrowUp' && index >= 0) { event.preventDefault(); links[(index - 1 + links.length) % links.length]?.focus(); }
      });
    });
    document.addEventListener('pointerdown', event => {
      if (!event.target.closest('.twins-brand-nav-group')) closeNav();
    });

    document.querySelectorAll('.twins-brand-market-menu').forEach(menu => {
      menu.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !menu.open) return;
        event.preventDefault();
        menu.open = false;
        menu.querySelector('summary')?.focus();
      });
    });

    document.querySelectorAll('.twins-brand-preview-form').forEach(preview => {
      const fields = [...preview.querySelectorAll('input, select, textarea')];
      const final = preview.querySelector('[data-preview-finalize]');
      const status = preview.querySelector('[data-preview-status]');
      final?.addEventListener('click', () => {
        let invalid = null;
        fields.forEach(field => {
          const value = field.value.trim();
          const emailInvalid = field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
          const patternInvalid = field.pattern && !(new RegExp(`^(?:${field.pattern})$`)).test(value);
          const failed = value === '' || emailInvalid || patternInvalid;
          field.setAttribute('aria-invalid', String(failed));
          if (failed && !invalid) invalid = field;
        });
        if (invalid) {
          if (status) status.hidden = true;
          invalid.focus();
          return;
        }
        if (status) status.hidden = false;
      });
      fields.forEach(field => field.addEventListener('input', () => field.removeAttribute('aria-invalid')));
    });

    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    document.querySelectorAll('[data-twins-review-slider][data-review-mode="featured"]').forEach(slider => {
      const track = slider.querySelector('.twins-brand-review-track');
      const cards = [...slider.querySelectorAll('.twins-brand-review-card')];
      const previous = slider.querySelector('[data-review-prev]');
      const next = slider.querySelector('[data-review-next]');
      const status = slider.querySelector('[data-review-page-status]');
      if (!track || !cards.length || !previous || !next || !status) return;

      let current = 0;
      let pages = 1;
      let touchStartX = null;
      let permanentlyPaused = false;
      const pauses = new Set();
      const cardsPerPage = () => matchMedia('(min-width: 1200px)').matches ? 3 : matchMedia('(min-width: 768px)').matches ? 2 : 1;
      const isPaused = () => pauses.size > 0 || document.hidden || reducedMotion.matches;
      const reportPause = () => {
        slider.dataset.autoplayPaused = String(permanentlyPaused || isPaused());
      };
      const setPause = (reason, value) => {
        if (value) pauses.add(reason); else pauses.delete(reason);
        reportPause();
      };
      const paint = () => {
        track.style.transform = `translate3d(-${current * 100}%, 0, 0)`;
        status.textContent = `${current + 1} of ${pages}`;
        previous.disabled = pages <= 1;
        next.disabled = pages <= 1;
      };
      const go = page => {
        current = (page + pages) % pages;
        paint();
      };
      const manualGo = page => {
        permanentlyPaused = true;
        slider.dataset.interactionPaused = 'true';
        reportPause();
        go(page);
      };
      const build = () => {
        const count = cardsPerPage();
        slider.style.setProperty('--twins-review-cards', String(count));
        pages = Math.max(1, Math.ceil(cards.length / count));
        current = Math.min(current, pages - 1);
        paint();
      };

      previous.addEventListener('click', () => manualGo(current - 1));
      next.addEventListener('click', () => manualGo(current + 1));
      slider.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft') { event.preventDefault(); manualGo(current - 1); }
        if (event.key === 'ArrowRight') { event.preventDefault(); manualGo(current + 1); }
      });
      slider.addEventListener('mouseenter', () => setPause('hover', true));
      slider.addEventListener('mouseleave', () => setPause('hover', false));
      slider.addEventListener('focusin', () => setPause('focus', true));
      slider.addEventListener('focusout', event => {
        if (!slider.contains(event.relatedTarget)) setPause('focus', false);
      });
      slider.addEventListener('pointerdown', () => setPause('pointer', true));
      slider.addEventListener('pointerup', () => setPause('pointer', false));
      slider.addEventListener('pointercancel', () => setPause('pointer', false));
      slider.addEventListener('touchstart', event => {
        touchStartX = event.touches[0]?.clientX ?? null;
        setPause('touch', true);
      }, { passive: true });
      slider.addEventListener('touchend', event => {
        const endX = event.changedTouches[0]?.clientX ?? touchStartX;
        if (touchStartX !== null && endX !== null && Math.abs(endX - touchStartX) >= 40) {
          manualGo(current + (endX < touchStartX ? 1 : -1));
        }
        touchStartX = null;
        setPause('touch', false);
      }, { passive: true });
      document.addEventListener('visibilitychange', reportPause);
      reducedMotion.addEventListener?.('change', reportPause);
      window.addEventListener('resize', build, { passive: true });
      build();
      reportPause();
      setInterval(() => {
        if (!permanentlyPaused && !isPaused()) go(current + 1);
      }, 12000);
    });

    const compressHeader = () => {
      if (header) header.dataset.compressed = String(window.scrollY > 40);
    };
    window.addEventListener('scroll', compressHeader, { passive: true });
    compressHeader();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})();
