(function (root, factory) {
  var api = factory(root);
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.TwinsDoorBuilderApp = api;
    var start = function () { api.boot(root.document, root); };
    if (root.document.readyState === 'loading') {
      root.document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
      start();
    }
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function (root) {
  'use strict';

  var mountedRoot = null;

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function imageHTML(image, alt, className, loading) {
    if (!image) return '';
    return '<img src="' + esc(image) + '" alt="' + esc(alt || '') + '"'
      + (className ? ' class="' + esc(className) + '"' : '')
      + (loading ? ' loading="' + esc(loading) + '"' : '')
      + ' data-no-lazy="1">';
  }

  function createController(dependencies) {
    var core = dependencies.core;
    var transport = dependencies.transport;
    var funnel = dependencies.funnel;
    var cache = transport.createSessionCache(dependencies.storage);
    var state = {
      step: 'collection',
      products: [],
      product: null,
      design: null,
      color: null,
      window: null,
      glass: null,
      skipped: {},
      history: []
    };

    function loadCached(key, url) {
      var cached = cache.get(key);
      if (cached) return Promise.resolve(cached);
      return transport.fetchJson(dependencies.fetchImpl, url, 10000).then(function (value) {
        cache.set(key, value);
        return value;
      });
    }

    function clearProduct() {
      state.product = null;
      state.design = null;
      state.color = null;
      state.window = null;
      state.glass = null;
      state.skipped = {};
      state.history.length = 0;
    }

    async function selectProduct(id) {
      clearProduct();
      var listed = state.products.some(function (item) { return item.id === id; });
      if (!listed) {
        state.step = 'fallback';
        return state;
      }
      try {
        var raw = await loadCached('twxdb:p:' + id, dependencies.detailUrl + encodeURIComponent(id));
        var product = core.normalizeProduct(raw);
        if (!product.id || product.id !== id || !product.title) {
          throw new Error('invalid product detail');
        }
        state.product = product;
        state.history.push('collection');
        state.step = core.nextStep('collection', state.product, state);
      } catch (_error) {
        state.step = 'fallback';
      }
      return state;
    }

    async function load(search) {
      try {
        state.products = [];
        clearProduct();
        var rawList = await loadCached('twxdb:list', dependencies.listUrl);
        state.products = core.normalizeCatalog(rawList);
        if (!state.products.length) throw new Error('empty catalog');
        var allowed = new Set(state.products.map(function (item) { return item.id; }));
        var deepLink = core.parseDeepLink(search, allowed);
        state.step = 'collection';
        if (deepLink) await selectProduct(deepLink);
      } catch (_error) {
        state.step = 'fallback';
      }
      return state;
    }

    function choose(kind, index) {
      if (!state.product) return state;
      var sourceName = {
        design: 'designs',
        color: 'colors',
        window: 'windows',
        glass: 'glass'
      }[kind];
      if (!sourceName) return state;
      if (kind === 'window' && index === -1) {
        state.window = {
          titleHtml: 'No windows (solid)',
          title: 'No windows (solid)',
          image: null,
          evidence: 'swatch-only',
          none: true
        };
      } else {
        state[kind] = state.product[sourceName][index] || null;
      }
      if (kind === 'window') state.glass = null;
      var canonicalName = kind === 'window' ? 'windows' : kind;
      state.history.push(state.step);
      state.step = core.nextStep(canonicalName, state.product, state);
      return state;
    }

    function back() {
      if (state.history.length) state.step = state.history.pop();
      return state;
    }

    function skipToQuote() {
      if (state.step !== 'summary') state.history.push(state.step);
      state.step = 'summary';
      return state;
    }

    function submit(formValues) {
      var payload = core.buildLeadPayload(formValues, state, dependencies.region);
      return funnel.submitLead({
        fetchImpl: dependencies.fetchImpl,
        endpoint: dependencies.endpoint,
        payload: payload
      }).then(function (result) {
        if (result.ok) state.step = 'thanks';
        return result;
      });
    }

    return {
      state: state,
      load: load,
      selectProduct: selectProduct,
      choose: choose,
      back: back,
      skipToQuote: skipToQuote,
      submit: submit
    };
  }

  function boot(documentRef, windowRef) {
    if (!documentRef || !windowRef) return false;
    var mounts = documentRef.querySelectorAll
      ? documentRef.querySelectorAll('#twxdb')
      : null;
    var mount = mounts
      ? (mounts.length === 1 ? mounts[0] : null)
      : documentRef.getElementById('twxdb');
    if (!mount || mountedRoot === mount) return false;
    mountedRoot = mount;

    var REGION = mount.getAttribute('data-region') || 'main';
    var ENDPOINT = mount.getAttribute('data-endpoint') || '';
    var PHONES = {
      main: { disp: '(833) 833-2010', tel: '+18338332010' },
      wi: { disp: '(608) 888-8785', tel: '+16088888785' }
    };
    var PHONE = PHONES[REGION] || PHONES.main;
    var LIST_URL = 'https://www.clopaydoor.com/api/v2/GetProductsList/GetProducts?productType=Residential';
    var DETAIL_URL = 'https://www.clopaydoor.com/api/v2/GetProductDetails/GetProductData?productId=';
    var STEPS = ['collection', 'design', 'color', 'windows', 'glass', 'summary'];
    var TITLES = {
      collection: 'Choose your collection',
      design: 'Pick your design',
      color: 'Pick your color',
      windows: 'Add windows',
      glass: 'Choose your glass',
      summary: 'Review your specification and get your free quote'
    };
    var BTN_LABEL = 'Get my free quote';
    var SAMPLE_CAPTION = 'Manufacturer sample — final appearance is confirmed before ordering.';

    function formHTML() {
      return '<form class="twxdb-form" novalidate>'
        + '<div class="twxdb-fld"><label for="twxdb-name">Name*</label><input class="twxdb-in" id="twxdb-name" name="name" type="text" required autocomplete="name"></div>'
        + '<div class="twxdb-fld"><label for="twxdb-phone">Phone*</label><input class="twxdb-in" id="twxdb-phone" name="phone" type="tel" required autocomplete="tel"></div>'
        + '<div class="twxdb-fld"><label for="twxdb-email">Email*</label><input class="twxdb-in" id="twxdb-email" name="email" type="email" required autocomplete="email"></div>'
        + '<div class="twxdb-fld"><label for="twxdb-zip">Zip*</label><input class="twxdb-in" id="twxdb-zip" name="zip" type="text" inputmode="numeric" required autocomplete="postal-code"></div>'
        + '<div class="twxdb-fld"><label for="twxdb-notes">Anything else? (optional)</label><textarea class="twxdb-in" id="twxdb-notes" name="notes"></textarea></div>'
        + '<div class="twxdb-hp" aria-hidden="true"><label for="twxdb-website">Website</label><input id="twxdb-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>'
        + '<button type="submit" class="twxdb-btn">' + BTN_LABEL + '</button>'
        + '<div class="twxdb-err" data-door-builder-error hidden></div>'
        + '</form>';
    }

    function fallbackHTML() {
      return '<div class="twxdb-app"><h2 class="twxdb-h">Get your free door quote</h2>'
        + '<p class="twxdb-sub">Our door catalog is taking a break — leave your details and we\'ll design it together.</p>'
        + formHTML() + '</div>';
    }

    var core = windowRef.TwinsDoorBuilderCore;
    var transport = windowRef.TwinsDoorBuilderTransport;
    var funnel = windowRef.TwinsDoorBuilderFunnel;
    if (!core || !transport || !funnel || typeof windowRef.fetch !== 'function') {
      mount.innerHTML = fallbackHTML();
      return false;
    }

    var storage = windowRef.sessionStorage || {
      getItem: function () { return null; },
      setItem: function () {}
    };
    var search = windowRef.location ? windowRef.location.search : '';
    var forceFail = /[?&]twxdbfail=1/.test(search);
    var fetchImpl = function (url, options) {
      if (forceFail && url === LIST_URL) {
        return Promise.reject(new Error('forced fail'));
      }
      return windowRef.fetch(url, options);
    };
    var controller = createController({
      core: core,
      transport: transport,
      funnel: funnel,
      fetchImpl: fetchImpl,
      storage: storage,
      listUrl: LIST_URL,
      detailUrl: DETAIL_URL,
      endpoint: ENDPOINT,
      region: REGION
    });
    var manifest = windowRef.TwinsDoorBuilderReferenceManifest || { products: {} };

    function display(value) {
      return core.sanitizeDisplay(value);
    }

    function groupBy(list) {
      var groups = [];
      var positions = {};
      (list || []).forEach(function (item, index) {
        var group = item.group || '';
        if (!(group in positions)) {
          positions[group] = groups.length;
          groups.push({ name: group, items: [] });
        }
        groups[positions[group]].items.push({ item: item, index: index });
      });
      return groups;
    }

    function dotsHTML() {
      var current = controller.state;
      var available = new Set(core.availableSteps(current.product, current));
      var currentIndex = STEPS.indexOf(current.step);
      var chosen = {
        collection: current.product,
        design: current.design,
        color: current.color,
        windows: current.window,
        glass: current.glass,
        summary: null
      };
      return '<div class="twxdb-dots" aria-hidden="true">' + STEPS.map(function (step, index) {
        var className = 'twxdb-dot';
        if (step === current.step) className += ' is-current';
        else if (current.product && !available.has(step)) className += ' is-skip';
        else if (chosen[step] || (index < currentIndex && available.has(step))) className += ' is-done';
        return '<span class="' + className + '" title="' + esc(TITLES[step]) + '"></span>';
      }).join('') + '</div>';
    }

    function barHTML() {
      var current = controller.state;
      var backButton = current.step !== 'collection'
        ? '<button type="button" class="twxdb-back" data-act="back">&larr; Back</button>'
        : '<span></span>';
      var skipButton = current.step !== 'summary'
        ? '<button type="button" class="twxdb-skip" data-act="skip">Skip to quote &rarr;</button>'
        : '<span></span>';
      return '<div class="twxdb-bar">' + backButton + dotsHTML() + skipButton + '</div>';
    }

    function picksHTML() {
      var current = controller.state;
      var rows = [];
      function row(label, choice, sample) {
        if (!choice) return '';
        var sampleImage = sample ? imageHTML(choice.image, choice.title, '', 'lazy') : '';
        var caption = sample ? '<small class="twxdb-sample-label">' + SAMPLE_CAPTION + '</small>' : '';
        return '<div class="twxdb-pick">' + sampleImage + '<b>' + esc(label) + '</b><span>'
          + display(choice.titleHtml || choice.title) + caption + '</span></div>';
      }
      rows.push(row('Collection', current.product, false));
      rows.push(row('Design', current.design, false));
      rows.push(row('Color', current.color, true));
      rows.push(row('Windows', current.window, true));
      rows.push(row('Glass', current.glass, true));
      return '<div class="twxdb-picks">' + rows.join('') + '</div>';
    }

    function previewHTML() {
      var current = controller.state;
      if (!current.product || current.step === 'collection') return '';
      var preview = core.selectPreview(current.product, current, manifest);
      var hero = preview.hero ? '<figure class="twxdb-preview twxdb-preview--hero">'
        + imageHTML(preview.hero.image, current.product.title, '', 'eager')
        + '<figcaption>' + esc(preview.hero.label) + '</figcaption></figure>' : '';
      var panel = preview.panel ? '<figure class="twxdb-preview twxdb-preview--panel">'
        + imageHTML(preview.panel.image, current.design && current.design.title)
        + '<figcaption>Panel-style reference shown at its original resolution.</figcaption></figure>' : '';
      return '<div class="twxdb-visuals">' + hero + panel + '</div>' + picksHTML();
    }

    function viewCollection() {
      var current = controller.state;
      var cards = current.products.map(function (product, index) {
        var selected = current.product && current.product.id === product.id;
        return '<button type="button" class="twxdb-card' + (selected ? ' is-sel' : '')
          + '" data-act="product" data-i="' + index + '">'
          + imageHTML(product.showcaseImage, product.title, '', 'lazy')
          + '<span class="twxdb-card-t">' + display(product.titleHtml) + '</span>'
          + '<span class="twxdb-card-d">' + display(product.shortDescriptionHtml) + '</span>'
          + '</button>';
      }).join('');
      return '<h2 class="twxdb-h">' + TITLES.collection + '</h2>'
        + '<p class="twxdb-sub">' + current.products.length
        + ' Clopay collections. Pick one and we\'ll build it up together.</p>'
        + '<div class="twxdb-grid">' + cards + '</div>';
    }

    function viewDesign() {
      var current = controller.state;
      var cards = current.product.designs.map(function (design, index) {
        var selected = current.design === design;
        return '<button type="button" class="twxdb-card' + (selected ? ' is-sel' : '')
          + '" data-act="design" data-i="' + index + '">'
          + imageHTML(design.image, design.title, '', 'lazy')
          + '<span class="twxdb-card-t">' + display(design.titleHtml) + '</span></button>';
      }).join('');
      return '<h2 class="twxdb-h">' + TITLES.design + '</h2><div class="twxdb-grid">'
        + cards + '</div>';
    }

    function viewColor() {
      var current = controller.state;
      var groups = groupBy(current.product.colors).map(function (group) {
        var chips = group.items.map(function (option) {
          var selected = current.color === option.item;
          return '<button type="button" class="twxdb-chip' + (selected ? ' is-sel' : '')
            + '" data-act="color" data-i="' + option.index + '">'
            + imageHTML(option.item.image, option.item.title, '', 'lazy')
            + '<span>' + display(option.item.titleHtml) + '</span></button>';
        }).join('');
        return (group.name ? '<h3 class="twxdb-g">' + esc(group.name) + '</h3>' : '')
          + '<div class="twxdb-chips">' + chips + '</div>';
      }).join('');
      return '<h2 class="twxdb-h">' + TITLES.color + '</h2>' + groups
        + '<p class="twxdb-cap">' + SAMPLE_CAPTION + '</p>';
    }

    function viewWindows() {
      var current = controller.state;
      var noWindows = current.window && current.window.none && current.window.title === 'No windows (solid)';
      var noneCard = '<div class="twxdb-chips"><button type="button" class="twxdb-chip'
        + (noWindows ? ' is-sel' : '') + '" data-act="window" data-i="-1">'
        + '<span class="twxdb-noneimg"></span><span>No windows (solid)</span></button></div>';
      var groups = groupBy(current.product.windows).map(function (group) {
        var chips = group.items.map(function (option) {
          var selected = current.window === option.item;
          return '<button type="button" class="twxdb-chip twxdb-chip--wide'
            + (selected ? ' is-sel' : '') + '" data-act="window" data-i="' + option.index + '">'
            + imageHTML(option.item.image, option.item.title, '', 'lazy')
            + '<span>' + display(option.item.titleHtml) + '</span></button>';
        }).join('');
        return (group.name ? '<h3 class="twxdb-g">' + esc(group.name) + '</h3>' : '')
          + '<div class="twxdb-chips">' + chips + '</div>';
      }).join('');
      return '<h2 class="twxdb-h">' + TITLES.windows + '</h2>' + noneCard + groups
        + '<p class="twxdb-cap">' + SAMPLE_CAPTION + '</p>';
    }

    function viewGlass() {
      var current = controller.state;
      var cards = current.product.glass.map(function (glass, index) {
        var selected = current.glass === glass;
        return '<button type="button" class="twxdb-card' + (selected ? ' is-sel' : '')
          + '" data-act="glass" data-i="' + index + '">'
          + imageHTML(glass.image, glass.title, '', 'lazy')
          + '<span class="twxdb-card-t">' + display(glass.titleHtml) + '</span></button>';
      }).join('');
      return '<h2 class="twxdb-h">' + TITLES.glass + '</h2><div class="twxdb-grid">'
        + cards + '</div><p class="twxdb-cap">' + SAMPLE_CAPTION + '</p>';
    }

    function viewSummary() {
      return '<h2 class="twxdb-h">Review your specification and get your free quote</h2>'
        + '<p class="twxdb-sub">We will use these selections to prepare your free quote. Manufacturer images are references; Twins confirms the exact appearance before ordering.</p>'
        + formHTML();
    }

    function viewThanks() {
      return '<div class="twxdb-app twxdb-thanks"><h2 class="twxdb-h">Thank you!</h2>'
        + '<p>Thanks — your specification was sent to our team. We will call you shortly to confirm options and prepare your quote.</p></div>';
    }

    function render(scroll) {
      var current = controller.state;
      var html;
      if (current.step === 'fallback') {
        html = fallbackHTML();
      } else if (current.step === 'thanks') {
        html = viewThanks();
      } else {
        var views = {
          collection: viewCollection,
          design: viewDesign,
          color: viewColor,
          windows: viewWindows,
          glass: viewGlass,
          summary: viewSummary
        };
        html = '<div class="twxdb-app">' + barHTML() + previewHTML() + views[current.step]() + '</div>';
      }
      mount.innerHTML = html;
      if (scroll && mount.scrollIntoView) {
        mount.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    function renderLoading(message) {
      mount.innerHTML = '<div class="twxdb-load">' + esc(message) + '</div>';
    }

    function pickProductById(productId) {
      renderLoading('Loading door details…');
      return controller.selectProduct(productId).then(function () {
        render(true);
      });
    }

    mount.addEventListener('click', function (event) {
      var element = event.target.closest ? event.target.closest('[data-act]') : null;
      if (!element || !mount.contains(element)) return;
      var action = element.getAttribute('data-act');
      var index = parseInt(element.getAttribute('data-i') || '-1', 10);
      if (action === 'product') {
        var product = controller.state.products[index];
        if (product) pickProductById(product.id);
        return;
      }
      if (action === 'design' || action === 'color' || action === 'window' || action === 'glass') {
        controller.choose(action, index);
      } else if (action === 'back') {
        controller.back();
      } else if (action === 'skip') {
        controller.skipToQuote();
      } else {
        return;
      }
      render(true);
    });

    mount.addEventListener('submit', function (event) {
      var form = event.target;
      if (!form.classList || !form.classList.contains('twxdb-form')) return;
      event.preventDefault();
      function g(name) {
        var element = form.querySelector('[name="' + name + '"]');
        return element ? element.value.trim() : '';
      }
      var err = form.querySelector('.twxdb-err');
      var btn = form.querySelector('button[type="submit"]');
      if (btn.disabled) return;
      if (!g('name') || !g('phone') || !g('email') || !g('zip')) {
        err.hidden = false;
        err.textContent = 'Please fill in your name, phone, email and zip.';
        return;
      }
      btn.disabled = true;
      btn.textContent = 'Sending…';
      err.hidden = true;
      function showLeadError() {
        btn.disabled = false;
        btn.textContent = BTN_LABEL;
        err.hidden = false;
        err.innerHTML = 'Something went wrong sending your specification. Call us at <a href="tel:'
          + PHONE.tel + '">' + PHONE.disp + '</a> and we\'ll take it from there.';
      }
      var formValues = {
        name: g('name'),
        phone: g('phone'),
        email: g('email'),
        zip: g('zip'),
        notes: g('notes'),
        website: g('website')
      };
      controller.submit(formValues).then(function (result) {
        if (!result.ok) {
          showLeadError();
          return;
        }
        render(true);
      }).catch(showLeadError);
    });

    mount.addEventListener('load', function (event) {
      if (event.target.matches && event.target.matches('.twxdb img')) {
        event.target.style.maxWidth = event.target.naturalWidth + 'px';
      }
    }, true);

    renderLoading('Loading door collections…');
    controller.load(search).then(function () {
      render();
    });
    return true;
  }

  return { createController: createController, boot: boot, imageHTML: imageHTML };
}));
