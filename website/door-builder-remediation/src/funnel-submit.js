(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.TwinsDoorBuilderFunnel = api;
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  var DOM_BINDINGS = {
    formSelector: '.twx-db',
    fieldSelectors: {
      name: '[name="name"],#twx-n',
      phone: '[name="phone"],#twx-p',
      email: '[name="email"],#twx-e',
      zip: '[name="zip"],#twx-z',
      website: '[name="website"],#twx-w'
    },
    regionAttribute: 'data-region',
    submitButtonSelector: 'button[type="submit"]',
    errorSelector: '[data-door-builder-error]'
  };

  async function submitLead(options) {
    var response;
    try {
      response = await options.fetchImpl(options.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(options.payload)
      });
    } catch (_error) {
      return { ok: false, reason: 'network' };
    }
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
      try {
        options.redirect();
      } catch (_error) {
        return { ok: true, navigationOk: false, reason: 'redirect' };
      }
    }
    return { ok: true, navigationOk: true };
  }

  function collectValues(form) {
    function value(name) {
      var element = form.querySelector(DOM_BINDINGS.fieldSelectors[name]);
      return element ? String(element.value || '').trim() : '';
    }
    return {
      name: value('name'),
      phone: value('phone'),
      email: value('email'),
      zip: value('zip'),
      region: form.getAttribute(DOM_BINDINGS.regionAttribute) || 'main',
      website: value('website')
    };
  }

  function bindFunnel(form, options) {
    var locked = false;
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (locked) return;
      locked = true;
      var button = form.querySelector(DOM_BINDINGS.submitButtonSelector);
      var error = form.querySelector(DOM_BINDINGS.errorSelector);
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
    DOM_BINDINGS: DOM_BINDINGS,
    submitLead: submitLead,
    collectValues: collectValues,
    bindFunnel: bindFunnel
  };
}));
