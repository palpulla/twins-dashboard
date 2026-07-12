/**
 * Twins Door Builder (visualizer) - shortcode [twins_door_builder]
 * Owned door configurator with real Clopay assets (client-side fetch, CORS *).
 * Steps: collection > design > color > windows > glass > summary+quote form.
 * Posts leads to the existing main-site endpoint POST /wp-json/twins/v1/door-builder
 * (snippet 7072, honeypot field 'website'), region derived from home_url() path.
 * Colors ONLY via var(--tw-navy) / var(--tw-yellow) / #F2F5F7 / white.
 * Testing: append ?twxdbfail=1 to force list-fetch failure -> fallback form.
 * Spec: docs/superpowers/specs/2026-07-09-phase3-door-builder-visualizer-design.md
 * Reversible: deactivate snippet -> shortcode inert.
 * NOTE: deployed WPCode body must NOT include an opening <?php tag (editor adds it).
 */
add_shortcode( 'twins_door_builder', function () {
	$region = ( strpos( home_url(), '/wi' ) !== false ) ? 'wi' : 'main';
	ob_start(); ?>
<div id="twxdb" class="twxdb" data-region="<?php echo esc_attr( $region ); ?>" data-endpoint="https://twinsgaragedoors.com/wp-json/twins/v1/door-builder"></div>
<style id="twxdb-css">
.twxdb{max-width:1140px;margin:0 auto;color:var(--tw-navy)}
.twxdb,.twxdb *{box-sizing:border-box}
.twxdb img{max-width:100%}
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
.twxdb-hero{display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start;background:#F2F5F7;border-radius:12px;padding:14px;margin:0 0 20px}
.twxdb-hero-img{flex:1 1 260px;min-width:0}
.twxdb-hero-img img{display:block;width:100%;border-radius:8px;background:white}
.twxdb-picks{flex:1 1 200px;display:flex;flex-direction:column;gap:8px;font-size:14px}
.twxdb-pick{display:flex;align-items:center;gap:8px;background:white;border-radius:8px;padding:8px 12px;line-height:1.35}
.twxdb-pick b{font-family:Montserrat,sans-serif;font-size:11px;letter-spacing:.08em;text-transform:uppercase;opacity:.6;min-width:82px}
.twxdb-pick img{width:26px;height:26px;border-radius:50%;object-fit:cover;border:2px solid #F2F5F7}
.twxdb-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.twxdb-card{display:flex;flex-direction:column;gap:8px;text-align:left;background:white;border:2px solid #F2F5F7;border-radius:10px;padding:10px;cursor:pointer;min-height:44px}
.twxdb-card:hover{border-color:var(--tw-navy)}
.twxdb-card.is-sel{border-color:var(--tw-yellow)}
.twxdb-card img{display:block;width:100%;border-radius:6px;background:#F2F5F7;object-fit:cover;aspect-ratio:4/3}
.twxdb-card-t{font-family:Montserrat,sans-serif;font-weight:700;font-size:14.5px;color:var(--tw-navy);line-height:1.3}
.twxdb-card-d{font-size:12.5px;color:var(--tw-navy);opacity:.7;line-height:1.45;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.twxdb-g{font-family:Montserrat,sans-serif;font-weight:700;font-size:15px;color:var(--tw-navy);margin:20px 0 10px}
.twxdb-g:first-child{margin-top:0}
.twxdb-chips{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:10px}
.twxdb-chip{display:flex;flex-direction:column;align-items:center;gap:6px;background:white;border:2px solid #F2F5F7;border-radius:10px;padding:10px 6px;cursor:pointer;font-size:12px;color:var(--tw-navy);line-height:1.3;text-align:center;min-height:44px}
.twxdb-chip:hover{border-color:var(--tw-navy)}
.twxdb-chip.is-sel{border-color:var(--tw-yellow)}
.twxdb-chip img{width:46px;height:46px;border-radius:50%;object-fit:cover;border:2px solid #F2F5F7}
.twxdb-chip--wide{grid-column:span 2}
.twxdb-chip--wide img{width:100%;height:auto;border-radius:6px}
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
.twxdb-thanks p{font-size:16px;line-height:1.6;margin:0 auto;max-width:520px}
@media(min-width:700px){
.twxdb-h{font-size:30px}
.twxdb-grid{grid-template-columns:repeat(3,1fr)}
.twxdb-dots{width:auto;order:0}
.twxdb-hero-img{flex:1 1 380px}
}
@media(min-width:1000px){
.twxdb-grid{grid-template-columns:repeat(4,1fr)}
}
</style>
<script>
(function () {
	var root = document.getElementById('twxdb');
	if (!root) { return; }
	var REGION = root.getAttribute('data-region') || 'main';
	var ENDPOINT = root.getAttribute('data-endpoint');
	var PHONES = { main: { disp: '(833) 833-2010', tel: '+18338332010' }, wi: { disp: '(608) 888-8785', tel: '+16088888785' } };
	var PHONE = PHONES[REGION] || PHONES.main;
	var LIST_URL = 'https://www.clopaydoor.com/api/v2/GetProductsList/GetProducts?productType=Residential';
	var DETAIL_URL = 'https://www.clopaydoor.com/api/v2/GetProductDetails/GetProductData?productId=';
	var FORCE_FAIL = /[?&]twxdbfail=1/.test(location.search);
	var STEPS = ['collection', 'design', 'color', 'windows', 'glass', 'summary'];
	var TITLES = { collection: 'Choose your collection', design: 'Pick your design', color: 'Pick your color', windows: 'Add windows', glass: 'Choose your glass', summary: 'Review and get your free quote' };
	var BTN_LABEL = 'Get my free quote';

	var state = { step: 'collection', products: null, product: null, design: null, color: null, window: null, glass: null, skipped: {}, history: [] };

	/* --- sanitizers --- */
	function san(h) {
		return String(h == null ? '' : h)
			.replace(/<br\b[^>]*>/gi, '<br>')
			.replace(/<(\/?)sup\b[^>]*>/gi, '<$1sup>')
			.replace(/<(?!br>|\/?sup>)[^>]*>/gi, '');
	}
	function plain(h) {
		var t = document.createElement('textarea');
		t.innerHTML = String(h == null ? '' : h).replace(/<[^>]*>/g, ' ');
		return t.value.replace(/\s+/g, ' ').trim();
	}
	function esc(s) {
		return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	/* --- data --- */
	function cacheGet(k) { try { var v = sessionStorage.getItem(k); return v ? JSON.parse(v) : null; } catch (e) { return null; } }
	function cacheSet(k, v) { try { sessionStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
	function fetchJSON(url, timeoutMs) {
		timeoutMs = timeoutMs || 10000;
		var ctl = new AbortController();
		var t = setTimeout(function () { ctl.abort(); }, timeoutMs);
		return fetch(url, { signal: ctl.signal }).then(function (r) {
			clearTimeout(t);
			if (!r.ok) { throw new Error('http ' + r.status); }
			return r.json();
		}, function (e) { clearTimeout(t); throw e; });
	}
	function loadList() {
		if (FORCE_FAIL) { return Promise.reject(new Error('forced fail')); }
		var c = cacheGet('twxdb:list');
		if (c) { return Promise.resolve(c); }
		return fetchJSON(LIST_URL).then(function (d) {
			if (!d || !d.length) { throw new Error('empty list'); }
			cacheSet('twxdb:list', d);
			return d;
		});
	}
	function loadProduct(id) {
		var k = 'twxdb:p:' + id;
		var c = cacheGet(k);
		if (c) { return Promise.resolve(c); }
		return fetchJSON(DETAIL_URL + encodeURIComponent(id)).then(function (d) {
			if (!d || !d.ProductId) { throw new Error('bad product'); }
			cacheSet(k, d);
			return d;
		});
	}

	/* --- navigation --- */
	function go(step) { state.history.push(state.step); state.step = step; render(true); }
	function back() { if (state.history.length) { state.step = state.history.pop(); render(true); } }
	function stepAvailable(s) {
		var p = state.product;
		if (s === 'collection' || s === 'summary') { return true; }
		if (!p) { return false; }
		if (s === 'design') { return !!(p.ProductDesigns && p.ProductDesigns.length); }
		if (s === 'color') { return !!(p.Colors && p.Colors.length); }
		if (s === 'windows') { return !!(p.TopSections && p.TopSections.length); }
		if (s === 'glass') { return !!(state.window && !state.window.none && p.SpecialityGlassOptions && p.SpecialityGlassOptions.length); }
		return false;
	}
	function nextAfter(s) {
		var i = STEPS.indexOf(s);
		for (var j = i + 1; j < STEPS.length - 1; j++) {
			if (stepAvailable(STEPS[j])) { return STEPS[j]; }
			state.skipped[STEPS[j]] = true;
		}
		return 'summary';
	}

	/* --- shared view pieces --- */
	function dotsHTML() {
		var cur = STEPS.indexOf(state.step);
		return '<div class="twxdb-dots" aria-hidden="true">' + STEPS.map(function (s, i) {
			var cls = 'twxdb-dot';
			var chosen = { collection: state.product, design: state.design, color: state.color, windows: state.window, glass: state.glass, summary: null }[s];
			if (s === state.step) { cls += ' is-current'; }
			else if (state.product && !stepAvailable(s)) { cls += ' is-skip'; }
			else if (chosen || (i < cur && chosen)) { cls += ' is-done'; }
			return '<span class="' + cls + '" title="' + TITLES[s] + '"></span>';
		}).join('') + '</div>';
	}
	function barHTML() {
		var backBtn = (state.step !== 'collection') ? '<button type="button" class="twxdb-back" data-act="back">&larr; Back</button>' : '<span></span>';
		var skip = (state.step !== 'summary') ? '<button type="button" class="twxdb-skip" data-act="skip">Skip to quote &rarr;</button>' : '<span></span>';
		return '<div class="twxdb-bar">' + backBtn + dotsHTML() + skip + '</div>';
	}
	function picksHTML() {
		var rows = [];
		function row(label, valueHTML, img) {
			return '<div class="twxdb-pick">' + (img ? '<img src="' + esc(img) + '" alt="" loading="lazy">' : '') + '<b>' + label + '</b><span>' + valueHTML + '</span></div>';
		}
		if (state.product) { rows.push(row('Collection', san(state.product.Title), null)); }
		if (state.design) { rows.push(row('Design', san(state.design.Title), null)); }
		if (state.color) { rows.push(row('Color', san(state.color.Title), state.color.ProductImage)); }
		if (state.window) { rows.push(row('Windows', san(state.window.Title), null)); }
		if (state.glass) { rows.push(row('Glass', san(state.glass.Title), null)); }
		return rows.join('');
	}
	function heroHTML() {
		if (!state.product || state.step === 'collection') { return ''; }
		var img = state.design ? state.design.ProductImage : state.product.ShowcaseImage;
		var alt = plain(state.design ? state.design.Title : state.product.Title);
		return '<div class="twxdb-hero"><div class="twxdb-hero-img"><img src="' + esc(img) + '" alt="' + esc(alt) + '" loading="eager" data-no-lazy="1"></div><div class="twxdb-picks">' + picksHTML() + '</div></div>';
	}
	function formHTML() {
		return '<form class="twxdb-form" novalidate>'
			+ '<div class="twxdb-fld"><label for="twxdb-name">Name*</label><input class="twxdb-in" id="twxdb-name" name="name" type="text" required autocomplete="name"></div>'
			+ '<div class="twxdb-fld"><label for="twxdb-phone">Phone*</label><input class="twxdb-in" id="twxdb-phone" name="phone" type="tel" required autocomplete="tel"></div>'
			+ '<div class="twxdb-fld"><label for="twxdb-email">Email*</label><input class="twxdb-in" id="twxdb-email" name="email" type="email" required autocomplete="email"></div>'
			+ '<div class="twxdb-fld"><label for="twxdb-zip">Zip*</label><input class="twxdb-in" id="twxdb-zip" name="zip" type="text" inputmode="numeric" required autocomplete="postal-code"></div>'
			+ '<div class="twxdb-fld"><label for="twxdb-notes">Anything else? (optional)</label><textarea class="twxdb-in" id="twxdb-notes" name="notes"></textarea></div>'
			+ '<div class="twxdb-hp" aria-hidden="true"><label for="twxdb-website">Website</label><input id="twxdb-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>'
			+ '<button type="submit" class="twxdb-btn">' + BTN_LABEL + '</button>'
			+ '<div class="twxdb-err" hidden></div>'
			+ '</form>';
	}
	function groupBy(list, key) {
		var out = [], idx = {};
		(list || []).forEach(function (it, i) {
			var g = it[key] || '';
			if (!(g in idx)) { idx[g] = out.length; out.push({ name: g, items: [] }); }
			out[idx[g]].items.push({ it: it, i: i });
		});
		return out;
	}

	/* --- step views --- */
	function viewCollection() {
		var cards = state.products.map(function (c, i) {
			var sel = state.product && String(state.product.ProductId) === String(c.ProductId);
			return '<button type="button" class="twxdb-card' + (sel ? ' is-sel' : '') + '" data-act="product" data-i="' + i + '">'
				+ '<img src="' + esc(c.ShowcaseImage) + '" alt="' + esc(plain(c.Title)) + '" loading="lazy">'
				+ '<span class="twxdb-card-t">' + san(c.Title) + '</span>'
				+ '<span class="twxdb-card-d">' + esc(plain(c.ShortDescription)) + '</span>'
				+ '</button>';
		}).join('');
		return '<h2 class="twxdb-h">' + TITLES.collection + '</h2>'
			+ '<p class="twxdb-sub">' + state.products.length + ' Clopay collections. Pick one and we\'ll build it up together.</p>'
			+ '<div class="twxdb-grid">' + cards + '</div>';
	}
	function viewDesign() {
		var cards = state.product.ProductDesigns.map(function (d, i) {
			var sel = state.design === state.product.ProductDesigns[i];
			return '<button type="button" class="twxdb-card' + (sel ? ' is-sel' : '') + '" data-act="design" data-i="' + i + '">'
				+ '<img src="' + esc(d.ProductImage) + '" alt="' + esc(plain(d.Title)) + '" loading="lazy">'
				+ '<span class="twxdb-card-t">' + san(d.Title) + '</span>'
				+ '</button>';
		}).join('');
		return '<h2 class="twxdb-h">' + TITLES.design + '</h2><div class="twxdb-grid">' + cards + '</div>';
	}
	function viewColor() {
		var groups = groupBy(state.product.Colors, 'GroupName').map(function (g) {
			var chips = g.items.map(function (o) {
				var sel = state.color === o.it;
				return '<button type="button" class="twxdb-chip' + (sel ? ' is-sel' : '') + '" data-act="color" data-i="' + o.i + '">'
					+ '<img src="' + esc(o.it.ProductImage) + '" alt="' + esc(plain(o.it.AlternativeText || o.it.Title)) + '" loading="lazy">'
					+ '<span>' + san(o.it.Title) + '</span></button>';
			}).join('');
			return (g.name ? '<h3 class="twxdb-g">' + esc(g.name) + '</h3>' : '') + '<div class="twxdb-chips">' + chips + '</div>';
		}).join('');
		var disc = plain(state.product.ColorDisclaimer) ? '<div class="twxdb-disc">' + san(state.product.ColorDisclaimer) + '</div>' : '';
		return '<h2 class="twxdb-h">' + TITLES.color + '</h2>' + groups
			+ '<p class="twxdb-cap">Colors shown as manufacturer swatches — you\'ll see your exact door at your free on-site consult.</p>' + disc;
	}
	function viewWindows() {
		var noneSel = state.window && state.window.none;
		var noneCard = '<div class="twxdb-chips"><button type="button" class="twxdb-chip' + (noneSel ? ' is-sel' : '') + '" data-act="window" data-i="-1">'
			+ '<span class="twxdb-noneimg"></span><span>No windows (solid)</span></button></div>';
		var groups = groupBy(state.product.TopSections, 'GroupName').map(function (g) {
			var chips = g.items.map(function (o) {
				var sel = state.window === o.it;
				return '<button type="button" class="twxdb-chip twxdb-chip--wide' + (sel ? ' is-sel' : '') + '" data-act="window" data-i="' + o.i + '">'
					+ '<img src="' + esc(o.it.ThumbnailImage) + '" alt="' + esc(plain(o.it.AlternativeText || o.it.Title)) + '" loading="lazy">'
					+ '<span>' + san(o.it.Title) + '</span></button>';
			}).join('');
			return (g.name ? '<h3 class="twxdb-g">' + esc(g.name) + '</h3>' : '') + '<div class="twxdb-chips">' + chips + '</div>';
		}).join('');
		var disc = plain(state.product.TopSectionDisclaimer) ? '<div class="twxdb-disc">' + san(state.product.TopSectionDisclaimer) + '</div>' : '';
		return '<h2 class="twxdb-h">' + TITLES.windows + '</h2>' + noneCard + groups + disc;
	}
	function viewGlass() {
		var cards = state.product.SpecialityGlassOptions.map(function (g, i) {
			var sel = state.glass === state.product.SpecialityGlassOptions[i];
			return '<button type="button" class="twxdb-card' + (sel ? ' is-sel' : '') + '" data-act="glass" data-i="' + i + '">'
				+ '<img src="' + esc(g.Image) + '" alt="' + esc(plain(g.AlternativeText || g.Title)) + '" loading="lazy">'
				+ '<span class="twxdb-card-t">' + san(g.Title) + '</span>'
				+ '</button>';
		}).join('');
		return '<h2 class="twxdb-h">' + TITLES.glass + '</h2><div class="twxdb-grid">' + cards + '</div>';
	}
	function viewSummary() {
		var picks = picksHTML();
		var intro = picks
			? '<p class="twxdb-sub">Here\'s your door. Leave your details and we\'ll bring the quote (and the real thing) to you.</p>'
			: '<p class="twxdb-none">No door picked yet — no problem. Leave your details, tell us what you have in mind, and we\'ll design it together.</p>';
		return '<h2 class="twxdb-h">' + TITLES.summary + '</h2>' + intro + formHTML();
	}
	function viewFallback() {
		return '<div class="twxdb-app"><h2 class="twxdb-h">Get your free door quote</h2>'
			+ '<p class="twxdb-sub">Our door catalog is taking a break — leave your details and we\'ll design it together.</p>'
			+ formHTML() + '</div>';
	}
	function viewThanks() {
		return '<div class="twxdb-app twxdb-thanks"><h2 class="twxdb-h" style="display:inline-block">Thank you!</h2>'
			+ '<p>Thanks — your design is on its way to our team. We\'ll call you shortly.</p></div>';
	}

	/* --- render --- */
	var booted = false;
	function render(scroll) {
		var html;
		if (state.step === 'fallback') { html = viewFallback(); }
		else if (state.step === 'thanks') { html = viewThanks(); }
		else {
			var views = { collection: viewCollection, design: viewDesign, color: viewColor, windows: viewWindows, glass: viewGlass, summary: viewSummary };
			html = '<div class="twxdb-app">' + barHTML() + heroHTML() + views[state.step]() + '</div>';
		}
		root.innerHTML = html;
		if (scroll && booted) { root.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
		booted = true;
	}
	function renderLoading(msg) { root.innerHTML = '<div class="twxdb-load">' + esc(msg) + '</div>'; }

	/* --- actions --- */
	function pickProduct(i) {
		var item = state.products[i];
		state.design = null; state.color = null; state.window = null; state.glass = null; state.skipped = {};
		renderLoading('Loading ' + plain(item.Title) + '…');
		loadProduct(item.ProductId).then(function (p) {
			state.product = p;
			go(nextAfter('collection'));
		}).catch(function () {
			state.step = 'fallback';
			render();
		});
	}
	root.addEventListener('click', function (ev) {
		var el = ev.target.closest ? ev.target.closest('[data-act]') : null;
		if (!el || !root.contains(el)) { return; }
		var act = el.getAttribute('data-act');
		var i = parseInt(el.getAttribute('data-i') || '-1', 10);
		if (act === 'product') { pickProduct(i); }
		else if (act === 'design') { state.design = state.product.ProductDesigns[i]; go(nextAfter('design')); }
		else if (act === 'color') { state.color = state.product.Colors[i]; go(nextAfter('color')); }
		else if (act === 'window') {
			if (i < 0) { state.window = { Title: 'No windows (solid)', none: true }; state.glass = null; }
			else { state.window = state.product.TopSections[i]; }
			go(nextAfter('windows'));
		}
		else if (act === 'glass') { state.glass = state.product.SpecialityGlassOptions[i]; go(nextAfter('glass')); }
		else if (act === 'back') { back(); }
		else if (act === 'skip') { go('summary'); }
	});
	root.addEventListener('submit', function (ev) {
		var f = ev.target;
		if (!f.classList || !f.classList.contains('twxdb-form')) { return; }
		ev.preventDefault();
		function g(n) { var el = f.querySelector('[name="' + n + '"]'); return el ? el.value.trim() : ''; }
		var err = f.querySelector('.twxdb-err');
		var name = g('name'), phone = g('phone'), email = g('email'), zip = g('zip'), notes = g('notes'), hp = g('website');
		if (!name || !phone || !email || !zip) {
			err.hidden = false;
			err.textContent = 'Please fill in your name, phone, email and zip.';
			return;
		}
		var payload = { name: name, phone: phone, email: email, zip: zip, region: REGION, website: hp };
		if (notes) { payload.notes = notes; }
		if (state.product) { payload.collection = plain(state.product.Title); }
		if (state.design) { payload.design = plain(state.design.Title); }
		if (state.color) { payload.color = plain(state.color.Title); }
		if (state.window) { payload.windows = plain(state.window.Title); }
		if (state.glass) { payload.glass = plain(state.glass.Title); }
		var btn = f.querySelector('button[type="submit"]');
		btn.disabled = true;
		btn.textContent = 'Sending…';
		err.hidden = true;
		fetch(ENDPOINT, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }).then(function (r) {
			if (!r.ok) { throw new Error('http ' + r.status); }
			state.step = 'thanks';
			render(true);
		}).catch(function () {
			btn.disabled = false;
			btn.textContent = BTN_LABEL;
			err.hidden = false;
			err.innerHTML = 'Something went wrong sending your design. Call us at <a href="tel:' + PHONE.tel + '">' + PHONE.disp + '</a> and we\'ll take it from there.';
		});
	});

	/* --- boot --- */
	renderLoading('Loading door collections…');
	loadList().then(function (list) {
		state.products = list;
		render();
	}).catch(function () {
		state.step = 'fallback';
		render();
	});
})();
</script>
	<?php return ob_get_clean();
} );
