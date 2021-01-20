<?php
/**
 * Plugin Name: Easy Digital Downloads - Payfast Integration
 * Description: Accept once off and recurring payments through Easy Digital Downloads using South Africa's most popular payment gateway, Payfast.
 * Author: Pacific Plugins
 * Author URI: https://pacificplugins.com/
 * Version 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PPS_EDD_PAYFAST_URL', plugin_dir_url( __FILE__ ) );
define( 'PPS_EDD_PAYFAST_VERSION', '1.0.0' );

// Check if Easy Digital Downloads is active
if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
	return;
}

add_action( 'edd_payfast_cc_form', '__return_false' );

require_once plugin_dir_path( __FILE__ ).'itn.php';

class EDD_Payfast{

	public function __construct(){

		add_action( 'edd_after_cc_fields', array( $this, 'pps_edd_payfast_add_errors' ), 999 );
		add_filter( 'edd_settings_sections_gateways', array( $this, 'gateway_section' ) );
		add_filter( 'edd_settings_gateways', array( $this, 'settings' ), 1 );
		add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ) );
	}

	function pps_edd_payfast_add_errors() {
		echo '<div id="edd-payfast-payment-errors"></div>';
	}

	function gateway_section( $sections ) {

		$sections['payfast-settings'] = 'Payfast';

		return $sections;

	}

	function settings( $settings ){

		$payfast_settings = array(
			array(
				'id'   => 'edd_payfast_settings',
				'name' => '<strong>Payfast Settings</strong>',
				'desc' => 'Configure the gateway settings',
				'type' => 'header',
			),
			// array(
			// 	'id'   => 'edd_payfast_test_mode',
			// 	'name' => 'Enable Test Mode',
			// 	'desc' => 'Test mode enables you to test payments before going live. Once the LIVE MODE is enabled on your Payfast account uncheck this',
			// 	'type' => 'checkbox',
			// 	'std'  => 0,
			// ),
			// array(
			// 	'id'   => 'edd_payfast_test_merchant_id',
			// 	'name' => 'Test Merchant ID',
			// 	'desc' => 'Enter your Test Secret Key here',
			// 	'type' => 'text',
			// 	'size' => 'regular',
			// ),
			// array(
			// 	'id'   => 'edd_payfast_test_merchant_key',
			// 	'name' => 'Test Merchant Key',
			// 	'desc' => 'Enter your Test Public Key here',
			// 	'type' => 'text',
			// 	'size' => 'regular',
			// ),
			array(
				'id'   => 'edd_payfast_live_merchant_id',
				'name' => 'Live Merchant ID',
				'desc' => 'Enter your Live Secret Key here',
				'type' => 'text',
				'size' => 'regular',
			),
			array(
				'id'   => 'edd_payfast_live_merchant_key',
				'name' => 'Live Merchant Key',
				'desc' => 'Enter your Live Public Key here',
				'type' => 'text',
				'size' => 'regular',
			),
			array(
				'id'   => 'edd_payfast_passphrase',
				'name' => 'Passphrase',
				'desc' => 'Enter your Passphrase here',
				'type' => 'text',
				'size' => 'regular',
			)
		);

		if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
			$payfast_settings = array( 'payfast-settings' => $payfast_settings );
		}

		return array_merge( $settings, $payfast_settings );

	}

	function register_gateway( $gateways ) {

		$gateways['payfast'] = array(
			'admin_label'    => 'Payfast',
			'checkout_label' => 'Payfast',
		);

		return $gateways;

	}


}

function edd_payfast() {

	if( ! function_exists( 'EDD' ) ) {
		return;
	}

	new EDD_Payfast();
}
add_action( 'plugins_loaded', 'edd_payfast', 10 );



function pps_payfast_keys() {

	if ( edd_get_option( 'edd_payfast_test_mode' ) ) {

		$url = 'https://sandbox.payfast.co.za/eng/process';
		$merchant_id = trim( edd_get_option( 'edd_payfast_test_merchant_id' ) );
		$merchant_key = trim( edd_get_option( 'edd_payfast_test_merchant_key' ) );

	} else {

		$url = 'https://www.payfast.co.za/eng/process';
		$merchant_id = trim( edd_get_option( 'edd_payfast_live_merchant_id' ) );
		$merchant_key = trim( edd_get_option( 'edd_payfast_live_merchant_key' ) );

	}

	$passphrase = trim( edd_get_option( 'edd_payfast_passphrase' ) );

	if ( empty( $merchant_id ) || empty( $merchant_key ) || empty( $passphrase ) ) {
		return array();
	}

	return array(
		'url' 			=> $url,
		'merchant_id' 	=> $merchant_id,
		'merchant_key' 	=> $merchant_key,
		'passphrase'	=> $passphrase
	);

}

function pps_edd_payfast_plugin_action_links( $links ) {

	$settings_link = array(
		'settings' => '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=payfast-settings' ) . '" title="Settings">Settings</a>',
	);
	
	return array_merge( $settings_link, $links );
	
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pps_edd_payfast_plugin_action_links' );

function pps_edd_payfast_process_payment( $purchase_data ) {

    $payfast_keys = pps_payfast_keys();

    $once_off_products = array( 'billable' => 0, 'billing_description' => array(), 'products' => array() );

    $subscription_products = array( 'signup' => 0, 'billable' => 0, 'billing_description' => array(), 'products' => array() );

    if( !empty( $purchase_data['cart_details'] ) ){

        foreach( $purchase_data['cart_details'] as $cart ){

            if( !empty( $cart['item_number']['options'] ) && !empty( $cart['item_number']['options']['recurring'] ) ){
                //Recurring purchase
                $billable = $subscription_products['billable'];				

                $subscription_products['billable'] = $billable + $cart['price'];
                $once_off_products['billing_description'][] = $cart['name'];

                $period = $cart['item_number']['options']['recurring']['period'];

                $frequency = 3;

                if( $period === 'month' ){
                    $frequency = 3;
                } else if( $period === 'year' ){
                    $frequency = 6;
                }

                $once_off = $once_off_products['billable'];

                $once_off_products['billable'] = $once_off + $cart['item_number']['options']['recurring']['signup_fee'];
                
                $subscription_products['products'][] = array(
                	'id' 	=> $cart['item_number']['id'],
                    'name' 	=> $cart['name'],
                    'total'	=> $cart['price'],
                    'frequency' => $frequency,
                    'cycles' => $cart['item_number']['options']['recurring']['times'],
                    'signup' => $cart['item_number']['options']['recurring']['signup_fee']
                );

            } else {
                //Once off purchase
                
                $billable = $once_off_products['billable'];				

                $once_off_products['billable'] = $billable + $cart['price'];
                $once_off_products['billing_description'][] = $cart['name'];

                $once_off_products['products'][] = array(
                	'id' 	=> $cart['item_number']['id'],
                    'name' 	=> $cart['name'],
                    'total'	=> $cart['price']
                );

            }
        }
    }

    $body = array(
        'merchant_id' 	=> $payfast_keys['merchant_id'],
        'merchant_key'	=> $payfast_keys['merchant_key'],
        'return_url'	=> add_query_arg( 'payfast-listener', 'return', admin_url( 'admin-ajax.php') ),
        'cancel_url' 	=> add_query_arg( 'payfast-listener', 'cancel', admin_url( 'admin-ajax.php') ),
        'notify_url'	=> add_query_arg( 'payfast-listener', 'notify', admin_url( 'admin-ajax.php') ),

        'name_first'	=> $purchase_data['post_data']['edd_first'],
        'name_last'		=> $purchase_data['post_data']['edd_last'],
        'email_address'	=> $purchase_data['post_data']['edd_email'],		

    );

    $payment_data = array(
        'price'        => $once_off_products['billable'],
        'date'         => $purchase_data['date'],
        'user_email'   => $purchase_data['user_email'],
        'purchase_key' => $purchase_data['purchase_key'],
        'currency'     => edd_get_currency(),
        'downloads'    => $purchase_data['downloads'],
        'cart_details' => $purchase_data['cart_details'],
        'user_info'    => $purchase_data['user_info'],
        'status'       => 'pending',
        'gateway'      => 'payfast',
    );

    $payment = edd_insert_payment( $payment_data );

    $subscription_count = 0;

    if( !empty( $subscription_products['products'] ) ){
        $subscription_count = count( $subscription_products['products'] );
    }

    if ( ! $payment ) {

        edd_record_gateway_error( 'Payment Error', sprintf( 'Payment creation failed before sending buyer to Payfast. Payment data: %s', json_encode( $payment_data ) ), $payment );

        edd_send_back_to_checkout( '?payment-mode=payfast&error=payment_creation_failed' );

    } else if( $subscription_count > 1 ) {

        edd_record_gateway_error( 'Payment Error', sprintf( 'We cannot create more than one subscription during checkout. Payment data: %s', json_encode( $payment_data ) ), $payment );

        edd_send_back_to_checkout( '?payment-mode=payfast&error=multiple_subscriptions' );

    } else {

        $body['m_payment_id'] = 'EDD-' . $payment . '-' . uniqid();

        $body['amount'] = number_format( sprintf( '%.2f', $once_off_products['billable'] ) );
        $body['item_name'] = implode( ", ", $once_off_products['billing_description'] );        

        if( !empty($subscription_products['products'] ) ){

            $body['subscription_type'] = 1;

            // $body['billing_date'] = '2021-02-14';
            
            $body['recurring_amount'] = $once_off_products['billable'];
            /**
             * 3 - Monthly
             * 4 - Quarterly
             * 5 - Biannually
             * 6 - Annually
             */
            $body['frequency'] = $subscription_products['products'][0]['frequency'];

            switch( $body['frequency'] ){
            	case 3:
            		$frequency_string = 'month';
        			break;
    			case 6:
    				$frequency = 'year';
    				break;
				default:
					$frequency = 'month';
            }
            /**
             * 0 - Unlimited
             * 
             */
            $body['cycles'] = $subscription_products['products'][0]['cycles'];

            $body['subscription_type'] = 1;

            $subscriber = new EDD_Recurring_Subscriber( $purchase_data['user_email'] );

            $subscriber_data = array(
            	'name'        => $purchase_data['post_data']['edd_first'] . ' '. $purchase_data['post_data']['edd_last'],
            	'email'       => $purchase_data['user_email'],
            	'user_id'     => $purchase_data['user_info']['id'],
            );

            $subscriber_created = $subscriber->create( $subscriber_data );

            $data = array(
			    'customer_id'       => $subscriber->id, // an integer, should be a valid customer_id
			    'period'            => $frequency_string, // accepts 'day', 'week', 'month', or 'year'; how often the subscription renews
			    'initial_amount'    => $once_off_products['billable'], // accepts a float
			    'recurring_amount'  => $subscription_products['billable'], // accepts a float
			    'bill_times'        => $subscription_products['products'][0]['cycles'], // accepts an integer; the number of times billing should happen, 0 means indefinite
			    'parent_payment_id' => $payment, // accepts an integer; the payment id returned by the initial payment
			    'product_id'        => $subscription_products['products'][0]['id'], // accepts an integer; the id of the product
			    'created'           => date( 'Y-m-d H:i:s', current_time('timestamp') ), // accepts a date string; formatted like 0000-00-00 00:00:00
			    'expiration'        => '0000-00-00 00:00:00', // accepts a date string; formatted like 0000-00-00 00:00:00
			    'status'            => 'Pending', // accepts 'Pending', 'Active', 'Cancelled', 'Expired', 'Failing', 'Completed'
			    'profile_id'        => '', // accepts a string returned by the payment gateway as their subscription ID
			);

            $subscription = new EDD_Subscription;

			$subscription_created = $subscription->create( $data );

			if( !empty( $subscription_created->id ) ){
				$_SESSION['edd_pf_subid'] = $subscription_created->id;
			}

        }

        $signature = pps_edd_generate_signature( $body, $payfast_keys['passphrase'] );

        $body['signature'] = $signature;

        $payfast_data = array();

        $payfast_data['amount']    = $once_off_products['billable'];
        $payfast_data['email']     = $purchase_data['user_email'];
        $payfast_data['reference'] = 'EDD-' . $payment . '-' . uniqid();

        edd_set_payment_transaction_id( $payment, $payfast_data['reference'] );

        $curl = curl_init( $payfast_keys['url'] );
        curl_setopt( $curl, CURLOPT_POSTFIELDS, $body );
        curl_setopt( $curl, CURLOPT_POSTREDIR, true );		
        curl_setopt($curl, CURLOPT_HEADER, 0 );
        curl_exec( $curl );


    }

}
add_action( 'edd_gateway_payfast', 'pps_edd_payfast_process_payment' );


