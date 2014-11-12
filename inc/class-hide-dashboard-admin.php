<?php

/**
 * @package hide_dashboard
 */

/**
 * Hide Dashboard admin interface.
 *
 * Admin-specific items such as settings.
 *
 * @since 0.0.1
 *
 */
class Hide_Dashboard_Admin {

	private
		$plugin_file;

	/**
	 * Hide Dashboard admin constructor.
	 *
	 * @since 0.0.1
	 *
	 * @param string $plugin_file the main plugin file
	 *
	 * @return Hide_Dashboard_Admin
	 */
	public function __construct( $plugin_file ) {

		$this->plugin_file = $plugin_file;

		//remember the text domain
		load_plugin_textdomain( 'hide-dashboard', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		add_action( 'admin_init', array( $this, 'admin_init' ) );

	}

	/**
	 * Handles admin_init functions.
	 *
	 * Builds meta boxes, sets up settings API.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function admin_init() {

		//add settings fields
		add_settings_field(
			'hd_enabled',
			__( 'Enable Hide Dashboard', 'hide-dashboard' ),
			array( $this, 'settings_field_enabled' ),
			'general'
		);

		add_settings_field(
			'hd_slug',
			__( 'Login Slug', 'hide-dashboard' ),
			array( $this, 'settings_field_slug' ),
			'general'
		);

		if ( get_site_option( 'users_can_register' ) ) { //only used if user registration is enabled.

			add_settings_field(
				'hd_register',
				__( 'Registration Slug', 'hide-dashboard' ),
				array( $this, 'settings_field_register' ),
				'general'
			);

		}

		add_settings_field(
			'hd_theme_compat',
			__( 'Enable Theme Compatibility', 'hide-dashboard' ),
			array( $this, 'settings_field_theme_compat' ),
			'general'
		);

		add_settings_field(
			'hd_theme_compat_slug',
			__( 'Theme Compatibility Slug', 'hide-dashboard' ),
			array( $this, 'settings_field_theme_compat_slug' ),
			'general'
		);

		add_settings_field(
			'hd_post_login_action',
			__( 'Custom Login Action', 'hide-dashboard' ),
			array( $this, 'settings_field_post_login_action' ),
			'general'
		);

		//Register the settings fields for the entire module
		register_setting(
			'general',
			'hd_enabled',
			array( $this, 'sanitize_enabled' )
		);

		register_setting(
			'general',
			'hd_login_action',
			array( $this, 'sanitize_login_action' )
		);

		register_setting(
			'general',
			'hd_register',
			array( $this, 'sanitize_register' )
		);

		register_setting(
			'general',
			'hd_slug',
			array( $this, 'sanitize_slug' )
		);

		register_setting(
			'general',
			'hd_theme_compat',
			array( $this, 'sanitize_theme_compat' )
		);

		register_setting(
			'general',
			'hd_theme_compat_slug',
			array( $this, 'sanitize_theme_compat' )
		);

	}

	/**
	 * Sanitize enabled field
	 *
	 * @since 0.0.1
	 *
	 * @param string $input form field input
	 *
	 * @return string
	 */
	public function sanitize_enabled( $input ) {

		return ( isset( $input ) && intval( $input == 1 ) ? true : false );

	}

	/**
	 * Sanitize login action field
	 *
	 * @since 0.0.1
	 *
	 * @param string $input form field input
	 *
	 * @return string
	 */
	public function sanitize_login_action( $input ) {

		return $input;
	}

	/**
	 * Sanitize registration slug field
	 *
	 * @since 0.0.1
	 *
	 * @param string $input form field input
	 *
	 * @return string
	 */
	public function sanitize_register( $input ) {

		return $input;
	}

	/**
	 * Sanitize login slug field
	 *
	 * @since 0.0.1
	 *
	 * @param string $input form field input
	 *
	 * @return string
	 */
	public function sanitize_slug( $input ) {

		$forbidden_slugs = array(
			'admin',
			'login',
			'wp-login.php',
			'dashboard',
			'wp-admin',
			''
		); //strings that can't be used for the slug due to conflict
		$slug            = trim( sanitize_title( $input ) );

		if ( in_array( $slug , $forbidden_slugs ) ) {

			$type    = 'error';
			$message = __( 'Invalid hide login slug used. The login url slug cannot be \"login,\" \"admin,\" \"dashboard,\" or \"wp-login.php\" or \"\" (blank) as these are use by default in WordPress.', 'hide-dashboard' );

			add_settings_error( 'hide-dashboard', esc_attr( 'settings_updated' ), $message, $type );
			update_site_option( 'hd_enabled', false );

		}

		return $slug;

	}

	/**
	 * Sanitize enabled theme compatibility mode field
	 *
	 * @since 0.0.1
	 *
	 * @param string $input form field input
	 *
	 * @return string
	 */
	public function sanitize_theme_compat( $input ) {

		return $input;
	}

	/**
	 * Sanitize theme compatibility slug field
	 *
	 * @since 0.0.1
	 *
	 * @param string $input form field input
	 *
	 * @return string
	 */
	public function sanitize_theme_compat_slug( $input ) {

		return $input;
	}

	/**
	 * Echo enabled field
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function settings_field_enabled() {

		//If permalinks aren't enabled promt them to enable them
		if ( ( get_option( 'permalink_structure' ) == '' || get_option( 'permalink_structure' ) == false ) && ! is_multisite() ) {

			$admin_url = is_multisite() ? admin_url( 'network/' ) : admin_url();

			printf(
				'<p class="noPermalinks">%s <a href="%soptions-permalink.php">%s</a> %s</p>',
				__( 'You must turn on', 'hide-dashboard' ),
				$admin_url,
				__( 'WordPress permalinks', 'hide-dashboard' ),
				__( 'to use this feature.', 'hide-dashboard' )
			);

		} else {

			echo '<input type="checkbox" id="hd_enabled" name="hd_enabled" value="1" ' . checked( get_site_option( 'hd_enabled' ), true, false ) . '/>';
			echo '<label for="hd_enabled"> ' . __( 'Enable the hide dashboard feature.', 'hide-dashboard' ) . '</label>';

		}

	}

	/**
	 * Echo login action field
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function settings_field_post_login_action() {

		echo 'field';
	}

	/**
	 * Echo registration slug field
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function settings_field_register() {

		echo 'field';
	}

	/**
	 * Echo login slug field
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function settings_field_slug() {

		if ( ( get_option( 'permalink_structure' ) == '' || get_option( 'permalink_structure' ) == false ) && ! is_multisite() ) {

			echo '';

		} else {

			$slug = get_site_option( 'hd_slug' ) !== false ? sanitize_title( get_site_option( 'hd_slug' ) ) : 'wplogin';

			echo '<input name="hd_slug" id="hd_slug" value="' . $slug . '" type="text"><br />';
			echo '<label for="hd_slug">' . __( 'Login URL:', 'hide-dashboard' ) . trailingslashit( get_option( 'siteurl' ) ) . '<span style="color: #4AA02C">' . $slug . '</span></label>';
			echo '<p class="description">' . __( 'The login url slug cannot be "login," "admin," "dashboard," or "wp-login.php" as these are use by default in WordPress.', 'hide-dashboard' ) . '</p>';
			echo '<p class="description"><em>' . __( 'Note: The output is limited to alphanumeric characters, underscore (_) and dash (-). Special characters such as "." and "/" are not allowed and will be converted in the same manner as a post title. Please review your selection before logging out.', 'hide-dashboard' ) . '</em></p>';

		}

	}

	/**
	 * Echo enabled theme compatibility mode field
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function settings_field_theme_compat() {

		echo 'field';
	}

	/**
	 * Echo theme compatibility slug field
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function settings_field_theme_compat_slug() {

		echo 'field';
	}

}