<?php
/**
 * Plugin Name: JuanPay DPN
 * Plugin URI: https://github.com/juanpayph/juanpay-dpn
 * Description: JuanPay DPN listener.  Requires PHP5.
 * Version: 1.0.11
 * Author: JuanPay Development Team
 * Author URI: http://www.juanpay.ph/
 * License: GPL
 * Text Domain: juanpay-dpn
 */

/**
 * wpJuanPayDPN is the class that handles ALL of the plugin functionality,
 * and helps us avoid name collisions
 */
class wpJuanPayDPN
{
	/**
	 * @var array Plugin settings
	 */
	private $_settings;

	/**
	 * Static property to hold our singleton instance
	 * @var wpJuanPayDPN
	 */
	static $instance = false;

	/**
	 * @var string Name used for options
	 */
	private $_optionsName = 'juanpay-dpn';

	/**
	 * @var string Name used for options
	 */
	private $_optionsGroup = 'juanpay-dpn-options';


	/**
	 * @var array URLs for sandbox and live
	 */
	private $_url = array(
		'sandbox'	=> 'https://sandbox.juanpay.ph',
		'live'		=> 'https://www.juanpay.ph'
	);

	/**
	 * @access private
	 * @var string Query var for listener to watch for
	 */
	private $_listener_query_var		= 'juanpayListener';

	/**
	 * @access private
	 * @var string Value that query var must be for listener to take overs
	 */
	private $_listener_query_var_value	= 'DPN';

	

