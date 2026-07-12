// twx2-page-builder.js — Phase 4 v2 collection-page builder (twx v2 kit).
// Runs inside the Elementor editor console (javascript_tool), logged-in admin.
// Source of truth for every Phase 4 page injected on 2026-07-09+.
// Markup lifted from local-harness-v2.html (Task 1, approved Option A mockup);
// copy comes VERBATIM from content-pack.json — this file writes no copy.
//
// Task 5 usage: replace TWX2_ENTRY below with the page's content-pack.json
// entry (verbatim JSON), open the page in the Elementor editor, paste this
// whole file, then run:  window.twx2BuildPage(TWX2_ENTRY, TWX2_ENTRY.product_id)
// Poll window.__twxSaved until 'ok'. Everything else (draft creation, Astra
// meta, Rank Math meta, publish) is REST, outside this file.
//
// KNOWN RISK (hit once on the Coachman proof page): the JSON-LD script widget
// (2nd widget of the FAQ section) was silently dropped across an editor
// reload+save cycle — likely a save fired before the document finished
// loading. ALWAYS wait for elementor.documents.getCurrent().container.children
// to be non-empty before running anything, and after ANY later edit session
// re-check the page for FAQPage/Product JSON-LD (qa-gate.py asserts this).

// ====== ENTRY — Task 5 swaps this constant per page (verbatim pack entry) ======
var TWX2_ENTRY = {
  "slug": "clopay-coachman",
  "product_id": 11
  /* full entry pasted at build time; this stub documents the shape */
};
// ===============================================================================

