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

  var forms = document.querySelectorAll('[data-callback-endpoint] form.twins-brand-callback');

  forms.forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var wrap = form.closest('[data-callback-endpoint]');
      var status = form.querySelector('[data-callback-status]');
      var button = form.querySelector('button[type="submit"]');

      var data = {
        name: form.name.value.trim(),
        phone: form.phone.value.trim(),
        email: '',
        zip: '',
        message: '',
        service: form.service.value,
        page: location.href,
        form_variant: 'site-callback',
        chooser_token: '',
        consent: 'true',
        website: form.website.value,
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
        .then(function () {
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
})();
