(() => {
  'use strict';

  const ZIP_ROUTES = Object.freeze({
    '537': '/wi/garage-door-cost-in-madison-wi/',
    '531': '/wi/garage-door-cost-in-milwaukee-wi/',
    '532': '/wi/garage-door-cost-in-milwaukee-wi/',
  });
  const ZIP_FALLBACK = '/wi/contact-us/';
  // `summary` is focusable and tabbable. Its omission was latent until the
  // drawer started carrying one <details> per metro: the drawer is
  // role="dialog" aria-modal="true" with a Tab trap that computes first/last
  // from this list, and without `summary` the trap computes the wrong
  // boundaries and Tab escapes the modal.
  const focusables = root => [...root.querySelectorAll('a[href],button:not([disabled]),summary,input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')]
    .filter(element => !element.closest('[hidden]'));
  const setExpanded = (button, value) => button.setAttribute('aria-expanded', String(value));

  function safeLocalPath(path) {
    return typeof path === 'string'
      && path.startsWith('/')
      && !path.startsWith('//')
      && !path.includes('..')
      && !path.includes('\\')
      && !/[\u0000-\u001f\u007f]/.test(path)
      ? path
      : '';
  }

  function initZip(root) {
    root.querySelectorAll('[data-twins-overhaul-zip]').forEach(widget => {
      const input = widget.querySelector('[data-twins-zip-input]');
      const control = widget.querySelector('[data-twins-zip-route]');
      const status = widget.querySelector('[data-twins-zip-status]');
      if (!input || !control || !status) return;

      const routeZip = () => {
        const zip = String(input.value || '').trim();
        if (!/^\d{5}$/.test(zip)) {
          input.setAttribute('aria-invalid', 'true');
          status.textContent = 'Enter a valid 5-digit ZIP code.';
          return;
        }

        input.removeAttribute('aria-invalid');
        const destination = safeLocalPath(ZIP_ROUTES[zip.slice(0, 3)] || ZIP_FALLBACK);
        if (!destination) {
          input.setAttribute('aria-invalid', 'true');
          status.textContent = 'The local guide is unavailable.';
          return;
        }
        status.textContent = destination === ZIP_FALLBACK
          ? 'Opening the Wisconsin contact guide for a service-area check.'
          : 'Opening the matching local cost guide.';
        window.location.href = destination;
      };

      control.addEventListener('click', routeZip);
      input.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        routeZip();
      });
    });
  }

  function trapTab(event, root) {
    if (event.key !== 'Tab') return;
    const items = focusables(root);
    if (!items.length) return;
    const first = items[0], last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }

  function initLocationReveals(root, reducedMotion) {
    const items = [...root.querySelectorAll('[data-location-reveal]')];
    const reveal = item => item.setAttribute('data-location-visible', 'true');
    if (!items.length) return;
    if (reducedMotion.matches) {
      items.forEach(reveal);
      return;
    }
    if (!('IntersectionObserver' in window)) {
      items.forEach(reveal);
      return;
    }

    document.documentElement.classList.add('twins-location-motion-ready');
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        reveal(entry.target);
        observer.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });
    items.forEach(item => observer.observe(item));
  }

  function initLocationMobileActions(root) {
    const hero = root.querySelector('.twins-location-hero');
    const actions = root.querySelector('.twins-brand-mobile-actions');
    if (!hero || !actions) return;

    const update = () => {
      const isMobile = matchMedia('(max-width: 768px)').matches;
      const heroRect = hero.getBoundingClientRect();
      const actionHeight = actions.getBoundingClientRect().height;
      const heroIsActive = isMobile && heroRect.top < innerHeight && heroRect.bottom > actionHeight;
      document.body.dataset.twinsLocationHeroActive = String(heroIsActive);
    };

    update();
    requestAnimationFrame(update);
    window.addEventListener('resize', update, { passive: true });
    window.addEventListener('scroll', update, { passive: true });
  }

  function createVisibilityPause(element, onChange) {
    onChange(false);
    if (!('IntersectionObserver' in window)) {
      return false;
    }

    let observer = null;
    try {
      observer = new IntersectionObserver(entries => {
        onChange(entries.some(entry => entry.target === element && entry.isIntersecting));
      }, { threshold: 0.2 });
      observer.observe(element);
      return true;
    } catch {
      observer?.disconnect();
      onChange(false);
      return false;
    }
  }

  /**
   * Previous/next controls for the team roster, otherwise a horizontal scroll
   * rail with no affordance.
   *
   * The rail is scroll-snap-type: x mandatory, which defeats programmatic
   * scrolling. Measured on staging: scrollBy and scrollTo with
   * behavior:'smooth' never move it, snapped or not. A direct scrollLeft
   * assignment does move it once snap is suspended and the style has been
   * committed, verified live at 14 -> 394. Snap is restored on the next frame
   * so dragging still snaps.
   *
   * TWINS_ROSTER_DEBUG on window turns on per-click instrumentation.
   */
  /**
   * Live review figures. The server renders the last verified snapshot (the
   * staging safety plugin blocks all WordPress outbound HTTP, so PHP cannot
   * fetch live data here; on production it can, and this still runs as a
   * freshness layer). The browser reads the public, read-only summary row
   * that the daily Places cron maintains and updates the numbers in place.
   * On any failure or implausible value the snapshot stays - the site must
   * never show an invented rating.
   */
  function initLiveReviewSummary(root) {
    const ratings = [...root.querySelectorAll('[data-twins-live-rating]')];
    const counts = [...root.querySelectorAll('[data-twins-live-count]')];
    if (!ratings.length && !counts.length) return;
    const endpoint = 'https://jwrpjuqaynownxaoeayi.supabase.co/rest/v1/places_profile_summary'
      + '?select=rating,user_rating_count&place_id=eq.ChIJ6WuQE9VSBogRgy76ORRGfHs';
    fetch(endpoint, {
      headers: { apikey: 'sb_publishable_22ascfuq2ALa8u5taJ0OZA_U_SpDewm', Accept: 'application/json' },
    })
      .then(response => (response.ok ? response.json() : null))
      .then(rows => {
        const row = Array.isArray(rows) ? rows[0] : null;
        if (!row) return;
        const rating = Number(row.rating);
        const count = Number(row.user_rating_count);
        if (Number.isFinite(rating) && rating > 0 && rating <= 5) {
          ratings.forEach(element => { element.textContent = rating.toFixed(1); });
        }
        if (Number.isInteger(count) && count > 0) {
          counts.forEach(element => { element.textContent = String(count); });
        }
      })
      .catch(() => {});
  }

  function initHomeTeamRoster(root) {
    const roster = root.querySelector('.twins-brand-home-team-roster');
    const controls = root.querySelector('[data-home-team-controls]');
    if (!roster || !controls) return;
    const previous = controls.querySelector('[data-home-team-prev]');
    const next = controls.querySelector('[data-home-team-next]');
    if (!previous || !next) return;

    const maxScroll = () => roster.scrollWidth - roster.clientWidth;
    const sync = () => {
      const max = maxScroll();
      if (max <= 8) { controls.hidden = true; return; }
      controls.hidden = false;
      previous.disabled = roster.scrollLeft <= 2;
      next.disabled = roster.scrollLeft >= max - 2;
    };
    const step = () => {
      const card = roster.querySelector('.twins-brand-home-team-person');
      const width = card ? card.getBoundingClientRect().width + 12 : 200;
      return Math.max(width, Math.round(roster.clientWidth * 0.6));
    };

    const nudge = direction => {
      const before = roster.scrollLeft;
      const target = Math.max(0, Math.min(maxScroll(), before + direction * step()));
      roster.style.scrollSnapType = 'none';
      void roster.offsetWidth;
      roster.scrollLeft = target;
      const after = roster.scrollLeft;
      if (window.TWINS_ROSTER_DEBUG) {
        window.TWINS_ROSTER_LOG = { before, step: step(), target, after, moved: after !== before, snap: getComputedStyle(roster).scrollSnapType };
      }
      requestAnimationFrame(() => {
        roster.style.scrollSnapType = '';
        sync();
      });
      sync();
    };

    previous.addEventListener('click', () => nudge(-1));
    next.addEventListener('click', () => nudge(1));
    roster.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);
    sync();
  }

  function initHomeReveals(root, reducedMotion) {
    const items = [...root.querySelectorAll('[data-home-reveal]')];
    if (!items.length) return;
    const reveal = item => { item.dataset.homeVisible = 'true'; };
    if (reducedMotion.matches || !('IntersectionObserver' in window)) {
      items.forEach(reveal);
      return;
    }

    let observer = null;
    const failOpen = () => {
      observer?.disconnect();
      document.documentElement.classList.remove('twins-home-motion-ready');
      items.forEach(reveal);
    };

    try {
      observer = new IntersectionObserver(entries => {
        try {
          entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            reveal(entry.target);
            observer.unobserve(entry.target);
          });
        } catch {
          failOpen();
        }
      }, { threshold: 0, rootMargin: '0px 0px 20% 0px' });
      items.forEach(item => observer.observe(item));
      document.documentElement.classList.add('twins-home-motion-ready');
    } catch {
      failOpen();
    }

    // Content must never sit hidden waiting on an observer. Anything still
    // unrevealed shortly after load is shown regardless. Kept outside the
    // observer setup so a missing timer API cannot disable the reveal itself.
    if (typeof window.setTimeout === 'function') {
      window.setTimeout(() => {
        items.forEach(item => {
          if (item.dataset.homeVisible !== 'true') reveal(item);
        });
      }, 1200);
    }
  }

  function initHomeContinuousMotion(root, reducedMotion) {
    const items = [...root.querySelectorAll('[data-home-motion]')];
    if (!items.length || !('IntersectionObserver' in window)) return;

    const states = new Map(items.map(item => [
      item,
      { viewport: false, pauses: new Set() },
    ]));
    const sync = item => {
      const state = states.get(item);
      if (!state) return;
      const visible = state.viewport && !document.hidden && !reducedMotion.matches;
      item.dataset.motionVisible = String(visible);
      item.dataset.motionActive = String(visible && state.pauses.size === 0);
      if (visible) item.dataset.motionEntered = 'true';
    };
    const syncAll = () => items.forEach(sync);
    const setPause = (item, reason, paused) => {
      const state = states.get(item);
      if (!state) return;
      if (paused) state.pauses.add(reason); else state.pauses.delete(reason);
      sync(item);
    };

    let observer = null;
    try {
      observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          const state = states.get(entry.target);
          if (!state) return;
          state.viewport = entry.isIntersecting;
          sync(entry.target);
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
      items.forEach(item => observer.observe(item));
    } catch {
      observer?.disconnect();
      return;
    }

    items.forEach(item => {
      item.dataset.motionEnhanced = 'true';
      sync(item);
      if (!item.hasAttribute('data-home-ticker')) return;
      item.addEventListener('mouseenter', () => setPause(item, 'hover', true));
      item.addEventListener('mouseleave', () => setPause(item, 'hover', false));
      item.addEventListener('focusin', () => setPause(item, 'focus', true));
      item.addEventListener('focusout', event => {
        if (!item.contains(event.relatedTarget)) setPause(item, 'focus', false);
      });
    });
    document.addEventListener('visibilitychange', syncAll);
    reducedMotion.addEventListener?.('change', syncAll);
  }

  function initHomeServiceTabs(root, reducedMotion) {
    root.querySelectorAll('[data-twins-service-tabs]').forEach(container => {
      const tabs = [...container.querySelectorAll('[data-service-tab]')];
      const panels = [...container.querySelectorAll('[data-service-panel]')];
      const tablist = container.querySelector('[data-service-tablist]');
      const controls = container.querySelector('[data-service-controls]');
      const previous = container.querySelector('[data-service-prev]');
      const next = container.querySelector('[data-service-next]');
      const status = container.querySelector('[data-service-status]');
      if (
        !tablist
        || !controls
        || !previous
        || !next
        || !status
        || !tabs.length
        || tabs.length !== panels.length
        || tabs.some(tab => !tab.id)
        || panels.some(panel => !panel.id)
      ) return;

      let current = 0;
      let timer = null;
      const pauses = new Set();
      const isPaused = () => reducedMotion.matches || document.hidden || pauses.size > 0;

      const paint = index => {
        current = (index + tabs.length) % tabs.length;
        tabs.forEach((tab, tabIndex) => {
          const active = tabIndex === current;
          tab.setAttribute('aria-selected', String(active));
          tab.tabIndex = active ? 0 : -1;
        });
        panels.forEach((panel, panelIndex) => {
          const active = panelIndex === current;
          panel.dataset.active = String(active);
          panel.toggleAttribute('inert', !active);
          panel.setAttribute('aria-hidden', String(!active));
        });
        status.textContent = `${current + 1} of ${tabs.length}`;
      };

      const stop = () => {
        if (timer !== null) window.clearInterval(timer);
        timer = null;
      };
      const syncTimer = () => {
        const paused = isPaused();
        container.dataset.autoplayPaused = String(paused);
        if (paused) {
          stop();
          return;
        }
        if (timer === null) timer = window.setInterval(() => paint(current + 1), 5000);
      };
      const setPause = (reason, paused) => {
        if (paused) pauses.add(reason); else pauses.delete(reason);
        syncTimer();
      };
      const select = (index, moveFocus) => {
        paint(index);
        container.dataset.interactionPaused = 'true';
        setPause('interaction', true);
        if (moveFocus) tabs[current].focus();
      };

      tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => select(index, false));
        tab.addEventListener('keydown', event => {
          let target = null;
          switch (event.key) {
            case 'ArrowRight':
            case 'ArrowDown': target = current + 1; break;
            case 'ArrowLeft':
            case 'ArrowUp': target = current - 1; break;
            case 'Home': target = 0; break;
            case 'End': target = tabs.length - 1; break;
            default: return;
          }
          event.preventDefault();
          select(target, true);
        });
      });
      previous?.addEventListener('click', () => select(current - 1, false));
      next?.addEventListener('click', () => select(current + 1, false));

      container.addEventListener('mouseenter', () => setPause('hover', true));
      container.addEventListener('mouseleave', () => setPause('hover', false));
      container.addEventListener('focusin', () => setPause('focus', true));
      container.addEventListener('focusout', event => {
        if (!container.contains(event.relatedTarget)) setPause('focus', false);
      });
      tablist.setAttribute('role', 'tablist');
      tablist.setAttribute('aria-label', 'Garage door services');
      tabs.forEach((tab, index) => {
        const panel = panels[index];
        tab.setAttribute('role', 'tab');
        tab.setAttribute('aria-controls', panel.id);
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', tab.id);
      });
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      status.setAttribute('aria-atomic', 'true');
      paint(0);
      tablist.removeAttribute('hidden');
      controls.removeAttribute('hidden');
      container.dataset.enhanced = 'true';
      const visibilityReady = createVisibilityPause(
        container,
        visible => setPause('viewport', !visible),
      );
      if (visibilityReady) {
        document.addEventListener('visibilitychange', syncTimer);
        reducedMotion.addEventListener?.('change', syncTimer);
      }
      syncTimer();
    });
  }

  function start() {
    initZip(document);
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
        // With 35 town links in the Service Area panel, arrow-walking alone is cruel.
        if (event.key === 'Home' && index >= 0) { event.preventDefault(); links[0]?.focus(); }
        if (event.key === 'End' && index >= 0) { event.preventDefault(); links[links.length - 1]?.focus(); }
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

    document.querySelectorAll('.twins-brand-preview-form, [data-popup-preview]').forEach(preview => {
      const fields = [...preview.querySelectorAll('input, select, textarea')];
      const final = preview.querySelector('[data-preview-finalize]');
      const status = preview.querySelector('[data-preview-status]');
      const successMessage = status?.textContent || '';
      const fieldLabel = field => {
        const label = field.closest('label');
        const text = label?.firstChild?.textContent?.trim();
        return text || 'This field';
      };
      const fieldIssue = field => {
        const value = field.value.trim();
        const label = fieldLabel(field);
        if (value === '') return `${label} is required.`;
        if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
          return `Enter a valid email address for ${label}.`;
        }
        if (field.pattern && !(new RegExp(`^(?:${field.pattern})$`)).test(value)) {
          return `${label} does not match the requested format.`;
        }
        return '';
      };
      final?.addEventListener('click', () => {
        let invalid = null;
        const issues = [];
        fields.forEach(field => {
          const issue = fieldIssue(field);
          const failed = issue !== '';
          field.setAttribute('aria-invalid', String(failed));
          if (failed) {
            issues.push(issue);
            if (!invalid) invalid = field;
          }
        });
        if (invalid) {
          if (status) {
            status.textContent = `Please fix the following: ${issues.join(' ')}`;
            status.dataset.previewState = 'validation';
            status.hidden = false;
          }
          invalid.focus();
          return;
        }
        if (status) {
          status.textContent = successMessage;
          status.dataset.previewState = 'complete';
          status.hidden = false;
        }
      });
      fields.forEach(field => field.addEventListener('input', () => {
        field.removeAttribute('aria-invalid');
        if (status?.dataset.previewState === 'validation') {
          status.hidden = true;
          status.textContent = successMessage;
          delete status.dataset.previewState;
        }
      }));
    });

    // Email-capture popup: opens after 20 seconds on page OR on desktop exit
    // intent, whichever comes first, once per visitor per 180 days
    // (twins-popup-v1 in localStorage; dismissal and signup both count). The
    // shared runtime owns only chrome behavior - open, close, focus trap, Esc,
    // suppression. It carries no transport: on staging the finalize control
    // reveals the standing private-preview notice through the shared preview
    // handler above, and the production submission ships exclusively in the
    // production-only script, which writes the same suppression key.
    const popup = document.querySelector('[data-twins-popup]');
    if (popup) {
      const popupDialog = popup.querySelector('[data-popup-dialog]');
      const popupClose = popup.querySelector('[data-popup-close]');
      const POPUP_KEY = 'twins-popup-v1';
      const POPUP_SUPPRESS_MS = 180 * 24 * 60 * 60 * 1000;
      const popupSuppressed = () => {
        try {
          const raw = window.localStorage.getItem(POPUP_KEY);
          if (!raw) return false;
          const at = Number(raw);
          return !Number.isFinite(at) || Date.now() - at < POPUP_SUPPRESS_MS;
        } catch {
          // Without storage the frequency cap cannot be honored, so the popup
          // must never show: staying quiet beats nagging on every page view.
          return true;
        }
      };
      const popupRemember = () => {
        try { window.localStorage.setItem(POPUP_KEY, String(Date.now())); } catch { /* best effort */ }
      };
      if (popupSuppressed()) {
        popup.remove();
      } else {
        let popupShown = false;
        let popupRestore = null;
        let popupTimer = null;
        // The menu drawer and the quote dialog share one scroll-lock slot with
        // the popup and never stack on each other (openBooking closes the
        // drawer first). The popup must not stack either: opening on top of
        // them interrupts a booking mid-flow and leaves the page scroll-locked
        // once both close. While either owns the screen the popup waits out
        // another full dwell instead.
        const popupBlockedByOverlay = () => Boolean((drawer && !drawer.hidden) || (booking && !booking.hidden));
        const openPopup = () => {
          if (popupShown || !popup.isConnected) return;
          if (popupTimer !== null) window.clearTimeout(popupTimer);
          if (popupBlockedByOverlay()) {
            popupTimer = window.setTimeout(openPopup, 20000);
            return;
          }
          popupShown = true;
          popupRestore = document.activeElement;
          popup.hidden = false;
          lockScroll();
          popupClose?.focus();
        };
        const closePopup = () => {
          if (popup.hidden) return;
          popup.hidden = true;
          unlockScroll();
          popupRemember();
          if (popupRestore instanceof HTMLElement) popupRestore.focus();
        };
        popupTimer = window.setTimeout(openPopup, 20000);
        document.addEventListener('mouseout', event => {
          if (popupShown || event.relatedTarget) return;
          if (event.clientY > 0) return;
          if (!matchMedia('(hover: hover) and (pointer: fine)').matches) return;
          openPopup();
        });
        popupClose?.addEventListener('click', () => closePopup());
        popup.querySelector('[data-popup-decline]')?.addEventListener('click', () => closePopup());
        popup.addEventListener('pointerdown', event => { if (event.target === popup) closePopup(); });
        popup.addEventListener('keydown', event => {
          if (event.key === 'Escape') { event.preventDefault(); closePopup(); return; }
          trapTab(event, popupDialog || popup);
        });
        // Completing the staging preview flow counts as signup for the
        // frequency cap. The shared preview handler registered above runs
        // first, so previewState is already settled when this listener fires.
        const popupPreviewStatus = popup.querySelector('[data-preview-status]');
        popup.querySelector('[data-preview-finalize]')?.addEventListener('click', () => {
          if (popupPreviewStatus?.dataset.previewState === 'complete') popupRemember();
        });
      }
    }

    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    initLocationReveals(document, reducedMotion);
    initHomeReveals(document, reducedMotion);
    initHomeTeamRoster(document);
    initLiveReviewSummary(document);
    initHomeContinuousMotion(document, reducedMotion);
    initHomeServiceTabs(document, reducedMotion);
    initLocationMobileActions(document);
    document.querySelectorAll('[data-twins-review-slider][data-review-mode="featured"]').forEach(slider => {
      const track = slider.querySelector('.twins-brand-review-track');
      const cards = [...slider.querySelectorAll('.twins-brand-review-card')];
      const previous = slider.querySelector('[data-review-prev]');
      const next = slider.querySelector('[data-review-next]');
      const status = slider.querySelector('[data-review-page-status]');
      if (!track || !cards.length || !previous || !next || !status) return;

      let current = 0;
      let pages = 1;
      let visibleCards = 1;
      let touchStartX = null;
      let permanentlyPaused = false;
      let timer = null;
      const pauses = new Set();
      const cardsPerPage = () => matchMedia('(min-width: 769px)').matches ? 3 : 1;
      const isPaused = () => pauses.size > 0 || document.hidden || reducedMotion.matches;
      const stopTimer = () => {
        if (timer !== null) window.clearInterval(timer);
        timer = null;
      };
      const syncTimer = () => {
        const paused = permanentlyPaused || isPaused() || pages <= 1;
        slider.dataset.autoplayPaused = String(paused);
        if (paused) {
          stopTimer();
          return;
        }
        if (timer === null) {
          timer = window.setInterval(() => {
            if (!permanentlyPaused && !isPaused()) go(current + 1);
          }, 6200);
        }
      };
      const setPause = (reason, value) => {
        if (value) pauses.add(reason); else pauses.delete(reason);
        syncTimer();
      };
      const paint = () => {
        track.style.transform = `translate3d(-${current * 100}%, 0, 0)`;
        status.textContent = `${current + 1} of ${pages}`;
        previous.disabled = pages <= 1;
        next.disabled = pages <= 1;
        const firstVisible = current * visibleCards;
        const lastVisible = Math.min(firstVisible + visibleCards, cards.length);
        cards.forEach((card, index) => {
          const visible = index >= firstVisible && index < lastVisible;
          card.toggleAttribute('inert', !visible);
          if (visible) card.removeAttribute('aria-hidden');
          else card.setAttribute('aria-hidden', 'true');
        });
      };
      const go = page => {
        current = (page + pages) % pages;
        paint();
      };
      const manualGo = page => {
        permanentlyPaused = true;
        slider.dataset.interactionPaused = 'true';
        syncTimer();
        go(page);
      };
      const build = () => {
        const count = cardsPerPage();
        visibleCards = count;
        slider.style.setProperty('--twins-review-cards', String(count));
        pages = Math.max(1, Math.ceil(cards.length / count));
        current = Math.min(current, pages - 1);
        paint();
        syncTimer();
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
      window.addEventListener('pointerup', () => setPause('pointer', false));
      window.addEventListener('pointercancel', () => setPause('pointer', false));
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
      slider.addEventListener('touchcancel', () => {
        touchStartX = null;
        setPause('touch', false);
      }, { passive: true });
      const visibilityReady = createVisibilityPause(
        slider,
        visible => setPause('viewport', !visible),
      );
      if (visibilityReady) {
        document.addEventListener('visibilitychange', syncTimer);
        reducedMotion.addEventListener?.('change', syncTimer);
      }
      window.addEventListener('resize', build, { passive: true });
      build();
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