(function () {
  var TEL = 'tel:+18338332010';
  var PHONE = 'Call (833) 833-2010';
  // Hero "Design This Door" deep-links into the owned door-builder app
  // (snippet 7127) pre-selecting this page's collection: ?product={ProductId}.
  var DESIGN_URL_BASE = 'https://twinsgaragedoors.com/door-builder/';
  function designUrl(e) { return DESIGN_URL_BASE + '?product=' + e.product_id; }
  var BOOK_URL = 'https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true';
  var IMG_L = 'https://twinsgaragedoors.com/wp-content/uploads/2026/03/ICONLeft-1.png';
  var IMG_R = 'https://twinsgaragedoors.com/wp-content/uploads/2026/03/ICONright.png';

  // Display names for sibling cards (pack h1 minus " Garage Doors"; 3 live pages + hub added)
  var NAMES = {
    'clopay-coachman': 'Clopay Coachman',
    'clopay-canyon-ridge-carriage-house-4-layer': 'Clopay Canyon Ridge Carriage House (4-Layer)',
    'clopay-canyon-ridge-carriage-house-5-layer': 'Clopay Canyon Ridge Carriage House (5-Layer)',
    'clopay-canyon-ridge-elements': 'Clopay Canyon Ridge Elements',
    'clopay-canyon-ridge-chevron': 'Clopay Canyon Ridge Chevron',
    'clopay-canyon-ridge-louver': 'Clopay Canyon Ridge Louver',
    'clopay-canyon-ridge-modern': 'Clopay Canyon Ridge Modern',
    'clopay-modern-steel-ultra-grain-plank': 'Clopay Modern Steel Ultra-Grain Plank',
    'clopay-avante': 'Clopay Avante',
    'clopay-avante-sleek': 'Clopay Avante Sleek',
    'clopay-vertistack-avante': 'Clopay VertiStack Avante',
    'clopay-bridgeport-steel': 'Clopay Bridgeport Steel',
    'clopay-bridgeport-inlay': 'Clopay Bridgeport Inlay',
    'clopay-grand-harbor': 'Clopay Grand Harbor',
    'clopay-classic-wood': 'Clopay Classic Wood',
    'clopay-reserve-wood-custom': 'Clopay Reserve Wood Custom',
    'clopay-reserve-wood-semi-custom': 'Clopay Reserve Wood Semi-Custom',
    'clopay-reserve-wood-limited-edition': 'Clopay Reserve Wood Limited Edition',
    'clopay-reserve-wood-extira': 'Clopay Reserve Wood Extira',
    'clopay-reserve-wood-modern': 'Clopay Reserve Wood Modern',
    'clopay-modern-steel': 'Clopay Modern Steel',
    'clopay-gallery-steel': 'Clopay Gallery Steel',
    'clopay-classic-collection': 'Clopay Classic Collection',
    'clopay-garage-doors': 'All Clopay Collections'
  };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  // Split intro_copy into two paragraphs at the sentence end nearest the middle
  // (text unchanged, presentation only).
  function paras(copy) {
    var t = String(copy || '').trim();
    var mid = t.length / 2, best = -1, bestD = Infinity;
    var re = /[.!?]["')\]]?\s+/g, m;
    while ((m = re.exec(t)) !== null) {
      var cut = m.index + m[0].length;
      var d = Math.abs(cut - mid);
      if (d < bestD) { bestD = d; best = cut; }
    }
    if (best < 0) return '<p>' + esc(t) + '</p>';
    return '<p>' + esc(t.slice(0, best).trim()) + '</p><p>' + esc(t.slice(best).trim()) + '</p>';
  }

  // ---- Elementor section factories (mechanics from the proven twx-page-builder.js) ----
  function S(html) {
    return {
      elType: 'section',
      settings: { stretch_section: 'section-stretched', layout: 'full_width', gap: 'no' },
      elements: [{
        elType: 'column',
        settings: { _column_size: 100, _inline_size: null },
        elements: [{ elType: 'widget', widgetType: 'html', settings: { html: html } }]
      }]
    };
  }
  function S2(html, jsonldHtml) { // FAQ section: visible FAQ widget + ONE script widget (JSON-LD)
    return {
      elType: 'section',
      settings: { stretch_section: 'section-stretched', layout: 'full_width', gap: 'no' },
      elements: [{
        elType: 'column',
        settings: { _column_size: 100, _inline_size: null },
        elements: [
          { elType: 'widget', widgetType: 'html', settings: { html: html } },
          { elType: 'widget', widgetType: 'html', settings: { html: jsonldHtml } }
        ]
      }]
    };
  }
  function SHORT(shortcode) {
    return {
      elType: 'section',
      settings: { stretch_section: 'section-stretched', layout: 'full_width', gap: 'no',
        padding: { unit: 'px', top: '64', bottom: '64', left: '0', right: '0', isLinked: false } },
      elements: [{
        elType: 'column',
        settings: { _column_size: 100, _inline_size: null },
        elements: [{ elType: 'widget', widgetType: 'shortcode', settings: { shortcode: shortcode } }]
      }]
    };
  }

  // ---- template sections (markup verbatim from local-harness-v2.html) ----
  function heroSec(e) {
    return S(
      '<div class="twx2-hero">' +
      '<div class="twx2-stamp">Free on-site quote</div>' +
      '<div class="twx2-eyebrow">[ Official Clopay Dealer ]</div>' +
      '<h1>' + esc(e.h1) + '</h1>' +
      '<p class="twx2-sub">' + esc(e.hero_subhead) + '</p>' +
      '<div class="twx2-cta"><a class="twx2-btn twx2-btn--gold" href="' + TEL + '">' + PHONE + '</a>' +
      '<a class="twx2-btn twx2-btn--ghost" href="' + designUrl(e) + '">Design This Door</a></div>' +
      '<div class="twx2-trustline"><b>&#9733;&#9733;&#9733;&#9733;&#9733;</b>&nbsp; 5.0 on Google &#183; Licensed and insured &#183; Local crew</div>' +
      '<div class="twx2-pair"><img class="twx2-back" src="' + IMG_L + '" alt="Twins Garage Doors mascot" data-no-lazy="1"><img class="twx2-front" src="' + IMG_R + '" alt="Twins Garage Doors mascot" data-no-lazy="1"></div>' +
      '</div>');
  }

  function ribbonSec() {
    return S(
      '<div class="twx2-ribbon">' +
      '<div class="twx2-rib-item"><span class="twx2-rib-ico"><svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M9 2h6"/></svg></span><span><b>Same-day appointments</b><small>Call before noon, seen today</small></span></div>' +
      '<div class="twx2-rib-item"><span class="twx2-rib-ico"><svg viewBox="0 0 24 24"><path d="M4 7h13l3 5-3 5H4z"/><path d="M9 10v4M13 10v4"/></svg></span><span><b>Upfront pricing</b><small>Quote before work starts</small></span></div>' +
      '<div class="twx2-rib-item"><span class="twx2-rib-ico"><svg viewBox="0 0 24 24"><path d="M3 12l5 5L21 4"/></svg></span><span><b>Done in one visit</b><small>Trucks stocked for most jobs</small></span></div>' +
      '</div>');
  }

  function introSec(e, shortName) {
    var cards = (e.checklist_cards || []).map(function (c) {
      return '<div class="twx2-card"><span class="twx2-chip">&#10003;</span><span><b>' + esc(c.title) + '</b><p>' + esc(c.body) + '</p></span></div>';
    }).join('');
    return S(
      '<div class="twx2-body">' +
      '<div><h3 class="twx2-h3">Why homes pick the ' + esc(shortName) + '</h3>' + paras(e.intro_copy) + '</div>' +
      '<div><div class="twx2-cards">' + cards + '</div></div>' +
      '</div>');
  }

  function stepsSec() {
    return S(
      '<div class="twx2-steps">' +
      '<div class="twx2-eyebrow twx2-eyebrow--section">[ What to expect ]</div>' +
      '<h3>From call to new door, no surprises</h3>' +
      '<div class="twx2-row3">' +
      '<div class="twx2-step"><span class="twx2-num">01</span><b>Book in 60 seconds</b><p>Call or book online. Same-day appointments when you call before noon.</p></div>' +
      '<div class="twx2-step"><span class="twx2-num">02</span><b>Your tech calls ahead</b><p>Upfront price before any work starts. You approve it, we start.</p></div>' +
      '<div class="twx2-step"><span class="twx2-num">03</span><b>Done in one visit</b><p>Trucks stocked for most jobs. Invoice matches the quote, every time.</p></div>' +
      '</div></div>');
  }

  function siblingsSec(e) {
    var cards = (e.siblings || []).map(function (slug) {
      var name = NAMES[slug] || slug;
      return '<a class="twx2-gcard" href="https://twinsgaragedoors.com/' + slug + '/">' +
        '<span class="twx2-gcard-body"><b>' + esc(name) + '</b>' +
        '<span class="twx2-gcard-cta">See designs &amp; colors &#8594;</span></span></a>';
    }).join('');
    return S(
      '<div class="twx2-steps">' +
      '<div class="twx2-eyebrow twx2-eyebrow--section">[ Explore more Clopay collections ]</div>' +
      '<h3>Doors people compare with this one</h3>' +
      '<div class="twx2-grid">' + cards + '</div>' +
      '</div>');
  }

  function faqSec(e, productName) {
    var faqs = e.faq || [];
    var items = faqs.map(function (f) {
      return '<details><summary>' + esc(f.q) + '</summary><p>' + esc(f.a) + '</p></details>';
    }).join('');
    var faqHtml =
      '<style>.twx2-faqwrap{background:var(--tw-soft);padding:38px 48px;font-family:Montserrat,\'Avenir Next\',\'Helvetica Neue\',Arial,sans-serif}.twx2-faqwrap .twx2-fhead{text-align:center;margin-bottom:20px}.twx2-faqwrap h3{color:var(--tw-navy);font-size:22px;font-weight:800;margin:6px 0 0}.twx2-faq{max-width:860px;margin:0 auto}.twx2-faq details{background:#fff;border:3px solid var(--tw-navy);border-radius:12px;margin-bottom:12px;padding:0 22px;box-shadow:4px 4px 0 rgba(1,13,56,.85)}.twx2-faq summary{cursor:pointer;font-weight:700;font-size:15.5px;color:var(--tw-navy);padding:16px 0;list-style:none;position:relative;padding-right:30px}.twx2-faq summary::-webkit-details-marker{display:none}.twx2-faq summary:after{content:"+";position:absolute;right:2px;top:50%;transform:translateY(-50%);font-size:22px;color:var(--tw-yellow);font-weight:800}.twx2-faq details[open] summary:after{content:"\\2212"}.twx2-faq details p{margin:0 0 16px;color:#42556e;font-size:14.5px;line-height:1.6}</style>' +
      '<div class="twx2-faqwrap"><div class="twx2-fhead">' +
      '<div class="twx2-eyebrow twx2-eyebrow--section">[ Ask the Twins ]</div>' +
      '<h3>Frequently Asked Questions</h3></div>' +
      '<div class="twx2-faq">' + items + '</div></div>';

    var faqLd = {
      '@context': 'https://schema.org', '@type': 'FAQPage',
      mainEntity: faqs.map(function (f) {
        return { '@type': 'Question', name: f.q,
          acceptedAnswer: { '@type': 'Answer', text: f.a } };
      })
    };
    var prodLd = {
      '@context': 'https://schema.org', '@type': 'Product',
      name: productName,
      brand: { '@type': 'Brand', name: 'Clopay' },
      description: e.meta_description
    };
    var jsonldHtml =
      '<script type="application/ld+json">' + JSON.stringify(faqLd) + '<\/script>' +
      '<script type="application/ld+json">' + JSON.stringify(prodLd) + '<\/script>';
    return S2(faqHtml, jsonldHtml);
  }

  function closerSec() {
    // Specificity guard: Elementor's `.elementor img{height:auto}` (0,1,1) beats
    // the kit's `.twx2-pair--band` (0,1,0), blowing the flanking twins up to
    // intrinsic size. Scoped !important restores the kit's calibrated sizes.
    // Second guard: the theme's global footer CTA (inner section -100px
    // margin-top) overlaps the last section's bottom 100px on every page;
    // margin-bottom keeps the closer's buttons above/clear of it.
    return S(
      '<style>.twx2-closer img.twx2-pair--band{height:104px!important;width:auto!important}.twx2-closer{margin-bottom:110px}@media(max-width:700px){.twx2-closer img.twx2-pair--band{height:56px!important}}</style>' +
      '<div class="twx2-closer"><img class="twx2-pair--band twx2-l" src="' + IMG_L + '" alt="" data-no-lazy="1">' +
      '<span>Ready to see it on your home?</span>' +
      '<a class="twx2-btn twx2-btn--navy" href="' + BOOK_URL + '" target="_blank" rel="noopener">Book Online</a>' +
      '<a class="twx2-btn twx2-btn--gold" href="' + TEL + '">' + PHONE + '</a>' +
      '<img class="twx2-pair--band twx2-r" src="' + IMG_R + '" alt="" data-no-lazy="1"></div>');
  }

  // ---- template assembly ----
  window.__twx2Tpl = function (e, productId) {
    var isHub = (productId == null);
    if (isHub) {
      // Hub per Task 4 step 4 / spec §3: hero (no product) → grid → steps → closer
      return [heroSec(e), SHORT('[clopay_collection_grid]'), stepsSec(), closerSec()];
    }
    // Product name = pack h1 minus trailing " Garage Doors"; short name drops "Clopay "
    var productName = String(e.h1).replace(/\s+Garage Doors\s*$/i, '');
    var shortName = productName.replace(/^Clopay\s+/i, '');
    return [
      heroSec(e),
      ribbonSec(),
      introSec(e, shortName),
      SHORT('[clopay_product id="' + productId + '" mode="specs"]'),
      stepsSec(),
      siblingsSec(e),
      faqSec(e, productName),
      closerSec()
    ];
  };

  // ---- injection (proven $e.run mechanics, unchanged) ----
  window.twx2BuildPage = function (entry, productId) {
    var models = window.__twx2Tpl(entry, productId);
    $e.run('document/elements/empty', { force: true });
    var root = elementor.documents.getCurrent().container;
    models.forEach(function (m, i) {
      $e.run('document/elements/create', { container: root, model: m, options: { at: i } });
    });
    window.__twxSaved = 'pending';
    $e.run('document/save/update')
      .then(function () { window.__twxSaved = 'ok'; })
      .catch(function (err) { window.__twxSaved = 'ERR:' + err.message; });
    return 'sections ' + elementor.documents.getCurrent().container.children.length;
  };
})();