function pps_edd_generate_signature($data, $passPhrase = null) {
    // Create parameter string
    $pfOutput = '';
    foreach( $data as $key => $val ) {
        if(!empty($val) || $val === 0 ) {
            $pfOutput .= $key .'='. urlencode( trim( $val ) ) .'&';
        }
    }

    // Remove last ampersand
    $getString = substr( $pfOutput, 0, -1 );
    if( $passPhrase !== null ) {
        $getString .= '&passphrase='. urlencode( trim( $passPhrase ) );
    }
    
    return md5( $getString );
} 



function pps_edd_payfast_verify_payment(){

    $keys = pps_payfast_keys();

    $body = array(
        'm_payment_id'		=> $_REQUEST['m_payment_id'], 
        'pf_payment_id'		=> $_REQUEST['pf_payment_id'], 
        'payment_status' 	=> $_REQUEST['payment_status'],
        'item_name'			=> $_REQUEST['item_name'],
        'amount_gross'		=> $_REQUEST['amount_gross'],
        'amount_fee'		=> $_REQUEST['amount_fee'],
        'amount_net'		=> $_REQUEST['amount_net'],
        'merchant_id'		=> $_REQUEST['merchant_id'],
    );

}

function pps_edd_payfast_redirect_verify() {

    if ( isset( $_REQUEST['trxref'] ) ) {

        $transaction_id = $_REQUEST['trxref'];

        $the_payment_id = edd_get_purchase_id_by_transaction_id( $transaction_id );

        if ( $the_payment_id && get_post_status( $the_payment_id ) == 'publish' ) {

            edd_empty_cart();

            edd_send_to_success_page();
        }

        $payfast_txn = pps_edd_payfast_verify_transaction( $transaction_id );

        $order_info = explode( '-', $transaction_id );

        $payment_id = $order_info[1];

        if ( $payment_id && ! empty( $payfast_txn->data ) && ( $payfast_txn->data->status === 'success' ) ) {

            $payment = new EDD_Payment( $payment_id );

            $order_total = edd_get_payment_amount( $payment_id );

            $currency_symbol = edd_currency_symbol( $payment->currency );

            $amount_paid = $payfast_txn->data->amount / 100;

            $payfast_txn_ref = $payfast_txn->data->reference;

            if ( $amount_paid < $order_total ) {

                $note = 'Look into this purchase. This order is currently revoked. Reason: Amount paid is less than the total order amount. Amount Paid was ' . $currency_symbol . $amount_paid . ' while the total order amount is ' . $currency_symbol . $order_total . '. Payfast Transaction Reference: ' . $payfast_txn_ref;

                $payment->status = 'revoked';

                $payment->add_note( $note );

                $payment->transaction_id = $payfast_txn_ref;

            } else {

                $note = 'Payment transaction was successful. Payfast Transaction Reference: ' . $payfast_txn_ref;

                $payment->status = 'publish';

                $payment->add_note( $note );

                $payment->transaction_id = $payfast_txn_ref;

            }

            $payment->save();

            edd_empty_cart();

            edd_send_to_success_page();

        } else {

            edd_set_error( 'failed_payment', 'Payment failed. Please try again.' );

            edd_send_back_to_checkout( '?payment-mode=payfast' );

        }
    }

}
add_action( 'pps_edd_payfast_redirect_verify', 'pps_edd_payfast_redirect_verify' );


