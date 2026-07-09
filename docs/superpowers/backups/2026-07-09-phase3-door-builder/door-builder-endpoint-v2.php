/**
 * Design Your Door funnel - lead endpoint (all three sites post here). v2.
 * POST /wp-json/twins/v1/door-builder  {name, phone, email, zip, region, website(honeypot)}
 * v2 adds OPTIONAL door-builder config fields: collection, design, color,
 * windows, glass, notes (sanitize_text_field, 200-char cap) — when any are
 * present the email body gains a "Door configuration" section. Backward
 * compatible: existing funnel forms send none of them and behave unchanged.
 * Emails contact@twinsgaragedoors.com with a region-tagged subject.
 * Pattern follows snippet 7028 (Madison Landing Page Lead Endpoint).
 * Spec: docs/superpowers/specs/2026-07-09-phase3-door-builder-visualizer-design.md
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'twins/v1', '/door-builder', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			// Honeypot: bots fill 'website'; pretend success.
			if ( ! empty( $req['website'] ) ) {
				return array( 'ok' => true );
			}
			$name  = sanitize_text_field( $req['name'] ?? '' );
			$phone = sanitize_text_field( $req['phone'] ?? '' );
			$email = sanitize_email( $req['email'] ?? '' );
			$zip   = sanitize_text_field( $req['zip'] ?? '' );
			$region = in_array( $req['region'] ?? '', array( 'main', 'wi', 'ky' ), true ) ? $req['region'] : 'main';
			if ( ! $name || ! $phone ) {
				return new WP_Error( 'missing_fields', 'Name and phone are required.', array( 'status' => 400 ) );
			}
			// v2: optional door-builder configuration fields.
			$config_keys  = array( 'collection', 'design', 'color', 'windows', 'glass', 'notes' );
			$config_lines = array();
			foreach ( $config_keys as $k ) {
				$v = isset( $req[ $k ] ) ? sanitize_text_field( substr( (string) $req[ $k ], 0, 200 ) ) : '';
				if ( $v !== '' ) {
					$config_lines[] = ucfirst( $k ) . ': ' . $v;
				}
			}
			$region_label = array( 'main' => 'Madison WI (main site)', 'wi' => 'Wisconsin (/wi)', 'ky' => 'Kentucky (/ky)' )[ $region ];
			$body = "New \"Design Your Door\" lead.\n\n"
				. "Region: {$region_label}\n"
				. "Name:   {$name}\n"
				. "Phone:  {$phone}\n"
				. "Email:  {$email}\n"
				. "Zip:    {$zip}\n\n"
				. "They were sent into the Clopay EZDoor builder after submitting. Follow up with a quote.";
			if ( $config_lines ) {
				$body .= "\n\nDoor configuration\n" . implode( "\n", $config_lines );
			}
			wp_mail(
				'contact@twinsgaragedoors.com',
				"New Door Builder lead ({$region}): {$name}",
				$body
			);
			return array( 'ok' => true );
		},
	) );
} );
