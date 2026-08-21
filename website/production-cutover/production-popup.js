/**
 * Production-only email popup submission.
 *
 * CUTOVER ONLY. Not part of the staging asset build: the shared staging JS
 * (twins-brand.js) is contract-banned from making outbound HTTP, so this ships
 * exclusively in the production package, exactly like production-callback.js.
 * production-overhaul-loader.php enqueues it alongside the callback handler.
 *
 * Posts {email, source, path} plus the honeypot and time-to-submit fields as
 * JSON to the endpoint the server declares on the popup wrapper's
 * data-popup-endpoint attribute (components/email-capture.php emits it from
 * TWINS_POPUP_LEAD_ENDPOINT). The client never selects a URL.
 *
 * Fail-silent by design: one attempt, no retries, and the email address is
 * never logged. The visitor always reaches the thanks state; the spam gate at
 * the edge function decides what the submission is worth. A filled honeypot
 * short-circuits to the thanks state without sending anything at all.
 * Suppression: writes the same twins-popup-v1 localStorage key the shared
 * chrome runtime uses, so a signup suppresses the popup for 180 days.
 */
(function () {
  'use strict';

  function remember() {
    try { window.localStorage.setItem('twins-popup-v1', String(Date.now())); } catch (error) { /* best effort */ }
  }

  function bind() {
    var wrap = document.querySelector('[data-twins-popup][data-popup-endpoint]');
    if (!wrap) return;
    var form = wrap.querySelector('form.twins-popup-form');
    if (!form) return;

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var fields = form.elements;
      var status = form.querySelector('[data-popup-status]');
      var button = form.querySelector('button[type="submit"]');
      var email = String((fields['email'] && fields['email'].value) || '').trim();
      var honeypot = String((fields['website'] && fields['website'].value) || '');

      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        if (status) {
          status.hidden = false;
          status.textContent = 'Please enter a valid email address.';
        }
        return;
      }

      var finish = function () {
        remember();
        if (button) button.disabled = true;
        form.hidden = true;
        if (status) {
          status.hidden = false;
          status.textContent = 'Thanks! You are on the list.';
        }
      };

      // Honeypot filled: pretend success, send nothing (LP spam-gate pattern).
      if (honeypot !== '') {
        finish();
        return;
      }

      var renderedAt = Number(wrap.getAttribute('data-popup-rendered-at')) || 0;
      var payload = {
        email: email,
        source: 'website-popup',
        path: window.location.pathname,
        // Full href so the intake function can recover UTM parameters the
        // same way it does for LP forms; `path` stays for the lead ledger.
        page: window.location.href,
        website: honeypot,
        rendered_at: renderedAt,
        elapsed_ms: renderedAt > 0 ? Math.max(0, Date.now() - renderedAt * 1000) : 0,
      };

      try {
        fetch(wrap.dataset.popupEndpoint, {
          method: 'POST',
          headers: { 'content-type': 'application/json' },
          body: JSON.stringify(payload),
          keepalive: true,
        }).catch(function () { /* fail-silent: no retries, nothing logged */ });
      } catch (error) { /* fail-silent */ }

      if (typeof window.gtag === 'function') {
        try { window.gtag('event', 'popup_signup'); } catch (error) { /* best effort */ }
      }

      finish();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind, { once: true });
  } else {
    bind();
  }
})();
