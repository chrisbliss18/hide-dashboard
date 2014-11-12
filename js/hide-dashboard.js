jQuery( document ).ready( function ( $ ) {

	//hide all remaining hide dashboard features if enabled isn't checked
	$( '#hd_enabled' ).change(function () {

		if ( $( '#hd_enabled' ).is( ':checked' ) ) {

			$( '#hd_slug, #hd_theme_compat' ).closest( 'tr' ).show();

		} else {

			$( '#hd_slug, #hd_theme_compat' ).closest( 'tr' ).hide();

		}

	} ).change();

} );
