<?php
/**
 * Design Your Door funnel - lead endpoint (all three sites post here).
 * POST /wp-json/twins/v1/door-builder  {name, phone, email, zip, region, website(honeypot)}
 * Emails contact@twinsgaragedoors.com with a region-tagged subject, then the
 * page JS redirects the visitor to the Clopay EZDoor builder.
 * Pattern follows snippet 7028 (Madison Landing Page Lead Endpoint).
 * Spec: docs/superpowers/specs/2026-07-08-twins-web-redesign-clopay-ezdoor-design.md
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
			$region_label = array( 'main' => 'Madison WI (main site)', 'wi' => 'Wisconsin (/wi)', 'ky' => 'Kentucky (/ky)' )[ $region ];
			$body = "New \"Design Your Door\" lead.\n\n"
				. "Region: {$region_label}\n"
				. "Name:   {$name}\n"
				. "Phone:  {$phone}\n"
				. "Email:  {$email}\n"
				. "Zip:    {$zip}\n\n"
				. "They were sent into the Clopay EZDoor builder after submitting. Follow up with a quote.";
			wp_mail(
				'contact@twinsgaragedoors.com',
				"New Door Builder lead ({$region}): {$name}",
				$body
			);
			return array( 'ok' => true );
		},
	) );
} );
