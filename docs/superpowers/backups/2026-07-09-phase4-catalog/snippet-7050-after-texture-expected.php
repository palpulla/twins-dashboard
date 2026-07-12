if ( ! defined( 'TWINS_CLOPAY_PROPID' ) ) { define( 'TWINS_CLOPAY_PROPID', '100841' ); }
/**
 * Twins x Clopay Product Page API v2 + Twins Web UI tokens (.twx-*).
 * Shortcode: [clopay_product id="170" mode="specs"]
 *   mode="specs" -> branded two-column section: gallery + colors/docs + Clopay CTA
 *   mode="full"  -> adds Clopay title/overview/construction above the specs block
 * The wp_head style block below is ALSO the shared Twins UI stylesheet used by
 * the redesigned collection + /design-your-door pages (design tokens: navy
 * #022751 / deep #010D38, yellow #FBBD04, Montserrat). Redesign spec:
 * docs/superpowers/specs/2026-07-08-twins-web-redesign-clopay-ezdoor-design.md
 * Reversible: deactivate snippet -> shortcode inert, twx styles gone.
 */

// --- Fetch + cache (24h transient, durable last-good fallback) ---
function twins_clopay_get_product( $product_id ) {
	$product_id = (int) $product_id;
	$key = 'twins_clopay_prod_' . $product_id;
	$cached = get_transient( $key );
	if ( $cached !== false ) {
		return $cached;
	}
	$url  = 'https://www.clopaydoor.com/api/v2/GetProductDetails/GetProductData?productId=' . $product_id;
	$resp = wp_remote_get( $url, array( 'timeout' => 10 ) );
	if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
		$last = get_option( 'twins_clopay_lastgood_' . $product_id );
		return $last ? $last : null;
	}
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( ! is_array( $data ) || empty( $data['ProductId'] ) ) {
		$last = get_option( 'twins_clopay_lastgood_' . $product_id );
		return $last ? $last : null;
	}
	set_transient( $key, $data, DAY_IN_SECONDS );
	update_option( 'twins_clopay_lastgood_' . $product_id, $data, false );
	return $data;
}

