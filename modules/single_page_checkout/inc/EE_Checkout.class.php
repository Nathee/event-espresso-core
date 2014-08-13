<?php if ( ! defined('EVENT_ESPRESSO_VERSION')) { exit('No direct script access allowed'); }
 /**
 *
 * Class EE_Checkout
 *
 * Description
 *
 * @package 			Event Espresso
 * @subpackage    core
 * @author				Brent Christensen
 * @since 				4.5.0
 *
 */
class EE_Checkout {

	/**
	 * array of EE_SPCO_Reg_Step objects
	 * @type EE_SPCO_Reg_Step[]
	 */
	public $reg_steps = array();

	/**
	 * where we are in the reg process
	 * @type EE_SPCO_Reg_Step
	 */
	public $current_step = '';

	/**
	 * where we are going next in the reg process
	 * @type EE_SPCO_Reg_Step
	 */
	public $next_step = '';

	/**
	 * the action being performed on the current step
	 * @type string
	 */
	public $action = '';

	/**
	 * base url for the site's registration checkout page - additional url params will be added to this
	 * @type string
	 */
	public $reg_page_base_url = '';

	/**
	 * base url for the site's registration cancelled page - additional url params will be added to this
	 * @type string
	 */
	public $cancel_page_url = '';

	/**
	 * base url for the site's thank you page - additional url params will be added to this
	 * @type string
	 */
	public $thank_you_page_url = '';

	/**
	 * redirect to thank you page ?
	 * @type bool
	 */
	public $redirect = FALSE;

	/**
	 * base url for any redirects - additional url params will be added to this
	 * @type string
	 */
	public $redirect_url = '';

	/**
	 * form of POST data for use with off-site gateways
	 * @type string
	 */
	public $redirect_form = '';

	/**
	 * array of data to be passed back to the client during AJAX requests
	 * @type array
	 */
	public $json_response = array();

	/**
	 * reg_url_link for a previously saved registration
	 * @type string
	 */
	public $reg_url_link = '';

	/**
	 * whether returning to edit attendee information or to retry a payment
	 * @type bool
	 */
	public $revisit = FALSE;

	/**
	 * whether the primary registrant is returning to edit attendee information or to retry a payment
	 * @type bool
	 */
	public $primary_revisit = FALSE;

	/**
	 * is registration allowed to progress or halted for some reason such as failing to pass recaptcha?
	 * @type bool
	 */
	public $continue_reg = TRUE;

	/**
	 * 	$_cart - the current cart object
	 * 	@access private
	 *	@var EE_CART $_cart
	 */
	public $cart = NULL;

	/**
	 * 	$_transaction - the current transaction object
	 * 	@access private
	 *	@var EE_Transaction $_transaction
	 */
	public $transaction = NULL;

	/**
	 * 	the related attendee object for the primary registrant
	 * @type EE_Attendee
	 */
	public $primary_attendee_obj = NULL;

	/**
	 * string slug for the payment method that was selected during the payment options step
	 * @type string
	 */
	public $selected_method_of_payment = NULL;

	/**
	 *	$_payment_method - the payment method object for the selected method of payment
	 * 	@access private
	 *	@var EE_Payment_Method $_payment_method
	 */
	public $payment_method = NULL;

	/**
	 * 	if a payment method was selected that uses an on-site gateway, then this is the billing form
	 * @type EE_Billing_Info_Form
	 */
	public $billing_form = NULL;

	/**
	 * 	the entire registration form composed of ALL of the subsections generated by the various reg steps
	 * @type EE_Form_Section_Proper
	 */
	public $registration_form = NULL;

	/**
	 * 	the entire registration form composed of ALL of the subsections generated by the various reg steps
	 * @type EE_Form_Section_Proper
	 */
	public $admin_request = FALSE;



	/**
	 *    class constructor
	 *
	 * @access    public
	 * @return    \EE_Checkout
	 */
	public function __construct(  ) {
		$this->reg_page_base_url = EE_Registry::instance()->CFG->core->reg_page_url();
		$this->thank_you_page_url = EE_Registry::instance()->CFG->core->thank_you_page_url();
		$this->cancel_page_url = EE_Registry::instance()->CFG->core->cancel_page_url();
		$this->continue_reg = apply_filters( 'FHEE__EE_Checkout___construct___continue_reg', TRUE );
		$this->admin_request = is_admin() && ! EE_Registry::instance()->REQ->front_ajax;
	}



	/**
	 *    add_reg_step
	 *
	 * @access    public
	 * @param EE_SPCO_Reg_Step $reg_step_obj
	 * @return    void
	 */
	public function add_reg_step( EE_SPCO_Reg_Step $reg_step_obj ) {
		$this->reg_steps[ $reg_step_obj->slug()  ] = $reg_step_obj;
	}



	/**
	 *    remove_reg_step
	 *
	 * @access    public
	 * @param string $reg_step_slug
	 * @return    void
	 */
	public function remove_reg_step( $reg_step_slug = '' ) {
		unset( $this->reg_steps[ $reg_step_slug  ] );
	}



