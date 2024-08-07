<?php
/*
Plugin Name: Mailgun Email Validator
Plugin URI: https://cendas.net
Description: This plugins is a fork from Jesin's Mailgun verification plugin (https://websistent.com/). It kicks spam with an highly advanced email validation in comment forms, user registration forms and contact forms using <a href="http://blog.mailgun.com/post/free-email-validation-api-for-web-forms/" target="_blank">Mailgun's Email validation</a> service.
Author: Shabbar Abbas (shabbar.sagit@gmail.com)
Version: 2.1.0.0
Author URI:  https://cendas.net
*/

if ( ! function_exists( 'json_decode' ) ) {
	function json_decode( $string, $assoc = FALSE ) {
            require_once 'JSON.php';
        $json = new Services_JSON();

        if ( $assoc )
            return (array) $json->decode( $string );
        else
            return $json->decode( $string );
	}
}

if ( ! class_exists( 'Email_Validation_Mailgun' ) ) {
	class Email_Validation_Mailgun {
		private $options = NULL;
		var $slug;
		var $basename;

		public function __construct() {
			$this->options = get_option( 'jesin_mailgun_email_validator' );
			$this->basename = plugin_basename( __FILE__ );
			$this->slug = str_replace( array( basename( __FILE__ ), '/' ), '', $this->basename );

			add_action( 'init', array( &$this, 'plugin_init' ) );
		}

		public function plugin_init() {
			load_plugin_textdomain( $this->slug, FALSE, $this->slug . '/languages' );
			add_filter( 'is_email', array( $this, 'validate_email' ) );
		}

		//Function which sends the email to Mailgun to check it
		public function validate_email( $emailID ) {
			global $pagenow, $wp;
			//If the format of the email itself is wrong return false without further checking
			if( ! filter_var( $emailID, FILTER_VALIDATE_EMAIL ) )
				return FALSE;

			//If no API was entered don't do anything
			if( ! isset( $this->options['mailgun_pubkey_api'] ) || empty( $this->options['mailgun_pubkey_api'] ) )
				return TRUE;

            if( isset( $this->options['mailgun_ignore_mails'] ) && !empty( $this->options['mailgun_ignore_mails'] ) )
            {
                $mailgun_ignore_mails = explode(",", $this->options['mailgun_ignore_mails']);
                if(count($mailgun_ignore_mails) > 0){
                    $domain = substr(strrchr($emailID , "@"), 1);
                    // Check if the domain is in the array of allowed domains
                    if(in_array($domain, $mailgun_ignore_mails)) {
                        return TRUE;
                    }
                }
            }

			if ( "edit.php" == $pagenow && "shop_order" == $wp->query_vars['post_type'] ) {
				return true;
			}

			$args = array(
				'sslverify' => FALSE,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( "api:".$this->options['mailgun_pubkey_api'] )
				)
			);
			//Send the email to Mailgun's email validation service
			$response = wp_remote_request( "https://api.mailgun.net/v4/address/validate?address=".urlencode($emailID), $args );

			//If there was a HTTP or connection error pass the validation so that the website visitor doesn't know anything
			if( is_wp_error( $response ) || isset( $response['error'] ) || '200' != $response['response']['code'] )
				return TRUE;

			//Extract the JSON response and return the result
			$result = json_decode( $response['body'], true );
			return $result['result'] == "deliverable" && ('low' == $result['risk'] || 'medium' == $result['risk']) && false == $result['is_disposable_address'] ? $emailID : false;
		}
	}

	$email_validation_mailgun = new Email_Validation_Mailgun();
}

if ( is_admin() ) require_once dirname( __FILE__ ) . '/admin_options.php';
