<?php
// NOTE: WPCode auto-prepends `<?php` — when appending to snippet 7050, paste
// everything BELOW this line at the END of the snippet's existing PHP code.
// (The companion twx-v2-kit.css goes INSIDE the <style id="twx-ui"> block,
// before </style> — see that file's header. 6755 gets the CSS only.)
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
