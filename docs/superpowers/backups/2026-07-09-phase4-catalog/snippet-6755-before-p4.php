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