// --- Shortcode ---
function twins_clopay_product_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => '', 'mode' => 'specs' ), $atts );
	$p = twins_clopay_get_product( $atts['id'] );
	if ( ! $p ) {
		return '<div class="twx-clopay twx-clopay--empty"><p>Want to explore this Clopay door? <a href="' . esc_url( home_url( '/design-your-door/' ) ) . '">Design it on your own home</a> or give us a call.</p></div>';
	}
	$full = ( $atts['mode'] === 'full' );
	$prop = TWINS_CLOPAY_PROPID ? '?propId=' . rawurlencode( TWINS_CLOPAY_PROPID ) : '';
	$uid  = 'twxc' . (int) $atts['id'];
	$colors = ( ! empty( $p['Colors'] ) && is_array( $p['Colors'] ) ) ? $p['Colors'] : array();
	$docs = array_merge(
		is_array( $p['ProductBrochures'] ?? null ) ? $p['ProductBrochures'] : array(),
		is_array( $p['ProductInstallationAndCare'] ?? null ) ? $p['ProductInstallationAndCare'] : array()
	);
	ob_start(); ?>
	<div class="twx-clopay" id="<?php echo esc_attr( $uid ); ?>">
		<?php if ( $full ) : ?>
			<div class="twx-clopay-full">
				<h2 class="twx-h2"><?php echo wp_kses_post( $p['Title'] ); ?></h2>
				<?php if ( ! empty( $p['ShortDescription'] ) ) : ?><p class="twx-lead"><?php echo esc_html( $p['ShortDescription'] ); ?></p><?php endif; ?>
				<?php if ( ! empty( $p['Overview'] ) ) : ?><div class="twx-rich"><?php echo wp_kses_post( $p['Overview'] ); ?></div><?php endif; ?>
				<?php if ( ! empty( $p['Construction'] ) ) : ?><div class="twx-rich"><?php echo wp_kses_post( $p['Construction'] ); ?></div><?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="twx-clopay-head">
			<span class="twx-eyebrow">Straight from Clopay &middot; always current</span>
			<h2 class="twx-h2">Colors, Designs &amp; Photo Gallery</h2>
		</div>
		<div class="twx-clopay-grid">
			<?php if ( ! empty( $p['ImageGallery'] ) ) : ?>
				<div class="twx-clopay-gallery">
					<iframe src="<?php echo esc_url( $p['ImageGallery'] ); ?>" width="480" height="380"
						data-no-lazy="1" title="<?php echo esc_attr( wp_strip_all_tags( $p['Title'] ) ); ?> photo gallery"></iframe>
				</div>
			<?php endif; ?>
			<div class="twx-clopay-side">
				<?php if ( $colors ) : ?>
					<h3 class="twx-h3">Available Colors <span class="twx-count"><?php echo count( $colors ); ?></span></h3>
					<ul class="twx-swatches">
						<?php foreach ( $colors as $i => $c ) : ?>
							<li<?php echo $i >= 12 ? ' class="twx-sw-extra"' : ''; ?>>
								<img src="<?php echo esc_url( $c['ProductImage'] ?? '' ); ?>" alt="<?php echo esc_attr( $c['AlternativeText'] ?? ( $c['Title'] ?? '' ) ); ?>" title="<?php echo esc_attr( $c['Title'] ?? '' ); ?>" loading="lazy" width="46" height="46">
								<span><?php echo esc_html( $c['Title'] ?? '' ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( count( $colors ) > 12 ) : ?>
						<button type="button" class="twx-more" onclick="twxMore('<?php echo esc_js( $uid ); ?>',this)">Show all <?php echo count( $colors ); ?> colors</button>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( $docs ) : ?>
					<h3 class="twx-h3">Brochures &amp; Guides</h3>
					<div class="twx-docs">
						<?php foreach ( $docs as $d ) : ?>
							<a class="twx-doc" href="<?php echo esc_url( $d['Url'] ?? '#' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $d['Title'] ?? 'Document' ); ?><?php echo ! empty( $d['Extension'] ) ? ' <em>' . esc_html( $d['Extension'] ) . '</em>' : ''; ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<a class="twx-wtb" href="<?php echo esc_url( 'https://www.clopaydoor.com/where-to-buy' . $prop ); ?>" target="_blank" rel="noopener">See Twins on Clopay&rsquo;s dealer locator &rarr;</a>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'clopay_product', 'twins_clopay_product_shortcode' );

// --- Twins UI tokens + Clopay section styles (shared stylesheet) ---
add_action( 'wp_head', function () {
	?>
<style id="twx-ui">
:root{--tw-navy:#022751;--tw-navy-deep:#010D38;--tw-yellow:#FBBD04;--tw-soft:#F2F5F7;--tw-text:#3A3A3A;--tw-muted:#7A7A7A}
/* buttons */
.twx-btn,.twx-btn:visited{display:inline-block;background:var(--tw-yellow);color:var(--tw-navy)!important;font-family:Montserrat,sans-serif;font-weight:700;font-size:16px;line-height:1;padding:16px 28px;border-radius:6px;text-decoration:none!important;letter-spacing:.02em;transition:transform .15s,box-shadow .15s;box-shadow:0 2px 10px rgba(1,13,56,.18)}
.twx-btn:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(1,13,56,.28);color:var(--tw-navy)!important}
.twx-btn--navy,.twx-btn--navy:visited{background:var(--tw-navy);color:var(--tw-yellow)!important;border:2px solid var(--tw-yellow)}
.twx-btn--navy:hover{color:var(--tw-yellow)!important}
.twx-btn--ghost,.twx-btn--ghost:visited{background:transparent;color:#fff!important;border:2px solid rgba(255,255,255,.75);box-shadow:none}
/* type */
.twx-eyebrow{display:block;font-family:Montserrat,sans-serif;font-weight:700;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--tw-muted);margin:0 0 6px}
.twx-h2{font-family:Montserrat,sans-serif;font-weight:800;font-size:32px;line-height:1.15;color:var(--tw-navy);margin:0 0 10px}
.twx-h3{font-family:Montserrat,sans-serif;font-weight:700;font-size:16px;color:var(--tw-navy);margin:0 0 10px}
.twx-lead{font-size:18px;color:var(--tw-text);line-height:1.6}
.twx-h2 .twx-accent,.twx-underline{position:relative}
.twx-underline:after{content:"";display:block;width:56px;height:4px;background:var(--tw-yellow);border-radius:2px;margin-top:10px}
/* hero */
.twx-hero{position:relative;background-size:cover;background-position:center;border-radius:0}
.twx-hero-inner{position:relative;max-width:1140px;margin:0 auto;padding:96px 24px 88px;text-align:left}
.twx-hero-scrim{position:absolute;inset:0;background:linear-gradient(90deg,rgba(1,13,56,.88) 0%,rgba(1,13,56,.72) 45%,rgba(1,13,56,.25) 100%)}
.twx-hero h1{font-family:Montserrat,sans-serif;font-weight:800;font-size:44px;line-height:1.1;color:#fff;margin:0 0 14px;text-shadow:0 2px 12px rgba(1,13,56,.5)}
.twx-hero p{font-size:19px;color:#fff;opacity:.94;max-width:560px;margin:0 0 26px;line-height:1.55}
.twx-hero .twx-ctas{display:flex;gap:14px;flex-wrap:wrap;align-items:center}
/* bands */
.twx-band{background:var(--tw-yellow);padding:34px 24px;text-align:center}
.twx-band .twx-band-inner{max-width:900px;margin:0 auto;display:flex;align-items:center;justify-content:center;gap:24px;flex-wrap:wrap}
.twx-band h2{font-family:Montserrat,sans-serif;font-weight:800;font-size:24px;color:var(--tw-navy);margin:0}
.twx-band--navy{background:var(--tw-navy-deep)}
.twx-band--navy h2{color:#fff}
.twx-band--navy .twx-phone,.twx-band--navy .twx-phone:visited{color:var(--tw-yellow);font-family:Montserrat,sans-serif;font-weight:800;font-size:22px;text-decoration:none}
/* cards */
.twx-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:1140px;margin:0 auto}
.twx-card{background:#fff;border:1px solid #E4E5E7;border-radius:10px;padding:26px 24px;box-shadow:0 2px 12px rgba(1,13,56,.06)}
.twx-card h3{font-family:Montserrat,sans-serif;font-weight:700;font-size:17px;color:var(--tw-navy);margin:0 0 8px}
.twx-card p{font-size:15px;color:var(--tw-text);line-height:1.6;margin:0}
.twx-card .twx-ico{display:inline-flex;width:44px;height:44px;border-radius:8px;background:var(--tw-soft);color:var(--tw-navy);font-size:22px;align-items:center;justify-content:center;margin-bottom:14px}
/* generic sections */
.twx-section{padding:64px 24px}
.twx-section--soft{background:var(--tw-soft)}
.twx-wrap{max-width:1140px;margin:0 auto}
.twx-wrap--narrow{max-width:860px;margin:0 auto}
.twx-cols2{display:grid;grid-template-columns:1fr 1fr;gap:36px}
.twx-check{list-style:none;padding:0;margin:0}
.twx-check li{position:relative;padding:0 0 10px 30px;font-size:15.5px;color:var(--tw-text);line-height:1.55}
.twx-check li:before{content:"\2713";position:absolute;left:0;top:0;width:20px;height:20px;border-radius:50%;background:var(--tw-yellow);color:var(--tw-navy);font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center}
/* form card (funnel) */
.twx-form-card{background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(1,13,56,.25);padding:30px 28px;max-width:460px}
.twx-form-card h3{font-family:Montserrat,sans-serif;font-weight:800;font-size:20px;color:var(--tw-navy);margin:0 0 4px}
.twx-form-card .twx-micro{font-size:12.5px;color:var(--tw-muted);margin:10px 0 0;line-height:1.5}
/* steps */
.twx-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:1140px;margin:0 auto;counter-reset:twxstep}
.twx-step{background:#fff;border:1px solid #E4E5E7;border-radius:10px;padding:26px 24px;position:relative}
.twx-step:before{counter-increment:twxstep;content:counter(twxstep);display:inline-flex;width:38px;height:38px;border-radius:50%;background:var(--tw-navy);color:var(--tw-yellow);font-family:Montserrat,sans-serif;font-weight:800;font-size:17px;align-items:center;justify-content:center;margin-bottom:12px}
.twx-step h3{font-family:Montserrat,sans-serif;font-weight:700;font-size:16px;color:var(--tw-navy);margin:0 0 6px}
.twx-step p{font-size:14.5px;color:var(--tw-text);line-height:1.55;margin:0}
/* clopay section */
.twx-clopay{max-width:1140px;margin:0 auto;scroll-margin-top:140px}
.twx-clopay-head{text-align:center;margin-bottom:28px}
.twx-clopay-head .twx-h2:after{content:"";display:block;width:56px;height:4px;background:var(--tw-yellow);border-radius:2px;margin:12px auto 0}
.twx-clopay-grid{display:grid;grid-template-columns:7fr 5fr;gap:32px;align-items:start}
.twx-clopay-gallery{border-radius:12px;overflow:hidden;box-shadow:0 6px 24px rgba(1,13,56,.14);background:var(--tw-soft)}
.twx-clopay-gallery iframe{display:block;border:0;width:100%;height:auto;aspect-ratio:24/19}
.twx-swatches{display:grid;grid-template-columns:repeat(auto-fill,minmax(72px,1fr));gap:10px;list-style:none;padding:0;margin:0 0 6px}
.twx-swatches li{text-align:center;font-size:10.5px;color:var(--tw-muted);line-height:1.25}
.twx-swatches img{display:block;margin:0 auto 5px;width:46px;height:46px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 0 0 1px #D2D3D5,0 2px 6px rgba(1,13,56,.12)}
.twx-sw-extra{display:none}
.twx-clopay.twx-open .twx-sw-extra{display:block}
.twx-more{background:none;border:0;padding:4px 0;font-family:Montserrat,sans-serif;font-weight:700;font-size:13px;color:var(--tw-navy);text-decoration:underline;cursor:pointer;margin-bottom:14px}
.twx-count{display:inline-block;background:var(--tw-yellow);color:var(--tw-navy);border-radius:999px;font-size:11.5px;font-weight:800;padding:2px 9px;vertical-align:2px;margin-left:6px}
.twx-docs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
.twx-doc,.twx-doc:visited{display:inline-block;background:#fff;border:1.5px solid var(--tw-navy);color:var(--tw-navy)!important;border-radius:999px;padding:8px 16px;font-size:13px;font-weight:600;text-decoration:none!important;line-height:1.2}
.twx-doc:hover{background:var(--tw-navy);color:#fff!important}
.twx-doc em{font-style:normal;opacity:.55;font-size:11px}
.twx-wtb,.twx-wtb:visited{font-family:Montserrat,sans-serif;font-weight:700;font-size:14px;color:var(--tw-navy)!important;text-decoration:none}
.twx-wtb:hover{text-decoration:underline}
.twx-clopay-side .twx-h3{margin-top:18px}
.twx-clopay-side .twx-h3:first-child{margin-top:0}
.twx-rich{color:var(--tw-text);line-height:1.65}
/* P1 accents */
.twx-card{border-top:4px solid var(--tw-yellow)}
.twx-step{border-top:4px solid var(--tw-yellow)}
.twx-card .twx-ico{background:var(--tw-soft)}
.twx-card .twx-ico img{width:30px;height:30px;object-fit:contain;display:block}
[style*="text-align:center"] .twx-underline:after{margin-left:auto;margin-right:auto}
/* mobile */
@media(max-width:900px){
.twx-clopay-grid,.twx-cols2{grid-template-columns:1fr}
.twx-cards,.twx-steps{grid-template-columns:1fr}
.twx-hero h1{font-size:32px}
.twx-hero-inner{padding:64px 20px 56px}
.twx-hero-scrim{background:linear-gradient(180deg,rgba(1,13,56,.85) 0%,rgba(1,13,56,.65) 100%)}
.twx-h2{font-size:26px}
.twx-section{padding:44px 18px}
.twx-band .twx-band-inner{flex-direction:column;gap:14px}
.twx-form-card{max-width:100%}
.twx-btn{width:100%;text-align:center;box-sizing:border-box}
.twx-hero .twx-ctas{width:100%}
.twx-hero .twx-ctas>*{flex:1 1 100%}
}
/* P2 menu fixes */
/* mobile menu modal: bound to viewport + internal scroll (UAEL modal popup) */
.uael-modal.uael-modal-saved_rows{max-height:calc(100vh - 72px)!important}
.uael-modal.uael-modal-saved_rows .uael-content{max-height:calc(100vh - 72px)!important;overflow-y:auto!important;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}
/* desktop header nav: fit 8 items on one row between 1201-1599px (nav widget 15c4a1b in header template) */
@media(min-width:1201px) and (max-width:1599px){
.elementor-element-15c4a1b .elementor-nav-menu--main .elementor-item{font-size:13px!important;letter-spacing:.25px!important;margin-left:6px!important;margin-right:6px!important}
}
/* ============================== twx v2 ==============================
 * Phase 4 component kit (.twx2-* classes). Translated 1:1 from the
 * APPROVED Option A mockup:
 *   docs/superpowers/backups/2026-07-09-phase4-catalog/twx-v2-mockup-approved.html
 * Deploy: append this whole block INSIDE the existing twx-ui style block
 * of snippets 7050 (main) + 6755 (/wi), just before the closing style tag.
 * (No literal style tags in this comment: HTML parsers would end the
 * block at the first closing style tag, even inside a CSS comment.)
 * Additive: no existing .twx- selector is touched. Revert = delete block.
 * Tokens re-declared below with the exact twx-ui values so the kit also
 * works standalone (local harness); harmless duplicate when appended.
 * ==================================================================== */
:root{--tw-navy:#022751;--tw-navy-deep:#010D38;--tw-yellow:#FBBD04;--tw-soft:#F2F5F7}

/* base type for v2 components (mockup body font, applied per-component
 * so the kit never restyles theme content outside .twx2- markup) */
.twx2-hero,.twx2-ribbon,.twx2-body,.twx2-steps,.twx2-closer,.twx2-social,.twx2-grid,#twx2-stickybar{font-family:Montserrat,'Avenir Next','Helvetica Neue',Arial,sans-serif}

/* ---- hero (mockup .m-hero + .A .m-hero flourishes) ---- */
.twx2-hero{position:relative;color:#fff;overflow:hidden;padding:56px 48px 24px;padding-right:min(38vw,460px);min-height:400px;background:
  radial-gradient(860px 480px at 84% 24%, rgba(251,189,4,.18), transparent 64%),
  repeating-linear-gradient(0deg, rgba(255,255,255,.04) 0 2px, transparent 2px 26px),
  repeating-linear-gradient(0deg, rgba(255,255,255,.03) 0 3px, transparent 3px 78px),
  linear-gradient(165deg, var(--tw-navy) 0%, var(--tw-navy-deep) 90%)}
.twx2-hero h1,.twx2-hero h2{font-size:clamp(26px,4vw,40px);font-weight:800;line-height:1.12;margin:0 0 12px;max-width:12em;text-wrap:balance;color:#fff}
.twx2-hero h1 sup,.twx2-hero h2 sup{font-size:.45em;top:-.6em;position:relative}
.twx2-hero h1 em,.twx2-hero h2 em{font-style:normal;color:var(--tw-yellow)}
.twx2-sub{max-width:44ch;color:#cfdbea;margin:0 0 24px;font-size:16.5px;line-height:1.55}
/* eyebrow — brackets are literal text content: "[ Official Clopay Dealer ]" */
.twx2-eyebrow{color:var(--tw-yellow);font-weight:800;font-size:12px;letter-spacing:2.5px;text-transform:uppercase;margin-bottom:14px}
.twx2-eyebrow--section{color:#5d7189;font-size:11.5px;margin-bottom:0}
.twx2-cta{display:flex;gap:14px;flex-wrap:wrap;align-items:center}
.twx2-trustline{margin-top:20px;font-size:13.5px;color:#cfdbea}
.twx2-trustline b{color:var(--tw-yellow);letter-spacing:2px}

/* ---- stamp badge (.A .stamp) ---- */
.twx2-stamp{position:absolute;top:30px;right:380px;transform:rotate(-8deg);background:var(--tw-yellow);color:var(--tw-navy);font-weight:800;font-size:13px;letter-spacing:1px;padding:10px 16px;border:3px dashed var(--tw-navy);border-radius:10px;text-transform:uppercase}

/* ---- twins pair — BOTH twins, staggered, NEVER hidden (.pair, calibrated) ---- */
.twx2-pair{position:absolute;right:14px;bottom:0;display:flex;align-items:flex-end}
.twx2-pair img{height:min(34vw,420px);filter:drop-shadow(6px 10px 0 rgba(1,13,56,.35));animation:twx2bob 5.5s ease-in-out infinite}
.twx2-pair img.twx2-back{height:min(26vw,325px);margin-right:-40px;position:relative;z-index:0;opacity:.97;animation-delay:1.4s;animation-duration:6.5s}
.twx2-pair img.twx2-front{position:relative;z-index:1}
@keyframes twx2bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
/* flanking variant for navy/soft CTA bands (.closer .flank) */
.twx2-pair--band{position:absolute;bottom:0;height:104px;animation:twx2bob 6s ease-in-out infinite;filter:drop-shadow(3px 5px 0 rgba(1,13,56,.3))}
.twx2-pair--band.twx2-l{left:22px}
.twx2-pair--band.twx2-r{right:22px;transform:scaleX(-1)}
@media(prefers-reduced-motion:reduce){.twx2-pair img,.twx2-pair--band{animation:none}}

/* ---- sticker buttons (.btn + .A variants) ---- */
.twx2-btn,.twx2-btn:visited{display:inline-block;font-family:Montserrat,'Avenir Next','Helvetica Neue',Arial,sans-serif;font-weight:800;font-size:15.5px;line-height:1.55;text-decoration:none!important;padding:14px 26px;border-radius:10px;cursor:pointer;transition:transform .1s}
.twx2-btn--gold,.twx2-btn--gold:visited{background:var(--tw-yellow);color:var(--tw-navy)!important;border:3px solid var(--tw-navy-deep);box-shadow:4px 4px 0 rgba(1,13,56,.85)}
.twx2-btn--gold:hover{color:var(--tw-navy)!important}
.twx2-btn--ghost,.twx2-btn--ghost:visited{background:transparent;color:#fff!important;border:3px solid #fff;box-shadow:4px 4px 0 rgba(1,13,56,.45)}
.twx2-btn--ghost:hover{color:#fff!important}
.twx2-btn--navy,.twx2-btn--navy:visited{background:var(--tw-navy);color:#fff!important;border:3px solid var(--tw-navy-deep);box-shadow:4px 4px 0 rgba(251,189,4,.9)}
.twx2-btn--navy:hover{color:#fff!important}

/* ---- trust ribbon (.ribbon + .A .rib-item) ---- */
.twx2-ribbon{background:var(--tw-yellow);padding:18px 48px;display:flex;gap:16px;flex-wrap:wrap;justify-content:center;border-top:3px solid var(--tw-navy-deep);border-bottom:3px solid var(--tw-navy-deep)}
.twx2-rib-item{display:flex;align-items:center;gap:12px;background:#fff;border-radius:12px;padding:12px 18px;min-width:230px;flex:1;max-width:310px;border:3px solid var(--tw-navy);box-shadow:3px 3px 0 rgba(1,13,56,.8)}
.twx2-rib-ico{width:38px;height:38px;border-radius:10px;background:var(--tw-navy);display:flex;align-items:center;justify-content:center;flex:none}
.twx2-rib-ico svg{width:20px;height:20px;stroke:var(--tw-yellow);fill:none;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}
.twx2-rib-item b{display:block;color:var(--tw-navy);font-size:14.5px;line-height:1.2}
.twx2-rib-item small{color:#4c5f76;font-size:12.5px}

/* ---- intro/why two-column section (.m-body) ---- */
.twx2-body{background:#fff;padding:46px 48px;display:grid;grid-template-columns:1.15fr .85fr;gap:36px;align-items:start}
.twx2-h3{color:var(--tw-navy);font-size:24px;font-weight:800;margin:0 0 4px;font-family:Montserrat,'Avenir Next','Helvetica Neue',Arial,sans-serif}
.twx2-h3::after{content:"";display:block;width:56px;height:5px;background:var(--tw-yellow);border-radius:3px;margin-top:8px}

/* ---- sticker cards (.A .card) — default yellow offset shadow ---- */
.twx2-cards{display:grid;gap:14px;margin-top:18px}
.twx2-card{display:flex;gap:14px;align-items:flex-start;background:#fff;border-radius:12px;padding:16px 18px;border:3px solid var(--tw-navy);box-shadow:4px 4px 0 rgba(251,189,4,.9)}
.twx2-card--navyshadow{box-shadow:4px 4px 0 rgba(1,13,56,.85)}
.twx2-chip{width:34px;height:34px;border-radius:9px;background:var(--tw-yellow);color:var(--tw-navy);font-weight:900;display:flex;align-items:center;justify-content:center;flex:none;font-size:17px}
.twx2-card b{color:var(--tw-navy)}
.twx2-card p{margin:2px 0 0;color:#42556e;font-size:14px;line-height:1.55}

/* ---- numbered what-to-expect steps (.steps + .A .step) ---- */
.twx2-steps{background:#fff;border-top:1px solid #e7ecf2;padding:38px 48px}
.twx2-steps h3{color:var(--tw-navy);font-size:22px;font-weight:800;margin:6px 0 20px}
.twx2-row3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.twx2-step{border-radius:12px;padding:18px;position:relative;background:#fff;border:3px solid var(--tw-navy);box-shadow:4px 4px 0 rgba(1,13,56,.85)}
.twx2-num{font-size:30px;font-weight:900;color:var(--tw-yellow);-webkit-text-stroke:1.5px var(--tw-navy);line-height:1}
.twx2-step b{display:block;color:var(--tw-navy);margin:8px 0 4px}
.twx2-step p{margin:0;color:#42556e;font-size:14px;line-height:1.55}

/* ---- closer band w/ flanking twins (.closer + .A .closer) ---- */
.twx2-closer{display:flex;gap:14px;align-items:center;justify-content:center;flex-wrap:wrap;background:var(--tw-soft);padding:26px 120px;position:relative}
.twx2-closer span{font-weight:700;color:var(--tw-navy)}
@media(max-width:700px){
  .twx2-closer{padding-left:16px;padding-right:16px}
  .twx2-pair--band{height:56px}
  .twx2-pair--band.twx2-l{left:4px}
  .twx2-pair--band.twx2-r{right:4px}
}

/* ---- review card + brands strip (.m-social, .A .review, .A .logos) ---- */
.twx2-social{background:#fff;padding:40px 48px;border-top:1px solid #e7ecf2;display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:center}
.twx2-review{position:relative;background:#fff;border-radius:14px;padding:26px 24px 20px;border:3px solid var(--tw-navy);box-shadow:5px 5px 0 rgba(251,189,4,.9)}
.twx2-qm{position:absolute;top:-22px;left:18px;font-size:64px;line-height:1;color:var(--tw-yellow);font-weight:900;font-family:Georgia,serif}
.twx2-review p{margin:8px 0 10px;color:#28405f;font-size:15px}
.twx2-review b{color:var(--tw-navy);font-size:13.5px}
.twx2-stars{color:var(--tw-yellow);letter-spacing:2px;font-size:15px}
.twx2-brands{display:flex;flex-direction:column;gap:8px}
.twx2-strip-label{font-size:11.5px;letter-spacing:2.5px;text-transform:uppercase;color:#5d7189;font-weight:800}
.twx2-brands .twx2-row{display:flex;gap:12px;flex-wrap:wrap}
.twx2-lg{border:2px solid var(--tw-navy);border-radius:9px;padding:9px 16px;font-weight:800;color:var(--tw-navy);font-size:14px;background:#fff;box-shadow:3px 3px 0 rgba(1,13,56,.25)}

/* ---- hub collection grid ([clopay_collection_grid] output; sticker
 * vocabulary from Option A cards — mockup has no hub design, so cards
 * follow the approved card treatment: 3px navy border, yellow offset) ---- */
.twx2-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:22px;max-width:1140px;margin:0 auto}
.twx2-gcard,.twx2-gcard:visited{display:flex;flex-direction:column;background:#fff;border:3px solid var(--tw-navy);border-radius:12px;overflow:hidden;box-shadow:4px 4px 0 rgba(251,189,4,.9);text-decoration:none!important;color:var(--tw-navy)!important;transition:transform .12s}
.twx2-gcard:hover{transform:translate(-2px,-2px);color:var(--tw-navy)!important}
.twx2-gcard img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block;border-bottom:3px solid var(--tw-navy)}
.twx2-gcard-body{padding:14px 16px 16px;display:flex;flex-direction:column;gap:6px;flex:1}
.twx2-gcard b{font-size:16px;color:var(--tw-navy);line-height:1.25}
.twx2-gcard b sup{font-size:.55em}
.twx2-gcard small{color:#42556e;font-size:13px;line-height:1.45}
.twx2-gcard-cta{margin-top:auto;padding-top:6px;font-weight:800;font-size:13px;color:var(--tw-navy)}
.twx2-grid--empty{display:block;background:var(--tw-soft);border:3px solid var(--tw-navy);border-radius:12px;padding:22px 24px;color:var(--tw-navy);font-weight:600;text-align:center}
.twx2-grid--empty a{color:var(--tw-navy);font-weight:800}

/* ---- mockup mobile behavior (breakpoints kept 1:1 at 860/700) ---- */
@media(max-width:860px){
  .twx2-hero{padding-right:48px;min-height:0}
  .twx2-pair{position:static;justify-content:flex-end;margin:20px -20px -28px 0}
  .twx2-pair img{height:250px}
  .twx2-pair img.twx2-back{height:195px;margin-right:-30px}
  .twx2-stamp{position:static;display:inline-block;transform:rotate(-3deg);margin-bottom:14px}
  .twx2-body{grid-template-columns:1fr}
  .twx2-row3{grid-template-columns:1fr}
  .twx2-social{grid-template-columns:1fr}
}

/* ---- mobile sticky Call/Book bar (site-wide, rendered on wp_footer;
 * z-index 99998 = below WP admin bar; mockup .phone .bar scaled to a
 * real 390px viewport) ---- */
#twx2-stickybar{display:none}
@media(max-width:768px){
  #twx2-stickybar{display:flex;position:fixed;left:0;right:0;bottom:0;gap:8px;padding:9px 10px;padding-bottom:calc(9px + env(safe-area-inset-bottom));background:var(--tw-navy-deep);border-top:3px solid var(--tw-yellow);z-index:99998}
  #twx2-stickybar a,#twx2-stickybar a:visited{flex:1;text-align:center;font-weight:800;font-size:14px;padding:12px 4px;border-radius:8px;text-decoration:none!important;line-height:1.2;white-space:nowrap}
  #twx2-stickybar .twx2-sb-call,#twx2-stickybar .twx2-sb-call:visited{background:var(--tw-yellow);color:var(--tw-navy)!important}
  #twx2-stickybar .twx2-sb-book,#twx2-stickybar .twx2-sb-book:visited{border:2px solid #fff;color:#fff!important;background:transparent}
  body.twx2-hasbar{padding-bottom:64px}
}
/* ============================ end twx v2 ============================ */
</style>
<script>function twxMore(id,btn){var w=document.getElementById(id);if(w){w.classList.add('twx-open');btn.style.display='none';}}</script>
	<?php
} );

// --- Daily cache refresh ---
add_action( 'twins_clopay_refresh', function () {
	foreach ( array( 170, 12, 13 ) as $pid ) {
		delete_transient( 'twins_clopay_prod_' . $pid );
		twins_clopay_get_product( $pid );
	}
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'twins_clopay_refresh' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'twins_clopay_refresh' );
	}
} );

// Preserve the twx inline stylesheet from WP Rocket Remove-Unused-CSS
add_filter( 'rocket_rucss_inline_content_exclusions', function ( $excluded ) {
	if ( ! is_array( $excluded ) ) { $excluded = array(); }
	$excluded[] = 'twx-ui';
	$excluded[] = '--tw-navy';
	return $excluded;
} );

/**
 * Phase 4 twx v2 additions for WPCode snippet 7050 (main site).
 *  - TWINS_HCP_BOOK_URL / TWINS_STICKYBAR defines
 *  - [clopay_collection_grid] shortcode (cached GetProducts residential list)
 *  - Mobile sticky Call/Book bar on wp_footer (front-end only, <=768px CSS)
 * Conventions follow the deployed Clopay snippet (transient + durable
 * last-good fallback, esc_* everywhere). Reversible: set TWINS_STICKYBAR
 * false to kill the bar; remove this block to remove grid + bar entirely.
 * Spec: docs/superpowers/specs/2026-07-09-phase4-clopay-catalog-twx-v2-design.md
 */

// --- Phase 4 defines ---
if ( ! defined( 'TWINS_HCP_BOOK_URL' ) ) {
	// Same HCP online-booking link the Madison LP mobile bar uses. Verified HTTP 200 (browser UA) 2026-07-09.
	define( 'TWINS_HCP_BOOK_URL', 'https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true' );
}
if ( ! defined( 'TWINS_STICKYBAR' ) ) { define( 'TWINS_STICKYBAR', true ); }

// --- Residential products list: fetch + cache (24h transient, durable last-good fallback) ---
function twins_clopay_get_products_list() {
	$key    = 'twins_clopay_products_list';
	$cached = get_transient( $key );
	if ( $cached !== false ) {
		return $cached;
	}
	$url  = 'https://www.clopaydoor.com/api/v2/GetProductsList/GetProducts?productType=Residential';
	$resp = wp_remote_get( $url, array( 'timeout' => 10 ) );
	if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
		$last = get_option( 'twins_clopay_lastgood_list' );
		return $last ? $last : null;
	}
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( ! is_array( $data ) || empty( $data ) || empty( $data[0]['ProductId'] ) ) {
		$last = get_option( 'twins_clopay_lastgood_list' );
		return $last ? $last : null;
	}
	set_transient( $key, $data, DAY_IN_SECONDS );
	update_option( 'twins_clopay_lastgood_list', $data, false );
	return $data;
}

// --- Product id -> collection page URL (slugs per Phase 4 content pack;
// filterable so Task 3+ can sync if the pack renames a slug) ---
function twins_clopay_collection_url( $product_id, $title = '' ) {
	$map = array(
		8   => 'clopay-reserve-wood-custom',
		9   => 'clopay-reserve-wood-semi-custom',
		10  => 'clopay-reserve-wood-limited-edition',
		11  => 'clopay-coachman',
		12  => 'clopay-gallery-steel',                       // existing twx page
		13  => 'clopay-classic-collection',                  // existing twx page
		16  => 'clopay-avante',
		23  => 'clopay-classic-wood',
		25  => 'clopay-reserve-wood-modern',
		26  => 'clopay-canyon-ridge-modern',
		27  => 'clopay-grand-harbor',
		29  => 'clopay-canyon-ridge-carriage-house-4-layer',
		30  => 'clopay-canyon-ridge-carriage-house-5-layer',
		170 => 'clopay-modern-steel',                        // existing twx page
		240 => 'clopay-canyon-ridge-louver',
		250 => 'clopay-bridgeport-steel',
		290 => 'clopay-avante-sleek',
		291 => 'clopay-reserve-wood-extira',
		320 => 'clopay-canyon-ridge-chevron',
		330 => 'clopay-canyon-ridge-elements',
		340 => 'clopay-modern-steel-ultra-grain-plank',
		370 => 'clopay-vertistack-avante',
		380 => 'clopay-bridgeport-inlay',
	);
	$map  = apply_filters( 'twins_clopay_slug_map', $map );
	$pid  = (int) $product_id;
	$slug = isset( $map[ $pid ] ) ? $map[ $pid ] : 'clopay-' . sanitize_title( wp_strip_all_tags( (string) $title ) );
	return home_url( '/' . $slug . '/' );
}

// --- [clopay_collection_grid] — all 23 residential collections, server-side ---
function twins_clopay_collection_grid_shortcode() {
	$list = twins_clopay_get_products_list();
	if ( ! $list ) {
		// Graceful empty-state if the API is down and no last-good copy exists.
		return '<div class="twx2-grid--empty"><p>Our full Clopay lineup is taking a moment to load. '
			. '<a href="' . esc_url( home_url( '/design-your-door/' ) ) . '">Design your door online</a> '
			. 'or call (833) 833-2010 and we&rsquo;ll walk you through every collection.</p></div>';
	}
	$sup_only = array( 'sup' => array() );
	ob_start();
	echo '<div class="twx2-grid">';
	foreach ( $list as $p ) {
		if ( empty( $p['ProductId'] ) || empty( $p['Title'] ) ) {
			continue;
		}
		// Card title: drop marketing tails like " – Now Available with C-Power™".
		$title_disp = preg_replace( '/\s*[\x{2013}\x{2014}-]\s*Now Available.*$/iu', '', (string) $p['Title'] );
		$plain      = trim( wp_strip_all_tags( $title_disp ) );
		$hook       = ! empty( $p['ShortDescription'] ) ? wp_trim_words( wp_strip_all_tags( (string) $p['ShortDescription'] ), 18, '&hellip;' ) : '';
		$img        = ! empty( $p['ShowcaseImage'] ) ? $p['ShowcaseImage'] : '';
		$href       = twins_clopay_collection_url( $p['ProductId'], $title_disp );
		echo '<a class="twx2-gcard" href="' . esc_url( $href ) . '">';
		if ( $img ) {
			echo '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( 'Clopay ' . $plain . ' garage door' ) . '" loading="lazy">';
		}
		echo '<span class="twx2-gcard-body">';
		echo '<b>' . wp_kses( $title_disp, $sup_only ) . '</b>';
		if ( $hook ) {
			echo '<small>' . esc_html( $hook ) . '</small>';
		}
		echo '<span class="twx2-gcard-cta">See designs &amp; colors &rarr;</span>';
		echo '</span></a>';
	}
	echo '</div>';
	return ob_get_clean();
}
add_shortcode( 'clopay_collection_grid', 'twins_clopay_collection_grid_shortcode' );

// Refresh the list alongside the existing daily product-detail refresh.
add_action( 'twins_clopay_refresh', function () {
	delete_transient( 'twins_clopay_products_list' );
	twins_clopay_get_products_list();
} );

// --- Mobile sticky Call/Book bar — site-wide on main, front-end only ---
add_action( 'wp_footer', 'twins_twx2_stickybar', 99 );
function twins_twx2_stickybar() {
	if ( ! ( ! defined( 'TWINS_STICKYBAR' ) || TWINS_STICKYBAR ) ) {
		return; // define TWINS_STICKYBAR as false to disable site-wide
	}
	if ( is_admin() ) {
		return;
	}
	// Skip Elementor editor + preview frames.
	if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor_library'] ) ) {
		return;
	}
	if ( class_exists( '\Elementor\Plugin' )
		&& isset( \Elementor\Plugin::$instance->editor )
		&& \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
		return;
	}
	// LP slugs carry their own bar (server-side suppression by path).
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	if ( '' !== $path && false !== strpos( $path, '-lp' ) ) {
		return;
	}
	?>
<div id="twx2-stickybar">
	<a class="twx2-sb-call" href="tel:+18338332010">&#128222; Call (833) 833-2010</a>
	<a class="twx2-sb-book" href="<?php echo esc_url( TWINS_HCP_BOOK_URL ); ?>" target="_blank" rel="noopener">Book Online</a>
</div>
<script id="twx2-stickybar-js">(function(){var b=document.getElementById('twx2-stickybar');if(!b){return;}if(document.querySelector('.tlp')){b.parentNode.removeChild(b);return;}document.body.classList.add('twx2-hasbar');})();</script>
	<?php
}
