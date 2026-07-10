/* ===== CSS extract: snippet 7050 wp_head style block, lines 326-339 ===== */
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

// ===== PHP extract: snippet 7050 lines 390-509 =====

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
