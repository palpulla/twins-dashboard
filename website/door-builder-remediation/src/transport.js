(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.TwinsDoorBuilderTransport = api;
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  function fetchJson(
    fetchImpl,
    url,
    timeoutMs,
    AbortControllerImpl,
    setTimeoutImpl,
    clearTimeoutImpl
  ) {
    AbortControllerImpl = AbortControllerImpl || AbortController;
    setTimeoutImpl = setTimeoutImpl || setTimeout;
    clearTimeoutImpl = clearTimeoutImpl || clearTimeout;
    var controller = new AbortControllerImpl();
    var timer = setTimeoutImpl(function () { controller.abort(); }, timeoutMs || 10000);
    return Promise.resolve()
      .then(function () { return fetchImpl(url, { signal: controller.signal }); })
      .then(function (response) {
        if (!response.ok) throw new Error('http ' + response.status);
        return response.json();
      })
      .finally(function () { clearTimeoutImpl(timer); });
  }

  function createSessionCache(storage) {
    return {
      get: function (key) {
        try {
          var value = storage.getItem(key);
          return value ? JSON.parse(value) : null;
        } catch (_error) {
          return null;
        }
      },
      set: function (key, value) {
        try {
          storage.setItem(key, JSON.stringify(value));
          return true;
        } catch (_error) {
          return false;
        }
      }
    };
  }

  return { fetchJson: fetchJson, createSessionCache: createSessionCache };
}));
