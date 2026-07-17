(() => {
  'use strict';

  const BUILDER_PRODUCT_ORDER = Object.freeze([
    '330', '320', '30', '29', '240', '26', '170', '340', '12', '16', '290', '370',
    '250', '380', '11', '27', '291', '8', '10', '25', '9', '13', '23',
  ]);
  const BUILDER_STAGE_LABELS = Object.freeze({
    collection: 'Collection',
    design: 'Design',
    color: 'Color',
    windows: 'Windows',
    glass: 'Glass',
    hardware: 'Hardware (optional)',
    summary: 'Summary',
    'contact-preview': 'Contact Preview',
  });
  const BUILDER_OPTION_FIELDS = Object.freeze({
    design: 'designs',
    color: 'colors',
    windows: 'windows',
    glass: 'glass',
    hardware: 'hardware',
  });
  const BUILDER_LOCAL_IMAGE = /^\/wp-content\/mu-plugins\/twins-staging-assets\/clopay\/[a-f0-9]{2}\/[a-f0-9]{64}\.(?:webp|jpg)$/;

  function builderPlainText(value, limit) {
    if (typeof value !== 'string') return '';
    const clean = value.replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim();
    return clean && clean.length <= limit ? clean : '';
  }

  function validBuilderImage(image) {
    return Boolean(
      image
      && Object.getPrototypeOf(image) === Object.prototype
      && Object.keys(image).join(',') === 'src,width,height,alt'
      && BUILDER_LOCAL_IMAGE.test(image.src)
      && Number.isInteger(image.width)
      && image.width > 0
      && image.width <= 8192
      && Number.isInteger(image.height)
      && image.height > 0
      && image.height <= 8192
      && image.width * image.height <= 40000000
      && typeof image.alt === 'string'
      && image.alt.length <= 128
    );
  }

  function validBuilderOption(option, prefix, index, windowFamily) {
    if (!option || Object.getPrototypeOf(option) !== Object.prototype) return false;
    if (option.id !== `${prefix}-${index}`) return false;
    if (!builderPlainText(option.title, 180) || typeof option.group !== 'string' || option.group.length > 180) return false;
    if (!validBuilderImage(option.image)) return false;
    return !windowFamily || typeof option.solid === 'boolean';
  }

  function validBuilderCatalog(catalog) {
    if (!catalog || Object.getPrototypeOf(catalog) !== Object.prototype || catalog.schemaVersion !== 1) return false;
    if (!Array.isArray(catalog.productOrder) || !Array.isArray(catalog.products)) return false;
    if (
      catalog.products.length !== BUILDER_PRODUCT_ORDER.length
      || catalog.productOrder.length !== BUILDER_PRODUCT_ORDER.length
    ) return false;

    for (let productIndex = 0; productIndex < BUILDER_PRODUCT_ORDER.length; productIndex += 1) {
      const expectedId = BUILDER_PRODUCT_ORDER[productIndex];
      const product = catalog.products[productIndex];
      if (catalog.productOrder[productIndex] !== expectedId || !product || product.id !== expectedId) return false;
      if (!builderPlainText(product.title, 180) || !validBuilderImage(product.showcase)) return false;
      for (const field of ['designs', 'colors', 'windows', 'glass', 'hardware', 'gallery']) {
        if (!Array.isArray(product[field]) || product[field].length > 96) return false;
        const prefix = field === 'designs' ? 'design'
          : field === 'colors' ? 'color'
            : field === 'windows' ? 'window'
              : field;
        if (!product[field].every((option, index) => validBuilderOption(
          option,
          prefix,
          index,
          field === 'windows',
        ))) return false;
      }
    }
    return true;
  }

  function builderCatalogFromDocument(builder) {
    const parent = builder.parentElement;
    const source = builder.querySelector('[data-twins-builder-catalog]')
      || parent?.querySelector('[data-twins-builder-catalog]');
    if (!source) return null;
    const serialized = String(source.textContent || '');
    if (!serialized || serialized.length > 2000000) return null;
    try {
      return JSON.parse(serialized);
    } catch {
      return null;
    }
  }

  function builderDeepLinkProduct(catalog) {
    if (!window.location || typeof window.location.search !== 'string') return null;
    const values = new URLSearchParams(window.location.search).getAll('product');
    if (values.length !== 1 || !/^[1-9]\d*$/.test(values[0])) return null;
    return catalog.products.find(product => product.id === values[0]) || null;
  }

  function builderElement(owner, tag, className, text) {
    const element = owner.createElement(tag);
    if (className) element.className = className;
    if (typeof text === 'string') element.textContent = text;
    return element;
  }

  function builderImage(owner, image, alt, className) {
    const element = builderElement(owner, 'img', className || '');
    element.src = image.src;
    element.alt = image.alt || alt;
    element.width = image.width;
    element.height = image.height;
    element.loading = 'lazy';
    element.decoding = 'async';
    element.setAttribute('data-builder-reference', 'manufacturer');
    return element;
  }

  function builderPrefersReducedMotion() {
    return Boolean(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function builderCleanTitle(title) {
    let clean = String(title || '').trim();
    clean = clean.replace(/\s*\([A-Z]{1,3}\)\s*[*±]?$/, '');
    clean = clean.replace(/[*±]+$/, '').trim();
    if (clean.length > 3 && clean === clean.toUpperCase() && /[A-Z]/.test(clean)) {
      clean = clean.toLowerCase().replace(/(^|[\s\-/])([a-z0-9])/g, (match, lead, letter) => lead + letter.toUpperCase());
    }
    return clean;
  }

  const builderSwatchColors = new Map();

  function builderDominantColor(src, apply) {
    if (builderSwatchColors.has(src)) {
      apply(builderSwatchColors.get(src));
      return;
    }
    const probe = new Image();
    probe.decoding = 'async';
    probe.onload = () => {
      try {
        const canvas = document.createElement('canvas');
        canvas.width = 12;
        canvas.height = 12;
        const context = canvas.getContext('2d');
        context.drawImage(probe, 0, 0, 12, 12);
        const data = context.getImageData(0, 0, 12, 12).data;
        let red = 0;
        let green = 0;
        let blue = 0;
        let counted = 0;
        for (let index = 0; index < data.length; index += 4) {
          if (data[index + 3] < 128) continue;
          red += data[index];
          green += data[index + 1];
          blue += data[index + 2];
          counted += 1;
        }
        if (counted === 0) {
          apply(null);
          return;
        }
        const color = `rgb(${Math.round(red / counted)}, ${Math.round(green / counted)}, ${Math.round(blue / counted)})`;
        builderSwatchColors.set(src, color);
        apply(color);
      } catch (error) {
        apply(null);
      }
    };
    probe.onerror = () => apply(null);
    probe.src = src;
  }

  function initBuilder(root) {
    const scope = root || document;
    const builders = [];
    if (scope.matches?.('[data-twins-overhaul-builder]')) builders.push(scope);
    scope.querySelectorAll?.('[data-twins-overhaul-builder]').forEach(builder => {
      if (!builders.includes(builder)) builders.push(builder);
    });

    let enhanced = 0;
    builders.forEach(builder => {
      if (builder.getAttribute('data-builder-enhanced') === 'true') return;
      const catalog = builderCatalogFromDocument(builder);
      if (!validBuilderCatalog(catalog)) return;

      const owner = builder.ownerDocument || document;
      const mount = builder.querySelector('[data-builder-stage]') || builder.querySelector('[data-builder-app]');
      if (!mount) return;
      const stepControls = [...builder.querySelectorAll('[data-builder-step-target]')];
      const state = {
        step: 'collection',
        product: null,
        design: null,
        color: null,
        window: null,
        glass: null,
        hardware: null,
      };

      function availableStages() {
        if (!state.product) return ['collection'];
        const product = state.product;
        const stages = ['collection'];
        if (product.designs.length) stages.push('design');
        if (product.colors.length) stages.push('color');
        if (product.windows.length) stages.push('windows');
        const windowIsSolid = state.window && state.window.solid;
        if (windowIsSolid) state.glass = null;
        if (
          (product.windows.length === 0 && product.glass.length > 0)
          || (state.window && !windowIsSolid && product.glass.length > 0)
        ) stages.push('glass');
        if (product.hardware.length) stages.push('hardware');
        stages.push('summary', 'contact-preview');
        return stages;
      }

      function optionLabel(option) {
        const title = builderCleanTitle(option.title);
        return option.group ? `${builderCleanTitle(option.group)} · ${title}` : title;
      }

      function configurationRows() {
        const rows = [];
        if (state.product) rows.push(['Collection', state.product.title]);
        for (const field of ['design', 'color', 'window', 'glass', 'hardware']) {
          if (!state[field]) continue;
          rows.push([field === 'window' ? 'Windows' : BUILDER_STAGE_LABELS[field], optionLabel(state[field])]);
        }
        return rows;
      }

      function summaryText() {
        return `Twins Garage Doors — Door Builder Preview\n${configurationRows().map(
          row => `${row[0]}: ${row[1]}`,
        ).join('\n')}`;
      }

      function renderReference(parent) {
        if (!state.product) return;
        const composed = Boolean(state.design);
        const panel = composed ? state.design.image : state.product.showcase;
        const figure = builderElement(owner, 'figure', 'twins-builder__reference');
        const stagePane = builderElement(owner, 'div', 'twins-builder__composite');
        stagePane.appendChild(builderImage(
          owner,
          panel,
          `${state.product.title} manufacturer reference`,
          'twins-builder__reference-image',
        ));
        if (composed && state.color) {
          const tint = builderElement(owner, 'div', 'twins-builder__tint');
          stagePane.appendChild(tint);
          builderDominantColor(state.color.image.src, color => {
            if (!color) return;
            tint.style.backgroundColor = color;
            tint.setAttribute('data-tint-ready', 'true');
          });
        }
        if (composed && state.window) {
          const windowsOverlay = builderImage(
            owner,
            state.window.image,
            `Window style: ${optionLabel(state.window)}`,
            'twins-builder__overlay twins-builder__overlay--windows',
          );
          stagePane.appendChild(windowsOverlay);
        }
        if (composed && state.hardware) {
          const hardwareOverlay = builderImage(
            owner,
            state.hardware.image,
            `Decorative hardware: ${optionLabel(state.hardware)}`,
            'twins-builder__overlay twins-builder__overlay--hardware',
          );
          stagePane.appendChild(hardwareOverlay);
        }
        figure.appendChild(stagePane);
        const selections = configurationRows().slice(1).map(row => row[1]).join(', ');
        const caption = composed
          ? `Illustrative preview of your selections${selections ? `: ${selections}` : ''}. Manufacturer reference only. Twins confirms final appearance before ordering.`
          : 'Manufacturer reference only. Pick a panel or design and your color, window, and hardware choices appear on the door; Twins confirms final appearance before ordering.';
        figure.appendChild(builderElement(
          owner,
          'figcaption',
          'twins-builder__reference-caption',
          caption,
        ));
        parent.appendChild(figure);

        const samples = [state.color, state.window, state.glass, state.hardware].filter(Boolean);
        if (samples.length) {
          const sampleStrip = builderElement(owner, 'div', 'twins-builder__samples');
          sampleStrip.setAttribute('aria-label', 'Selected manufacturer samples');
          samples.forEach(sample => {
            const sampleFigure = builderElement(owner, 'figure', 'twins-builder__sample');
            sampleFigure.appendChild(builderImage(
              owner,
              sample.image,
              optionLabel(sample),
              'twins-builder__sample-image',
            ));
            sampleFigure.appendChild(builderElement(owner, 'figcaption', '', optionLabel(sample)));
            sampleStrip.appendChild(sampleFigure);
          });
          parent.appendChild(sampleStrip);
        }

        if (state.product.gallery.length) {
          const gallery = builderElement(owner, 'div', 'twins-builder__gallery');
          gallery.setAttribute('aria-label', 'Real product gallery references');
          state.product.gallery.forEach(photo => {
            const galleryFigure = builderElement(owner, 'figure', 'twins-builder__gallery-item');
            galleryFigure.appendChild(builderImage(
              owner,
              photo.image,
              optionLabel(photo),
              'twins-builder__gallery-image',
            ));
            galleryFigure.appendChild(builderElement(owner, 'figcaption', '', optionLabel(photo)));
            gallery.appendChild(galleryFigure);
          });
          parent.appendChild(gallery);
        }
      }

      function actionButton(label, action, className) {
        const control = builderElement(owner, 'button', className || 'twins-builder__action', label);
        control.type = 'button';
        control.setAttribute('data-builder-action', action);
        return control;
      }

      function renderCollection(body) {
        const grid = builderElement(owner, 'div', 'twins-builder__collection-grid');
        catalog.products.forEach(product => {
          const control = actionButton('', 'select-product', 'twins-builder__product-card');
          control.setAttribute('data-builder-product-id', product.id);
          control.setAttribute('aria-pressed', String(state.product === product));
          control.appendChild(builderImage(owner, product.showcase, product.title, 'twins-builder__product-image'));
          control.appendChild(builderElement(owner, 'span', 'twins-builder__product-title', product.title));
          grid.appendChild(control);
        });
        body.appendChild(grid);
      }

      function renderOptions(body, field) {
        const productField = BUILDER_OPTION_FIELDS[field];
        const options = state.product[productField];
        const grid = builderElement(owner, 'div', 'twins-builder__option-grid');
        options.forEach(option => {
          const control = actionButton('', 'select-option', 'twins-builder__option');
          control.setAttribute('data-builder-field', field);
          control.setAttribute('data-builder-option-id', option.id);
          control.setAttribute('aria-pressed', String(state[field === 'windows' ? 'window' : field] === option));
          control.appendChild(builderImage(owner, option.image, optionLabel(option), 'twins-builder__option-image'));
          if (option.group) control.appendChild(builderElement(owner, 'span', 'twins-builder__option-group', builderCleanTitle(option.group)));
          control.appendChild(builderElement(owner, 'span', 'twins-builder__option-title', builderCleanTitle(option.title)));
          grid.appendChild(control);
        });
        body.appendChild(grid);
        if (field === 'hardware' && state.hardware) {
          body.appendChild(actionButton('Clear hardware choice', 'clear-hardware', 'twins-builder__clear'));
        }
        body.appendChild(builderElement(
          owner,
          'p',
          'twins-builder__sample-note',
          'Manufacturer sample — final appearance is confirmed before ordering.',
        ));
      }

      function renderSummary(body) {
        const list = builderElement(owner, 'dl', 'twins-builder__summary');
        configurationRows().forEach(row => {
          list.appendChild(builderElement(owner, 'dt', '', row[0]));
          list.appendChild(builderElement(owner, 'dd', '', row[1]));
        });
        body.appendChild(list);
        const copy = actionButton('Copy Summary', 'copy-summary', 'twins-builder__copy');
        copy.setAttribute('data-builder-copy-summary', '');
        body.appendChild(copy);
        const status = builderElement(owner, 'p', 'twins-builder__copy-status');
        status.setAttribute('data-builder-copy-status', '');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        body.appendChild(status);
      }

      function renderContactPreview(body) {
        const phone = builderPlainText(builder.getAttribute('data-builder-phone'), 30);
        const tel = builderPlainText(builder.getAttribute('data-builder-tel'), 30);
        body.appendChild(builderElement(
          owner,
          'p',
          'twins-builder__contact-note',
          'This private staging preview does not send or store lead information. Selections remain only in memory until you leave or reload. Copy Summary explicitly places the summary on your system clipboard.',
        ));
        if (phone && /^\(\d{3}\) \d{3}-\d{4}$/.test(phone) && /^\+\d{11,15}$/.test(tel)) {
          const link = builderElement(owner, 'a', 'twins-builder__phone', `Call Twins at ${phone}`);
          link.href = `tel:${tel}`;
          body.appendChild(link);
        }
      }

      function move(direction) {
        const coreStages = availableStages().filter(stage => stage !== 'hardware');
        if (state.step === 'hardware') {
          const summary = coreStages.indexOf('summary');
          state.step = direction > 0 ? 'summary' : coreStages[Math.max(0, summary - 1)];
        } else {
          const current = Math.max(0, coreStages.indexOf(state.step));
          state.step = coreStages[Math.min(coreStages.length - 1, Math.max(0, current + direction))];
        }
        render(true);
      }

      function render(focusHeading) {
        const stages = availableStages();
        if (!stages.includes(state.step)) state.step = stages[stages.length - 2] || 'collection';
        builder.setAttribute('data-builder-current-step', state.step);
        stepControls.forEach(control => {
          const target = control.getAttribute('data-builder-step-target') || '';
          const isAvailable = stages.includes(target);
          control.hidden = !isAvailable;
          control.disabled = !isAvailable;
          if (control.parentElement?.tagName === 'LI') control.parentElement.hidden = !isAvailable;
          if (target === state.step) control.setAttribute('aria-current', 'step');
          else control.removeAttribute('aria-current');
        });

        const view = builderElement(owner, 'section', 'twins-builder__view');
        const heading = builderElement(owner, 'h2', 'twins-builder__step-heading', BUILDER_STAGE_LABELS[state.step]);
        heading.tabIndex = -1;
        view.appendChild(heading);
        const body = builderElement(owner, 'div', 'twins-builder__stage-body');
        if (state.step === 'collection') renderCollection(body);
        else if (BUILDER_OPTION_FIELDS[state.step]) renderOptions(body, state.step);
        else if (state.step === 'summary') renderSummary(body);
        else renderContactPreview(body);
        view.appendChild(body);

        if (state.product && state.step !== 'collection') {
          const reference = builderElement(owner, 'aside', 'twins-builder__preview');
          reference.appendChild(builderElement(owner, 'h2', 'twins-builder__preview-title', 'Your reference board'));
          renderReference(reference);
          const layout = builderElement(owner, 'div', 'twins-builder__workspace');
          layout.append(view, reference);
          mount.replaceChildren(layout);
        } else {
          mount.replaceChildren(view);
        }

        if (state.product) {
          const actions = builderElement(owner, 'div', 'twins-builder__navigation');
          if (state.step !== 'collection') actions.appendChild(actionButton('Back', 'back', 'twins-builder__back'));
          if (state.step !== 'contact-preview') actions.appendChild(actionButton('Continue', 'next', 'twins-builder__next'));
          mount.appendChild(actions);
        }
        if (focusHeading) {
          heading.focus({ preventScroll: true });
          const anchor = heading.getBoundingClientRect().top + window.pageYOffset - 150;
          window.scrollTo({
            top: Math.max(anchor, 0),
            behavior: builderPrefersReducedMotion() ? 'auto' : 'smooth',
          });
        }
      }

      stepControls.forEach(control => {
        control.addEventListener('click', () => {
          const target = control.getAttribute('data-builder-step-target') || '';
          if (!availableStages().includes(target)) return;
          state.step = target;
          render(true);
        });
      });

      mount.addEventListener('click', event => {
        const control = event.target.closest?.('[data-builder-action]');
        if (!control || !mount.contains(control)) return;
        const action = control.getAttribute('data-builder-action');
        if (action === 'select-product') {
          const productId = control.getAttribute('data-builder-product-id');
          state.product = catalog.products.find(product => product.id === productId) || null;
          state.design = null;
          state.color = null;
          state.window = null;
          state.glass = null;
          state.hardware = null;
          state.step = availableStages()[1] || 'summary';
          render(true);
          return;
        }
        if (action === 'select-option' && state.product) {
          const field = control.getAttribute('data-builder-field') || '';
          const productField = BUILDER_OPTION_FIELDS[field];
          if (!productField) return;
          const optionId = control.getAttribute('data-builder-option-id') || '';
          const option = state.product[productField].find(item => item.id === optionId);
          if (!option) return;
          state[field === 'windows' ? 'window' : field] = option;
          if (field === 'windows') state.glass = null;
          render(false);
          const replacement = [...mount.querySelectorAll('[data-builder-option-id]')].find(candidate => (
            candidate.getAttribute('data-builder-field') === field
            && candidate.getAttribute('data-builder-option-id') === optionId
          ));
          if (replacement) {
            try {
              replacement.focus({ preventScroll: true });
            } catch {
              replacement.focus();
            }
          }
          return;
        }
        if (action === 'back') move(-1);
        else if (action === 'next') move(1);
        else if (action === 'clear-hardware') {
          state.hardware = null;
          render(true);
        } else if (action === 'copy-summary') {
          const status = mount.querySelector('[data-builder-copy-status]');
          if (!status) return;
          if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
            status.textContent = 'Copy is unavailable in this browser. Keep this summary open while you call Twins.';
            return;
          }
          navigator.clipboard.writeText(summaryText()).then(() => {
            status.textContent = 'Configuration summary copied.';
          }).catch(() => {
            status.textContent = 'Copy was blocked. Keep this summary open while you call Twins.';
          });
        }
      });

      const deepLinkedProduct = builderDeepLinkProduct(catalog);
      if (deepLinkedProduct) {
        state.product = deepLinkedProduct;
        state.step = availableStages()[1] || 'summary';
      }
      render(false);
      const fallback = builder.parentElement?.querySelector('[data-builder-fallback]');
      if (fallback) fallback.hidden = true;
      builder.querySelectorAll(
        '[data-builder-contact-preview], [data-builder-status], .twins-builder__copy-label',
      ).forEach(serverOnly => {
        serverOnly.hidden = true;
      });
      builder.hidden = false;
      builder.setAttribute('data-builder-enhanced', 'true');
      enhanced += 1;
    });
    return enhanced;
  }

  function start() {
    initBuilder(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
