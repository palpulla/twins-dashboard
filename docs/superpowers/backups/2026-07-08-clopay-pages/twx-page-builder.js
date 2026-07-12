// Twins collection-page builder — runs inside the Elementor editor console.
// Replaces the document's sections with the redesigned .twx- template.
// Record copy: this file is the source of truth for what was injected on
// 2026-07-08 (see spec 2026-07-08-twins-web-redesign-clopay-ezdoor-design.md).

window.__twxTpl = function (cfg) {
  const S = (html) => ({
    elType: 'section',
    settings: { stretch_section: 'section-stretched', layout: 'full_width', gap: 'no' },
    elements: [{
      elType: 'column',
      settings: { _column_size: 100, _inline_size: null },
      elements: [{ elType: 'widget', widgetType: 'html', settings: { html } }]
    }]
  });
  const SHORT = {
    elType: 'section',
    settings: { stretch_section: 'section-stretched', layout: 'full_width', gap: 'no',
      padding: { unit: 'px', top: '64', bottom: '64', left: '0', right: '0', isLinked: false } },
    elements: [{
      elType: 'column',
      settings: { _column_size: 100, _inline_size: null },
      elements: [{ elType: 'widget', widgetType: 'shortcode',
        settings: { shortcode: '[clopay_product id="' + cfg.clopayId + '" mode="specs"]' } }]
    }]
  };

  const hero = S(
    '<div class="twx-hero" style="background-image:url(\'' + cfg.heroImg + '\')">' +
    '<div class="twx-hero-scrim"></div><div class="twx-hero-inner">' +
    '<span class="twx-eyebrow" style="color:var(--tw-yellow)">Official Clopay Dealer</span>' +
    '<h1>' + cfg.h1 + '</h1><p>' + cfg.sub + '</p>' +
    '<div class="twx-ctas"><a class="twx-btn" href="' + cfg.quoteUrl + '">Get a Free Quote</a>' +
    '<a class="twx-btn twx-btn--ghost" href="tel:' + cfg.tel + '">Call ' + cfg.phone + '</a></div>' +
    '</div></div>');

  const band = S(
    '<div class="twx-band"><div class="twx-band-inner">' +
    '<h2>See this exact door on YOUR home</h2>' +
    '<a class="twx-btn twx-btn--navy" href="' + cfg.designUrl + '">Design Your Door &rarr;</a>' +
    '</div></div>');

  const why = S(
    '<div class="twx-section twx-section--soft"><div class="twx-wrap">' +
    '<div style="text-align:center;margin-bottom:28px"><span class="twx-eyebrow">Why Twins Garage Doors</span>' +
    '<h2 class="twx-h2">Installed by the local pros</h2></div>' +
    '<div class="twx-cards">' +
    '<div class="twx-card"><h3>Official Clopay Dealer</h3><p>We install genuine Clopay doors with factory-backed warranties, right here in ' + cfg.region + '.</p></div>' +
    '<div class="twx-card"><h3>Install, Service &amp; Repair</h3><p>Our own technicians install your door and stay available afterward for maintenance, springs, openers, and repairs.</p></div>' +
    '<div class="twx-card"><h3>T\'Winning Every Time</h3><p>Local and family-run. We show up when we say we will and stand behind every install.</p></div>' +
    '</div></div></div>');

  const about = S(
    '<div class="twx-section"><div class="twx-wrap--narrow">' +
    '<span class="twx-eyebrow">About this door</span>' +
    '<h2 class="twx-h2 twx-underline">' + cfg.introHeading + '</h2>' +
    '<p class="twx-lead">' + cfg.intro + '</p>' +
    '<ul class="twx-check">' + cfg.benefits.map(b => '<li>' + b + '</li>').join('') + '</ul>' +
    '<p style="color:var(--tw-muted);font-size:14.5px;line-height:1.6;margin-top:14px">' + cfg.considerations + '</p>' +
    '<p style="color:var(--tw-navy);font-family:Montserrat,sans-serif;font-weight:700;margin-top:10px">' + cfg.verdict + '</p>' +
    '</div></div>');

  const faq = S(
    '<style>.twx-faq{max-width:860px;margin:0 auto}.twx-faq details{background:#fff;border:1px solid #E4E5E7;border-radius:10px;margin-bottom:10px;padding:0 22px}.twx-faq summary{cursor:pointer;font-family:Montserrat,sans-serif;font-weight:700;font-size:15.5px;color:var(--tw-navy);padding:16px 0;list-style:none;position:relative;padding-right:30px}.twx-faq summary::-webkit-details-marker{display:none}.twx-faq summary:after{content:"+";position:absolute;right:2px;top:50%;transform:translateY(-50%);font-size:22px;color:var(--tw-yellow);font-weight:800}.twx-faq details[open] summary:after{content:"\\2212"}.twx-faq details p{margin:0 0 16px;color:var(--tw-text);font-size:14.5px;line-height:1.6}</style>' +
    '<div class="twx-section twx-section--soft"><div style="text-align:center;margin-bottom:24px">' +
    '<span class="twx-eyebrow">Ask the Twins</span><h2 class="twx-h2">Frequently Asked Questions</h2></div>' +
    '<div class="twx-faq">' +
    cfg.faq.map(f => '<details><summary>' + f.q + '</summary><p>' + f.a + '</p></details>').join('') +
    '</div></div>');

  const finalBand = S(
    '<div class="twx-band twx-band--navy"><div class="twx-band-inner">' +
    '<h2>Ready for your new door?</h2>' +
    '<a class="twx-btn" href="' + cfg.quoteUrl + '">Get a Free Quote</a>' +
    '<a class="twx-phone" href="tel:' + cfg.tel + '">' + cfg.phone + '</a>' +
    '</div></div>');

  return [hero, band, SHORT, why, about, faq, finalBand];
};

window.__twxBuild = function (cfg) {
  const models = window.__twxTpl(cfg);
  $e.run('document/elements/empty', { force: true });
  const root = elementor.documents.getCurrent().container;
  models.forEach((m, i) => $e.run('document/elements/create', { container: root, model: m, options: { at: i } }));
  window.__twxSaved = 'pending';
  $e.run('document/save/update').then(() => { window.__twxSaved = 'ok'; }).catch(e => { window.__twxSaved = 'ERR:' + e.message; });
  return JSON.stringify({ sections: elementor.documents.getCurrent().container.children.length });
};

// ---- Region configs ----
// main:  phone (833) 833-2010 / tel +18338332010 / quote https://twinsgaragedoors.com/contact-us/
//        design https://twinsgaragedoors.com/design-your-door/  region "Wisconsin"
// ky:    phone (859) 440-2227 / tel +18594402227 / quote https://twinsgaragedoors.com/ky/contact-us/
//        design https://twinsgaragedoors.com/ky/design-your-door/  region "Kentucky"

// ---- Door copy (condensed from the pre-redesign pages; substance preserved) ----
// MODERN STEEL (clopayId 170): h1 "Clopay Modern Steel™ Garage Doors"
// GALLERY (clopayId 12): h1 "Clopay Gallery® Steel Garage Doors"
// CLASSIC (clopayId 13): h1 "Clopay Classic™ Collection Garage Doors"
// Full config objects are embedded in the injection calls; see change-log entry.
