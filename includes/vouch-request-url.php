<?php

use Automattic\WooCommerce\Utilities\NumberUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create a request for sending it to vouch.
 */
class Vouch_Request_Helper {

	protected $api_endpoint;
	protected $cart_items = array();
	protected $gateway;

	/**
	 * Constructor.
	 */
	public function __construct( $gateway ) {
		$this->gateway    = $gateway;
	}

	/**
	 * Prepare request URL for order.
	 */
	public function vouch_woocommerce_get_request_url( $order ) {
		$access_token = $this->vouch_woocommerce_get_access_token();
		$this->api_endpoint    = 'https://prod.api.iamvouched.com/v1/merchant/gig/create';
		// $this->api_endpoint    = 'http://localhost:3000/v1/merchant/gig/create';
		$merchant_details       = $this->vouch_woocommerce_get_merchant_details( $order );

		$transaction_details = array();
		$transaction_details['mode'] = 'buying';
		$transaction_details['gig_name'] =  str_replace(' ', '_', $this->gateway->get_option( 'merchant_site_name' )).'_'.$order->get_id();
		$transaction_details['isVirtual'] = true;
		$transaction_details['milestones'] = [];
		$transaction_details['category'] = 'Other';
		$transaction_details['order_status'] = "yet_to_pay";
		$transaction_details['total_amount'] = $order->get_total();
		$transaction_details['classification'] = 'product';
		$transaction_details['requirements'] = 'Cart Description'."\r\n";
		$transaction_details['isPayingFull'] = true;

		foreach ( $merchant_details['item_info'] as $item ) {
			if (is_array($item)) {
				$transaction_details['requirements']=$transaction_details['requirements'].$item['quantity'].' '.$item['item_name'].'('.$item['item_number'].')'.' for Rs. '.$item['amount']."\r\n";
			}
		}

		$transaction_details['merchant_data'] = $merchant_details;

		$data = array(
			'headers'     => array(
				'Content-Type' => 'application/json; charset=utf-8',
				'Authorization' => 'Bearer ' . $access_token,
			),
			'body'        => json_encode($transaction_details),
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout' => 300,
		);

		return array(
			'endpoint'=>$this->api_endpoint,
			'args'=>$data
		);

	}

	/**
	 * reduce the length of a string.
	 */
	protected function vouch__woo_manage_string_length( $str, $limit_number = 100 ) {
			if ( strlen( $str ) > $limit_number ) {
				$str = substr( $str, 0, $limit_number ) . '.....';
			}
		return $str;
	}



	protected function vouch_woocommerce_prepare_general_data( $order ) {
		$general_data = array();

			$general_data['currency_code'] = get_woocommerce_currency();
			$general_data['charset'] = 'utf-8';
			$general_data['return'] = esc_url_raw( add_query_arg( 'utm_nooverride', '1', $this->gateway->get_return_url( $order ) ) );
			$general_data['cancel_return'] = esc_url_raw( $order->get_cancel_order_url_raw() );
			$general_data['order_id'] = $order->get_id();
			$general_data['notify_url'] = $this->vouch__woo_manage_string_length( WC()->api_request_url( 'WC_Gateway_Vouch' ), 255 );
			$general_data['first_name'] = $this->vouch__woo_manage_string_length( $order->get_billing_first_name(), 32 );
			$general_data['last_name'] = $this->vouch__woo_manage_string_length( $order->get_billing_last_name(), 64 );
			$general_data['address1'] = $this->vouch__woo_manage_string_length( $order->get_billing_address_1(), 100 );
			$general_data['address2'] = $this->vouch__woo_manage_string_length( $order->get_billing_address_2(), 100 );
			$general_data['city'] = $this->vouch__woo_manage_string_length( $order->get_billing_city(), 40 );
			$general_data['state'] = $this->vouch_woocommerce_state_name( $order->get_billing_country(), $order->get_billing_state() );
			$general_data['zip'] = $this->vouch__woo_manage_string_length( wc_format_postcode( $order->get_billing_postcode(), $order->get_billing_country() ), 32 );
			$general_data['country'] = $this->vouch__woo_manage_string_length( $order->get_billing_country(), 2 );
			$general_data['email'] = $this->vouch__woo_manage_string_length( $order->get_billing_email() );
			$general_data['merchant_site_name'] = $this->gateway->get_option( 'merchant_site_name' );
			$general_data['merchant_platform'] = 'woocommerce';
			$general_data['merchant_notify_done'] = false;
			$general_data['checkout_page'] = wc_get_checkout_url();;

		return $general_data;
	}


	public function vouch_woocommerce_get_access_token(){
		$data = array();
		$data['grant_type'] = 'refresh_token';
		$data['refresh_token'] = $this->gateway->get_option('api_key');
		$params = array(
			'body'        => $data,
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout' => 300
		);		
		$api_endpoint    = 'https://prod.api.iamvouched.com/v1/oauth/token';
		// $api_endpoint    = 'http://localhost:3000/v1/oauth/token';



		$response = wp_remote_post($api_endpoint,$params);
		$response_body = wp_remote_retrieve_body( $response );
		return json_decode($response_body)->access_token;

	}

	/**
	 * Get Merchant Details for passing to Vouch.
	 */
	protected function vouch_woocommerce_get_merchant_details( $order ) {

		$merchant_details = array_merge(
		$this->vouch_woocommerce_prepare_general_data( $order ),
		$this->vouch_woocommerce_prepare_shipping_data( $order ),
		$this->vouch_woocommerce_prepare_phone_number_data( $order )
		);

		$merchant_details['item_info']=$this->vouch_woocommerce_prepare_items_info_data( $order );

		return $merchant_details;
	}

