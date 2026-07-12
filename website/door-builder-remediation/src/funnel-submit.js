(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.TwinsDoorBuilderFunnel = api;
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  async function submitLead(options) {
    try {
      var response = await options.fetchImpl(options.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(options.payload)
      });
      if (!response || !response.ok) {
        return { ok: false, reason: 'http' };
      }
      var body;
      try {
        body = await response.json();
      } catch (_error) {
        return { ok: false, reason: 'json' };
      }
      if (!body || body.ok !== true) {
        return { ok: false, reason: 'body' };
      }
      if (typeof options.redirect === 'function') {
        options.redirect();
      }
      return { ok: true };
    } catch (_error) {
      return { ok: false, reason: 'network' };
    }
  }

  function collectValues(form) {
    function value(name, id) {
      var element = form.querySelector('[name="' + name + '"],#' + id);
      return element ? String(element.value || '').trim() : '';
    }
    return {
      name: value('name', 'twx-n'),
      phone: value('phone', 'twx-p'),
      email: value('email', 'twx-e'),
      zip: value('zip', 'twx-z'),
      region: form.getAttribute('data-region') || 'main',
      website: value('website', 'twx-w')
    };
  }

  function bindFunnel(form, options) {
    var locked = false;
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (locked) return;
      locked = true;
      var button = form.querySelector('button[type="submit"]');
      var error = form.querySelector('[data-door-builder-error]');
      var values = collectValues(form);
      button.disabled = true;
      error.hidden = true;
      var result = await submitLead({
        fetchImpl: options.fetchImpl || fetch,
        endpoint: options.endpoint,
        payload: values,
        redirect: function () {
          var redirectImpl = options.redirectImpl || function (url) { location.assign(url); };
          redirectImpl(options.successUrl);
        }
      });
      if (!result.ok) {
        locked = false;
        button.disabled = false;
        error.hidden = false;
        error.textContent = options.errorMessage;
      }
    });
  }

  return {
    submitLead: submitLead,
    collectValues: collectValues,
    bindFunnel: bindFunnel
  };
}));
