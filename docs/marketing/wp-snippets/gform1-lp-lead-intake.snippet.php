<?php
// WPCode PHP snippet: main 7165 "Contact form (gform 1) -> lp-lead-intake (Dunzo GHL)".
// 2026-07-10 delta: generate a chooser_token, set it as a 10-min cookie (read by the
// thank-you chooser snippet 7330), and add service/zip/form_variant/chooser_token/consent
// to the lp-lead-intake payload so the /thank-you/ chooser can resolve this lead.
add_action( 'gform_after_submission_1', function ( $entry, $form ) {
	$token = wp_generate_uuid4();
	setcookie( 'twins_chooser', $token, time() + 600, '/', '', true, false );
	$services = array();
	foreach ( $entry as $key => $value ) {
		if ( strpos( (string) $key, '6.' ) === 0 && ! empty( $value ) ) {
			$services[] = $value;
		}
	}
	$address = trim( rgar( $entry, '5.1' ) . ' ' . rgar( $entry, '5.3' ) . ' ' . rgar( $entry, '5.5' ) );
	$message = trim( (string) rgar( $entry, '7' ) );
	if ( $services ) {
		$message .= "\nServices: " . implode( ', ', $services );
	}
	if ( $address ) {
		$message .= "\nAddress: " . $address;
	}
	$payload = array(
		'name'          => trim( rgar( $entry, '1' ) . ' ' . rgar( $entry, '2' ) ),
		'email'         => rgar( $entry, '3' ),
		'phone'         => rgar( $entry, '4' ),
		'message'       => trim( $message ),
		'page'          => rgar( $entry, 'source_url' ) ? rgar( $entry, 'source_url' ) : 'contact-us gform_1',
		'service'       => $services ? implode( ', ', $services ) : '',
		'zip'           => rgar( $entry, '5.5' ),
		'form_variant'  => 'A',
		'chooser_token' => $token,
		'consent'       => 'true',
	);
	wp_remote_post(
		'https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/lp-lead-intake',
		array(
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( $payload ),
			'timeout'  => 8,
			'blocking' => false,
		)
	);
}, 10, 2 );
