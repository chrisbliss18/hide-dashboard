jQuery( document ).ready( function ( $ ) {

	//Show a warning about the new login slug if it has changed
	if ( hide_dashboard.slug_changed ) {
		alert( hide_dashboard.slug_text );
	}

	//hide all remaining hide dashboard features if enabled isn't checked
	$( '#hd_enabled' ).change(function () {

		if ( $( '#hd_enabled' ).is( ':checked' ) ) {

			$( '#hd_slug, #hd_theme_compat, #hd_register, #hd_login_action' ).closest( 'tr' ).show();
			$( '#hd_theme_compat' ).change();

		} else {

			$( '#hd_slug, #hd_theme_compat, #hd_theme_compat_slug, #hd_register, #hd_login_action' ).closest( 'tr' ).hide();

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
