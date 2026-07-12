/**
 * CANDIDATE ONLY — repository-generated door builder.
 * Live use requires fresh exports, verified staging, owner-signed grant,
 * CHATGPT_PROFILE_1 lease attestation and exact rollback evidence.
 */
add_shortcode( 'twins_door_builder', function () {
  $region = strpos( home_url(), '/wi' ) !== false ? 'wi' : 'main';
  ob_start(); ?>
<div id="twxdb" class="twxdb" data-region="<?php echo esc_attr( $region ); ?>" data-endpoint="https://twinsgaragedoors.com/wp-json/twins/v1/door-builder"></div>
<style id="twxdb-css">.twxdb{max-width:1140px;margin:0 auto;color:var(--tw-navy)}
.twxdb,.twxdb *{box-sizing:border-box}
.twxdb img{display:block;width:auto;height:auto;max-width:min(100%,var(--twxdb-natural-width,100%))}
.twxdb button{font-family:inherit}
.twxdb-h{font-family:Montserrat,sans-serif;font-weight:800;font-size:24px;line-height:1.15;color:var(--tw-navy);margin:0 0 8px}
.twxdb-h:after{content:"";display:block;width:56px;height:4px;background:var(--tw-yellow);border-radius:2px;margin-top:10px}
.twxdb-sub{font-size:15px;color:var(--tw-navy);opacity:.75;line-height:1.55;margin:0 0 18px}
.twxdb-bar{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:0 0 18px}
.twxdb-dots{display:flex;gap:9px;width:100%;order:-1;justify-content:center;padding:4px 0 10px}
.twxdb-dot{width:11px;height:11px;border-radius:50%;background:#F2F5F7;border:2px solid var(--tw-navy);opacity:.3}
.twxdb-dot.is-done{background:var(--tw-navy);opacity:1}
.twxdb-dot.is-current{background:var(--tw-yellow);opacity:1}
.twxdb-dot.is-skip{opacity:.12;border-style:dashed}
.twxdb-back{background:white;border:2px solid #F2F5F7;color:var(--tw-navy);font-weight:700;font-size:14px;border-radius:6px;padding:10px 16px;min-height:44px;cursor:pointer}
.twxdb-back:hover{border-color:var(--tw-navy)}
.twxdb-skip{margin-left:auto;font-family:Montserrat,sans-serif;font-weight:700;font-size:14px;color:var(--tw-navy);text-decoration:underline;cursor:pointer;background:none;border:0;padding:10px 0;min-height:44px}
.twxdb-visuals{display:grid;gap:18px;margin:0 0 20px;align-items:start}
.twxdb-preview{margin:0;background:#F2F5F7;border-radius:12px;padding:14px;text-align:center}
.twxdb-preview img{margin:0 auto;border-radius:8px;background:#fff}
.twxdb-preview figcaption{font-size:12.5px;line-height:1.5;color:var(--tw-navy);opacity:.75;margin-top:8px}
.twxdb-preview--panel img{image-rendering:auto}
.twxdb-picks{display:flex;flex-direction:column;gap:8px;font-size:14px;margin:0 0 20px}
.twxdb-pick{display:flex;align-items:center;gap:8px;background:#F2F5F7;border-radius:8px;padding:8px 12px;line-height:1.35}
.twxdb-pick b{font-family:Montserrat,sans-serif;font-size:11px;letter-spacing:.08em;text-transform:uppercase;opacity:.6;min-width:82px}
.twxdb-pick span{min-width:0}
.twxdb-sample-label{display:block;font-size:11px;line-height:1.35;opacity:.65;margin-top:3px}
.twxdb-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.twxdb-card{display:flex;flex-direction:column;gap:8px;text-align:left;background:white;border:2px solid #F2F5F7;border-radius:10px;padding:10px;cursor:pointer;min-height:44px}
.twxdb-card:hover{border-color:var(--tw-navy)}
.twxdb-card.is-sel{border-color:var(--tw-yellow)}
.twxdb-card img{margin:0 auto;max-height:220px;object-fit:contain}
.twxdb-card-t{font-family:Montserrat,sans-serif;font-weight:700;font-size:14.5px;color:var(--tw-navy);line-height:1.3}
.twxdb-card-d{font-size:12.5px;color:var(--tw-navy);opacity:.7;line-height:1.45;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.twxdb-g{font-family:Montserrat,sans-serif;font-weight:700;font-size:15px;color:var(--tw-navy);margin:20px 0 10px}
.twxdb-g:first-child{margin-top:0}
.twxdb-chips{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:10px}
.twxdb-chip{display:flex;flex-direction:column;align-items:center;gap:6px;background:white;border:2px solid #F2F5F7;border-radius:10px;padding:10px 6px;cursor:pointer;font-size:12px;color:var(--tw-navy);line-height:1.3;text-align:center;min-height:44px}
.twxdb-chip:hover{border-color:var(--tw-navy)}
.twxdb-chip.is-sel{border-color:var(--tw-yellow)}
.twxdb-chip img{margin:0 auto;max-width:min(46px,100%,var(--twxdb-natural-width,46px));max-height:46px;object-fit:contain}
.twxdb-chip--wide{grid-column:span 2}
.twxdb-chip--wide img{max-width:min(100%,var(--twxdb-natural-width,100%));border-radius:6px}
.twxdb-pick img{max-width:min(26px,100%,var(--twxdb-natural-width,26px));max-height:26px;object-fit:contain}
.twxdb-noneimg{display:block;width:46px;height:46px;border-radius:50%;background:#F2F5F7;border:2px solid var(--tw-navy)}
.twxdb-cap{font-size:12.5px;color:var(--tw-navy);opacity:.7;line-height:1.5;margin:14px 0 0}
.twxdb-disc{font-size:12px;color:var(--tw-navy);opacity:.6;line-height:1.5;margin:10px 0 0}
.twxdb-form{background:white;border:2px solid #F2F5F7;border-radius:12px;padding:20px;max-width:460px;margin-top:18px}
.twxdb-form label{display:block;font-family:Montserrat,sans-serif;font-weight:700;font-size:13px;color:var(--tw-navy);margin:0 0 4px}
.twxdb-form .twxdb-fld{margin:0 0 12px}
.twxdb-in{width:100%;border:2px solid #F2F5F7;border-radius:6px;padding:12px 14px;font-size:16px;font-family:inherit;color:var(--tw-navy);background:white;min-height:48px}
.twxdb-in:focus{outline:none;border-color:var(--tw-navy)}
textarea.twxdb-in{min-height:84px;resize:vertical}
.twxdb-btn{display:inline-block;width:100%;background:var(--tw-yellow);color:var(--tw-navy);font-family:Montserrat,sans-serif;font-weight:700;font-size:16px;line-height:1;padding:16px 28px;border-radius:6px;border:0;letter-spacing:.02em;cursor:pointer;min-height:48px}
.twxdb-btn:disabled{opacity:.6;cursor:default}
.twxdb-err{background:#F2F5F7;border-left:4px solid var(--tw-yellow);border-radius:6px;padding:12px 14px;font-size:14px;color:var(--tw-navy);line-height:1.5;margin-top:12px}
.twxdb-err a{color:var(--tw-navy);font-weight:700}
.twxdb-hp{position:absolute!important;left:-9999px!important;top:auto!important;width:1px;height:1px;overflow:hidden}
.twxdb-none{font-size:15px;color:var(--tw-navy);opacity:.8;line-height:1.55;margin:0 0 6px}
.twxdb-load{padding:40px 0;text-align:center;font-size:15px;color:var(--tw-navy);opacity:.75}
.twxdb-thanks{text-align:center;padding:44px 16px}
.twxdb-thanks .twxdb-h:after{margin-left:auto;margin-right:auto}
.twxdb-thanks p{font-size:16px;line-height:1.6;margin:0 auto;max-width:520px}
@media(min-width:700px){.twxdb-visuals{grid-template-columns:minmax(0,2fr) minmax(180px,1fr)}.twxdb-h{font-size:30px}.twxdb-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.twxdb-dots{width:auto;order:0}}
@media(min-width:1000px){.twxdb-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
</style>
<script>window.TwinsDoorBuilderReferenceManifest = {"schemaVersion":1,"allowedHost":"www.clopaydoor.com","products":{"12":{"title":"Gallery Steel","referencePhotos":[{"url":"https://www.clopaydoor.com/images/default-source/product-images/garage-doors/gallerysteel/normal/gallery-steel-ug-long-rec14-822.webp?sfvrsn=3eb6b5f1_6","evidence":"reference-photo","label":"Gallery Steel inspiration photo","source":"clopay-public-product-api","sourceField":"ProductImageGallery.ImageUrl","rightsStatement":"Manufacturer-hosted reference image; deployment remains subject to owner review."},{"url":"https://www.clopaydoor.com/images/default-source/product-images/garage-doors/gallerysteel/normal/gallery-lp-ug-rectgrilles-01.webp?sfvrsn=e1e3dabb_6","evidence":"reference-photo","label":"Gallery Steel installed-door reference","source":"clopay-public-product-api","sourceField":"ProductImageGallery.ImageUrl","rightsStatement":"Manufacturer-hosted reference image; deployment remains subject to owner review."}]}}};</script>
<script>(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.TwinsDoorBuilderCore = api;
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  function stringValue(value) {
    return value == null ? '' : String(value);
  }

  function decodeEntities(value) {
    return stringValue(value)
      .replace(/&nbsp;/gi, ' ')
      .replace(/&amp;/gi, '&')
      .replace(/&quot;/gi, '"')
      .replace(/&#39;|&apos;/gi, "'")
      .replace(/&reg;/gi, '®')
      .replace(/&trade;/gi, '™');
  }

  function sanitizeDisplay(value) {
    return stringValue(value)
      .replace(/<br\b[^>]*>/gi, '<br>')
      .replace(/<(\/?)sup\b[^>]*>/gi, '<$1sup>')
      .replace(/<(?!br>|\/?sup>)[^>]*>/gi, '');
  }

  function plainText(value) {
    return decodeEntities(stringValue(value)
      .replace(/<br\b[^>]*>/gi, ' ')
      .replace(/<\/?sup\b[^>]*>/gi, '')
      .replace(/<[^>]*>/g, ' '))
      .replace(/\s+/g, ' ')
      .trim();
  }

  function validateImageUrl(value) {
    try {
      var url = new URL(stringValue(value));
      if (url.protocol !== 'https:' || url.hostname !== 'www.clopaydoor.com') {
        return null;
      }
      return url.href;
    } catch (_error) {
      return null;
    }
  }

  function optionList(value, imageKey, evidence) {
    return (Array.isArray(value) ? value : []).map(function (item) {
      return {
        titleHtml: sanitizeDisplay(item && item.Title),
        title: plainText(item && item.Title),
        group: plainText(item && item.GroupName),
        image: validateImageUrl(item && item[imageKey]),
        evidence: evidence
      };
    });
  }

  function windowList(value) {
    return optionList(value, 'ThumbnailImage', 'swatch-only').map(function (item) {
      item.none = /\bsolid\b/i.test(item.group) || /\bsolid\b/i.test(item.title);
      return item;
    });
  }

  function normalizeProduct(raw) {
    raw = raw && typeof raw === 'object' ? raw : {};
    var gallery = Array.isArray(raw.ProductImageGallery) ? raw.ProductImageGallery : [];
    return {
      id: plainText(raw.ProductId),
      titleHtml: sanitizeDisplay(raw.Title),
      title: plainText(raw.Title),
      showcaseImage: validateImageUrl(raw.ShowcaseImage),
      designs: optionList(raw.ProductDesigns, 'ProductImage', 'panel-style'),
      colors: optionList(raw.Colors, 'ProductImage', 'swatch-only'),
      windows: windowList(raw.TopSections),
      glass: optionList(raw.SpecialityGlassOptions, 'Image', 'swatch-only'),
      referencePhotos: gallery.filter(function (item) {
        return !item || item.IsImage !== false;
      }).map(function (item) {
        return {
          title: plainText(item && item.Title),
          image: validateImageUrl(item && item.ImageUrl),
          evidence: 'reference-photo'
        };
      }).filter(function (item) {
        return Boolean(item.image);
      })
    };
  }

  function normalizeCatalog(raw) {
    return (Array.isArray(raw) ? raw : []).map(function (item) {
      return {
        id: plainText(item && item.ProductId),
        titleHtml: sanitizeDisplay(item && item.Title),
        title: plainText(item && item.Title),
        shortDescriptionHtml: sanitizeDisplay(item && item.ShortDescription),
        shortDescription: plainText(item && item.ShortDescription),
        showcaseImage: validateImageUrl(item && item.ShowcaseImage)
      };
    }).filter(function (item) {
      return Boolean(item.id && item.title);
    });
  }

  function parseDeepLink(search, allowedIds) {
    var value = new URLSearchParams(stringValue(search)).get('product');
    if (!value || !/^[1-9][0-9]*$/.test(value)) {
      return null;
    }
    return allowedIds && allowedIds.has(value) ? value : null;
  }

  function addPayloadChoice(payload, key, choice) {
    var value = plainText(choice && (choice.titleHtml || choice.title));
    if (value) {
      payload[key] = value;
    }
  }

  function buildLeadPayload(input, state, region) {
    input = input || {};
    state = state || {};
    var payload = {
      name: plainText(input.name),
      phone: plainText(input.phone),
      email: plainText(input.email),
      zip: plainText(input.zip),
      region: ['main', 'wi', 'ky'].indexOf(region) >= 0 ? region : 'main',
      website: plainText(input.website)
    };
    if (plainText(input.notes)) {
      payload.notes = plainText(input.notes);
    }
    addPayloadChoice(payload, 'collection', state.product);
    addPayloadChoice(payload, 'design', state.design);
    addPayloadChoice(payload, 'color', state.color);
    addPayloadChoice(payload, 'windows', state.window);
    addPayloadChoice(payload, 'glass', state.glass);
    return payload;
  }

  function selectPreview(product, state, manifest) {
    product = product || {};
    state = state || {};
    manifest = manifest && typeof manifest === 'object' ? manifest : { products: {} };
    var entry = manifest.products && manifest.products[String(product.id)];
    var curated = entry && Array.isArray(entry.referencePhotos) ? entry.referencePhotos : [];
    var curatedPhoto = curated.map(function (item) {
      if (!item || item.evidence !== 'reference-photo') {
        return null;
      }
      var image = validateImageUrl(item.url);
      return image ? {
        image: image,
        evidence: 'reference-photo',
        label: 'Inspiration photo — your selected options are listed separately and Twins will confirm the final appearance.',
        allowUpscale: false
      } : null;
    }).filter(Boolean)[0];
    var galleryPhoto = product.referencePhotos && product.referencePhotos[0];
    var hero = curatedPhoto || (galleryPhoto ? {
      image: galleryPhoto.image,
      evidence: 'reference-photo',
      label: 'Inspiration photo — your selected options are listed separately and Twins will confirm the final appearance.',
      allowUpscale: false
    } : null);
    if (!hero && product.showcaseImage) {
      hero = {
        image: product.showcaseImage,
        evidence: 'reference-photo',
        label: 'Inspiration photo — your selected options are listed separately and Twins will confirm the final appearance.',
        allowUpscale: false
      };
    }
    var panel = state.design && state.design.image ? {
      image: state.design.image,
      evidence: 'panel-style',
      label: 'Panel-style reference shown at its original resolution.',
      allowUpscale: false
    } : null;
    return {
      hero: hero,
      panel: panel,
      samples: [state.color, state.window, state.glass].filter(Boolean).map(function (item) {
        return {
          title: plainText(item.titleHtml || item.title),
          image: item.image || null,
          evidence: 'swatch-only',
          label: 'Manufacturer sample — final appearance is confirmed before ordering.',
          allowUpscale: false
        };
      })
    };
  }

  function availableSteps(product, state) {
    product = product || {};
    state = state || {};
    var steps = ['collection'];
    if (product.designs && product.designs.length) steps.push('design');
    if (product.colors && product.colors.length) steps.push('color');
    if (product.windows && product.windows.length) steps.push('windows');
    if (state.window && !state.window.none && product.glass && product.glass.length) {
      steps.push('glass');
    }
    steps.push('summary');
    return steps;
  }

  function nextStep(current, product, state) {
    var canonical = ['collection', 'design', 'color', 'windows', 'glass', 'summary'];
    var available = new Set(availableSteps(product, state));
    var index = canonical.indexOf(current);
    for (var position = index + 1; position < canonical.length; position += 1) {
      if (available.has(canonical[position])) return canonical[position];
    }
    return 'summary';
  }

  return {
    sanitizeDisplay: sanitizeDisplay,
    plainText: plainText,
    validateImageUrl: validateImageUrl,
    normalizeCatalog: normalizeCatalog,
    normalizeProduct: normalizeProduct,
    parseDeepLink: parseDeepLink,
    buildLeadPayload: buildLeadPayload,
    selectPreview: selectPreview,
    availableSteps: availableSteps,
    nextStep: nextStep
  };
}));
</script>
<script>(function (root, factory) {
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
</script>
<script>(function (root, factory) {
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
</script>
<script>(function (root, factory) {
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

    function dependencyFallbackHTML() {
      return '<div class="twxdb-app"><h2 class="twxdb-h">Call us for your free door quote</h2>'
        + '<p class="twxdb-sub">The door builder is unavailable right now. Call us at <a href="tel:'
        + PHONE.tel + '">' + PHONE.disp + '</a> and we\'ll help you choose the right door.</p></div>';
    }

    var core = windowRef.TwinsDoorBuilderCore;
    var transport = windowRef.TwinsDoorBuilderTransport;
    var funnel = windowRef.TwinsDoorBuilderFunnel;
    if (!core || !transport || !funnel || typeof windowRef.fetch !== 'function') {
      mount.innerHTML = dependencyFallbackHTML();
      return false;
    }
    mountedRoot = mount;

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
        event.target.style.setProperty('--twxdb-natural-width', event.target.naturalWidth + 'px');
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
</script>
<?php return ob_get_clean();
} );
