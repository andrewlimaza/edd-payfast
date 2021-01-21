<?php

function pps_edd_payfast_redirect() {

    $keys = pps_payfast_keys();

	if( isset( $_REQUEST['payfast-listener'] ) ){

		if( $_REQUEST['payfast-listener'] === 'cancel' ){            

            if( !empty( $_SESSION['edd_pf_payment'] ) ){
                
                if( class_exists( 'EDD_Payment' ) ){

                    $payment = new EDD_Payment( intval( $_SESSION['edd_pf_payment'] ) );
                    $payment->status = 'abandoned';
                    $payment->save();

                }

            }

            if( !empty( $_SESSION['edd_pf_subid'] ) ){

                if( class_exists('EDD_Subscription' ) ){

                    $subscription = new EDD_Subscription( intval( $_SESSION['edd_pf_subid'] ) );
                    $subscription->update( array( 'status' => 'cancelled' ) );

                }

            }

            edd_set_error( 'failed_payment', 'Payment Cancelled.' );

            edd_send_back_to_checkout( array( 'payment_method' => 'payfast', 'payfast-status' => 'cancelled' ) );

		} else if( $_REQUEST['payfast-listener'] === 'notify' ){
            
			$pfData = $_POST;

			$pfParamString = '';

			// Strip any slashes in data
			foreach( $pfData as $key => $val ) {
			    $pfData[$key] = stripslashes( $val );
			}

			// Convert posted variables to a string
			foreach( $pfData as $key => $val ) {
			    if( $key !== 'signature' ) {
			        $pfParamString .= $key .'='. urlencode( $val ) .'&';
			    } else {
			        break;
			    }
			}

			$pfParamString = substr( $pfParamString, 0, -1 ); 

			$check1 = pfValidSignature($pfData, $pfParamString, $keys['passphrase'] );
			$check2 = pfValidIP();
			$check3 = pfValidPaymentData( floatval( $_REQUEST['amount_gross'] ), $pfData );

            if( edd_get_option( 'edd_payfast_test_mode' ) ){
                $payfast_verify_url = 'sandbox.payfast.co.za';
            } else {
                $payfast_verify_url = 'www.payfast.co.za';
            }

			$check4 = pfValidServerConfirmation($pfParamString, $payfast_verify_url );

			if( $check1 && $check2 && $check3 && $check4 ){
			    // All checks have passed, the payment is successful
                
                //Only recurring orders will have this              
                if( !empty( $_REQUEST['token'] ) ){

                    $subscriber = new EDD_Recurring_Subscriber( $_REQUEST['email_address'] );
    
                    $subscriptions = $subscriber->get_subscriptions();    

                    if( !empty( $subscriptions ) ){

                        foreach( $subscriptions as $sub ){
                            
                            $subscription = new EDD_Subscription( $sub->id );

                            $subscription->update( array(
                                'profile_id' => $_REQUEST['token'],
                                'status' => 'active',
                                'transaction_id' => $_REQUEST['pf_payment_id']
                            ) );

                        }
                    }                    
                
                } else {
                    //Once off payments - nothing specific we need to do here right now
                }

                $initial_payment_id = explode( "-", $_REQUEST['m_payment_id'] );

                $edd_payment_id = 0;

                if( !empty( $initial_payment_id[1] ) ){
                    $edd_payment_id = $initial_payment_id[1];
                }

                if( !class_exists( 'EDD_Payment' ) ){
                    return;
                }

                $payment = new EDD_Payment( $edd_payment_id );

                $order_total = edd_get_payment_amount( $edd_payment_id );

                // if ( $_REQUEST['amount_gross'] < $order_total ) {

                //     $note = 'Look into this purchase. This order is currently revoked. Reason: Amount paid is less than the total order amount. Amount Paid was ' . $amount_paid . ' while the total order amount is ' . $order_total . '. Payfast Transaction Reference: ' . $_REQUEST['pf_payment_id'];

                //     $payment->status = 'revoked';

                //     $payment->add_note( $note );

                //     $payment->transaction_id = $_REQUEST['pf_payment_id'];

                // } else {

                    $note = 'Payment transaction was successful. Payfast Transaction Reference: ' . $_REQUEST['pf_payment_id'];

                    $payment->status = 'publish';

                    $payment->add_note( $note );

                    $payment->transaction_id = $_REQUEST['pf_payment_id'];

                // }

                $payment->save();
                 
			} else {
			    // Some checks have failed, check payment manually and log for investigation
                edd_set_error( 'failed_payment', 'Payment failed. Please try again.' );

                edd_send_back_to_checkout( array( 'payment_method' => 'payfast' ) );
			} 

		} else if( $_REQUEST['payfast-listener'] === 'return' ){

            edd_empty_cart();
            
            edd_send_to_success_page();

        }

	}
	
}
add_action( 'init', 'pps_edd_payfast_redirect' );

function pfValidSignature( $pfData, $pfParamString, $pfPassphrase = null ) {

    // Calculate security signature
    if($pfPassphrase === null) {
        $tempParamString = $pfParamString;
    } else {
        $tempParamString = $pfParamString.'&passphrase='.urlencode( $pfPassphrase );
    }

    $signature = md5( $tempParamString );
    return ( $pfData['signature'] === $signature );

} 

function pfValidIP() {
    // Variable initialization
    $validHosts = array(
        'www.payfast.co.za',
        'sandbox.payfast.co.za',
        'w1w.payfast.co.za',
        'w2w.payfast.co.za',
        );

    $validIps = [];

    foreach( $validHosts as $pfHostname ) {
        $ips = gethostbynamel( $pfHostname );

        if( $ips !== false )
            $validIps = array_merge( $validIps, $ips );
    }

    // Remove duplicates
    $validIps = array_unique( $validIps );
    $referrerIp = gethostbyname(parse_url($_SERVER['HTTP_REFERER'])['host']);
    if( in_array( $referrerIp, $validIps, true ) ) {
        return true;
    }
    return false;
} 

function pfValidPaymentData( $cartTotal, $pfData ) {
    return !(abs((float)$cartTotal - (float)$pfData['amount_gross']) > 0.01);
} 

function pfValidServerConfirmation( $pfParamString, $pfHost = 'sandbox.payfast.co.za', $pfProxy = null ) {
    // Use cURL (if available)
    if( in_array( 'curl', get_loaded_extensions(), true ) ) {
        // Variable initialization
        $url = 'https://'. $pfHost .'/eng/query/validate';

        // Create default cURL object
        $ch = curl_init();
    
        // Set cURL options - Use curl_setopt for greater PHP compatibility
        // Base settings
        curl_setopt( $ch, CURLOPT_USERAGENT, NULL );  // Set user agent
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );      // Return output as string rather than outputting it
        curl_setopt( $ch, CURLOPT_HEADER, false );             // Don't include header in output
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
        
        // Standard settings
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $pfParamString );
        if( !empty( $pfProxy ) )
            curl_setopt( $ch, CURLOPT_PROXY, $pfProxy );
    
        // Execute cURL
        $response = curl_exec( $ch );
        curl_close( $ch );
        if ($response === 'VALID') {
            return true;
        }
    }
    return false;
} 