	/**
	 *    set_reg_step_order
	 *
	 * @access    public
	 * @param string $reg_step_slug
	 * @param int    $order
	 * @return    void
	 */
	public function set_reg_step_order( $reg_step_slug = '', $order = 100 ) {
		if ( isset( $this->reg_steps[ $reg_step_slug  ] )) {
			$this->reg_steps[ $reg_step_slug ]->set_order( $order );
		}
	}



	/**
	 *    set_current_step
	 *
	 * @access    public
	 * @param string $current_step
	 * @return    void
	 */
	public function set_current_step( $current_step = 'attendee_information' ) {
		// grab what step we're on
		$this->current_step = isset( $this->reg_steps[ $current_step ] ) ? $this->reg_steps[ $current_step ] : reset( $this->reg_steps );
		if ( $this->current_step instanceof EE_SPCO_Reg_Step ) {
			$this->current_step->set_is_current_step( TRUE );
		} else {
			EE_Error::add_error(
				__( 'The current step could not be set.', 'event_espresso' ),
				__FILE__, __FUNCTION__, __LINE__
			);
		}
	}



	/**
	 * 	set_next_step
	 * advances the reg_steps array pointer and sets the next step, then reverses pointer back to the current step
	 *
	 *  @access 	public
	 *  @return 	void
	 */
	public function set_next_step() {
		// set pointer to start of array
		reset( $this->reg_steps );
		// if there is more than one step
		if ( count( $this->reg_steps ) > 1 ) { //  && $this->current_step->slug() != 'finalize_registration'
			// advance to the current step and set pointer
			while ( key( $this->reg_steps ) != $this->current_step->slug() && key( $this->reg_steps ) != '' ) {
				next( $this->reg_steps );
			}
		}
		// advance one more spot ( if it exists )
		$this->next_step = next( $this->reg_steps );
		// verify instance
		$this->next_step = $this->next_step instanceof EE_SPCO_Reg_Step && $this->current_step->slug() != 'finalize_registration' ? $this->next_step  : NULL;
		// then back to current step to reset
		prev( $this->reg_steps );
	}




	/**
	 * 	get_next_reg_step
	 * 	this simply returns the next step from reg_steps array
	 *
	 *  @access 	public
	 *  @return 	EE_SPCO_Reg_Step
	 */
	public function get_next_reg_step() {
		$next = next( $this->reg_steps );
		prev( $this->reg_steps );
		return $next;
	}



	/**
	 * sort_reg_steps
	 *
	 * @access public
	 * @return void
	 */
	public function sort_reg_steps() {
		$reg_step_sorting_callback = apply_filters( 'FHEE__EE_Checkout__sort_reg_steps__reg_step_sorting_callback', 'reg_step_sorting_callback' );
		uasort( $this->reg_steps, array( $this, $reg_step_sorting_callback ));
	}



	/**
	 * reg_step_sorting_callback
	 *
	 * @access public
	 * @param EE_SPCO_Reg_Step $reg_step_A
	 * @param EE_SPCO_Reg_Step $reg_step_B
	 * @return array()
	 */
	public function reg_step_sorting_callback( EE_SPCO_Reg_Step $reg_step_A, EE_SPCO_Reg_Step $reg_step_B ) {
		// send finalize_registration step to the end of the array
		if ( $reg_step_A->slug() == 'finalize_registration' ) {
			return 1;
		} else if ( $reg_step_B->slug() == 'finalize_registration' ) {
			return -1;
		}
		if ( $reg_step_A->order() == $reg_step_B->order() ) {
			return 0;
		}
		return ( $reg_step_A->order() > $reg_step_B->order() ) ? 1 : -1;
	}



	/**
	 * 	stash_transaction_and_checkout
	 *
	 * 	@access public
	 * 	@return 	bool
	 */
	public function stash_transaction_and_checkout() {
		if ( ! $this->reg_url_link ) {
			EE_Registry::instance()->SSN->set_session_data(
				array(
					'checkout' => $this,
					'transaction' => $this->transaction
				)
			);
		}
	}



	/**
	 * 	toggle_transaction_status
	 * 	changes TXN status based on monies owing
	 *
	 * 	@access public
	 * 	@return 	void
	 */
	public function toggle_transaction_status() {
		// if TXN status has not been updated already due to a payment, and is still set as "failed"...
		if ( $this->transaction->status_ID() == EEM_Transaction::failed_status_code ) {
			//but monies are still owing...
			if ( $this->transaction->total() > 0 ) {
				// then update to incomplete
				$this->transaction->set_status( EEM_Transaction::incomplete_status_code );
			} else {
				// or update to complete
				$this->transaction->set_status( EEM_Transaction::complete_status_code );
			}
		}
		$this->transaction->save();
	}



