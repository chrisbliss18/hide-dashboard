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
		$auth_cookie_expired,
		$forbidden_slugs,
		$plugin_file,
		$slug_changed,
		$slug_text;

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
		$this->forbidden_slugs = array(
			'admin',
			'login',
			'wp-login.php',
			'dashboard',
			'wp-admin',
			''
		); //strings that can't be used for the slug due to conflict
		$this->slug_changed    = false;
		$this->slug_text       = '';

		//remember the text domain
		load_plugin_textdomain( 'hide-dashboard', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) ); //enqueue scripts for admin page
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		//Execute hide backend functionality if plugin is active
		//Execute module functions on frontend init
		if ( get_site_option( 'hd_enabled' ) == true ) {

			//Determine if Jetpack is active so we don't block it out
			$jetpack_active_modules = get_option( 'jetpack_active_modules' );
			$is_jetpack_active      = in_array( 'jetpack/jetpack.php', (array) get_option( 'active_plugins', array() ) );

			if (
			! (
				$is_jetpack_active === true &&
				is_array( $jetpack_active_modules ) &&
				in_array( 'json-api', $jetpack_active_modules ) &&
				isset( $_GET['action'] ) &&
				$_GET['action'] == 'jetpack_json_api_authorization'
			)
			) {

				$this->auth_cookie_expired = false;

				add_action( 'auth_cookie_expired', array( $this, 'auth_cookie_expired' ) );
				add_action( 'init', array( $this, 'init' ), 1000 );
				add_action( 'login_init', array( $this, 'login_init' ) );
				add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 11 );

				add_filter( 'body_class', array( $this, 'body_class' ) );
				add_filter( 'loginout', array( $this, 'loginout' ) );
				add_filter( 'wp_redirect', array( $this, 'filter_login_url' ), 10, 2 );
				add_filter( 'lostpassword_url', array( $this, 'filter_login_url' ), 10, 2 );
				add_filter( 'site_url', array( $this, 'filter_login_url' ), 10, 2 );
				add_filter( 'retrieve_password_message', array( $this, 'retrieve_password_message' ) );
				add_filter( 'comment_moderation_text', array( $this, 'comment_moderation_text' ) );

			}

		}

	}

	/**
	 * Enqueue necessary admin scripts.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {

		$plugin_data = get_plugin_data( $this->plugin_file, false );

		if ( get_current_screen()->id == 'options-general' ) {
			wp_enqueue_script( 'hide-dashboard-js', plugins_url( '/js/hide-dashboard.js', $this->plugin_file ), array( 'jquery' ), $plugin_data['Version'] );
			wp_localize_script(
				'hide-dashboard-js',
				'hide_dashboard',
				array(
					'slug_changed' => $this->slug_changed,
					'slug_text'    => $this->slug_text,
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

		//Handle items that must be changed after changing the slug.
		if ( get_site_option( 'hd_slug_changed' ) !== false ) {

			delete_site_option( 'hd_slug_changed' ); //cleanup after ourselves.... Mom don't code here

			$slug = 'wp-admin';

			//Get the correct_slug
			if ( get_site_option( 'hd_enabled' ) == true ) {

				add_rewrite_rule( $slug . '/?$', 'wp-login.php', 'top' );
				$slug = get_site_option( 'hd_slug' );

			}

			$this->slug_changed = true;
			$login_url          = get_site_url() . '/' . $slug;

			$this->slug_text = sprintf(
				'%s%s%s%s%s',
				__( 'Warning: Your admin URL has changed. Use the following URL to login to your site', 'hide-dashboard' ),
				PHP_EOL . PHP_EOL,
				$login_url,
				PHP_EOL . PHP_EOL,
				__( 'Please note this may be different than what you sent as the URL was sanitized to meet various requirements. A reminder has also been sent to the site administrator.', 'hide-dashboard' )
			);

			//Send an email to notify as well
			$this->send_new_slug( $slug );

			//Save the rewrite rules
			flush_rewrite_rules();

		}

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
	 * Lets the plugin know that this is a re-authorization
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function auth_cookie_expired() {

		$this->auth_cookie_expired = true;

		wp_clear_auth_cookie();

	}

	/**
	 * Removes the admin bar class from the body tag
	 *
	 * @since 0.0.1
	 *
	 * @param  array $classes body tag classes
	 *
	 * @return array          body tag classes
	 */
	public function body_class( $classes ) {

		if ( is_admin() && is_user_logged_in() !== true ) {

			foreach ( $classes as $key => $value ) {

				if ( $value == 'admin-bar' ) {
					unset( $classes[ $key ] );
				}

			}

		}

		return $classes;

	}

	/**
	 *Filter url in comment moderation links
	 *
	 * * @since 0.0.1
	 *
	 * @param string $notify_message Notification message
	 *
	 * @return string Notification message
	 */
	public function comment_moderation_text( $notify_message ) {

		preg_match_all( "#(https?:\/\/((.*)wp-admin(.*)))#", $notify_message, $urls );

		if ( isset( $urls ) && is_array( $urls ) && isset( $urls[0] ) ) {

			foreach ( $urls[0] as $url ) {

				$notify_message = str_replace( trim( $url ), wp_login_url( trim( $url ) ), $notify_message );

			}

		}

		return $notify_message;

	}

	/**
	 * Filters redirects for correct login URL
	 *
	 * @since 0.0.1
	 *
	 * @param  string $url URL redirecting to
	 *
	 * @return string       Correct redirect URL
	 */
	public function filter_login_url( $url ) {

		return str_replace( 'wp-login.php', get_site_option( '_hdslug' ), $url );

	}

	/**
	 * Returns the root of the WordPress install
	 *
	 * @since 0.0.1
	 *
	 * @return string the root folder
	 */
	public static function get_home_root() {

		//homeroot from wp_rewrite
		$home_root = parse_url( site_url() );

		if ( isset( $home_root['path'] ) ) {

			$home_root = trailingslashit( $home_root['path'] );

		} else {

			$home_root = '/';

		}

		return $home_root;

	}

	/**
	 * Returns the server type of the plugin user.
	 *
	 * @since 0.0.1
	 *
	 * @return string|bool server type the user is using of false if undetectable.
	 */
	private static function get_server() {

		$server_raw = strtolower( filter_var( $_SERVER['SERVER_SOFTWARE'], FILTER_SANITIZE_STRING ) );

		//figure out what server they're using
		if ( strpos( $server_raw, 'apache' ) !== false ) {

			$server =  'apache';

		} elseif ( strpos( $server_raw, 'nginx' ) !== false ) {

			$server =  'nginx';

		} elseif ( strpos( $server_raw, 'litespeed' ) !== false ) {

			$server =  'litespeed';

		} else { //unsupported server

			$server =  false;

		}

		$server = apply_filters( 'hd_server_type', $server ); //allow users to override

		return $server;

	}

	/**
	 * Execute hide backend functionality
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function init() {

		if ( get_site_option( 'users_can_register' ) == 1 && isset( $_SERVER['REQUEST_URI'] ) && $_SERVER['REQUEST_URI'] == $this->get_home_root() . get_site_option( 'hd_register' ) ) {

			wp_redirect( wp_login_url() . '?action=register' );

			exit;

		}

		//redirect wp-admin and wp-register.php to 404 when not logged in
		if (
			(
				(
					get_site_option( 'users_can_register' ) == false &&
					(
						isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'wp-register.php' ) ||
						isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'wp-signup.php' )
					)
				) ||
				(
					isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) && is_user_logged_in() !== true
				) ||
				( is_admin() && is_user_logged_in() !== true ) ||
				(
					$this->settings['register'] != 'wp-register.php' &&
					strpos( $_SERVER['REQUEST_URI'], 'wp-register.php' ) !== false ||
					strpos( $_SERVER['REQUEST_URI'], 'wp-signup.php' ) !== false ||
					(
						isset( $_REQUEST['redirect_to'] ) &&
						strpos( $_REQUEST['redirect_to'], 'wp-admin/customize.php' ) !== false

					)
				)
			) &&
			strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' ) === false
			&& $this->auth_cookie_expired === false
		) {

			global $hd_is_old_admin;

			$hd_is_old_admin = true;

			if ( get_site_option( 'hd_theme_compat' ) == true ) { //theme compatibility (process theme and redirect to a 404)

				wp_redirect( $this->get_home_root() . sanitize_title( get_site_option( 'hd_theme_compat_slug' ) ), 302 );
				exit;

			} else { //just set the current page as a 404

				add_action( 'wp_loaded', array( $this, 'set_404' ) );

			}

		}

		$url_info                  = parse_url( $_SERVER['REQUEST_URI'] );
		$login_path                = site_url( get_site_option( 'hd_slug' ), 'relative' );
		$login_path_trailing_slash = site_url( get_site_option( 'hd_slug' ) . '/', 'relative' );

		if ( $url_info['path'] === $login_path || $url_info['path'] === $login_path_trailing_slash ) {

			if ( ! is_user_logged_in() ) {
				//Add the login form

				if ( strlen( trim( get_site_option( 'hd_post_logout_slug' ) ) ) > 0 && isset( $_GET['action'] ) && sanitize_text_field( $_GET['action'] ) == trim( get_site_option( 'hd_post_logout_slug' ) ) ) {

					//add hook here for custom users... Begin deprication of itsec hook
					do_action( 'itsec_custom_login_slug' );
					do_action( 'hd_custom_login_slug' );

				}

				//suppress error messages due to timing
				error_reporting( 0 );
				@ini_set( 'display_errors', 0 );

				status_header( 200 );

				//don't allow domain mapping to redirect
				if ( defined( 'DOMAIN_MAPPING' ) && DOMAIN_MAPPING == 1 ) {
					remove_action( 'login_head', 'redirect_login_to_orig' );
				}

				if ( ! function_exists( 'login_header' ) ) {

					include( ABSPATH . 'wp-login.php' );
					exit;

				}

			} elseif ( ! isset( $_GET['action'] ) || ( sanitize_text_field( $_GET['action'] ) != 'logout' && sanitize_text_field( $_GET['action'] ) != 'postpass' && ( strlen( trim( get_site_option( 'hd_post_logout_slug' ) ) ) > 0 && sanitize_text_field( $_GET['action'] ) != trim( get_site_option( 'hd_post_logout_slug' ) ) ) ) ) {
				//Just redirect them to the dashboard (for logged in users)

				if ( $this->auth_cookie_expired === false ) {

					wp_safe_redirect( get_admin_url() );
					exit();

				}

			} elseif ( isset( $_GET['action'] ) && ( sanitize_text_field( $_GET['action'] ) == 'postpass' || ( strlen( trim( get_site_option( 'hd_post_logout_slug' ) ) ) > 0 && sanitize_text_field( $_GET['action'] ) == trim( get_site_option( 'hd_post_logout_slug' ) ) ) ) ) {
				//handle private posts for

				if ( strlen( trim( get_site_option( 'hd_post_logout_slug' ) ) ) > 0 && sanitize_text_field( $_GET['action'] ) == trim( get_site_option( 'hd_post_logout_slug' ) ) ) {

					//add hook here for custom users: Begin deprication of itsec slug
					do_action( 'itsec_custom_login_slug' );
					do_action( 'hd_custom_login_slug' );

				}

				//suppress error messages due to timing
				error_reporting( 0 );
				@ini_set( 'display_errors', 0 );

				status_header( 200 ); //its a good login page. make sure we say so

				//include the login page where we need it
				if ( ! function_exists( 'login_header' ) ) {
					include( ABSPATH . '/wp-login.php' );
					exit;
				}

				//Take them back to the page if we need to
				if ( isset( $_SERVER['HTTP_REFERRER'] ) ) {

					wp_safe_redirect( esc_url( $_SERVER['HTTP_REFERRER'] ) );
					exit();

				}

			}

		}

	}

	/**
	 * Filter the old login page out
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function login_init() {

		if ( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) ) { //are we on the login page

			global $hd_is_old_admin;

			$hd_is_old_admin = true;

			$this->set_404();

		}

	}

	/**
	 * Filter meta link
	 *
	 * @since 0.0.1
	 *
	 * @param string $link the link
	 *
	 * @return string the link
	 */
	public function filter_loginout( $link ) {

		return str_replace( 'wp-login.php', get_site_option( 'hd_slug' ), $link );

	}

	/**
	 * Actions for plugins loaded.
	 *
	 * Makes certain logout is processed on NGINX.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function plugins_loaded() {

		if ( is_user_logged_in() && isset( $_GET['action'] ) && sanitize_text_field( $_GET['action'] ) == 'logout' ) {

			check_admin_referer( 'log-out' );
			wp_logout();

			$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : 'wp-login.php?loggedout=true';

			wp_safe_redirect( $redirect_to );
			exit();

		}

	}

	/**
	 * Filter the login URL in the password reset message
	 *
	 * @since 0.0.1
	 *
	 * @param string $message The password reset message
	 *
	 * @return string the password reset message
	 */
	public function retrieve_password_message( $message ) {

		return str_replace( 'wp-login.php', get_site_option( 'hd_slug' ), $message );

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

		//Notify them of the login change if they turn the feature on or off
		if ( get_site_option( 'hd_enabled' ) != $enabled ) {
			add_site_option( 'hd_slug_changed', true ); //set an option so we can show the popup
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

		//If the new slug doesn't match the old we should let them know
		if ( get_site_option( 'hd_slug' ) !== $slug ) {
			add_site_option( 'hd_slug_changed', true ); //set an option so we can show the popup
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
	 * Sets 404 error at later time.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function set_404() {

		global $wp_query;

		status_header( 404 );

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		$wp_query->set_404();
		$page_404 = get_404_template();

		if ( strlen( $page_404 ) > 1 ) {

			include( $page_404 );

		} else {

			include( get_query_template( 'index' ) );

		}

		die();

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