(function (root, factory) {
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
      windows: optionList(raw.TopSections, 'ThumbnailImage', 'swatch-only'),
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