	/**
	 * 	save_all_data
	 * 	simply loops through the current transaction and saves all data for each registration
	 *
	 * 	@access public
	 * 	@return 	bool
	 */
	public function save_all_data() {
		// verify the transaction
		if ( $this->transaction instanceof EE_Transaction ) {
//			d( $this->transaction );
			$this->transaction->save();
			// grab the saved registrations from the transaction
			foreach ( $this->transaction->registrations( array(), TRUE ) as $line_item_id => $registration ) {  //
//				printr( $registration, '$registration  <br /><span style="font-size:10px;font-weight:normal;">' . __FILE__ . '<br />line no: ' . __LINE__ . '</span>', 'auto' );
//				d( $registration );
				// verify object
				if ( $registration instanceof EE_Registration ) {
					// EITHER a) first time thru SPCO so process ALL registrations
					// OR b) primary registrant is editing info, so process ALL registrations
					// OR b) another registrant is editing info, so ONLY process their registration
					if ( ! $this->revisit || $this->primary_revisit || ( $this->revisit && $this->reg_url_link == $registration->reg_url_link() )) {
						//set TXN ID
						if ( ! $registration->transaction_ID() ) {
							$registration->set_transaction_id( $this->transaction->ID() );
						}
						// verify and save the attendee
						$attendee = $registration->attendee();
						if ( $attendee ) {
							if ( $attendee instanceof EE_Attendee ) {
//								d( $attendee );
//								printr( $attendee, '$attendee  <br /><span style="font-size:10px;font-weight:normal;">' . __FILE__ . '<br />line no: ' . __LINE__ . '</span>', 'auto' );
								$attendee->save();
								$registration->set_attendee_id( $attendee->ID() );
								if ( ! $registration->update_cache_after_object_save( 'Attendee', $attendee )) {
									EE_Error::add_error(
										__( 'The newly saved Attendee object could not be cached on the registration.', 'event_espresso' ),
										__FILE__, __FUNCTION__, __LINE__
									);
									return FALSE;
								}
							} else {
								EE_Error::add_error(
									__( 'An invalid Attendee object was discovered when attempting to save your registration information.', 'event_espresso' ),
									__FILE__, __FUNCTION__, __LINE__
								);
								return FALSE;
							}
						} else {
							EE_Error::add_error(
								__( 'No Attendee object was found when attempting to save your registration information.', 'event_espresso' ),
								__FILE__, __FUNCTION__, __LINE__
							);
							return FALSE;
						}
						// save so that REG has ID
						$registration->save();
						// now save the answers
						foreach ( $registration->answers() as $cache_key => $answer ) {
							// verify object
							if ( $answer instanceof EE_Answer ) {
//								printr( $answer, '$answer  <br /><span style="font-size:10px;font-weight:normal;">' . __FILE__ . '<br />line no: ' . __LINE__ . '</span>', 'auto' );
//								d( $answer );
								$answer->set_registration( $registration->ID() );
								$answer->save();
								if ( ! $registration->update_cache_after_object_save( 'Answer', $answer, $cache_key )) {
									EE_Error::add_error(
										__( 'The newly saved Answer object could not be cached on the registration.', 'event_espresso' ),
										__FILE__, __FUNCTION__, __LINE__
									);
									return FALSE;
								}
							} else {
								EE_Error::add_error(
									__( 'An invalid Answer object was discovered when attempting to save your registration information.', 'event_espresso' ),
									__FILE__, __FUNCTION__, __LINE__
								);
								return FALSE;
							}
						}
						if ( ! $this->transaction->update_cache_after_object_save( 'Registration', $registration, $line_item_id )) {
							EE_Error::add_error( __( 'The newly saved Registration object could not be cached on the Transaction.', 'event_espresso' ), __FILE__, __FUNCTION__, __LINE__);
							return FALSE;
						}
					}
				} else {
					EE_Error::add_error(
						__( 'An invalid Registration object was discovered when attempting to save your registration information.', 'event_espresso' ),
						__FILE__, __FUNCTION__, __LINE__
					);
					return FALSE;
				}
			}
		} else {
			EE_Error::add_error( __( 'A valid Transaction was not found when attempting to save your registration information.', 'event_espresso' ), __FILE__, __FUNCTION__, __LINE__);
			return FALSE;
		}
		return TRUE;
	}



	/**
	 * mark_twain - print debug output
	 * why Mark Twain? Something shouted by crewmen on a Mississippi riverboat to test the depth of the water; a crewman shouts "mark twain!"
	 *
	 * @param $class
	 * @param $func
	 * @param $file
	 * @param $line
	 * @param $extra
	 * @return void
	 */
	function mark_twain( $class, $func, $file, $line, $extra = '' ) {
		if ( EE_Registry::instance()->REQ->front_ajax  ) {
			echo '<br/><span style="color:#2EA2CC;">' . $class . '<span style="font-weight:normal;color:#0074A2"> -> </span>' . $func . '()</span><br/>';
			echo $extra ? $extra . ' <br/>' : '';
			echo '<span style="font-size:9px;font-weight:normal;color:#666">' . $file . '</span>    <b style="font-size:10px;color:#333">  ' . $line . ' </b><br/>';
		}
	}



}
// End of file EE_Checkout.class.php
// Location: /EE_Checkout.class.php