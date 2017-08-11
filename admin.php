<?php

if ( !class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class XO_Login_Log_List_Table extends WP_List_Table {
	function __construct() {
		parent::__construct( array(
			'singular' => 'loginlog',
			'plural' => 'loginlogs',
			'ajax' => false
		) );
		wp_get_current_user();
	}

	function column_default( $item, $column_name ) {
		return $item[$column_name];
	}

	function column_success( $item ) {
		return $item['success'] ? __( 'Success', 'xo-security' ) : __( 'Failure', 'xo-security' );
	}

	function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="%1$s[]" value="%2$s" />', $this->_args['singular'], $item['id'] );
	}

	function get_columns() {
		global $current_user;

		$columns = array(
			'cb' => '<input type="checkbox" />',
			'login_time' => __( 'Date', 'xo-security' ),
			'success' => __( 'Result', 'xo-security' ),
			'ip_address' => __( 'IP address', 'xo-security' ),
			'lang' => __( 'Language', 'xo-security' ),
			'user_agent' => __( 'User Agent', 'xo-security' )
		);
		if ( $current_user->has_cap( 'administrator' ) ) {
			$columns['user_name'] = __( 'Username', 'xo-security' );
			$columns['failed_password'] = __( 'Password', 'xo-security' );
		}
		return $columns;
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'login_time' => array( 'logintime', true ),
			'success' => array( 'success', false ),
			'ip_address' => array( 'ipaddress', false ),
			'lang' => array( 'lang', false ),
			'user_name' => array( 'username', false ),
		);
		return $sortable_columns;
	}

	function get_bulk_actions() {
		$actions = array( 'delete' => __( 'Delete', 'xo-security' ) );
		return $actions;
	}

	// 結果フィルター ドロップ ダウンを表示します。
	function status_dropdown() {
		$status = isset( $_GET['status'] ) ? $_GET['status'] : '';
		echo '<label for="filter-by-status" class="screen-reader-text">' . __( 'Filter by results', 'xo-security' ) . '</label>';
		echo '<select name="status" id="filter-by-status">';
		echo '<option' . selected( $status, '', false ) . ' value="">' . __( 'All results', 'xo-security' ) . '</option>';
		printf( "<option %s value='%s'>%s</option>\n", selected( $status, '0', false ), '0', __( 'Failure', 'xo-security' ) );
		printf( "<option %s value='%s'>%s</option>\n", selected( $status, '1', false ), '1', __( 'Success', 'xo-security' ) );
		echo '</select>' . "\n";
	}

	// ユーザー フィルター ドロップ ダウンを表示します。
	function users_dropdown() {
		global $current_user;

		if ( $current_user->has_cap( 'administrator' ) ) {
			// 管理者
			$users = get_users();
			$user_login = isset( $_GET['u'] ) ? $_GET['u'] : '';
			echo '<label for="filter-by-user" class="screen-reader-text">' . __( 'Filter by user', 'xo-security' ). '</label>';
			echo '<select name="u" id="filter-by-user">';
			echo '<option' . selected( $user_login, '', false ) . ' value="">' . __( 'All users', 'xo-security' ) . '</option>';
			foreach ( $users as $user ) {
				printf( "<option %s value='%s'>%s</option>\n", selected( $user_login, $user->user_login, false ), esc_html( $user->user_login ), esc_html( $user->user_login ) );
			}
			echo '</select>' . "\n";
		} else {
			// 管理者以外
			echo '<label for="filter-by-user" class="screen-reader-text">' . __( 'Filter by user', 'xo-security' ). '</label>';
			echo '<select name="u" id="filter-by-user">';
			printf( '<option selected="selected" value="%s">%s</option>', esc_html( $current_user->user_login ), esc_html( $current_user->user_login ) );
			echo '</select>' . "\n";
		}
	}

	function extra_tablenav( $which ) {
		echo '<div class="alignleft actions">';
		if ( 'top' == $which && !is_singular() ) {
			$this->status_dropdown();
			$this->users_dropdown();
			submit_button( __( 'Filter', 'xo-security' ), 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
		}
		echo '</div>';
	}

	function process_bulk_action() {
		global $wpdb;

		$loginlog_table = $wpdb->base_prefix . XO_SECURITY_LOGINLOG_TABLE_NAME;
		if ( 'delete' === $this->current_action() ) {
			$ids = isset( $_GET['loginlog'] ) ? $_GET['loginlog'] : false;
			if ( !is_array( $ids ) ) {
				$ids = array( $ids );
			}
			$ids = implode( ',', $ids );
			if ( !empty( $ids ) ) {
				$wpdb->query( "DELETE FROM `{$loginlog_table}` WHERE id IN($ids);" );
			}
		}
	}

	function prepare_items() {
		global $wpdb;
		global $current_user;

		$loginlog_table = $wpdb->base_prefix . XO_SECURITY_LOGINLOG_TABLE_NAME;
		$per_page = $this->get_items_per_page( 'login_log_per_page', 100 );
		$status = isset( $_GET['status'] ) ? $_GET['status'] : '';

		if ( $current_user->has_cap( 'administrator' ) ) {
			$user_login = isset( $_GET['u'] ) ? $_GET['u'] : '';
		} else {
			$user_login = $current_user->user_login;
		}

		list( $columns, $hidden ) = $this->get_column_info();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		// WHERE 句
		$status = esc_sql( $status );
		$user_login = esc_sql( $user_login );
		$where = ($status == '') ? '' : "success={$status}";
		$where .= ($user_login == '') ? '' : (($where == '') ? '' : " AND ") . "user_name='{$user_login}'";
		$where = ($where == '') ? '' : ' WHERE ' . $where;

		// 全データ件数を取得する
		$total_items = intval( $wpdb->get_var( "SELECT count(*) FROM `{$loginlog_table}`{$where}" ) );

		// 1ページ分のデータを取得する
		$orderby = (!empty( $_REQUEST['orderby'] )) ? $_REQUEST['orderby'] : 'logintime';
		$order = (!empty( $_REQUEST['order'] )) ? $_REQUEST['order'] : 'desc';
		$current_page = $this->get_pagenum();
		$start = ($current_page - 1) * $per_page;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT `id`,`success`,`login_time` AS `logintime`,`ip_address` AS `ipaddress`,`lang`,`user_agent` AS `useragent`,`user_name` AS `username`,`failed_password` AS `failedpassword`" .
			"FROM {$loginlog_table}{$where} ORDER BY `{$orderby}` {$order} LIMIT %d, %d;", $start, $per_page
		), 'ARRAY_A' );
		foreach($rows as $row) {
			$item = array(
				'id' => $row['id'],
				'success' => $row['success'],
				'login_time' => $row['logintime'],
				'ip_address' => $row['ipaddress'],
				'lang' => $row['lang'],
				'user_agent' => $row['useragent'],
				'user_name' => $row['username'],
				'failed_password' => esc_html( $row['failedpassword'] )
			);
			$this->items[] = $item;
		}

		$this->set_pagination_args( array( 'total_items' => $total_items, 'per_page' => $per_page, 'total_pages' => ceil( $total_items / $per_page ) ) );
	}
}