function pps_edd_payfast_ipn_verify() {

    if ( ( strtoupper( $_SERVER['REQUEST_METHOD'] ) != 'POST' ) || ! array_key_exists( 'HTTP_X_PAYFAST_SIGNATURE', $_SERVER ) ) {
        exit;
    }

    $json = file_get_contents( 'php://input' );

    if ( edd_get_option( 'edd_payfast_test_mode' ) ) {

        $secret_key = trim( edd_get_option( 'edd_payfast_test_secret_key' ) );

    } else {

        $secret_key = trim( edd_get_option( 'edd_payfast_live_secret_key' ) );

    }

    // validate event do all at once to avoid timing attack
    if ( $_SERVER['HTTP_X_PAYFAST_SIGNATURE'] !== hash_hmac( 'sha512', $json, $secret_key ) ) {
        exit;
    }

    $event = json_decode( $json );

    if ( 'charge.success' == $event->event ) {

        http_response_code( 200 );

        $transaction_id = $event->data->reference;

        $the_payment_id = edd_get_purchase_id_by_transaction_id( $transaction_id );

        if ( $the_payment_id && get_post_status( $the_payment_id ) == 'publish' ) {
            exit;
        }

        $order_info = explode( '-', $transaction_id );

        $payment_id = $order_info[1];

        $saved_txn_ref = edd_get_payment_transaction_id( $payment_id );

        if ( $event->data->reference != $saved_txn_ref ) {
            exit;
        }

        $payment = new EDD_Payment( $payment_id );

        $order_total = edd_get_payment_amount( $payment_id );

        $currency_symbol = edd_currency_symbol( $payment->currency );

        $amount_paid = $event->data->amount / 100;

        $payfast_txn_ref = $event->data->reference;

        if ( $amount_paid < $order_total ) {

            $note = 'Look into this purchase. This order is currently revoked. Reason: Amount paid is less than the total order amount. Amount Paid was ' . $currency_symbol . $amount_paid . ' while the total order amount is ' . $currency_symbol . $order_total . '. Payfast Transaction Reference: ' . $payfast_txn_ref;

            $payment->status = 'revoked';

            $payment->add_note( $note );

            $payment->transaction_id = $payfast_txn_ref;

        } else {

            $note = 'Payment transaction was successful. Payfast Transaction Reference: ' . $payfast_txn_ref;

            $payment->status = 'publish';

            $payment->add_note( $note );

            $payment->transaction_id = $payfast_txn_ref;

        }

        $payment->save();

        exit;
    }

    exit;
}
add_action( 'pps_edd_payfast_ipn_verify', 'pps_edd_payfast_ipn_verify' );


