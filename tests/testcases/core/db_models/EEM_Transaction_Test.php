<?php
/**
 *
 * All tests for the EEM_Transaction class.
 *
 * @author 	Mike Nelson
 * @since 		4.6.x
 * @package 	Event Espresso
 * @subpackage 	tests
 * @group 	core/db_models
 */
class EEM_Transaction_Test extends EE_UnitTestCase{


	/**
	 * @group 7965
	 */
	function test_delete_junk_transactions(){
		$old_txn_count = EEM_Transaction::instance()->count();
		$pretend_bot_creations = 9;
		$pretend_real_recent_txns = 3;
		$pretend_real_good_txns = 5;
		require_once EE_TESTS_DIR . 'mocks' . DS . 'core' . DS . 'EE_Session_Mock.core.php';
		$this->factory->transaction->create_many( $pretend_bot_creations, array( 'TXN_timestamp' => time() - WEEK_IN_SECONDS * 2 , 'STS_ID' => EEM_Transaction::failed_status_code ) );
		$this->factory->transaction->create_many( $pretend_real_recent_txns, array( 'TXN_timestamp' => time() - EE_Session_Mock::instance()->lifespan() + MINUTE_IN_SECONDS , 'STS_ID' => EEM_Transaction::failed_status_code ) );
		$this->factory->transaction->create_many( $pretend_real_good_txns, array( 'STS_ID' => EEM_Transaction::abandoned_status_code ) );
		$num_deleted = EEM_Transaction::instance()->delete_junk_transactions();
		$this->assertEquals( $pretend_bot_creations, $num_deleted );
	}



}
// End of file EEM_Transaction_Test.php