class XO_Security_Admin {
	private $list_table;
	private $loginlog_table;
	private $options;
	private $loginfile_content = "<?php require_once './wp-login.php'; ?>\n";

	function __construct() {
		global $wpdb;

		$this->loginlog_table = $wpdb->base_prefix . XO_SECURITY_LOGINLOG_TABLE_NAME;
		$this->options = get_option( 'xo_security_options' );

		add_action( 'plugins_loaded', array( $this, 'setup' ), 99999 );
	}

	function setup() {
		add_action( 'admin_bar_init', array( $this, 'admin_bar_init' ), 9999 );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'option_page_init' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_setup' ) );
	}

	function author_rewrite_rules() {
		return array();
	}

	function admin_bar_init() {
		wp_enqueue_style( 'xo-security-admin', plugins_url( 'admin.css', __FILE__ ), false, XO_SECURITY_VERSION );
	}

	// 管理画面メニューを追加します。
	function add_admin_menu() {
		$login_log_page = add_submenu_page( 'profile.php', __( 'Login log', 'xo-security' ), __( 'Login log', 'xo-security' ), 'level_2', 'xo-security-login-log', array( $this, 'login_log_page' ) );
		add_action( "load-{$login_log_page}", array( $this, 'add_login_log_page_tabs' ) );

		$settings_page = add_options_page( 'XO Security', 'XO Security', 'manage_options', 'xo-security-settings', array( $this, 'option_page' ) );
		add_action( "load-{$settings_page}", array( $this, 'add_settings_page_tabs' ) );
		add_filter( "admin_head-{$settings_page}", array( $this, 'options_page_add_js' ) );
	}

	// ログインログ ページにタブを追加します。
	function add_login_log_page_tabs() {
		// オプション タブ
		add_screen_option( 'per_page', array( 'label' => __( 'Number of items per page:', 'xo-security' ), 'default' => 100, 'option' => 'login_log_per_page' ) );

		// ヘルプ タブ
		$screen = get_current_screen();
		$screen->add_help_tab( array(
			'id' => 'loginlogs-help',
			'title' => __( 'Overview', 'xo-security' ),
			'content' =>
				'<p>' . __( 'View the log of login.', 'xo-security' ). '</p>'. 
				'<p>' . __( 'If the user name is the same as an existing user, the password is not displayed (not recorded in the log itself).', 'xo-security' ) . '</p>',
		) );

		$this->list_table = new XO_Login_Log_List_Table();
	}

	// 設定画面にタブを追加します。
	function add_settings_page_tabs() {
		$screen = get_current_screen();
		$screen->add_help_tab( array(
			'id' => 'overview',
			'title' => __( 'Overview', 'xo-security' ),
			'content' =>
				'<p>' . __( 'XO Security setup.', 'xo-security' ) . '</p>' .
				'<p>' . __( 'Limit of records and login attempts of login log, change the login page, disable such as XML-RPC, it makes the security-related settings.', 'xo-security' ) . '</p>'
		) );
		$screen->add_help_tab( array(
			'id' => 'login-config',
			'title' => __( 'Login', 'xo-security' ),
			'content' =>
				'<p>' . __( 'Make the settings for the login.', 'xo-security' ) . '</p>' .
				'<p>' . __( 'Number of trials restriction limits the number of times that you can try from the same IP address during a specified time. From was over a specified number of IP address you will not be able to log in.', 'xo-security' ) . '</p>' .
				'<p>' . __( 'Login page, Change the name of the login page.', 'xo-security' ) . '</p>' .
				'<p>' . __( 'Login alert, Send an e-mail when login.', 'xo-security' ) . '</p>' .
				'<p>' . __( 'Automatic deletion of login log automatically deletes the old log in the log of the previous period specified.', 'xo-security' ) . '</p>'
		) );
		$screen->add_help_tab( array(
			'id' => 'xml-rpc',
			'title' => __( 'XML-RPC', 'xo-security' ),
			'content' =>
				'<p>' . __( 'Disable XML-RPC, XML-RPC functionality disabled. This setting does not disable XML-RPC pingback.', 'xo-security' ) . '</p>' .
				'<p>' . __( 'Disabling pingback XML-RPC, XML-RPC pingback functionality disabled.', 'xo-security' ) . '</p>'
		) );
		$screen->add_help_tab( array(
			'id' => 'rest-api',
			'title' => __( 'REST API', 'xo-security' ),
			'content' =>
				'<p>' . __( 'Disable REST API, REST API functionality disabled.', 'xo-security' ) . '</p>' .
				'<p>' . __( 'REST API URL prefix, Change the prefix for the REST API URL.', 'xo-security' ) . '</p>'
		) );
		$screen->add_help_tab( array(
			'id' => 'secret',
			'title' => __( 'Secret', 'xo-security' ),
			'content' =>
				'<p>' . __( 'Disabling author archives the author archive page does not appear. Avoid it this URL of the author archive page gets in a login name.', 'xo-security' ) . '</p>' .
				'<p>' . __( 'Disable comment author name class removes the comment author name class added to the comments list "comment-author-xxx" (where xxx is the login name). Protects against it to obtain the login name.', 'xo-security' ) . '</p>'
		) );
	}

