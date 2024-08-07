<?php
if( !class_exists( 'Email_Validation_Mailgun_Admin' ) )
{
	class Email_Validation_Mailgun_Admin
	{
		private $options = NULL;

		public function __construct()
		{
			$this->options = get_option( 'jesin_mailgun_email_validator' );

			add_action( 'admin_menu' , array( &$this, 'plugin_menu' ) );
			add_action( 'admin_init' , array( &$this, 'plugin_settings' ) );
			add_action( 'admin_notices' , array( &$this, 'admin_messages' ) );
		}

		//Display admin notices
		public function admin_messages()
		{
			global $email_validation_mailgun;
			//Displayed if no API key is entered
			if( !isset( $this->options['mailgun_pubkey_api'] ) || empty( $this->options['mailgun_pubkey_api'] ) )
				echo '<div class="updated"><p>' . sprintf( __( 'The %s will not work until a %s is entered.', $email_validation_mailgun->slug ), '<a href="'.admin_url( 'options-general.php?page=' . $email_validation_mailgun->slug ).'">Mailgun Email Validator plugin</a>', 'Mailgun Private API key' ) . '</p></div>';
		}
		
		public function settings_link( $links )
		{
			global $email_validation_mailgun;
			array_unshift( $links, '<a href="'.admin_url( 'options-general.php?page=' . $email_validation_mailgun->slug ).'">' . __( 'Settings', $email_validation_mailgun->slug ) . '</a>' );
			$links[] = '<a href="http://websistent.com/wordpress-plugins/" target="_blank" title="' . sprintf( __( 'More Plugins by %s', $email_validation_mailgun->slug ), 'Jesin' ) . '">' . __( 'More Plugins', $email_validation_mailgun->slug ) . '</a>';
			return $links;
		}

		//Hook in and create a menu
		public function plugin_menu()
		{
			global $email_validation_mailgun;
			add_filter( 'plugin_action_links_' . $email_validation_mailgun->basename, array( &$this, 'settings_link' ) );
			$plugin_page = add_options_page( __( 'Email Validation Settings', $email_validation_mailgun->slug ), __( 'Email Validation', $email_validation_mailgun->slug ), 'manage_options', $email_validation_mailgun->slug, array( &$this, 'plugin_options' ) );
			add_action( 'admin_head-' . $plugin_page, array( &$this, 'plugin_panel_styles' ) );
			add_action( 'admin_footer-' . $plugin_page, array( &$this, 'plugin_panel_scripts' ) ); //Add AJAX to the footer of the options page
		}

		//Create the options page
		public function plugin_settings()
		{
			add_action( 'wp_ajax_mailgun_api', array( &$this, 'mailgun_api_ajax_callback') ); //AJAX to verify the API key
			add_action( 'wp_ajax_test_email', array( &$this, 'test_email_ajax_callback') ); //AJAX for demo email validation

			global $email_validation_mailgun;
			register_setting( $email_validation_mailgun->slug.'_options', 'jesin_mailgun_email_validator', array( &$this, 'sanitize_input' ) );
			add_settings_section( $email_validation_mailgun->slug.'_settings', '', array( &$this, 'dummy_cb'), $email_validation_mailgun->slug);
			add_settings_field('mailgun_pubkey_api','Mailgun Private API', array( &$this, 'api_field' ), $email_validation_mailgun->slug, $email_validation_mailgun->slug.'_settings', array( 'label_for' => 'mailgun_pubkey_api' ) ); //Public API key field
			add_settings_field('mailgun_ignore_mails','Ignore Emails', array( &$this, 'ignore_emails_field' ), $email_validation_mailgun->slug, $email_validation_mailgun->slug.'_settings', array( 'label_for' => 'mailgun_ignore_mails' ) ); //Public API key field
		}

		public function plugin_panel_styles()
		{
			global $email_validation_mailgun;
			echo '<style type="text/css">#icon-'.$email_validation_mailgun->slug.'{background:transparent url(\'' . plugin_dir_url( __FILE__ ) . 'screen-icon.png\') no-repeat;}</style>';
		}

		//Add AJAX to the footer
		public function plugin_panel_scripts()
		{
			global $email_validation_mailgun;
?>
<script type="text/javascript">
jQuery(document).ready(
	jQuery('#mailgun_api_verify').click (function($) 
	{
		if (jQuery.trim(jQuery('#mailgun_pubkey_api').val()).length == 0) {
			jQuery('#api_output').html('<?php _e( 'This field cannot be empty', $email_validation_mailgun->slug ); ?>');
			return;
		}

		var data = {
			action: 'mailgun_api',
			api: jQuery('#mailgun_pubkey_api').val()
		};

		jQuery('#api_output').html('<?php _e( 'Checking', $email_validation_mailgun->slug ); ?>...');
		jQuery('#api_output').css("cursor","wait");
		jQuery('#mailgun_api_verify').attr("disabled","disabled");
		jQuery.post(ajaxurl, data, function(response) {
			jQuery('#api_output').html(response);
			jQuery('#api_output').css("cursor","default");
			jQuery('#mailgun_api_verify').removeAttr("disabled");
		}
		);
	}
));

jQuery(document).ready(
	jQuery('#validate_email').click (function($)
	{
		if (jQuery.trim(jQuery('#sample_email').val()).length == 0) {
			jQuery('#email_output').html('<?php _e( 'Please enter an email address to validate', $email_validation_mailgun->slug ); ?>');
			return;
		}

		var data = {
			action: 'test_email',
			email_id: jQuery('#sample_email').val()
		};
		jQuery('#email_output').html('<?php _e( 'Checking', $email_validation_mailgun->slug ); ?>...');
		jQuery('#email_output').css("cursor","wait");
		jQuery('#validate_email').attr("disabled","disabled");
		jQuery.post(ajaxurl, data, function(response) {
			jQuery('#email_output').html(response);
			jQuery('#email_output').css("cursor","default");
			jQuery('#validate_email').removeAttr("disabled");
		}
		);
	}
));
</script>
<?php	}

		//AJAX Callback function for validating the Public API key
		public function mailgun_api_ajax_callback()
		{
			global $email_validation_mailgun;

			$args = array(
				'sslverify' => false,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( "api:".$_POST['api'] )
				)
			);

			//We are using a static email here as only the API is validated
			$response = wp_remote_request( "https://api.mailgun.net/v4/address/validate?address=foo%40mailgun.net", $args );

			//A Network error has occurred
			if( is_wp_error($response) )
				echo '<span style="color:red">' . $response->get_error_message() . '</span>';
			
			elseif( isset($response->errors['http_request_failed']) )
			{
				echo '<span style="color:red">' . __( 'The following error occurred when validating the key.', $email_validation_mailgun->slug ) . '<br />';
				foreach($response->errors['http_request_failed'] as $http_errors)
					echo $http_errors;
				echo '</span>';
			}

			elseif( '200' == $response['response']['code'] )
				echo '<span style="color:green">' . __( 'API Key is valid', $email_validation_mailgun->slug ) . '</span>';

			//Invalid API as Mailgun returned 401 Unauthorized
			elseif( '401' == $response['response']['code'] )
				echo '<span style="color:red">' . sprintf( __( 'Invalid API Key. Error code: %s %s', $email_validation_mailgun->slug ), $response['response']['code'], $response['response']['message'] ) . '</span>';

			//A HTTP error other than 401 has occurred
			else
				echo '<span style="color:red">' . sprintf( __( 'A HTTP error occurred when validating the API. Error code: %s %s', $email_validation_mailgun->slug ), $response['response']['code'], $response['response']['message'] ) . '</span>';

			die();
		}

		//AJAX Callback function for demo email validation
		public function test_email_ajax_callback()
		{
			global $email_validation_mailgun;

			if( !filter_var( $_POST['email_id'], FILTER_VALIDATE_EMAIL ) )
			{
				echo '<span style="color:red">' . __( 'The format of the email address is invalid.', $email_validation_mailgun->slug ) . '</span>';
				die();
			}

			//Someone tries validating without entering the Public API key
			if( !isset( $this->options['mailgun_pubkey_api'] ) || empty( $this->options['mailgun_pubkey_api'] ) )
			{
				echo '<span style="color:red">' . __( 'Please enter a Mailgun Private API and click Save Settings.', $email_validation_mailgun->slug ) . '</span>';
				die();
			}

			$args = array(
				'sslverify' => false,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( "api:".$this->options['mailgun_pubkey_api'] )
				)
			);

            if( isset( $this->options['mailgun_ignore_mails'] ) && !empty( $this->options['mailgun_ignore_mails'] ) )
            {
                $mailgun_ignore_mails = explode(",", $this->options['mailgun_ignore_mails']);
                if(count($mailgun_ignore_mails) > 0){
                    $domain = substr(strrchr($_POST['email_id'] , "@"), 1);
                    // Check if the domain is in the array of allowed domains
                    if(in_array($domain, $mailgun_ignore_mails)) {
                        echo '<span style="color:red">Provided email is in the list of ignored domains</span>';
                        die();
                    }
                }
            }

			$response = wp_remote_request( "https://api.mailgun.net/v4/address/validate?address=" . urlencode( $_POST['email_id'] ), $args );

			if( is_wp_error($response) )
			{
				echo '<span style="color:red">' . $response->get_error_message() . '</span>';
				die();
			}
			$result = json_decode($response['body'],true);

			//A Network error has occurred
			if( isset($response->errors['http_request_failed']) )
			{
				echo '<span style="color:red">' . __( 'The following error occured', $email_validation_mailgun->slug ) . '<br />';
				foreach( $response->errors['http_request_failed'] as $http_errors )
					echo $http_errors;
				echo '</span>';
			}

			elseif( '200' == $response['response']['code'] )
			{
                if ($result['result'] == "deliverable" && ('low' == $result['risk'] || 'medium' == $result['risk']) && false == $result['is_disposable_address'])
					echo '<span style="color:green">' . __( 'Address is valid', $email_validation_mailgun->slug ) . '</span>';
				else
					echo '<span style="color:red">' . __( 'Address is invalid', $email_validation_mailgun->slug ) . '</span>';
			}

			//API key is invalid so email couldn't be verified
			elseif( '401' == $response['response']['code'] )
				echo '<span style="color:red">' . sprintf( __( 'Invalid API Key.%sError code: %s %s', $email_validation_mailgun->slug ), '<br />', $response['response']['code'], $response['response']['message'] ) . '</span>';

			die();
		}

		//Validate user input in the admin panel
		public function sanitize_input( $input )
		{
			$input['mailgun_pubkey_api'] = trim( $input['mailgun_pubkey_api'] );
			if( !empty( $input['mailgun_pubkey_api'] ) )
			{
				preg_match_all( '/[0-9a-z-]/', $input['mailgun_pubkey_api'], $matches );
				$input['mailgun_pubkey_api'] = implode( $matches[0] );
			}

			return $input;
		}

		//Create the Public API field
		public function api_field()
		{
			global $email_validation_mailgun;

			$api_key = ( (isset($this->options['mailgun_pubkey_api']) && !empty($this->options['mailgun_pubkey_api'])) ? $this->options['mailgun_pubkey_api'] : '' );
			echo '<input class="regular_text code" id="mailgun_pubkey_api" name="jesin_mailgun_email_validator[mailgun_pubkey_api]" size="40" type="text" value="'.$api_key.'" required />
				<input id="mailgun_api_verify" type="button" value="Verify API Key" /><br />
				<div id="api_output"></div>
				<p class="description">' . sprintf( __( 'Enter your Mailgun Private API key which is shown at the left under %s after you %slogin%s', $email_validation_mailgun->slug ), '<strong>Account Information</strong>', '<a href="https://mailgun.com/sessions/new">', '</a>' ) . '</p>';
		}
        public function ignore_emails_field()
        {
            global $email_validation_mailgun;

            $ignored_emails = ( (isset($this->options['mailgun_ignore_mails']) && !empty($this->options['mailgun_ignore_mails'])) ? $this->options['mailgun_ignore_mails'] : '' );
            echo '<input class="regular_text code" id="mailgun_ignore_mails" name="jesin_mailgun_email_validator[mailgun_ignore_mails]" size="40" type="text" value="'.$ignored_emails.'"  />
                <p class="description">Enter comma separated email domains which are not required to be validated. Example t-online.de, gmail.com etc</p>';
        }

		//HTML of the plugin options page
		public function plugin_options()
		{
			global $email_validation_mailgun;
		?>
			<div class="wrap">
			<?php screen_icon( $email_validation_mailgun->slug ); ?>
			<h2><?php _e( 'Email Validation Settings', $email_validation_mailgun->slug); ?></h2>
			<p><?php printf( __( 'This plugin requires a Mailgun account which is totally free. %sSignup for a free account%s', $email_validation_mailgun->slug ), '<a href="https://mailgun.com/signup" target="_blank">', '</a>' ); ?></p>
			<form method="post" action="options.php">
			<?php settings_fields( $email_validation_mailgun->slug . '_options' );
			do_settings_sections( $email_validation_mailgun->slug );
			submit_button(); ?>
			</form>
			<?php if( isset( $this->options['mailgun_pubkey_api'] ) && !empty( $this->options['mailgun_pubkey_api'] ) ): ?>
			<h2 class="title"><?php _e( 'Email Validation Demo', $email_validation_mailgun->slug ); ?></h2>
			<p><?php _e( 'You can use this form to see how mailgun validates email addresses.', $email_validation_mailgun->slug ); ?></p>
			<label for="sample_email">Email:</label><input style="margin-left: 20px" class="regular_text code" type="text" id="sample_email" size="40"/>
			<input type="button" id="validate_email" value="Validate Email" />
			<div id="email_output" style="font-size:20px;padding:10px 0 0 50px"></div>
			<div><p style="font-size:24px"><?php printf( __( 'If you find this plugin useful please consider giving it a %sfive star%s rating.', $email_validation_mailgun->slug ), '<a href="http://wordpress.org/support/view/plugin-reviews/' . $email_validation_mailgun->slug . '?rate=5#postform" target="_blank">', '</a>' ); ?></p></div>
			<?php endif; ?>
			</div>
		<?php
		}

		public function dummy_cb() {} //Empty callback for the add_settings_section() function
	}
	
	$email_validation_mailgun_admin = new Email_Validation_Mailgun_Admin();
}