	/**
	 * Get phone number args for  vouch.
	 */
	protected function vouch_woocommerce_prepare_phone_number_data( $order ) {
		$mobile_number = wc_sanitize_phone_number( $order->get_billing_phone() );
		$code = WC()->countries->get_country_calling_code( $order->get_billing_country() );
		if(is_array( $code )){
			$code = $code[0];
		}else{
			$code = $code;
		}
		$mobile_number = str_replace( $code, '', preg_replace( '/^0/', '', $order->get_billing_phone() ) );
		return array(
			'ph_code' => $code,
			'mobile' => $mobile_number,
		);
	}

	/**
	 * Prepare shipping data for order.
	 */
	protected function vouch_woocommerce_prepare_shipping_data( $order ) {
		if ( $order->needs_shipping_address() ) {
		return array(
			'first_name' => $this->vouch__woo_manage_string_length( $order->get_shipping_first_name(), 32 ),
			'last_name' => $this->vouch__woo_manage_string_length( $order->get_shipping_last_name(), 64 ),
			'first_address' => $this->vouch__woo_manage_string_length( $order->get_shipping_address_1(), 100 ),
			'second_address' => $this->vouch__woo_manage_string_length( $order->get_shipping_address_2(), 100 ),
			'city' => $this->vouch__woo_manage_string_length( $order->get_shipping_city(), 40 ),
			'state' => $this->vouch_woocommerce_state_name( $order->get_shipping_country(), $order->get_shipping_state() ),
			'country' => $this->vouch__woo_manage_string_length( $order->get_shipping_country(), 2 ),
			'zip' => $this->vouch__woo_manage_string_length( wc_format_postcode( $order->get_shipping_postcode(), $order->get_shipping_country() ), 32 ),
			'shipping_needed' => true
		);
		} else {
			return array(
				'shipping_needed' => true
			);
		}
	}

	/**
	 * Get shipping cost line item args for Vouch.
	 */
	protected function vouch_woocommerce_fetch_shipping_cost( $order ) {
		$data = array();
		$shipping_total = $order->get_shipping_total();
		if ( $this->vouch_woocommerce_change_format( $order->get_shipping_total() + $order->get_shipping_tax(), $order ) !== $this->vouch_woocommerce_change_format( $order->get_total(), $order ) && $order->get_shipping_total() > 0 && $order->get_shipping_total() < 999.99  ) {
			$data['shipping_1'] = $this->vouch_woocommerce_change_format( $shipping_total, $order );
		} elseif ( $order->get_shipping_total() > 0 ) {
			$this->vouch_woocommerce_append_signle_item( sprintf( __( 'Shipping via %s', 'woocommerce' ), $order->get_shipping_method() ), 1, $this->vouch_woocommerce_change_format( $shipping_total, $order ) );
		}
		return $data;
	}


	/**
	 * Get line item args for Vouch request.
	 */
	protected function vouch_woocommerce_prepare_items_info_data( $order ) {
		$this->vouch_woocommerce_prepare_items_Info( $order );
		$data = array_merge( $this->cart_items, $this->vouch_woocommerce_fetch_shipping_cost( $order ) );
		return $data;
	}


	/**
	 * Prepare all items related inormation.
	 */
	protected function vouch_woocommerce_prepare_items_Info( $order ) {
		$this->cart_items = array();
		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
			if ( $item['type'] === 'fee' ) {
				$item_total = $this->vouch_woocommerce_change_format( $item['line_total'], $order );
				$this->vouch_woocommerce_append_signle_item( $item->get_name(), 1, $item_total );
			} else {
				$sku             =  $item->get_product() ?  $item->get_product()->get_sku() : '';
				$item_total = $this->vouch_woocommerce_change_format( $order->get_item_subtotal( $item, false ), $order );
				$this->vouch_woocommerce_append_signle_item($item->get_name() , $item->get_quantity(), $item_total, $sku );
			}
		}
	}

	/**
	 * Append a single product Item to the globle array.
	 */
	protected function vouch_woocommerce_append_signle_item( $item_name, $quantity = 1, $amount = 0.0, $item_number = '' ) {
		array_push($this->cart_items,
		array(
			'item_name'   => $this->vouch__woo_manage_string_length( html_entity_decode( wc_trim_string( $item_name ? wp_strip_all_tags( $item_name ) : __( 'Item', 'woocommerce' ), 127 ), ENT_NOQUOTES, 'UTF-8' ), 127 ),
			'quantity'    => (int) $quantity,
			'amount'      => wc_float_to_string( (float) $amount ),
			'item_number' => $this->vouch__woo_manage_string_length( $item_number, 127 ),
		)
		);
	}

	/**
	 * Fetch the state.
	 */
	protected function vouch_woocommerce_state_name( $countrycode, $st ) {
		$states = WC()->countries->get_states( $countrycode );
		if ( isset( $states[ $st ] ) ) {
			return $states[ $state ];
		}
		return $st;
	}

	/**
	 * Formats Amount.
	 */
	protected function vouch_woocommerce_change_format( $price, $order ) {

		if ( in_array( $order->get_currency(), array( 'HUF', 'JPY', 'TWD' ), true ) ) {
			$decimals = 0;
		}else{
			$decimals = 2;
		}
		return number_format( $price, $decimals, '.', '' );
	}
}
