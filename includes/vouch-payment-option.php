<?php

		if ( ! defined( 'ABSPATH' ) ) {
			exit;
		}

		class WC_Gateway_Vouch extends WC_Payment_Gateway {

			/**
			 * Constructor for the gateway.
			 */
			public function __construct() {
				$this->setup_properties();
				$this->init_form_fields();
				$this->title              = $this->get_option( 'title' )?$this->get_option( 'title' ):'Vouch- buyer protection ';
				$this->description        = $this->get_option( 'merchant_site_name' ).' is a vouched business and offers'."<br /><br />".'1.Payment protection backed by a money back guarantee. i.e if you do not receive a product vouch will refund your money'."<br />".'2.Superior tracking,updates & customer support through the Vouch app';
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_payment_complete_order_status' ), 10, 3 );
				new vouch_woocommerce_Callback_Handler($this);
			}

			/**
			 * Setup general properties for the gateway.
			 */
			protected function setup_properties() {
				$this->id                 = 'vouch';
				$this->icon               = apply_filters( 'woocommerce_vouch_icon', plugins_url('../assets/vouch_icon.svg', __FILE__ ) );
				$this->method_title       = __( 'Vouch- buyer protection', 'woocommerce' );
				$this->method_description = __( 'Payment Protection by vouch on both the parties', 'woocommerce' );
				$this->has_fields         = false;
			}

			/**
			 * Initialise Gateway Settings Form Fields.
			 */
			public function init_form_fields() {
				$this->form_fields = array(
					'enabled'            => array(
						'title'       => __( 'Enable/Disable', 'woocommerce' ),
						'label'       => __( 'Vouch- buyer protection', 'woocommerce' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title'              => array(
						'title'       => __( 'Title', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'Payment method title that the customer will see on your checkout.', 'woocommerce' ),
						'default'     => __( 'Vouch- buyer protection', 'woocommerce' ),
						'desc_tip'    => true,
					),
					'merchant_site_name'        => array(
						'title'       => __( 'Your Online Store name', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'Please enter your online store/website site name.', 'woocommerce' ),
						'default'     => '',
						'desc_tip'    => true,
					),
					'api_key'          => array(
						'title'       => __( 'Api key', 'woocommerce' ),
						'type'        => 'password',
						'description' => __( 'Api key provided by vouch', 'woocommerce' ),
						'default'     => '',
						'desc_tip'    => true,
						'placeholder' => __( 'xxxxxxxxxx', 'woocommerce' ),
					)
				);
			}


			/**
			 * Check If The Gateway Is Available For Use.
			 */
			public function is_available() {
				$api_key = $this->get_option( 'api_key' );
				$merchant_site_name = $this->get_option( 'merchant_site_name' );
				if(!isset($api_key) || trim($api_key) === '' || !isset($merchant_site_name) || trim($merchant_site_name) === ''){
					return false;
				}
				return parent::is_available();
			}

			/**
			 * Process the payment and return the result.
			 */
			public function process_payment( $order_id ) {
				global $woocommerce;

				$order          = wc_get_order( $order_id );
				$request = new Vouch_Request_Helper( $this );
				$request_data=$request->vouch_woocommerce_get_request_url( $order );
				$response = wp_remote_post( $request_data['endpoint'], $request_data['args']);
				error_log("Responce" . $response);
				$response_body = wp_remote_retrieve_body( $response );
				error_log("Responce Body" . $response_body);
				if ( is_wp_error( $response ) ) 
				throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'spyr-authorizenet-aim' ) );

				if ( empty( $response['body'] ) )
				throw new Exception( __( 'Authorize.net\'s Response was empty.', 'spyr-authorizenet-aim' ) );
				$order->update_status( apply_filters( 'woocommerce_vouch_process_payment_order_status', 'on-hold' , $order ), __( 'Payment to be made through vouch.', 'woocommerce' ) );
				$woocommerce->cart->empty_cart();
				return array(
					'result'   => 'success',
					'redirect' => json_decode($response_body)->data->payment_link,
				);
			}


			/**
			 * Change payment complete order status to completed for vouch orders.
			 */
			public function change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
				if ( $order && 'vouch' === $order->get_payment_method() ) {
					$status = 'completed';
				}
				return $status;
			}

		}