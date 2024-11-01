<?php
/**
 * Handles responses from Vouch Woo Callback Notify.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * vouch_woocommerce_Callback_Handler class.
 */
class vouch_woocommerce_Callback_Handler {

	/**
	 * Receiver email address to validate.
	 */
	protected $gateway;



	// public function __construct($gateway) {
	// 	$this->gateway    = $gateway;
	// 	add_action('woocommerce_thankyou', 'custom_thankyou_message', 10, 1);
	// }


	// // Define the callback function
	// function custom_thankyou_message($order_id) {
	// 	// Get the order object
	// 	$order = wc_get_order($order_id);
	
	// 	// Check if the order is valid
	// 	if (!$order) {
	// 		return;
	// 	}
	
	// }
	
	
	
	
	









	/**
	 * Constructor.
	 */
	public function __construct($gateway) {
		$this->gateway    = $gateway;
		add_action( 'woocommerce_api_wc_gateway_vouch', array( $this, 'vouch_woocommerce_verify_request_data' ) );
		add_action( 'vouch-woo-notify-request', array( $this, 'vouch_woocommerce_verify_response' ) );
	}

	/**
	 * Check for Vouch Request.
	 */
	public function vouch_woocommerce_verify_request_data() {
		if ( ! empty( $_POST ) && $this->vouch_woocommerce_validate_notify_request()) { 
			$sanitized_post_data = $this->vouch_woo_sanitization_validation( wp_unslash( $_POST ) );
			do_action( 'vouch-woo-notify-request', $sanitized_post_data );
			exit;
		}

		wp_die( 'Vouch Woo Callback notify Request Failure', 'Vouch Woo Callback Notify', array( 'response' => 500 ) );
	}

	/**
	 * Check If the Order is already notified to the merchant.
	 */
	public function vouch_woocommerce_validate_notify_request() {
		$request = new Vouch_Request_Helper( $this->gateway );
		// $access_token = $request->vouch_woocommerce_get_access_token();
		$new = $_POST;
		$sanitized_post_data = $this->vouch_woo_sanitization_validation( wp_unslash( $_POST ) );
		$payload_string = http_build_query($sanitized_post_data);
		$recieved_signature = $_SERVER['HTTP_X_VOUCH_SIGNATURE'];
		$secret = 'a78bcc3a-1238-11ed-861d-02421456';

		$expectedSignature = hash_hmac('sha256', $payload_string, $secret);

		if (isset($_SERVER['HTTP_X_VOUCH_SIGNATURE']) &&
        $_SERVER['HTTP_X_VOUCH_SIGNATURE'] === $expectedSignature) {
        error_log('Valid request received');
		return true;
    } else {
        // Invalid signature, ignore the request
        // Log the invalid request
        error_log('Invalid request received');
		return false;
    }


		// $data = array();
		// $data['order_id'] = $sanitized_post_data['custom']->order_id;
		// $data['order_key'] = $sanitized_post_data['custom']->order_key;
		// $params = array(
		// 	'headers'     => array(
		// 		'Content-Type' => 'application/json; charset=utf-8',
		// 		'Authorization' => 'Bearer ' . $access_token,
		// 	),
		// 	'body'        => json_encode($data),
		// 	'method'      => 'POST',
		// 	'data_format' => 'body',
		// );		
		// // $endpoint    = 'https://prod.api.iamvouched.com/v1/merchant/not/woo';
		// $endpoint    = 'http://localhost:3000/v1/merchant/not/woo';

		// $response = wp_remote_post( $endpoint, $params );

		// if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 ) {
		// 	return true;
		// }

		// return false;
	}

	public function vouch_woo_sanitization_validation( $posed_data ){
			$sanitized_post_data = sanitize_post( $posed_data );	
			$data = array();
			$data['order_id'] = sanitize_text_field($sanitized_post_data['order_id']);
			$data['woo_order_id'] = intval($sanitized_post_data['woo_order_id']);
			$data['order_name'] = sanitize_text_field($sanitized_post_data['order_name']);
			$data['amount'] = sanitize_text_field($sanitized_post_data['amount']);
			$data['currency_code'] = sanitize_text_field($sanitized_post_data['currency_code']);
			$data['event'] = sanitize_text_field($sanitized_post_data['event']);
			$data['utr'] = sanitize_text_field($sanitized_post_data['utr']);
			// // $data['custom']->order_id = intval($sanitized_custom_data->order_id);
			// $data['custom']['order_id'] = intval($sanitized_custom_data['order_id']);
			// // $data['custom']->order_key = sanitize_text_field($sanitized_custom_data->order_key);
			// $data['custom']['order_key'] = sanitize_text_field($sanitized_custom_data['order_key']);

			// $data['payment_status'] = sanitize_text_field($sanitized_post_data['payment_status']);
			// $data['currency_type'] = sanitize_text_field($sanitized_post_data['currency_type']);
			// $data['gross'] = sanitize_text_field($sanitized_post_data['gross']);
			// $data['return_url'] = esc_url_raw($sanitized_post_data['return_url']);
			return $data;
	}

	/**
	 * There was a valid response.
	 */
	public function vouch_woocommerce_verify_response( $posted ) {
		$order = ! empty( $posted['woo_order_id'] ) ? $this->vouch_woocommerce_get_order( $posted['woo_order_id'] ) : false;

		if ( $order ) {
			$posted['event'] = strtolower( $posted['event'] );

			if($posted['event'] == 'collect_failed'){
				$this->vouch_woocommerce_verify_order_currency( $order, $posted['currency_code'] );
				$this->vouch_woocommerce_verify_order_amount( $order, $posted['amount'] );
				$order->update_status('failed');				;
				$order->add_order_note(http_build_query($posted));		
				// header ("Location: ".$posted['return_url']);
			}elseif($posted['event'] == 'collect_success'){
				$this->vouch_woocommerce_verify_order_currency( $order, $posted['currency_code'] );
				$this->vouch_woocommerce_verify_order_amount( $order, $posted['amount'] );
				$this->payment_complete( $order );
				$order->payment_complete( $posted['utr'] );
				$order->add_order_note(http_build_query($posted));
			}
		}
	}


	/**
	 * verify if currency in request is being matched with the order.
	 */
	protected function vouch_woocommerce_verify_order_currency( $order, $currency ) {
		if ( $order->get_currency() !== $currency ) {
			exit;
		}
	}

	/**
	 * verify if currency in request is being matched with the order.
	 */
	protected function vouch_woocommerce_verify_order_amount( $order, $amount ) {
		if ( number_format( $order->get_total(), 2, '.', '' ) !== number_format( $amount, 2, '.', '' ) ) {
			exit;
		}
	}

	/**
	 * Get the order with the given Id.
	 */
	protected function vouch_woocommerce_get_order( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}
		return $order;

	}

	/**
	 * Complete order.
	 */
	protected function payment_complete( $order ) {
		if ( ! $order->has_status( array( 'completed' ) ) ) {
			$order->payment_complete( $txn_id );
		}
	}

}
