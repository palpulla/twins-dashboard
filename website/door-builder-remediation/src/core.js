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

  return {
    sanitizeDisplay: sanitizeDisplay,
    plainText: plainText,
    validateImageUrl: validateImageUrl,
    normalizeCatalog: normalizeCatalog,
    normalizeProduct: normalizeProduct,
    parseDeepLink: parseDeepLink,
    buildLeadPayload: buildLeadPayload
  };
}));