	// ログインログ ページのオプション設定時の処理です。
	function set_screen_option( $status, $option, $value ) {
		if ( 'login_log_per_page' == $option ) {
			$new_value = (int) $value;
			if ( $new_value >= 1 && $new_value <= 999 ) {
				return $new_value;
			}
			return $status;
		}
	}

	// ログインログ ページを表示します。
	function login_log_page() {
		$this->list_table->prepare_items();
	?>
		<div class="wrap">
			<div id="icon-profile" class="icon32"></div>
			<h1><?php _e( 'Login log', 'xo-security' ); ?></h1>
			<form id="loginlogs-filter" method="get" action="">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
				<?php $this->list_table->display(); ?>
			</form>
		</div>
	<?php
	}

	// セキュリティ設定ページ用のスクリプトを追加します。
	function options_page_add_js() {
?>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		var
			site_url = '<?php echo site_url('/'); ?>',
			field_interval = $('#field_interval'),
			field_login_page = $('#field_login_page'),
			field_login_page_name = $('#field_login_page_name'),
			field_rest_rename = $('#field_rest_rename'),
			field_rest_name = $('#field_rest_name'),
			interval_change = function () {
				if ('0' === field_interval.val()) {
					$('#field_limit_count').attr("disabled", "disabled");
				} else {
					$('#field_limit_count').removeAttr("disabled");
				}
			},
			login_page_change = function() {
				if (field_login_page.prop("checked")) {
					field_login_page_name.removeAttr("disabled");
				} else {
					field_login_page_name.attr("disabled", "disabled");
				}
				loginurl_update();
			},
			loginurl_update = function() {
				if (field_login_page.prop("checked")) {
					var login_url = field_login_page_name.val();
					if (login_url !== '') {
						login_url = login_url.toLowerCase().replace(/[^\w-]/g, '');
						field_login_page_name.val(login_url);
						$('#login_url').text(site_url + login_url + '.php');
					} else {
						$('#login_url').text('');
					}
				} else {
					$('#login_url').text(site_url + 'wp-login.php');
				}
			},
			rest_rename_change = function() {
				if (field_rest_rename.prop("checked")) {
					field_rest_name.removeAttr("disabled");
				} else {
					field_rest_name.attr("disabled", "disabled");
				}
				rest_name_update();
			},
			rest_name_update = function() {
				var rest_name = field_rest_name.val();
				if (rest_name !== undefined) {
					rest_name = rest_name.toLowerCase().replace(/[^\w-]/g, '');
					field_rest_name.val(rest_name);
				}
			};
		field_interval.change(interval_change);
		field_login_page.change(login_page_change);
		field_login_page_name.change(loginurl_update);
		field_rest_rename.change(rest_rename_change);
		field_rest_name.change(rest_name_update);
		interval_change();
		login_page_change();
		rest_rename_change();
	});
