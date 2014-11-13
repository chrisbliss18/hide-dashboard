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
class Hide_Dashboard {

	private
		$forbidden_slugs,
		$plugin_data,
		$plugin_file;

	/**
	 * Hide Dashboard admin constructor.
	 *
	 * @since 0.0.1
	 *
	 * @param string $plugin_file the main plugin file
	 *
	 * @return Hide_Dashboard
	 */
	public function __construct( $plugin_file ) {

		$this->plugin_file     = $plugin_file;
		$this->plugin_data     = get_plugin_data( $this->plugin_file, false );
		$this->forbidden_slugs = array(
			'admin',
			'login',
			'wp-login.php',
			'dashboard',
			'wp-admin',
			''
		); //strings that can't be used for the slug due to conflict

		//remember the text domain
		load_plugin_textdomain( 'hide-dashboard', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) ); //enqueue scripts for admin page
		add_action( 'admin_init', array( $this, 'admin_init' ) );

	}

	/**
	 * Enqueue necessary admin scripts.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {

		$slug_changed = get_site_option( 'hd_slug_changed' );
		$slug_text    = '';

		if ( $slug_changed !== false ) {

			delete_site_option( 'hd_slug_changed' );

			$new_slug = get_site_url() . '/' . get_site_option( 'hd_slug' );

			$slug_text = sprintf(
				'%s%s%s%s%s',
				__( 'Warning: Your admin URL has changed. Use the following URL to login to your site', 'hide-dashboard' ),
				PHP_EOL . PHP_EOL,
				$new_slug,
				PHP_EOL . PHP_EOL,
				__( 'Please note this may be different than what you sent as the URL was sanitized to meet various requirements. A reminder has also been sent to the site administrator.', 'hide-dashboard' )
			);

		}

		if ( get_current_screen()->id == 'options-general' ) {
			wp_enqueue_script( 'hide-dashboard-js', plugins_url( '/js/hide-dashboard.js', $this->plugin_file ), array( 'jquery' ), $this->plugin_data['Version'] );
			wp_localize_script(
				'hide-dashboard-js',
				'hide_dashboard',
				array(
					'slug_changed' => $slug_changed,
					'slug_text'    => $slug_text,
				)
			);
		}

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
			'hd_login_action',
			__( 'Custom Login Action', 'hide-dashboard' ),
			array( $this, 'settings_field_login_action' ),
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
			array( $this, 'sanitize_theme_compat_slug' )
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

		$enabled = ( isset( $input ) && intval( $input == 1 ) ? true : false );

		//Notify them of the login change if they turn off the feature
		if ( get_site_option( 'hd_enabled' ) == true && $enabled == false ) {

			add_site_option( 'hd_slug_changed', true ); //set an option so we can show the popup
			$this->send_new_slug( 'wp-admin' ); //Send an email so they know what's up

		}

		return $enabled;

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

		return trim( sanitize_title( $input ) );

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

		if ( get_site_option( 'users_can_register' ) ) {

			$slug = trim( sanitize_title( $input ) );

			if ( in_array( $slug, $this->forbidden_slugs ) ) {

				$slug    = 'wp-register.php';
				$type    = 'error';
				$message = __( 'Invalid registration slug used. The registration slug cannot be \"login,\" \"admin,\" \"dashboard,\" or \"wp-login.php\" or \"\" (blank) as these are use by default in WordPress.', 'hide-dashboard' );

				add_settings_error( 'hide-dashboard', esc_attr( 'settings_updated' ), $message, $type );
				update_site_option( 'hd_enabled', false );

			}

		} elseif ( get_site_option( 'hd_register' ) !== false ) {

			$slug = get_site_option( 'hd_register' );

		} else {

			$slug = 'wp-register.php';

		}

		return $slug;

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

		$slug = trim( sanitize_title( $input ) );

		if ( in_array( $slug, $this->forbidden_slugs ) ) {

			$type    = 'error';
			$message = __( 'Invalid hide login slug used. The login url slug cannot be \"login,\" \"admin,\" \"dashboard,\" or \"wp-login.php\" or \"\" (blank) as these are use by default in WordPress.', 'hide-dashboard' );

			add_settings_error( 'hide-dashboard', esc_attr( 'settings_updated' ), $message, $type );
			update_site_option( 'hd_enabled', false );

		}

		if ( get_site_option( 'hd_slug' ) !== $slug ) {

			add_site_option( 'hd_slug_changed', true ); //set an option so we can show the popup
			$this->send_new_slug( $slug ); //Send an email so they know what's up

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

		return ( isset( $input ) && intval( $input == 1 ) ? true : false );

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

		$slug = trim( sanitize_title( $input ) );

		if ( in_array( $slug, $this->forbidden_slugs ) ) {

			$type    = 'error';
			$message = __( 'Invalid theme compatibility login slug used. The theme compatibility slug cannot be \"login,\" \"admin,\" \"dashboard,\" or \"wp-login.php\" or \"\" (blank) as these are use by default in WordPress.', 'hide-dashboard' );

			add_settings_error( 'hide-dashboard', esc_attr( 'settings_updated' ), $message, $type );
			update_site_option( 'hd_enabled', false );

		}

		return $slug;

	}

	/**
	 * Sends an email to notify site admins of the new login url
	 *
	 * @since 0.0.1
	 *
	 * @param  string $new_slug the new login url
	 *
	 * @return void
	 */
	private function send_new_slug( $new_slug ) {

		$new_slug = trim( sanitize_title( $new_slug ) ); //never worry about extra cleanup

		//Put the copy all together
		$body = sprintf(
			'<p>%s,</p><p>%s <a href="%s">%s</a>. %s <a href="%s">%s</a> %s.</p>',
			__( 'Dear Site Admin', 'hide-dashboard' ),
			__( 'This friendly email is just a reminder that you have changed the dashboard login address on', 'hide-dashboard' ),
			get_site_url(),
			get_site_url(),
			__( 'You must now use', 'hide-dashboard' ),
			trailingslashit( get_site_url() ) . $new_slug,
			trailingslashit( get_site_url() ) . $new_slug,
			__( 'to login to your WordPress website', 'hide-dashboard' )
		);

		//Setup the remainder of the email
		$recipient = get_site_option( 'admin_email' );
		$subject   = '[' . get_option( 'siteurl' ) . '] ' . __( 'WordPress Login Address Changed', 'hide-dashboard' );
		$subject   = apply_filters( 'itsec_lockout_email_subject', $subject );
		$headers   = 'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>' . "\r\n";

		//Send the email only if it is valid
		if ( is_email( trim( $recipient ) ) ) {

			//Use HTML Content type
			add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );

			wp_mail( trim( $recipient ), $subject, $body, $headers );

			//Remove HTML Content type
			remove_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );

		}

	}

	/**
	 * Set HTML content type for email
	 *
	 * @return string html content type
	 */
	public function set_html_content_type() {

		return 'text/html';

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

			$admin_url = is_multisite() ? admin_url( 'network/' ) : admin_url(); //make sure to use network admin on multisite

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
	public function settings_field_login_action() {

		$slug = get_site_option( 'hd_login_action' ) !== false ? sanitize_title( get_site_option( 'hd_login_action' ) ) : ''; //set the default slug to wplogin

		echo '<input name="hd_login_action" id="hd_login_action" value="' . $slug . '" type="text"><br />';
		echo '<label for="hd_login_action">' . __( 'Custom Action:', 'hide-dashboard' ) . '</label>';
		echo '<p class="description">' . __( 'WordPress uses the "action" variable to handle many login and logout functions. By default this plugin can handle the normal ones but some plugins and themes may utilize a custom action (such as logging out of a private post). If you need a custom action please enter it here.', 'hide-dashboard' ) . '</p>';

	}

	/**
	 * Echo registration slug field
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function settings_field_register() {

		$slug = get_site_option( 'hd_register' ) !== false && get_site_option( 'hd_register' ) !== 'wp-register.php' ? sanitize_title( get_site_option( 'hd_register' ) ) : 'wp-register.php'; //set the default slug to wplogin

		echo '<input name="hd_register" id="hd_register" value="' . $slug . '" type="text"><br />';
		echo '<label for="hd_register">' . __( 'Registration URL:', 'hide-dashboard' ) . trailingslashit( get_option( 'siteurl' ) ) . '<span style="color: #4AA02C">' . $slug . '</span></label>';

	}

	/**
	 * Echo login slug field
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function settings_field_slug() {

		$slug = get_site_option( 'hd_slug' ) !== false ? sanitize_title( get_site_option( 'hd_slug' ) ) : 'wplogin'; //set the default slug to wplogin

		echo '<input name="hd_slug" id="hd_slug" value="' . $slug . '" type="text"><br />';
		echo '<label for="hd_slug">' . __( 'Login URL:', 'hide-dashboard' ) . trailingslashit( get_option( 'siteurl' ) ) . '<span style="color: #4AA02C">' . $slug . '</span></label>';
		echo '<p class="description">' . __( 'The login url slug cannot be "login," "admin," "dashboard," or "wp-login.php" as these are use by default in WordPress.', 'hide-dashboard' ) . '</p>';
		echo '<p class="description"><em>' . __( 'Note: The output is limited to alphanumeric characters, underscore (_) and dash (-). Special characters such as "." and "/" are not allowed and will be converted in the same manner as a post title. Please review your selection before logging out.', 'hide-dashboard' ) . '</em></p>';

	}

	/**
	 * Echo enabled theme compatibility mode field
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function settings_field_theme_compat() {

		echo '<input type="checkbox" id="hd_theme_compat" name="hd_theme_compat" value="1" ' . checked( get_site_option( 'hd_theme_compat' ), true, false ) . '/>';
		echo '<label for="hd_theme_compat"> ' . __( 'Enable theme compatibility. If  you see errors in your theme when using hide backend, in particular when going to wp-admin while not logged in, turn this on to fix them.', 'hide-dashboard' ) . '</label>';

	}

	/**
	 * Echo theme compatibility slug field
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function settings_field_theme_compat_slug() {

		$slug = get_site_option( 'hd_theme_compat_slug' ) !== false ? sanitize_title( get_site_option( 'hd_theme_compat_slug' ) ) : 'not_found'; //set the default slug for a 404 page

		echo '<input name="hd_theme_compat_slug" id="hd_theme_compat_slug" value="' . $slug . '" type="text"><br />';
		echo '<label for="hd_theme_compat_slug">' . __( '404 Slug:', 'hide-dashboad' ) . trailingslashit( get_option( 'siteurl' ) ) . '<span style="color: #4AA02C">' . $slug . '</span></label>';
		echo '<p class="description">' . __( 'The slug to redirect folks to when theme compatibility mode is enabled (just make sure it does not exist in your site).', 'hide-dashboad' ) . '</p>';

	}

}