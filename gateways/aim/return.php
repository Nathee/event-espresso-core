<?php

function espresso_transactions_aim_get_attendee_id($attendee_id) {
	global $EE_Session;
	return $EE_Session->id();
}
add_filter('filter_hook_espresso_transactions_get_attendee_id', 'espresso_transactions_aim_get_attendee_id');

function espresso_process_aim( $EE_Session ) {
	global $EE_Session, $espresso_wp_user;

	$session_data = $EE_Session->get_session_data();
	$billing_info = $session_data['billing_info'];	

	if ( $billing_info != 'no payment required' ) {

		require_once 'lib/AuthorizeNet.php';
	
		$payment_settings = get_user_meta($espresso_wp_user, 'payment_settings', true);
		$authnet_aim_settings = $payment_settings['aim'];
		$authnet_aim_login_id = $authnet_aim_settings['authnet_aim_login_id'];
		$authnet_aim_transaction_key = $authnet_aim_settings['authnet_aim_transaction_key'];
	
	// Enable test mode if needed
	//4007000000027  <-- test successful visa
	//4222222222222  <-- test failure card number
		if ($authnet_aim_settings['use_sandbox']) {
			define("AUTHORIZENET_SANDBOX", true);
			define("AUTHORIZENET_LOG_FILE", true);
		} else {
			define("AUTHORIZENET_SANDBOX", false);
		}
	
		$reg_info = $session_data['cart']['REG'];
		$primary_attendee = $session_data['primary_attendee'];
	
	//start transaction
		$transaction = new AuthorizeNetAIM($authnet_aim_login_id, $authnet_aim_transaction_key);
	
		$transaction->amount = $session_data['_cart_grand_total_amount'];
		$transaction->card_num = $billing_info['reg-page-billing-card-nmbr']['value'];
		$transaction->exp_date = $billing_info['reg-page-billing-card-exp-date-mnth']['value'].$billing_info['reg-page-billing-card-exp-date-year']['value'];
		$transaction->card_code = $billing_info['reg-page-billing-card-ccv-code']['value'];
		$transaction->first_name = $billing_info['reg-page-billing-fname']['value'];
		$transaction->last_name = $billing_info['reg-page-billing-lname']['value'];
		$transaction->email = $billing_info['reg-page-billing-email']['value'];
		$transaction->address = $billing_info['reg-page-billing-address']['value'];
		$transaction->city = $billing_info['reg-page-billing-city']['value'];
		$transaction->state = $billing_info['reg-page-billing-state']['value'];
		$transaction->zip = $billing_info['reg-page-billing-zip']['value'];
		$transaction->cust_id = $primary_attendee['registration_id']['value'];
		$transaction->invoice_num = $EE_Session->id(); // <<<<<<<<<<<<<<<<<<<<<<< This actually should NOT be generated YET !!! right?? or is it ? and if so from where ?
	
		if ($authnet_aim_settings['test_transactions']) {
			$transaction->test_request = "true";
		}
	
		// create an object to hold payment data
		$payment_data = new stdClass;
	
		$payment_data->txn_type = 'authorize.net AIM';
		$payment_data->payment_status = 'Incomplete';
		$payment_data->txn_id = 0;
		$payment_data->txn_details = 'No response from authorize.net';
		$payment_data = apply_filters('filter_hook_espresso_prepare_event_link', $payment_data);
		$payment_data = apply_filters('filter_hook_espresso_get_total_cost', $payment_data);
	//Capture response
		$response = $transaction->authorizeAndCapture();
	
	
		if (!empty($response)) {
			if ($authnet_aim_settings['use_sandbox']) {
				$payment_data->txn_id = $response->invoice_number;
			} else {
				$payment_data->txn_id = $response->transaction_id;
			}
			//$payment_data->txn_details = serialize($response);
			$txn_details = $response;
			if ($response->approved) {
				$payment_data->payment_status = 'Completed';
			} else {
				$payment_data->payment_status = 'Payment Declined';
			}
		}
		do_action('action_hook_espresso_log', __FILE__, __FUNCTION__, $payment_data->payment_status);
		
		$txn_results = array(
											'approved' => $txn_details->approved ? $txn_details->approved : 0,
											'status' => $payment_data->payment_status,
											'response_msg' => $txn_details->response_reason_text,
											'md5_hash' => $txn_details->md5_hash,
											'details'	=> array(
																				  'transaction_id' => $txn_details->transaction_id,
																				  'invoice_number' => $txn_details->invoice_number,
																		 	),								
										  );
		
		$EE_Session->set_session_data(  array( 'txn_results' => $txn_results ), $section = 'session_data' );
	
		add_action('action_hook_espresso_email_after_payment', 'espresso_email_after_payment');  //<-- Should this be here ? or in the successful txn bit above ( around line 79 ? ) or does this send failed txn info as well /
	
		// return $payment_data;  <<<<-------  do we need to return success or flase or anything ?
		
	} else {
		// no payment required
	}
	
	// return ? or redirect ?
	// we need to update the registration and transaction tables 

}

add_action('action_hook_espresso_process_transaction', 'espresso_process_aim');