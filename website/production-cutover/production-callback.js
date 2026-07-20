/**
 * Production-only callback form submission.
 *
 * CUTOVER ONLY. Not part of the staging asset build: the shared staging JS is
 * contract-banned from making outbound HTTP (fetch), so this ships exclusively
 * in the production package. Wire it into the production twins-brand.js build
 * (or enqueue it as a standalone production script) alongside the
 * ProductionQuoteAdapter callback form.
 *
 * Posts the LP lead-intake payload contract as JSON to the endpoint declared on
 * the form wrapper's data-callback-endpoint attribute.
 */
(function () {
  'use strict';

  function bind() {
    var forms = document.querySelectorAll('[data-callback-endpoint] form.twins-brand-callback');

    forms.forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();

        var wrap = form.closest('[data-callback-endpoint]');
        var status = form.querySelector('[data-callback-status]');
        var button = form.querySelector('button[type="submit"]');
        // Read controls via form.elements: form.name would resolve to the form's
        // own `name` IDL attribute (an empty string), not the name <input>.
        var fields = form.elements;

        var data = {
          name: fields['name'].value.trim(),
          phone: fields['phone'].value.trim(),
          email: '',
          zip: '',
          message: '',
          service: fields['service'].value,
          page: location.href,
          form_variant: 'site-callback',
          chooser_token: '',
          consent: 'true',
          website: fields['website'].value,
        };

        if (!data.name || !data.phone) {
          status.hidden = false;
          status.textContent = 'Please add your name and phone number.';
          return;
        }

        button.disabled = true;
        button.textContent = 'Sending...';

        fetch(wrap.dataset.callbackEndpoint, {
          method: 'POST',
          headers: { 'content-type': 'application/json' },
          body: JSON.stringify(data),
        })
          .then(function (response) {
            // fetch only rejects on network failure; a 4xx/5xx still resolves,
            // so treat a non-ok status as a failure instead of a false success.
            if (!response.ok) {
              throw new Error('Lead intake responded ' + response.status);
            }
            form.hidden = true;
            status.hidden = false;
            status.textContent = 'Got it. We will call you back shortly.';
          })
          .catch(function () {
            button.disabled = false;
            button.textContent = 'Get My Call Back';
            status.hidden = false;
            status.textContent = 'Something went wrong. Please call us instead.';
          });
      });
    });
  }

  // Bind once the form exists, whether this script loads in the footer (DOM
  // already parsed) or earlier (before the form is in the DOM).
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind, { once: true });
  } else {
    bind();
  }
})();
