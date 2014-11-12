jQuery( document ).ready( function ( $ ) {

	//hide all remaining hide dashboard features if enabled isn't checked
	$( '#hd_enabled' ).change(function () {

		if ( $( '#hd_enabled' ).is( ':checked' ) ) {

			$( '#hd_slug' ).closest( 'tr' ).show();

		} else {

			$( '#hd_slug' ).closest( 'tr' ).hide();

		}

	} ).change();

} );
