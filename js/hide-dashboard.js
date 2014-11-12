jQuery( document ).ready( function ( $ ) {

	//hide all remaining hide dashboard features if enabled isn't checked
	$( '#hd_enabled' ).change(function () {

		if ( $( '#hd_enabled' ).is( ':checked' ) ) {

			$( '#hd_slug, #hd_theme_compat' ).closest( 'tr' ).show();
			$( '#hd_theme_compat' ).change();

		} else {

			$( '#hd_slug, #hd_theme_compat, #hd_theme_compat_slug' ).closest( 'tr' ).hide();

		}

	} ).change();

	//A separate check to hide the theme compatibility slug if we don't need it
	$( '#hd_theme_compat' ).change(function () {

		if ( $( '#hd_theme_compat' ).is( ':checked' ) ) {

			$( '#hd_theme_compat_slug' ).closest( 'tr' ).show();

		} else {

			$( '#hd_theme_compat_slug' ).closest( 'tr' ).hide();

		}

	} ).change();

} );
