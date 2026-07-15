(function (window, document) {
  'use strict';

  const ZIP_ROUTES = Object.freeze({
    '537': '/wi/garage-door-cost-in-madison-wi/',
    '531': '/wi/garage-door-cost-in-milwaukee-wi/',
    '532': '/wi/garage-door-cost-in-milwaukee-wi/'
  });
  const ZIP_FALLBACK = '/wi/contact-us/';

  function prefersReducedMotion() {
    return typeof window.matchMedia === 'function' &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function safeLocalPath(path) {
    return typeof path === 'string' &&
      path.charAt(0) === '/' &&
      path.charAt(1) !== '/' &&
      path.indexOf('..') === -1 &&
      path.indexOf('\\') === -1 &&
      !/[\u0000-\u001f\u007f]/.test(path)
      ? path
      : '';
  }

  function initMenu(root) {
    const scope = root || document;
    const trigger = scope.querySelector('.twins-overhaul-menu-trigger');
    const navigation = scope.querySelector('.twins-overhaul-nav');
    const page = document.body;
    if (!trigger || !navigation || !page) return null;

    page.classList.add('twins-overhaul-enhanced');

    function closeMenu() {
      page.classList.remove('twins-overhaul-menu-open');
      trigger.setAttribute('aria-expanded', 'false');
    }

    function toggleMenu() {
      const open = trigger.getAttribute('aria-expanded') === 'true';
      if (open) {
        closeMenu();
      } else {
        page.classList.add('twins-overhaul-menu-open');
        trigger.setAttribute('aria-expanded', 'true');
      }
    }

    trigger.addEventListener('click', toggleMenu);
    scope.querySelectorAll('[data-twins-overhaul-menu-close]').forEach(function (control) {
      control.addEventListener('click', function () {
        closeMenu();
        trigger.focus();
      });
    });
    scope.querySelectorAll('.twins-overhaul-nav a').forEach(function (control) {
      control.addEventListener('click', closeMenu);
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeMenu();
        trigger.focus();
      }
    });

    return Object.freeze({ close: closeMenu, toggle: toggleMenu });
  }

  function initZip(root) {
    const scope = root || document;
    const widgets = scope.querySelectorAll('[data-twins-overhaul-zip]');

    widgets.forEach(function (widget) {
      const input = widget.querySelector('[data-twins-zip-input]');
      const control = widget.querySelector('[data-twins-zip-route]');
      const status = widget.querySelector('[data-twins-zip-status]');
      if (!input || !control || !status) return;

      function routeZip() {
        const zip = String(input.value || '').trim();
        if (!/^\d{5}$/.test(zip)) {
          input.setAttribute('aria-invalid', 'true');
          status.textContent = 'Enter a valid 5-digit ZIP code.';
          return;
        }

        input.removeAttribute('aria-invalid');
        const destination = safeLocalPath(ZIP_ROUTES[zip.slice(0, 3)] || ZIP_FALLBACK);

        status.textContent = destination === ZIP_FALLBACK
          ? 'Opening the Wisconsin contact guide for a service-area check.'
          : 'Opening the matching local cost guide.';
        window.location.href = destination;
      }

      control.addEventListener('click', routeZip);
      input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          routeZip();
        }
      });
    });

    return widgets.length;
  }

  const BUILDER_PRODUCT_ORDER = Object.freeze([
    '330', '320', '30', '29', '240', '26', '170', '340', '12', '16', '290', '370',
    '250', '380', '11', '27', '291', '8', '10', '25', '9', '13', '23'
  ]);
  const BUILDER_STAGE_LABELS = Object.freeze({
    collection: 'Collection',
    design: 'Design',
    color: 'Color',
    windows: 'Windows',
    glass: 'Glass',
    hardware: 'Hardware (optional)',
    summary: 'Summary',
    'contact-preview': 'Contact Preview'
  });
  const BUILDER_OPTION_FIELDS = Object.freeze({
    design: 'designs',
    color: 'colors',
    windows: 'windows',
    glass: 'glass',
    hardware: 'hardware'
  });
  const BUILDER_LOCAL_IMAGE = /^\/wp-content\/mu-plugins\/twins-staging-assets\/clopay\/[a-f0-9]{2}\/[a-f0-9]{64}\.(?:webp|jpg)$/;

  function builderPlainText(value, limit) {
    if (typeof value !== 'string') return '';
    const clean = value.replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim();
    return clean && clean.length <= limit ? clean : '';
  }

  function validBuilderImage(image) {
    return Boolean(image &&
      Object.getPrototypeOf(image) === Object.prototype &&
      Object.keys(image).join(',') === 'src,width,height,alt' &&
      BUILDER_LOCAL_IMAGE.test(image.src) &&
      Number.isInteger(image.width) && image.width > 0 && image.width <= 8192 &&
      Number.isInteger(image.height) && image.height > 0 && image.height <= 8192 &&
      image.width * image.height <= 40000000 &&
      typeof image.alt === 'string' && image.alt.length <= 128);
  }

  function validBuilderOption(option, prefix, index, windowFamily) {
    if (!option || Object.getPrototypeOf(option) !== Object.prototype) return false;
    if (option.id !== prefix + '-' + index) return false;
    if (!builderPlainText(option.title, 180) || typeof option.group !== 'string' || option.group.length > 180) return false;
    if (!validBuilderImage(option.image)) return false;
    return !windowFamily || typeof option.solid === 'boolean';
  }

  function validBuilderCatalog(catalog) {
    if (!catalog || Object.getPrototypeOf(catalog) !== Object.prototype || catalog.schemaVersion !== 1) return false;
    if (!Array.isArray(catalog.productOrder) || !Array.isArray(catalog.products)) return false;
    if (catalog.products.length !== BUILDER_PRODUCT_ORDER.length ||
        catalog.productOrder.length !== BUILDER_PRODUCT_ORDER.length) return false;

    for (let productIndex = 0; productIndex < BUILDER_PRODUCT_ORDER.length; productIndex += 1) {
      const expectedId = BUILDER_PRODUCT_ORDER[productIndex];
      const product = catalog.products[productIndex];
      if (catalog.productOrder[productIndex] !== expectedId || !product || product.id !== expectedId) return false;
      if (!builderPlainText(product.title, 180) || !validBuilderImage(product.showcase)) return false;
      for (const field of ['designs', 'colors', 'windows', 'glass', 'hardware', 'gallery']) {
        if (!Array.isArray(product[field]) || product[field].length > 96) return false;
        const prefix = field === 'designs' ? 'design' : field === 'colors' ? 'color' :
          field === 'windows' ? 'window' : field;
        if (!product[field].every(function (option, index) {
          return validBuilderOption(option, prefix, index, field === 'windows');
        })) return false;
      }
    }
    return true;
  }

  function builderCatalogFromDocument(builder) {
    const parent = builder.parentElement;
    const source = builder.querySelector('[data-twins-builder-catalog]') ||
      (parent && parent.querySelector('[data-twins-builder-catalog]'));
    if (!source) return null;
    const serialized = String(source.textContent || '');
    if (!serialized || serialized.length > 2000000) return null;
    try {
      return JSON.parse(serialized);
    } catch (error) {
      return null;
    }
  }

  function builderDeepLinkProduct(catalog) {
    if (!window.location || typeof window.location.search !== 'string') return null;
    const parameters = new URLSearchParams(window.location.search);
    const values = parameters.getAll('product');
    if (values.length !== 1 || !/^[1-9]\d*$/.test(values[0])) return null;
    return catalog.products.find(function (product) { return product.id === values[0]; }) || null;
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

  function initBuilder(root, catalog) {
    const scope = root || document;
    const builders = [];
    if (scope.matches && scope.matches('[data-twins-overhaul-builder]')) builders.push(scope);
    if (scope.querySelectorAll) {
      scope.querySelectorAll('[data-twins-overhaul-builder]').forEach(function (builder) {
        if (builders.indexOf(builder) === -1) builders.push(builder);
      });
    }

    let enhanced = 0;
    builders.forEach(function (builder) {
      if (builder.getAttribute('data-builder-enhanced') === 'true') return;
      const localCatalog = catalog || builderCatalogFromDocument(builder);
      if (!validBuilderCatalog(localCatalog)) return;

      const owner = builder.ownerDocument || document;
      const mount = builder.querySelector('[data-builder-stage]') || builder.querySelector('[data-builder-app]');
      if (!mount) return;
      const stepControls = Array.from(builder.querySelectorAll('[data-builder-step-target]'));
      const state = {
        step: 'collection',
        product: null,
        design: null,
        color: null,
        window: null,
        glass: null,
        hardware: null
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
        const glassWithoutWindows = product.windows.length === 0 && product.glass.length > 0;
        const glassWithSelectedWindow = state.window && !windowIsSolid && product.glass.length > 0;
        if (glassWithoutWindows || glassWithSelectedWindow) stages.push('glass');
        if (product.hardware.length) stages.push('hardware');
        stages.push('summary', 'contact-preview');
        return stages;
      }

      function optionLabel(option) {
        return option.group ? option.group + ' — ' + option.title : option.title;
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
        const rows = configurationRows();
        return 'Twins Garage Doors — Door Builder Preview\n' + rows.map(function (row) {
          return row[0] + ': ' + row[1];
        }).join('\n');
      }

      function renderReference(parent) {
        if (!state.product) return;
        const panel = state.design ? state.design.image : state.product.showcase;
        const figure = builderElement(owner, 'figure', 'twins-builder__reference');
        figure.appendChild(builderImage(owner, panel, state.product.title + ' manufacturer reference', 'twins-builder__reference-image'));
        figure.appendChild(builderElement(
          owner,
          'figcaption',
          'twins-builder__reference-caption',
          'Manufacturer reference only. The large image shows the selected panel or design. Colors, windows, glass, and hardware are samples; Twins confirms final appearance before ordering.'
        ));
        parent.appendChild(figure);

        const samples = [state.color, state.window, state.glass, state.hardware].filter(Boolean);
        if (samples.length) {
          const sampleStrip = builderElement(owner, 'div', 'twins-builder__samples');
          sampleStrip.setAttribute('aria-label', 'Selected manufacturer samples');
          samples.forEach(function (sample) {
            const sampleFigure = builderElement(owner, 'figure', 'twins-builder__sample');
            sampleFigure.appendChild(builderImage(owner, sample.image, optionLabel(sample), 'twins-builder__sample-image'));
            sampleFigure.appendChild(builderElement(owner, 'figcaption', '', optionLabel(sample)));
            sampleStrip.appendChild(sampleFigure);
          });
          parent.appendChild(sampleStrip);
        }

        if (state.product.gallery.length) {
          const gallery = builderElement(owner, 'div', 'twins-builder__gallery');
          gallery.setAttribute('aria-label', 'Real product gallery references');
          state.product.gallery.forEach(function (photo) {
            const figure = builderElement(owner, 'figure', 'twins-builder__gallery-item');
            figure.appendChild(builderImage(owner, photo.image, optionLabel(photo), 'twins-builder__gallery-image'));
            figure.appendChild(builderElement(owner, 'figcaption', '', optionLabel(photo)));
            gallery.appendChild(figure);
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
        localCatalog.products.forEach(function (product) {
          const control = actionButton('', 'select-product', 'twins-builder__product-card');
          control.setAttribute('data-builder-product-id', product.id);
          control.setAttribute('aria-pressed', state.product === product ? 'true' : 'false');
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
        options.forEach(function (option) {
          const control = actionButton('', 'select-option', 'twins-builder__option');
          control.setAttribute('data-builder-field', field);
          control.setAttribute('data-builder-option-id', option.id);
          control.setAttribute('aria-pressed', state[field === 'windows' ? 'window' : field] === option ? 'true' : 'false');
          control.appendChild(builderImage(owner, option.image, optionLabel(option), 'twins-builder__option-image'));
          if (option.group) control.appendChild(builderElement(owner, 'span', 'twins-builder__option-group', option.group));
          control.appendChild(builderElement(owner, 'span', 'twins-builder__option-title', option.title));
          grid.appendChild(control);
        });
        body.appendChild(grid);
        if (field === 'hardware' && state.hardware) {
          body.appendChild(actionButton('Clear hardware choice', 'clear-hardware', 'twins-builder__clear'));
        }
        body.appendChild(builderElement(owner, 'p', 'twins-builder__sample-note', 'Manufacturer sample — final appearance is confirmed before ordering.'));
      }

      function renderSummary(body) {
        const list = builderElement(owner, 'dl', 'twins-builder__summary');
        configurationRows().forEach(function (row) {
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
        body.appendChild(builderElement(owner, 'p', 'twins-builder__contact-note', 'This private staging preview does not send or store lead information. Selections remain only in memory until you leave or reload. Copy Summary explicitly places the summary on your system clipboard.'));
        if (phone && /^\(\d{3}\) \d{3}-\d{4}$/.test(phone) && /^\+\d{11,15}$/.test(tel)) {
          const link = builderElement(owner, 'a', 'twins-builder__phone', 'Call Twins at ' + phone);
          link.href = 'tel:' + tel;
          body.appendChild(link);
        }
      }

      function move(direction) {
        const coreStages = availableStages().filter(function (stage) { return stage !== 'hardware'; });
        if (state.step === 'hardware') {
          const summary = coreStages.indexOf('summary');
          state.step = direction > 0 ? 'summary' : coreStages[Math.max(0, summary - 1)];
        } else {
          const current = Math.max(0, coreStages.indexOf(state.step));
          const next = Math.min(coreStages.length - 1, Math.max(0, current + direction));
          state.step = coreStages[next];
        }
        render(true);
      }

      function render(focusHeading) {
        const stages = availableStages();
        if (stages.indexOf(state.step) === -1) state.step = stages[stages.length - 2] || 'collection';
        builder.setAttribute('data-builder-current-step', state.step);
        stepControls.forEach(function (control) {
          const target = control.getAttribute('data-builder-step-target') || '';
          const isAvailable = stages.indexOf(target) !== -1;
          control.hidden = !isAvailable;
          control.disabled = !isAvailable;
          if (control.parentElement && control.parentElement.tagName === 'LI') {
            control.parentElement.hidden = !isAvailable;
          }
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
          layout.appendChild(view);
          layout.appendChild(reference);
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
          heading.focus();
        }
      }

      stepControls.forEach(function (control) {
        control.addEventListener('click', function () {
          const target = control.getAttribute('data-builder-step-target') || '';
          if (availableStages().indexOf(target) === -1) return;
          state.step = target;
          render(true);
        });
      });

      mount.addEventListener('click', function (event) {
        const control = event.target.closest ? event.target.closest('[data-builder-action]') : null;
        if (!control || !mount.contains(control)) return;
        const action = control.getAttribute('data-builder-action');
        if (action === 'select-product') {
          const productId = control.getAttribute('data-builder-product-id');
          state.product = localCatalog.products.find(function (product) { return product.id === productId; }) || null;
          state.design = null;
          state.color = null;
          state.window = null;
          state.glass = null;
          state.hardware = null;
          const stages = availableStages();
          state.step = stages[1] || 'summary';
          render(true);
          return;
        }
        if (action === 'select-option' && state.product) {
          const field = control.getAttribute('data-builder-field') || '';
          const productField = BUILDER_OPTION_FIELDS[field];
          if (!productField) return;
          const optionId = control.getAttribute('data-builder-option-id') || '';
          const option = state.product[productField].find(function (item) { return item.id === optionId; });
          if (!option) return;
          state[field === 'windows' ? 'window' : field] = option;
          if (field === 'windows') state.glass = null;
          render(false);
          const replacement = Array.from(mount.querySelectorAll('[data-builder-option-id]')).find(function (candidate) {
            return candidate.getAttribute('data-builder-field') === field
              && candidate.getAttribute('data-builder-option-id') === optionId;
          });
          if (replacement) {
            try {
              replacement.focus({ preventScroll: true });
            } catch (error) {
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
        }
        else if (action === 'copy-summary') {
          const status = mount.querySelector('[data-builder-copy-status]');
          if (!status) return;
          if (typeof navigator === 'undefined' || !navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
            status.textContent = 'Copy is unavailable in this browser. Keep this summary open while you call Twins.';
            return;
          }
          navigator.clipboard.writeText(summaryText()).then(function () {
            status.textContent = 'Configuration summary copied.';
          }).catch(function () {
            status.textContent = 'Copy was blocked. Keep this summary open while you call Twins.';
          });
        }
      });

      const deepLinkedProduct = builderDeepLinkProduct(localCatalog);
      if (deepLinkedProduct) {
        state.product = deepLinkedProduct;
        const stages = availableStages();
        state.step = stages[1] || 'summary';
      }
      render(false);
      const shell = builder.parentElement;
      const fallback = shell && shell.querySelector('[data-builder-fallback]');
      if (fallback) fallback.hidden = true;
      builder.querySelectorAll('[data-builder-contact-preview], [data-builder-status], .twins-builder__copy-label').forEach(function (serverOnly) {
        serverOnly.hidden = true;
      });
      builder.hidden = false;
      builder.setAttribute('data-builder-enhanced', 'true');
      enhanced += 1;
    });

    return enhanced;
  }

  function initPreservedForms(root) {
    const scope = root || document;
    const message = 'This private staging preview does not submit or store application information.';
    let guarded = 0;

    function guardForm(form, submitControls, formAlert) {
      if (!form || form.getAttribute('data-twins-staging-submit-guard') === 'true') return;

      function showGuard(event) {
        if (event && typeof event.preventDefault === 'function') event.preventDefault();
        if (formAlert) {
          formAlert.textContent = message;
          formAlert.classList.add('show');
        }
      }

      form.removeAttribute('action');
      form.removeAttribute('method');
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showGuard();
      }, true);
      form.setAttribute('data-twins-staging-submit-guard', 'true');

      submitControls.filter(Boolean).forEach(function (submitControl) {
        submitControl.setAttribute('type', 'button');
        submitControl.setAttribute('aria-disabled', 'true');
        if (submitControl.tagName === 'INPUT') {
          submitControl.value = 'Preview only — submission disabled';
        } else {
          submitControl.textContent = 'Preview only — submission disabled';
        }
        submitControl.addEventListener('click', showGuard);
      });
      guarded += 1;
    }

    const host = scope.querySelector('#twx-careers-host');
    const shadow = host && host.shadowRoot;
    const careersForm = shadow && shadow.querySelector('#application-form');
    if (careersForm) {
      guardForm(
        careersForm,
        [shadow.querySelector('#submit-button')],
        shadow.querySelector('#form-alert')
      );
    }

    scope.querySelectorAll('body.twins-overhaul-campaign form').forEach(function (form) {
      const controls = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"], input[type="image"], button:not([type])'));
      guardForm(form, controls, form.querySelector('[role="alert"], .form-alert, .wpcf7-response-output'));
    });

    return guarded;
  }

  function initReveal(root) {
    const scope = root || document;
    const targets = scope.querySelectorAll('[data-twins-reveal]');
    const page = document.body;
    if (!page || prefersReducedMotion() || typeof window.IntersectionObserver !== 'function') {
      targets.forEach(function (target) { target.classList.add('is-visible'); });
      return targets.length;
    }

    let observer;
    try {
      observer = new window.IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        });
      }, { threshold: .1, rootMargin: '0px 0px -8% 0px' });
      page.classList.add('twins-overhaul-motion-enabled');
      targets.forEach(function (target) { observer.observe(target); });
    } catch (error) {
      page.classList.remove('twins-overhaul-motion-enabled');
      targets.forEach(function (target) { target.classList.add('is-visible'); });
    }

    return targets.length;
  }

  function init(root) {
    const scope = root || document;
    initMenu(scope);
    initZip(scope);
    initBuilder(scope);
    initPreservedForms(scope);
    initReveal(scope);
  }

  window.TwinsOverhaulPreview = Object.freeze({
    initMenu: initMenu,
    initZip: initZip,
    initBuilder: initBuilder,
    initPreservedForms: initPreservedForms,
    initReveal: initReveal
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(document); }, { once: true });
  } else {
    init(document);
  }
}(window, document));