</script>
<?php
	}

	private function get_default_options() {
		$default_options = array(
			'interval' => '0',
			'limit_count' => '4',
			'blocked_tarpit' => '10',
			'failed_tarpit' => '1',
			'login_page' => '',
			'auto_truncate' => '356',
			'xmlrpc' => false,
			'pingback' => false,
			'rest' => false,
			'rest_rename' => false,
			'rest_name' => '',
			'author_archive' => false,
			'comment_author_class' => false,
		);
		return $default_options;
	}

	function option_page() {
		$this->options = get_option( 'xo_security_options' );
		if ( $this->options === false ) {
			$this->options = $this->get_default_options();
		}

		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'status-page';

		echo '<div class="wrap">';
		echo '<h1>' . __( 'XO Security Settings', 'xo-security' ) . '</h1>';
		echo '<h2 class="nav-tab-wrapper">';
		echo '<a href="?page=xo-security-settings&amp;tab=status-page" class="nav-tab ' . ( $active_tab == 'status-page' ? 'nav-tab-active' : '' ) . '">' . __( 'Status', 'xo-security' ) . '</a>';
		echo '<a href="?page=xo-security-settings&amp;tab=login-page" class="nav-tab ' . ( $active_tab == 'login-page' ? 'nav-tab-active' : '' ) . '">' . __( 'Login', 'xo-security' ) . '</a>';
		echo '<a href="?page=xo-security-settings&amp;tab=xmlrpc-page" class="nav-tab ' . ( $active_tab == 'xmlrpc-page' ? 'nav-tab-active' : '' ) . '">' . __( 'XML-RPC', 'xo-security' ) . '</a>';
		echo '<a href="?page=xo-security-settings&amp;tab=restapi-page" class="nav-tab ' . ( $active_tab == 'restapi-page' ? 'nav-tab-active' : '' ) . '">' . __( 'REST API', 'xo-security' ) . '</a>';
		echo '<a href="?page=xo-security-settings&amp;tab=secret-page" class="nav-tab ' . ( $active_tab == 'secret-page' ? 'nav-tab-active' : '' ) . '">' . __( 'Secret', 'xo-security' ) . '</a>';
		echo '</h2>';
		echo '<form method="post" action="options.php">';

		switch ( $active_tab ){
			case 'status-page':
				$this->status_page();
				break;
			case 'login-page':
				settings_fields( 'xo_security_login' );
				do_settings_sections( 'xo_security_login' );
				submit_button();
				break;
			case 'xmlrpc-page':
				settings_fields( 'xo_security_xmlrpc' );
				do_settings_sections( 'xo_security_xmlrpc' );
				submit_button();
				break;
			case 'restapi-page':
				settings_fields( 'xo_security_restapi' );
				do_settings_sections( 'xo_security_restapi' );
				submit_button();
				break;
			case 'secret-page':
				settings_fields( 'xo_security_secret' );
				do_settings_sections( 'xo_security_secret' );
				submit_button();
				break;
		}

		echo '</form>';
		echo '</div>';
	}

	function status_page() {
		$interval = isset( $this->options['interval'] ) ? $this->options['interval'] : '0';
		$login_alert = isset( $this->options['login_alert'] ) ? $this->options['login_alert'] : false;
		$login_page = isset( $this->options['login_page'] ) ? $this->options['login_page'] : false;
		$xmlrpc = isset( $this->options['xmlrpc'] ) ? $this->options['xmlrpc'] : false;
		$pingback = isset( $this->options['pingback'] ) ? $this->options['pingback'] : false;
		$rest = isset( $this->options['rest'] ) ? $this->options['rest'] : false;
		$rest_rename = isset( $this->options['rest_rename'] ) ? $this->options['rest_rename'] : false;
		$author_archive = isset( $this->options['author_archive'] ) ? $this->options['author_archive'] : false;
		$comment_author_class = isset( $this->options['comment_author_class'] ) ? $this->options['comment_author_class'] : false;
		?>
		<h3 class="label"><?php _e( 'Setting status', 'xo-security' ); ?></h3>
		<table class="xo-security-form-table">
			<thead>
				<tr>
					<td class="status-check"></td>
					<th class="status-title"><?php _e( 'Feature', 'xo-security' ); ?></th>
					<th class="status-description"><?php _e( 'Description', 'xo-security' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<th scope="row" class="status-check"><span class="check-on"></span></th>
					<td class="status-title"><?php _e( 'Record login', 'xo-security' ); ?></td>
					<td class="status-description"><?php _e( 'Record the login log.', 'xo-security' ); ?></td>
				</tr>
				<tr>
					<th scope="row" class="status-check"><span class="<?php echo ( $interval != '0' ? 'check-on' : 'check-off' ); ?>"></span></th>
					<td class="status-title"><?php _e( 'Infinite login attempts', 'xo-security' ); ?></td>
					<td class="status-description"><?php _e( 'Block connections that repeat login failures.', 'xo-security' ); ?></td>
				</tr>
				<tr>
					<th scope="row" class="status-check"><span class="<?php echo ( $login_page ? 'check-on' : 'check-off' ); ?>"></span></th>
					<td class="status-title"><?php _e( 'Modify login page', 'xo-security' ); ?></td>
					<td class="status-description"><?php _e( 'Change the name of the login page.', 'xo-security' ); ?></td>
				</tr>
				<tr>
					<th scope="row" class="status-check"><span class="<?php echo ( $login_alert ? 'check-on' : 'check-off' ); ?>"></span></th>
					<td class="status-title"><?php _e( 'Login Alert', 'xo-security' ); ?></td>
					<td class="status-description"><?php _e( 'Send a mail when you log in.', 'xo-security' ); ?></td>
				</tr>
			<?php if ( defined( 'XO_SECURITY_LANGUAGE_WHITE_LIST' ) && XO_SECURITY_LANGUAGE_WHITE_LIST !== '' ): ?>
				<tr>
					<th scope="row" class="status-check"><span class="check-on"></span></th>
					<td class="status-title"><?php _e( 'Login language Restrictions', 'xo-security' ); ?></td>
					<td class="status-description"><?php _e( 'Limit the languages that can be logged in.', 'xo-security' ); ?></td>
				</tr>
			<?php endif; ?>
				<tr>
					<th scope="row" class="status-check"><span class="<?php echo ( $xmlrpc ? 'check-on' : 'check-off' ); ?>"></span></th>
					<td class="status-title"><?php _e( 'Disable XML-RPC', 'xo-security' ); ?></td>
					<td class="status-description"><?php _e( 'Disable the XML-RPC.', 'xo-security' ); ?></td>
				</tr>
				<tr>
					<th scope="row" class="status-check"><span class="<?php echo ( $pingback ? 'check-on' : 'check-off' ); ?>"></span></th>
					<td class="status-title"><?php _e( 'Disable XML-RPC Pinback', 'xo-security' ); ?></td>
					<td class="status-description"><?php _e( 'Disable XML-RPC pingback features.', 'xo-security' ); ?></td>
				</tr>
				<tr>
					<th scope="row" class="status-check"><span class="<?php echo ( $rest ? 'check-on' : 'check-off' ); ?>"></span></th>
					<td class="status-title"><?php _e( 'Disable REST API', 'xo-security' ); ?></td>
					<td class="status-description"><?php _e( 'Disable the REST API.', 'xo-security' ); ?></td>
				</tr>
				<tr>
					<th scope="row" class="status-check"><span class="<?php echo ( $rest_rename ? 'check-on' : 'check-off' ); ?>"></span></th>
					<td class="status-title"><?php _e( 'Change REST API URL prefix', 'xo-security' ); ?></td>
					<td class="status-description"><?php _e( 'Change the prefix for the REST API URL.', 'xo-security' ); ?></td>
				</tr>
				<tr>
					<th scope="row" class="status-check"><span class="<?php echo ( $author_archive ? 'check-on' : 'check-off' ); ?>"></span></th>
					<td class="status-title"><?php _e( 'Disable author archives', 'xo-security' ); ?></td>
					<td class="status-description"><?php _e( 'Author archive page does not appear. Avoid it this URL of the author archive page gets in a login name.', 'xo-security' ); ?></td>
				</tr>
				<tr>
					<th scope="row" class="status-check"><span class="<?php echo ( $comment_author_class ? 'check-on' : 'check-off' ); ?>"></span></th>
					<td class="status-title"><?php _e( 'Disable comment author name class', 'xo-security' ); ?></td>
					<td class="status-description"><?php _e( 'Remove the comment author name class added to the comments list. Protects against it to obtain the login name.', 'xo-security' ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	function option_page_init() {
		if ( delete_transient( 'xo_security_flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		register_setting( 'xo_security_login', 'xo_security_options', array( $this, 'login_sanitize' ) );
		add_settings_section( 'xo_security_login_section', '', '__return_false', 'xo_security_login' );
		add_settings_field( 'limit_count', __( 'Infinite attempts', 'xo-security' ), array( $this, 'option_page_field_limit_count' ), 'xo_security_login', 'xo_security_login_section' );
		add_settings_field( 'blocked_tarpit', __( 'Block time delay', 'xo-security' ), array( $this, 'option_page_field_blocked_tarpit' ), 'xo_security_login', 'xo_security_login_section' );
		add_settings_field( 'failed_tarpit', __( 'Failure time delay', 'xo-security' ), array( $this, 'option_page_field_failed_tarpit' ), 'xo_security_login', 'xo_security_login_section' );
		add_settings_field( 'login_page', __( 'Modify login page', 'xo-security' ), array( $this, 'option_page_field_login_page' ), 'xo_security_login', 'xo_security_login_section' );
		add_settings_field( 'login_alert', __( 'Login Alert', 'xo-security' ), array( $this, 'option_page_field_login_alert' ), 'xo_security_login', 'xo_security_login_section' );
		add_settings_field( 'auto_truncate', __( 'Automatic removal of log', 'xo-security' ), array( $this, 'option_page_field_auto_truncate' ), 'xo_security_login', 'xo_security_login_section' );

		register_setting( 'xo_security_xmlrpc', 'xo_security_options', array( $this, 'xmlrpc_sanitize' ) );
		add_settings_section( 'xo_security_xmlrpc_section', '', '__return_false', 'xo_security_xmlrpc' );
		add_settings_field( 'xmlrpc', __( 'Disable XML-RPC', 'xo-security' ), array( $this, 'option_page_field_xmlrpc' ), 'xo_security_xmlrpc', 'xo_security_xmlrpc_section' );
		add_settings_field( 'pingback', __( 'Disable XML-RPC Pinback', 'xo-security' ), array( $this, 'option_page_field_pingback' ), 'xo_security_xmlrpc', 'xo_security_xmlrpc_section' );

		register_setting( 'xo_security_restapi', 'xo_security_options', array( $this, 'restapi_sanitize' ) );
		add_settings_section( 'xo_security_rest_section', '', '__return_false', 'xo_security_restapi' );
		add_settings_field( 'rest', __( 'Disable REST API', 'xo-security' ), array( $this, 'option_page_field_rest' ), 'xo_security_restapi', 'xo_security_rest_section' );
		add_settings_field( 'rest_rename', __( 'Change REST API URL prefix', 'xo-security' ), array( $this, 'option_page_field_rest_rename' ), 'xo_security_restapi', 'xo_security_rest_section' );

		register_setting( 'xo_security_secret', 'xo_security_options', array( $this, 'secret_sanitize' ) );
		add_settings_section( 'xo_security_security_section', '', '__return_false', 'xo_security_secret' );
		add_settings_field( 'author_archive', __( 'Disable author archives', 'xo-security' ), array( $this, 'option_page_field_author_archive' ), 'xo_security_secret', 'xo_security_security_section' );
		add_settings_field( 'comment_author_class', __( 'Disable comment author name class', 'xo-security' ), array( $this, 'option_page_field_comment_author_class' ), 'xo_security_secret', 'xo_security_security_section' );
	}

	function option_page_field_limit_count() {
		$interval = isset( $this->options['interval'] ) ? $this->options['interval'] : '0';
		echo '<select id="field_interval" name="xo_security_options[interval]">';
		echo '<option value="0"' . ($interval == '0' ? ' selected' : '') . '>' . __( 'No limit', 'xo-security' ) . '</option>';
		echo '<option value="1"' . ($interval == '1' ? ' selected' : '') . '>' . __( 'During the one-hour', 'xo-security' ) . '</option>';
		echo '<option value="12"' . ($interval == '12' ? ' selected' : '') . '>' . __( 'During the 12-hour', 'xo-security' ) . '</option>';
		echo '<option value="24"' . ($interval == '24' ? ' selected' : '') . '>' . __( 'During the 24-hour', 'xo-security' ) . '</option>';
		echo '<option value="48"' . ($interval == '48' ? ' selected' : '') . '>' . __( 'During the 48-hour', 'xo-security' ) . '</option>';
		echo '</select>';

		$limit_count = isset( $this->options['limit_count'] ) ? $this->options['limit_count'] : '4';
		echo '<p><input id="field_limit_count" name="xo_security_options[limit_count]" type="number" value="' . $limit_count . '" class="small-text" /> ' . __( 'times permission', 'xo-security' ) . '</p>';
	}

	function option_page_field_blocked_tarpit() {
		$blocked_tarpit = isset( $this->options['blocked_tarpit'] ) ? $this->options['blocked_tarpit'] : '30';
		echo '<p><input id="field_blocked_tarpit" name="xo_security_options[blocked_tarpit]" type="number" value="' . $blocked_tarpit . '" class="small-text" /> ' . __( 'sec (0-120)', 'xo-security' ) . '</p>';
	}

	function option_page_field_failed_tarpit() {
		$failed_tarpit = isset( $this->options['failed_tarpit'] ) ? $this->options['failed_tarpit'] : '3';
		echo '<p><input id="field_failed_tarpit" name="xo_security_options[failed_tarpit]" type="number" value="' . $failed_tarpit . '" class="small-text" /> ' . __( 'sec (0-10)', 'xo-security' ) . '</p>';
	}

	function option_page_field_login_alert() {
		$login_alert = isset( $this->options['login_alert'] ) ? $this->options['login_alert'] : false;
		$login_alert_admin_only = isset( $this->options['login_alert_admin_only'] ) ? $this->options['login_alert_admin_only'] : false;
		echo '<label for="field_login_alert"><input id="field_login_alert" name="xo_security_options[login_alert]" type="checkbox" value="1" class="code" ' . checked( 1, $login_alert, false ) . ' /> ' . __( 'ON', 'xo-security' ) . '</label>';
		$subject = isset( $this->options['login_alert_subject'] ) ? $this->options['login_alert_subject'] : __( 'Login at %SITENAME% site', 'xo-security' );
		echo '<p><label for="field_login_alert_subject">'.__( 'Subject:', 'xo-security' ).'</label><br /><input id="field_login_alert_subject" name="xo_security_options[login_alert_subject]" type="text" value="' . $subject . '" class="regular-text" style="display: inline;" maxlength="100" /></p>';
		$body = isset( $this->options['login_alert_body'] ) ? $this->options['login_alert_body'] : __( '%USERNAME% logged in at %DATE% %TIME%', 'xo-security' );
		echo '<p><label for="field_login_alert_body">'.__( 'Body:', 'xo-security' ).'</label><br /><textarea id="field_login_alert_body" name="xo_security_options[login_alert_body]" class="large-text code" rows="3" col="50" />' . $body . '</textarea></p>';
		echo '<p>' . __( 'In the Subject and Body, the following variables can be used: %SITENAME%, %USERNAME%, %DATE%, %TIME%, %IPADDRESS%, %USERAGENT%', 'xo-security' ) . '</p>';
		echo '<p><label for="field_login_alert_admin_only"><input id="field_login_alert_admin_only" name="xo_security_options[login_alert_admin_only]" type="checkbox" value="1" class="code" ' . checked( 1, $login_alert_admin_only, false ) . ' /> ' . __( 'Administrators only', 'xo-security' ) . '</label></p>';
	}

	function option_page_field_auto_truncate() {
		$auto_truncate = isset( $this->options['auto_truncate'] ) ? $this->options['auto_truncate'] : '0';
		echo '<select id="field_auto_truncate" name="xo_security_options[auto_truncate]">';
		echo '<option value="0"' . ($auto_truncate == '0' ? ' selected' : '') . '>' . __( 'Not automatic deletion', 'xo-security' ) . '</option>';
		echo '<option value="30"' . ($auto_truncate == '30' ? ' selected' : '') . '>' . __( 'Older than 30 days', 'xo-security' ) . '</option>';
		echo '<option value="356"' . ($auto_truncate == '356' ? ' selected' : '') . '>' . __( 'Older than 356 days', 'xo-security' ) . '</option>';
		echo '</select>';
	}

	function option_page_field_login_page() {
		$c = isset( $this->options['login_page'] ) ? $this->options['login_page'] : false;
		echo '<label for="field_login_page"><input id="field_login_page" name="xo_security_options[login_page]" type="checkbox" value="1" class="code" ' . checked( 1, $c, false ) . ' /> ' . __( 'ON', 'xo-security' ) . '</label>';
		$name = isset( $this->options['login_page_name'] ) ? $this->options['login_page_name'] : '';
		echo '<p><label for="field_login_page_name">'.__( 'Login file:', 'xo-security' ).' <input id="field_login_page_name" name="xo_security_options[login_page_name]" type="text" value="' . $name . '" class="regular-text" style="max-width:15em; display: inline;" maxlength="40" /></label>.php</p>';
		echo '<p>URL: <span id="login_url"></span></p>';
		echo '<p>' . __( 'Characters can be used in the login file is only lowercase letters, numbers, hyphens and underscores.', 'xo-security' ) . '</p>';
	}

	function option_page_field_xmlrpc() {
		$xmlrpc = isset( $this->options['xmlrpc'] ) ? $this->options['xmlrpc'] : false;
		echo '<label for="field_xmlrpc"><input id="field_xmlrpc" name="xo_security_options[xmlrpc]" type="checkbox" value="1" class="code" ' . checked( 1, $xmlrpc, false ) . ' /> ' . __( 'ON', 'xo-security' ) . '</label>';
	}

	function option_page_field_pingback() {
		$pingback = isset( $this->options['pingback'] ) ? $this->options['pingback'] : false;
		echo '<label for="field_pingback"><input id="field_pingback" name="xo_security_options[pingback]" type="checkbox" value="1" class="code" ' . checked( 1, $pingback, false ) . ' /> ' . __( 'ON', 'xo-security' ) . '</label>';
	}

	function option_page_field_rest() {
		$c = isset( $this->options['rest'] ) ? $this->options['rest'] : false;
		echo '<label for="field_rest"><input id="field_rest" name="xo_security_options[rest]" type="checkbox" value="1" class="code" ' . checked( 1, $c, false ) . ' /> ' . __( 'ON', 'xo-security' ) . '</label>';
	}

	function option_page_field_rest_rename() {
		$c = isset( $this->options['rest_rename'] ) ? $this->options['rest_rename'] : false;
		echo '<label for="field_rest_rename"><input id="field_rest_rename" name="xo_security_options[rest_rename]" type="checkbox" value="1" class="code" ' . checked( 1, $c, false ) . ' /> ' . __( 'ON', 'xo-security' ) . '</label>';
		$name = !empty( $this->options['rest_name'] ) ? $this->options['rest_name'] : rest_get_url_prefix();
		echo '<p><label for="field_rest_name">' . __( 'Prefix:', 'xo-security' ) . ' <input id="field_rest_name" name="xo_security_options[rest_name]" type="text" value="' . $name . '" class="regular-text" style="max-width:15em; display: inline;" maxlength="40" /></label></p>';
		echo '<p>' . __( 'Characters can be used in the prefix is only lowercase letters, numbers, hyphens and underscores.', 'xo-security' ) . '</p>';
	}

	function option_page_field_author_archive() {
		$author_archive = isset( $this->options['author_archive'] ) ? $this->options['author_archive'] : false;
		echo '<label for="field_author_archive"><input id="field_author_archive" name="xo_security_options[author_archive]" type="checkbox" value="1" class="code" ' . checked( 1, $author_archive, false ) . ' /> ' . __( 'ON', 'xo-security' ) . '</label>';
	}

	function option_page_field_comment_author_class() {
		$comment_author_class = isset( $this->options['comment_author_class'] ) ? $this->options['comment_author_class'] : false;
		echo '<label for="field_comment_author_class"><input id="field_comment_author_class" name="xo_security_options[comment_author_class]" type="checkbox" value="1" class="code" ' . checked( 1, $comment_author_class, false ) . ' /> ' . __( 'ON', 'xo-security' ) . '</label>';
	}

	function login_sanitize( $input ) {
		global $wpdb, $option_page;
		
		if ( $option_page !== 'xo_security_login' )
			return $input;
		
		$output = get_option( 'xo_security_options' );

		$input['interval'] = isset( $input['interval'] ) ? intval( $input['interval'] ) : 0;
		if ( $input['interval'] > 0 ) {
			$input['limit_count'] = isset( $input['limit_count'] ) ? intval( $input['limit_count'] ) : 4;
			if ( 0 >= $input['limit_count'] || $input['limit_count'] > 100 ) {
				add_settings_error( 'xo_security', 'limit_count', __( 'Attempts to limit the number of times enter numbers from 1 to 100.', 'xo-security' ) );
			}
		}

		$input['blocked_tarpit'] = isset( $input['blocked_tarpit'] ) ? intval( $input['blocked_tarpit'] ) : 30;
		if ( 0 > $input['blocked_tarpit'] || $input['blocked_tarpit'] > 120 ) {
			add_settings_error( 'xo_security', 'blocked_tarpit', __( 'In the numbers from 0 to 120 block when the response delay.', 'xo-security' ) );
		}

		$input['failed_tarpit'] = isset( $input['failed_tarpit'] ) ? intval( $input['failed_tarpit'] ) : 3;
		if ( 0 > $input['failed_tarpit'] || $input['failed_tarpit'] > 10 ) {
			add_settings_error( 'xo_security', 'failed_tarpit', __( 'Failure response delay enter in the numbers from 0 to 10.', 'xo-security' ) );
		}

		$input['login_page'] = isset( $input['login_page'] ) ? $input['login_page'] : false;
		$input['login_page_name'] = isset( $input['login_page_name'] ) ? $input['login_page_name'] : '';
		if ( ( $input['login_page'] !== $output['login_page'] ) || ( $input['login_page_name'] !== $output['login_page_name'] ) ) {
			$url = wp_nonce_url( 'options-general.php?page=xo-security-settings', 'xo-security-settings' );
			if ( false === ( $creds = request_filesystem_credentials( $url, '', false, false, null ) ) ) {
				add_settings_error( 'xo_security', 'file', __( 'Login file cannot be created.', 'xo-security' ) );
			} else {
				if ( !WP_Filesystem( $creds ) ) {
					add_settings_error( 'xo_security', 'file', __( 'Unable to write.', 'xo-security' ) );
				} else {
					global $wp_filesystem;
					if ( $output['login_page_name'] ) {
						$old_path = ABSPATH . $output['login_page_name'] . '.php';
						if ( $wp_filesystem->exists( $old_path ) ) {
							$wp_filesystem->delete( $old_path );
						}
					}
					if ( $input['login_page'] ) {
						if ( $input['login_page_name'] ) {
							$new_path = ABSPATH . $input['login_page_name'] . '.php';
							if ( $wp_filesystem->exists( $new_path ) ) {
								$input['login_page'] = $output['login_page'];
								$input['login_page_name'] = $output['login_page_name'];
								add_settings_error( 'xo_security', 'login_page_name', __( 'The file specified in the login file already exists. Please enter a different name.', 'xo-security' ) );
							} else {
								if ( !$wp_filesystem->put_contents( $new_path, stripslashes( $this->loginfile_content ), FS_CHMOD_FILE ) ) {
									$input['login_page'] = $output['login_page'];
									$input['login_page_name'] = $output['login_page_name'];
									add_settings_error( 'xo_security', 'file', __( 'Failed to create a login file.', 'xo-security' ) );
								}
							}
						} else {
							$input['login_page'] = $output['login_page'];
							$input['login_page_name'] = $output['login_page_name'];
							add_settings_error( 'xo_security', 'login_page_name', __( 'It is not possible to omit the login file.', 'xo-security' ) );
						}
					} else {
						$input['login_page_name'] = '';
					}
				}
			}
		}

		if ( $input['auto_truncate'] > 0 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$this->loginlog_table}` WHERE `login_time` <= DATE_SUB(NOW(), INTERVAL %d day)", $input['auto_truncate'] ) );
		}
		
		$output['interval'] = $input['interval'];
		$output['limit_count'] = $input['limit_count'];
		$output['blocked_tarpit'] = $input['blocked_tarpit'];
		$output['failed_tarpit'] = $input['failed_tarpit'];
		$output['login_page'] = $input['login_page'];
		$output['login_page_name'] = $input['login_page_name'];
		$output['login_alert'] = isset( $input['login_alert'] ) ? $input['login_alert'] : false;
		$output['login_alert_subject'] = $input['login_alert_subject'];
		$output['login_alert_body'] = $input['login_alert_body'];
		$output['login_alert_admin_only'] = isset( $input['login_alert_admin_only'] ) ? $input['login_alert_admin_only'] : false;
		$output['auto_truncate'] = $input['auto_truncate'];

		return $output;
	}

	function xmlrpc_sanitize( $input ) {
		global $option_page;
		
		if ( $option_page !== 'xo_security_xmlrpc' )
			return $input;

		$output = get_option( 'xo_security_options' );

		$output['xmlrpc'] = isset( $input['xmlrpc'] ) ? $input['xmlrpc'] : false;
		$output['pingback'] = isset( $input['pingback'] ) ? $input['pingback'] : false;

		return $output;
	}

	function restapi_sanitize( $input ) {
		global $option_page;
		
		if ( $option_page !== 'xo_security_restapi' )
			return $input;

		$output = get_option( 'xo_security_options' );

		$input['rest_rename'] = isset( $input['rest_rename'] ) ? $input['rest_rename'] : false;
		$input['rest_name'] = isset( $input['rest_name'] ) ? $input['rest_name'] : '';
		if (
			( !isset( $output['rest_rename'] ) || $input['rest_rename'] !== $output['rest_rename'] ) ||
			( !isset( $output['rest_name'] ) || $input['rest_name'] !== $output['rest_name'] )
		) {
			set_transient( 'xo_security_flush_rewrite_rules', true, MINUTE_IN_SECONDS );
		}

		$output['rest'] = isset( $input['rest'] ) ? $input['rest'] : false;
		$output['rest_rename'] = $input['rest_rename'];
		$output['rest_name'] = $input['rest_name'];
		
		return $output;	
	}

	function secret_sanitize( $input ) {
		global $option_page;
		
		if ( $option_page !== 'xo_security_secret' )
			return $input;

		$output = get_option( 'xo_security_options' );

		$input['author_archive'] = isset( $input['author_archive'] ) ? $input['author_archive'] : false;
		if ( !isset( $output['author_archive'] ) || $input['author_archive'] !== $output['author_archive'] ) {
			if ( $input['author_archive'] ) {
				add_filter( 'author_rewrite_rules', array( $this, 'author_rewrite_rules' ) );
			} else {
				remove_filter( 'author_rewrite_rules', array( $this, 'author_rewrite_rules' ) );
			}
			set_transient( 'xo_security_flush_rewrite_rules', true, MINUTE_IN_SECONDS );
		}
		
		$output['author_archive'] = $input['author_archive'];
		$output['comment_author_class'] = isset( $input['comment_author_class'] ) ? $input['comment_author_class'] : false;

		return $output;
	}

	function dashboard_setup() {
		wp_add_dashboard_widget( 'xo_security_dashboard_login_widget', __( 'Login information', 'xo-security' ), array( $this, 'dashboard_login_widget' ) );
	}

	function dashboard_login_widget() {
		global $wpdb;
		global $current_user;

		$datetime_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$user_login = $current_user->get( 'user_login' );

		$current_date = $wpdb->get_var(
			"SELECT `login_time` FROM `{$this->loginlog_table}` WHERE `user_name`='{$user_login}' AND `success`=1 ORDER BY `login_time` DESC LIMIT 1;"
		);
		$last_date = $wpdb->get_var(
			"SELECT `login_time` FROM `{$this->loginlog_table}` WHERE `user_name`='{$user_login}' AND `success`=1 ORDER BY `login_time` DESC LIMIT 1,2;"
		);

		echo '<div class="login_widget">';
		echo '<ul>';
		echo '<li>' . __( 'Current login date', 'xo-security' ) . ': ' .  ( $current_date === null ? __( 'Unknown', 'xo-security' ) : mysql2date( $datetime_format, $current_date ) ) . '</li>';
		echo '<li>' . __( 'Last login date', 'xo-security' ) . ': ' .  ( $last_date === null ? __( 'Unknown', 'xo-security' ) : mysql2date( $datetime_format, $last_date ) ) . '</li>';
		echo '</ul>';
		echo '</div>' . "\n";

		if ( $current_user->has_cap( 'administrator' ) ) {
			$timestamp = current_time( 'timestamp' );
			$interval_hour = isset( $this->options['interval'] ) ? (int)$this->options['interval'] : 0;
			$limit_count = isset( $this->options['limit_count'] ) ? (int)$this->options['limit_count'] : 0;

			$blocking_count = 0;
			if ( $interval_hour > 0 && $limit_count !== 0 ) {
				$time = date( 'Y-m-d H:i:s', (int)$timestamp - ( $interval_hour * 60 * 60 ) );
				$blocking_count = $wpdb->get_var(
					"SELECT COUNT(`ip_address`) FROM (" .
					"SELECT `ip_address` FROM (" .
					"SELECT `ip_address` FROM `{$this->loginlog_table}` AS t2 WHERE {$limit_count}>=(" .
					"SELECT COUNT(*) FROM `{$this->loginlog_table}` AS t1 WHERE t1.`ip_address`=t2.`ip_address` AND t1.`id`>=t2.`id`" .
					") AND `success`=0 AND `login_time` > '{$time}' ORDER BY `ip_address`, `id` DESC".
					") AS t3 GROUP BY `ip_address` HAVING ((Count(*))>={$limit_count})" .
					") AS t4;"
				);
			}

			$hour = $interval_hour > 0 ? $interval_hour : 24;
			$time = date( 'Y-m-d H:i:s', (int)$timestamp - ( $hour * 60 * 60 ) );
			$failed_recent_count = $wpdb->get_var(
				"SELECT COUNT(`ip_address`) FROM `{$this->loginlog_table}` WHERE `success`=0 AND `login_time`>'{$time}';"
			);

			$first_date = $wpdb->get_var(
				"SELECT `login_time` FROM `{$this->loginlog_table}` ORDER BY `login_time` ASC LIMIT 1;"
			);
			if ( $first_date === null ) {
				$day = 0;
				$failed_count = 0;
			} else {
				$day = (int)(abs( $timestamp - strtotime( $first_date ) ) / ( 60 * 60 * 24 ) );
				$failed_count = $wpdb->get_var(
					"SELECT COUNT(`ip_address`) FROM `{$this->loginlog_table}` WHERE `success`=0;"
				);
			}

			echo '<div class="login_widget">';
			echo '<ul>';
			echo '<li>' . __( 'Login blocking count', 'xo-security' ) . ': ' .  $blocking_count . '</li>';
			echo '<li>' . sprintf( _n( 'Failed login count (%d hour)', 'Failed login count (%d hours)', $hour, 'xo-security' ), $hour ) . ': ' .  $failed_recent_count . '</li>';
			echo '<li>' . sprintf( _n( 'Failed login count (%d day)', 'Failed login count (%d days)', $day, 'xo-security' ), $day) . ': ' .  $failed_count . '</li>';
			echo '</ul>';
			echo '</div>' . "\n";
		}
	}
}