	/**
	 * This is our constructor, which is private to force the use of
	 * getInstance() to make this a Singleton
	 *
	 * @return wpJuanPayDPN
	 */
	private function __construct() {
		$this->_getSettings();
		$this->_fixDebugEmails();

	
		/**
		 * Add filters and actions
		 */
		add_action( 'admin_init', array($this,'registerOptions') );
		add_action( 'admin_menu', array($this,'adminMenu') );
		add_action( 'wp_ajax_nopriv_juanpay_listener', array( $this, 'listener' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
		add_filter( 'query_vars', array( $this, 'addjuanpayListenerVar' ) );
		add_filter( 'init', array( $this, 'init_locale' ) );

	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return wpJuanPayDPN
	 */
	public static function getInstance() {
		if ( !self::$instance )
			self::$instance = new self;
		return self::$instance;
	}

	public function init_locale() {
		load_plugin_textdomain( 'juanpay-dpn', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	private function _getSettings() {
		if (empty($this->_settings))
			$this->_settings = get_option( $this->_optionsName );
		if ( !is_array( $this->_settings ) )
			$this->_settings = array();

		$defaults = array(
			'sandbox'			=> 'sandbox',
			'debugging'			=> 'on',
			'debugging_email'	=> '',
		);
		$this->_settings = wp_parse_args( $this->_settings, $defaults );
	}

	public function getSetting( $settingName, $default = false ) {
		if (empty($this->_settings))
			$this->_getSettings();

		if ( isset($this->_settings[$settingName]) )
			return $this->_settings[$settingName];
		else
			return $default;
	}

	public function registerOptions() {
		register_setting( $this->_optionsGroup, $this->_optionsName );
	}

	public function adminMenu() {
		$page = add_options_page( __( 'JuanPay DPN Settings', 'juanpay-dpn' ), __( 'JuanPay DPN', 'juanpay-dpn' ), 'manage_options', 'JuanPayDPN', array( $this, 'options' ) );
		add_action( 'admin_print_styles-' . $page, array( $this, 'admin_css' ) );
	}

	public function admin_css() {
		wp_enqueue_style( 'juanpay-dpn', plugin_dir_url( __FILE__ ) . 'juanpay-dpn.css', array(), '0.0.1' );
	}

	/**
	 * This is used to display the options page for this plugin
	 */
	public function options() {
?>
		<script type="text/javascript">
		jQuery( function( $ ) {
			$( '#wp_juanpay_dpn span.help' ).click(function(){
				$( this ).next().toggle();
			});
		});
		</script>
		<div class="wrap">
			<h2><?php _e( 'JuanPay DPN Options', 'juanpay-dpn' ); ?></h2>
			<form action="options.php" method="post" id="wp_juanpay_dpn">
				<?php settings_fields( $this->_optionsGroup ); ?>
				<table class="form-table">
					
					
					
					<tr valign="top">
						<th scope="row">
							<?php _e('JuanPay Sandbox or Live:', 'juanpay-dpn') ?>
						</th>
						<td>
							<input type="radio" name="<?php echo $this->_optionsName; ?>[sandbox]" value="live" id="<?php echo $this->_optionsName; ?>_sandbox-live"<?php checked('live', $this->_settings['sandbox']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_sandbox-live"><?php _e('Live', 'juanpay-dpn'); ?></label><br />
							<input type="radio" name="<?php echo $this->_optionsName; ?>[sandbox]" value="sandbox" id="<?php echo $this->_optionsName; ?>_sandbox-sandbox"<?php checked('sandbox', $this->_settings['sandbox']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_sandbox-sandbox"><?php _e('Use Sandbox (for testing only)', 'juanpay-dpn'); ?></label><br />
						</td>
					</tr>
					
					
					<tr valign="top">
						<th scope="row">
							<?php _e('Debugging Mode:', 'juanpay-dpn') ?>
						</th>
						<td>
							<input type="radio" name="<?php echo $this->_optionsName; ?>[debugging]" value="on" id="<?php echo $this->_optionsName; ?>_debugging-on"<?php checked('on', $this->_settings['debugging']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_debugging-on"><?php _e('On', 'juanpay-dpn'); ?></label><br />
							<input type="radio" name="<?php echo $this->_optionsName; ?>[debugging]" value="off" id="<?php echo $this->_optionsName; ?>_debugging-off"<?php checked('off', $this->_settings['debugging']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_debugging-off"><?php _e('Off', 'juanpay-dpn'); ?></label><br />
							<small>
								<?php _e( 'If this is on, debugging messages will be sent to the E-Mail address set below.', 'juanpay-dpn' ); ?>
							</small>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_debugging_email">
								<?php _e('Debugging E-Mail:', 'juanpay-dpn') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[debugging_email]" value="<?php echo esc_attr($this->_settings['debugging_email']); ?>" id="<?php echo $this->_optionsName; ?>_version" class="regular-text" />
							<small>
								<?php _e( 'This is a comma separated list of E-Mail addresses that will receive the debug messages.', 'juanpay-dpn' ); ?>
							</small>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row">
							<?php _e('JuanPay DPN Listener URL:', 'juanpay-dpn'); ?>
						</th>
						<td>
							<?php echo add_query_arg( array( 'action' => 'juanpay_listener' ), admin_url('admin-ajax.php') ); ?>
							<?php $this->_show_help( 'listener' ); ?>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="Submit" value="<?php _e('Update Options &raquo;', 'juanpay-dpn'); ?>" />
				</p>
			</form>
		</div>
<?php
	}

	private function _show_help( $help ) {
                
		echo '<span class="help" title="' . __( 'Click for help', 'juanpay-dpn' ) . '">' . __( 'Help', 'juanpay-dpn' ) . '</span>';
		switch ( $help ) {
			case 'listener':
				?>
					<div class="hide-if-js">
						<p><?php _e('To set this in your JuanPay account:', 'juanpay-dpn'); ?></p>
						<ol>
							<li>
								<?php _e('Click <strong>Settings</strong> on the <strong>Menu</strong>.', 'juanpay-dpn'); ?>
							</li>
							<li>
								<?php _e('Click <strong>API tab</strong>.', 'juanpay-dpn'); ?>
							</li>
							
							<li>
								<?php _e('Copy/Paste the URL shown above into the DPN URL field.', 'juanpay-dpn'); ?>
							</li>
							
							<li>
								<?php _e('Click <strong>Save</strong>.', 'juanpay-dpn'); ?>
							</li>
							<li>
								<?php _e("You're Done!.", 'juanpay-dpn'); ?>
							</li>
						</ol>
					</div>
				<?php
				break;
		}
	}

	public function template_redirect() {
		// Check that the query var is set and is the correct value.
		if ( get_query_var( $this->_listener_query_var ) == $this->_listener_query_var_value )
			$this->listener();
	}

	/**
	 * This is our listener.  If the proper query var is set correctly it will
	 * attempt to handle the response.
	 */
	public function listener() {
		$_POST = stripslashes_deep($_POST);
                log_me($_POST);
		// Try to validate the response to make sure it's from JuanPay
		if ($this->_validateMessage())
			$this->_processMessage();

		// Stop WordPress entirely
		exit;
	}

	/**
	 * Get the JuanPay URL based on current setting for sandbox vs live
	 */
	public function getUrl() {
		return $this->_url[$this->_settings['sandbox']];
	}

	public function _fixDebugEmails() {
		$this->_settings['debugging_email'] = preg_split('/\s*,\s*/', $this->_settings['debugging_email']);
		$this->_settings['debugging_email'] = array_filter($this->_settings['debugging_email'], 'is_email');
		$this->_settings['debugging_email'] = implode(',', $this->_settings['debugging_email']);
	}

	private function _debug_mail( $subject, $message ) {
		// Used for debugging.
		if ( $this->_settings['debugging'] == 'on' && !empty($this->_settings['debugging_email']) )
			wp_mail( $this->_settings['debugging_email'], $subject, $message );
	}

	/**
	 * Validate the message by checking with JuanPay to make sure they really
	 * sent it
	 */
	private function _validateMessage() {
		// We need to send the message back to JuanPay just as we received it
		$params = array(
			'body' => $_POST,
			'sslverify' => apply_filters( 'juanpay_dpn_sslverify', false ),
			'timeout' 	=> 30,
		);

		// Send the request 
		$resp = wp_remote_post( $this->_url[$this->_settings['sandbox']]."/dpn/validate", $params );
                log_me($resp);

		// Put the $_POST data back to how it was so we can pass it to the action
		$message = __('URL:', 'juanpay-dpn' );
		$message .= "\r\n".print_r($this->_url[$this->_settings['sandbox']], true)."\r\n\r\n";
		$message .= __('Options:', 'juanpay-dpn' );
		$message .= "\r\n".print_r($this->_settings, true)."\r\n\r\n";
		$message .= __('Response:', 'juanpay-dpn' );
		$message .= "\r\n".print_r($resp, true)."\r\n\r\n";
		$message .= __('Post:', 'juanpay-dpn' );
		$message .= "\r\n".print_r($_POST, true);

		// If the response was valid, check to see if the request was valid
		if ( !is_wp_error($resp) && $resp['response']['code'] >= 200 && $resp['response']['code'] < 300 && (strcmp( $resp['body'], "VERIFIED") == 0)) {
                        log_me('DPN Listener Test - Validation Succeeded');
			log_me($message);

			$this->_debug_mail( __( 'DPN Listener Test - Validation Succeeded', 'juanpay-dpn' ), $message );
			return true;
		} else {
			// If we can't validate the message, assume it's bad
                        log_me('DPN Listener Test - Validation Failed');
			log_me($message);

			$this->_debug_mail( __( 'DPN Listener Test - Validation Failed', 'juanpay-dpn' ), $message );
			return false;
		}
	}

	/**
	 * Add our query var to the list of query vars
	 */
	public function addjuanpayListenerVar($public_query_vars) {
		$public_query_vars[] = $this->_listener_query_var;
		return $public_query_vars;
	}

	/**
	 * Throw an action based off the transaction type of the message
	 */
	private function _processMessage() {
		do_action( 'juanpay-ipn', $_POST );
		$actions = array( 'juanpay-ipn' );
		$subject = sprintf( __( 'DPN Listener Test - %s', 'juanpay-dpn' ), '_processMessage()' );
		if ( !empty($_POST['txn_type']) ) {
			do_action("juanpay-{$_POST['txn_type']}", $_POST);
			$actions[] = "juanpay-{$_POST['txn_type']}";
		}
		$message = sprintf( __( 'Actions thrown: %s', 'juanpay-dpn' ), implode( ', ', $actions ) );
		$message .= "\r\n\r\n";
		$message .= sprintf( __( 'Passed to actions: %s', 'juanpay-dpn' ), "\r\n" . print_r($_POST, true) );
		$this->_debug_mail( $subject, $message );
	}
}

/**
 * Helper functions
 */

function log_me($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}


// Instantiate our class
$wpJuanPayDPN = wpJuanPayDPN::getInstance();