function pps_edd_payfast_verify_transaction( $payment_token ) {

    $payfast_url = 'https://api.payfast.co/transaction/verify/' . $payment_token;

    if ( edd_get_option( 'edd_payfast_test_mode' ) ) {

        $secret_key = trim( edd_get_option( 'edd_payfast_test_secret_key' ) );

    } else {

        $secret_key = trim( edd_get_option( 'edd_payfast_live_secret_key' ) );

    }

    $headers = array(
        'Authorization' => 'Bearer ' . $secret_key,
    );

    $args = array(
        'headers' => $headers,
        'timeout' => 60,
    );

    $request = wp_remote_get( $payfast_url, $args );

    if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {

        $payfast_response = json_decode( wp_remote_retrieve_body( $request ) );

    } else {

        $payfast_response = json_decode( wp_remote_retrieve_body( $request ) );

    }

    return $payfast_response;

}


function pps_edd_payfast_testmode_notice() {

    if ( edd_get_option( 'edd_payfast_test_mode' ) ) {
        ?>
        <div class="error">
            <p>Payfast testmode is still enabled for EDD, click <a href="<?php echo get_bloginfo( 'wpurl' ); ?>/wp-admin/edit.php?post_type=download&page=edd-settings&tab=gateways&section=payfast-settings">here</a> to disable it when you want to start accepting live payment on your site.</p>
        </div>
        <?php
    }

}
add_action( 'admin_notices', 'pps_edd_payfast_testmode_notice' );


