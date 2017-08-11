<?php

class XO_Security {
	private $loginlog_table;
	private $options;

	function __construct() {
		global $wpdb;

		$this->loginlog_table = $wpdb->base_prefix . XO_SECURITY_LOGINLOG_TABLE_NAME;
		$this->options = get_option( 'xo_security_options' );
		add_action( 'plugins_loaded', array( $this, 'setup' ), 99999 );
	}

	/**
	 * テーブルを作成します。プラグインを有効にしたときに実行されます。
	 */
	public static function activation() {
		global $wpdb;

		$loginlog_table = $wpdb->base_prefix . XO_SECURITY_LOGINLOG_TABLE_NAME;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$loginlog_table}'" ) != $loginlog_table ) {
			$wpdb->query(
						"CREATE TABLE `{$loginlog_table}` (" .
					"`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT," .
					"`success` boolean NOT NULL," .
					"`login_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'," .
					"`ip_address` varchar(35) DEFAULT NULL," .
					"`lang` varchar(5) DEFAULT NULL," .
					"`user_agent` varchar(255) DEFAULT NULL," .
					"`user_name` varchar(255) DEFAULT NULL," .
					"`failed_password` varchar(255) DEFAULT NULL," .
					"PRIMARY KEY (`id`)" .
					");"
			);
		}
	}

	/**
	 * テーブルおよびオプション設定を削除します。プラグインを削除したときに実行されます。
	 */
	public static function uninstall() {
		global $wpdb;

		$loginlog_table = $wpdb->base_prefix . XO_SECURITY_LOGINLOG_TABLE_NAME;
		$wpdb->query( "DROP TABLE {$loginlog_table};" );
		delete_option( 'xo_security_options' );
	}

	function setup() {
		add_action( 'wp_login_failed', array( $this, 'handler_wp_login_failed' ) );
		add_action( 'wp_login', array( $this, 'handler_wp_login' ), 1, 2 );
		add_action( 'xmlrpc_call', array( $this, 'handler_xmlrpc_call' ), 10, 1 );
		add_filter( 'xmlrpc_login_error', array( $this, 'xmlrpc_login_error_message' ) );
		add_action( 'login_init', array( $this, 'login_init' ) );
		add_filter( 'login_errors', array( $this, 'login_error_message' ) );
		add_filter( 'shake_error_codes', array( $this, 'login_failure_shake' ) );
		add_filter( 'wp_authenticate_user', array( $this, 'authenticate_user' ), 99999, 2 );

		$login_page = isset( $this->options['login_page'] ) ? $this->options['login_page'] : false;
		if ( $login_page ) {
			add_action( 'login_init', array( $this, 'login_page_login_init' ) );
			add_filter( 'site_url', array( $this, 'login_page_site_url' ), 10, 4 );
			add_filter( 'wp_redirect', array( $this, 'login_page_wp_redirect' ), 10, 2 );
		}

		$disable_author_archive = isset( $this->options['author_archive'] ) ? $this->options['author_archive'] : false;
		if ( $disable_author_archive ) {
			add_filter( 'author_rewrite_rules', array( $this, 'author_rewrite_rules' ) );
			add_action( 'init', array( $this, 'author_rewrite' ) );
		}

		$disable_comment_author_class = isset( $this->options['comment_author_class'] ) ? $this->options['comment_author_class'] : false;
		if ( $disable_comment_author_class ) {
			add_filter( 'comment_class', array( $this, 'remove_comment_author_class' ) );
		}

		$disable_xmlrpc = isset( $this->options['xmlrpc'] ) ? $this->options['xmlrpc'] : false;
		if ( $disable_xmlrpc ) {
			add_filter( 'xmlrpc_enabled', array( $this, 'xmlrpc_enabled' ) );
		}
		$disable_pingback = isset( $this->options['pingback'] ) ? $this->options['pingback'] : false;
		if ( $disable_pingback ) {
			add_filter( 'xmlrpc_methods', array( $this, 'remove_pingback' ) );
			add_filter( 'wp_headers', array( $this, 'remove_x_pingback' ) );
		}

		$disable_rest = isset( $this->options['rest'] ) ? $this->options['rest'] : false;
		if ( $disable_rest ) {
			global $wp_version;
			if ( version_compare( $wp_version, '4.7' ) >= 0 ) {
				add_filter( 'rest_authentication_errors', array( $this, 'rest_authentication_errors' ) );
			} else {
				$this->disable_rest();
			}
		}
		$rest_rename = isset( $this->options['rest_rename'] ) ? $this->options['rest_rename'] : false;
		if ( $rest_rename ) {
			if ( has_action( 'wp_head', 'rest_output_link_wp_head' ) )
				remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
			add_filter( 'rest_url_prefix', array( $this, 'rest_url_prefix' ) );
		}
	}

	public function rest_authentication_errors( $result ) {
		if ( !is_user_logged_in() ) {
			return new WP_Error(
				'restapi_cannot_request',
				__( 'Sorry, cannot request the REST API.', 'xo-security' ),
				array( 'status' => rest_authorization_required_code() ) );
		}
		return $result;
	}

	public function disable_rest() {
		remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
		if ( has_action( 'wp_head', 'rest_output_link_wp_head' ) )
			remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );

		// REST API 1.x
		add_filter( 'json_enabled', '__return_false' );
		add_filter( 'json_jsonp_enabled', '__return_false' );

		// REST API 2.x
		add_filter( 'rest_enabled', '__return_false' );
		add_filter( 'rest_jsonp_enabled', '__return_false' );
	}

	public function rest_url_prefix() {
		if ( !empty( $this->options['rest_name'] ) ) {
			return $this->options['rest_name'];
		}
	}

	public function handler_wp_login_failed( $username ) {
		$this->failed_login( $username );
	}

	public function handler_wp_login( $login, $current_user ) {
		if ( '' == $current_user->user_login ) {
			return;
		}
		$this->successful_login( $current_user );
	}

	public function handler_xmlrpc_call( $method ) {
		$current_user = wp_get_current_user();
		if ( '' == $current_user->user_login ) {
			return;
		}
		$this->successful_login( $current_user );
	}

	public function authenticate_user( $user, $password ) {
		if ( !is_wp_error( $user ) && !$this->is_login_ok() ) {
			$blocked_tarpit = isset( $this->options['blocked_tarpit'] ) ? (int) $this->options['blocked_tarpit'] : 0;
			if ( $blocked_tarpit > 0 ) {
				sleep( $blocked_tarpit );
			}
			$user = new WP_Error( 'limit_login', __( 'We restrict the login right now.', 'xo-security' ) );
		}
		return $user;
	}

	public function login_error_message( $error ) {
		if ( !$this->is_login_ok() ) {
			$blocked_tarpit = isset( $this->options['blocked_tarpit'] ) ? (int) $this->options['blocked_tarpit'] : 0;
			if ( $blocked_tarpit > 0 ) {
				sleep( $blocked_tarpit );
			}
			return __( 'We restrict the login right now.', 'xo-security' );
		}
		return __( 'Username or password is incorrect', 'xo-security' );
	}

	public function login_init() {
		if ( defined( 'XO_SECURITY_LANGUAGE_WHITE_LIST' ) && XO_SECURITY_LANGUAGE_WHITE_LIST !== '' ) {
			$lang = strtolower( substr( $this->get_language(), 0, 2 ) );
			$whitelangs = explode( ',', strtolower( XO_SECURITY_LANGUAGE_WHITE_LIST ) );
			if( ! in_array( $lang, $whitelangs ) ) {
				$blocked_tarpit = isset( $this->options['blocked_tarpit'] ) ? (int) $this->options['blocked_tarpit'] : 0;
				if ( $blocked_tarpit > 0 ) {
					sleep( $blocked_tarpit );
				}
				wp_redirect( home_url( '/404' ) );
				exit;
			}
		}

		if ( !$this->is_login_ok() ) {
			wp_redirect( home_url( '/404' ) );
			exit;
		}
	}

	// 指定以外のログイン URL は 404 エラーとします。
	public function login_page_login_init() {
		$login_page_name = (isset( $this->options['login_page_name'] ) ? $this->options['login_page_name'] : '');
		if ( strpos( $_SERVER['REQUEST_URI'], $login_page_name ) === false ) {
			wp_redirect( home_url( '/404' ) );
			exit;
		}
	}

	// ログイン済みか新設のログイン URL の場合は wp-login.php を置き換えます。
	public function login_page_site_url( $url, $path, $orig_scheme, $blog_id ) {
		if ( isset( $this->options['login_page_name'] ) ) {
			$loginfile = $this->options['login_page_name'] . '.php';
			if ( $path == 'wp-login.php' && ( is_user_logged_in() || strpos( $_SERVER['REQUEST_URI'], $loginfile ) !== false ) ) {
				$url = str_replace( 'wp-login.php', $loginfile, $url );
			}
		}
		return $url;
	}

	// ログアウト時のリダイレクト先の設定します。
	public function login_page_wp_redirect( $location, $status ) {
		if ( isset( $this->options['login_page_name'] ) ) {
			$loginfile = $this->options['login_page_name'] . '.php';
			if ( strpos( $_SERVER['REQUEST_URI'], $loginfile ) !== false ) {
				$location = str_replace( 'wp-login.php', $loginfile, $location );
			}
		}
		return $location;
	}

	function xmlrpc_enabled() {
		$blocked_tarpit = isset( $this->options['blocked_tarpit'] ) ? (int)$this->options['blocked_tarpit'] : 0;
		if ( $blocked_tarpit > 0 ) {
			sleep( $blocked_tarpit );
		}
		return false;
	}

	function xmlrpc_login_error_message( $error, $user ) {
		return new IXR_Error( 404, __( 'Unable to login.', 'xo-security' ) );
	}

	function login_failure_shake( $error_codes ) {
		$error_codes[] = 'limit_login';
		return $error_codes;
	}

	function author_rewrite_rules() {
		return array();
	}

	function author_rewrite() {
		$author = filter_input( INPUT_GET, 'author' );
		$uri = @$_SERVER['REQUEST_URI'];
		if ( $author || preg_match( '#/author/.+#', $uri ) ) {
			wp_redirect( home_url( '/404' ) );
			exit;
		}
	}

	function remove_comment_author_class( $classes ) {
		foreach ( $classes as $key => $class ) {
			if ( strstr( $class, 'comment-author-' ) ) {
				unset( $classes[$key] );
			}
		}
		return $classes;
	}

	function remove_pingback( $methods ) {
		unset( $methods['pingback.ping'] );
		unset( $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	function remove_x_pingback( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	// クライアントの IP アドレスを取得します。
	private function get_ipaddress() {
		$ip = null;
		if ( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip = trim( $ips[0] );
		} else if ( !empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	// USER AGENT を取得します。
	private function get_user_agent() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		return substr( esc_html( $user_agent ), 0, 254 );
	}

	// ブラウザのメイン言語を取得します。
	private function get_language() {
		$result = null;
		if ( isset ( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$langs = explode( ',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
			if ( count( $langs ) >= 1 ) {
				$main_lang = explode( ';', $langs[0] );
				if ( count( $main_lang ) >= 1 ) {
					$result = substr( trim( $main_lang[0] ), 0, 5 );
				}
			}
		}
		return $result;
	}

	function failed_login( $user_name ) {
		global $wpdb;

		$login_time = current_time( 'mysql' );
		$ip_address = $this->get_ipaddress();
		$lang = $this->get_language();
		$user_agent = $this->get_user_agent();
		$password = esc_html( filter_input ( INPUT_POST, 'pwd' ) );

		if ( get_user_by( 'login', $user_name ) ) {
			$password = null;
		}

		$wpdb->insert( $this->loginlog_table, array(
			'success' => false,
			'login_time' => $login_time,
			'ip_address' => $ip_address,
			'lang' => $lang,
			'user_agent' => $user_agent,
			'user_name' => $user_name,
			'failed_password' => $password
		) );

		$failed_tarpit = isset( $this->options['failed_tarpit'] ) ? (int) $this->options['failed_tarpit'] : 0;
		if ( $failed_tarpit > 0 ) {
			sleep( $failed_tarpit );
		}
	}

	function successful_login( $user ) {
		global $wpdb;

		$user_name = $user->user_login;
		$login_time = current_time( 'mysql' );
		$ip_address = $this->get_ipaddress();
		$lang = $this->get_language();
		$user_agent = $this->get_user_agent();

		$wpdb->insert( $this->loginlog_table, array(
			'success' => true,
			'login_time' => $login_time,
			'ip_address' => $ip_address,
			'lang' => $lang,
			'user_agent' => $user_agent,
			'user_name' => $user_name,
			'failed_password' => null
		) );

		// 古いログインログの自動削除
		$truncate_date = isset( $this->options['auto_truncate'] ) ? intval( $this->options['auto_truncate'] ) : 0;
		if ( $truncate_date > 0 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->loginlog_table} WHERE `login_time` <= DATE_SUB(NOW(), INTERVAL %d day)", $truncate_date ) );
		}

		$login_alert = isset( $this->options['login_alert'] ) ? $this->options['login_alert'] : false;
		if ( $login_alert ) {
			$login_alert_admin_only = isset( $this->options['login_alert_admin_only'] ) ? $this->options['login_alert_admin_only'] : false;
			if ( $login_alert_admin_only ) {
				if ( $user->has_cap( 'administrator' ) ) {
					$this->send_login_mail( $user, $ip_address, $user_agent );
				}
			} else {
				$this->send_login_mail( $user, $ip_address, $user_agent );
			}
		}
	}

	function send_login_mail( $user, $ip_address, $user_agent ) {
		$user_email = $user->user_email;

		$user_name = $user->user_login;
		$site_name = get_bloginfo( 'name' );
		$subject = isset( $this->options['login_alert_subject'] ) ? $this->options['login_alert_subject'] : '';
		$body = isset( $this->options['login_alert_body'] ) ? $this->options['login_alert_body'] : '';

		$subject = str_replace( array( '%SITENAME%', '%USERNAME%', '%DATE%', '%TIME%', '%IPADDRESS%', '%USERAGENT%' ),
					array( $site_name, $user_name, date_i18n( 'Y-m-d' ), date_i18n( 'H:i:s' ), $ip_address, $user_agent ), $subject );
		$body = str_replace( array( '%SITENAME%', '%USERNAME%', '%DATE%', '%TIME%', '%IPADDRESS%', '%USERAGENT%' ),
					array( $site_name, $user_name, date_i18n( 'Y-m-d' ), date_i18n( 'H:i:s' ), $ip_address, $user_agent ), $body );

		wp_mail( $user_email, esc_html( $subject ), esc_html( $body ) );
	}

	private function is_login_ok() {
		global $wpdb;

		$locked = false;
		$ipaddress_esc = esc_sql( $this->get_ipaddress() );
		$interval_hour = isset( $this->options['interval'] ) ? (int) $this->options['interval'] : 0;
		if ( $interval_hour > 0 ) {
			$time = date( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - ($interval_hour * 60 * 60) );
			$success_login_time = $wpdb->get_var( "SELECT login_time FROM {$this->loginlog_table} WHERE ip_address='{$ipaddress_esc}' AND success=1 ORDER BY login_time DESC LIMIT 1" );
			if ( $success_login_time !== null ) {
				$time = max( $time, $success_login_time );
			}
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->loginlog_table} WHERE ip_address='{$ipaddress_esc}' AND success=0 AND login_time > %s;", $time ) );
			$limit_count = isset( $this->options['limit_count'] ) ? (int) $this->options['limit_count'] : 1;
			if ( $count >= $limit_count ) {
				$locked = true;
			}
		}
		if ( !$locked ) {
			$user_agent = $this->get_user_agent();
			if ( $user_agent === '' ) {
				$locked = true;
			} else {
				if ( defined( 'XO_SECURITY_UA_WHITE_LIST' ) && XO_SECURITY_UA_WHITE_LIST !== '' ) {
					$locked = true;
					$whites = explode( ',', XO_SECURITY_UA_WHITE_LIST );
					foreach ( $whites as $white ) {
						if ( !( stripos( $user_agent, $white ) === false ) ) {
							$locked = false;
							break;
						}
					}
				}
				if ( !$locked ) {
					if ( defined( 'XO_SECURITY_UA_BLACK_LIST' ) && XO_SECURITY_UA_BLACK_LIST !== '' ) {
						$blocks = explode( ',', XO_SECURITY_UA_BLACK_LIST );
						foreach ( $blocks as $block ) {
							if ( !( stripos( $user_agent, $block ) === false ) ) {
								$locked = true;
								break;
							}
						}
					}
				}
			}
		}
		return !$locked;
	}
}