function pps_edd_payfast_payment_icons( $icons ) {

    $icons[ PPS_EDD_PAYFAST_URL . 'assets/images/payfast.png' ] = 'Payfast';

    return $icons;

}
add_filter( 'edd_accepted_payment_icons', 'pps_edd_payfast_payment_icons' );


function pps_edd_payfast_extra_edd_currencies( $currencies ) {

    $currencies['ZAR'] = 'South African Rand (R)';

    return $currencies;

}
add_filter( 'edd_currencies', 'pps_edd_payfast_extra_edd_currencies' );


function pps_edd_payfast_extra_currency_symbol( $symbol, $currency ) {

    switch ( $currency ) {
        case 'ZAR':
            $symbol = 'R';
            break;
    }

    return $symbol;

}
add_filter( 'edd_currency_symbol', 'pps_edd_payfast_extra_currency_symbol', 10, 2 );


function pps_edd_payfast_format_ngn_currency_before( $formatted, $currency, $price ) {

    $symbol = edd_currency_symbol( $currency );

    return $symbol . $price;
}
add_filter( 'edd_zar_currency_filter_before', 'pps_edd_payfast_format_ngn_currency_before', 10, 3 );


function pps_edd_payfast_check_config() {

	$is_enabled = edd_is_gateway_active( 'payfast' );

	if ( ( ! $is_enabled || empty( pps_payfast_keys() ) ) && 'payfast' == edd_get_chosen_gateway() ) {
		edd_set_error( 'payfast_gateway_not_configured', 'There is an error with the Payfast configuration.' );
	}

	if ( ! in_array( edd_get_currency(), array( 'ZAR' ) ) && 'payfast' == edd_get_chosen_gateway() ) {
		edd_set_error( 'payfast_gateway_invalid_currency', 'Set your store currency to ZAR (R)' );
	}

}
add_action( 'edd_pre_process_purchase', 'pps_edd_payfast_check_config', 1 );

