<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * Update Donation amount for sorting purposes
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Extension
 * @author		Kevin Chatel
 * @link		http://www.signatureweb.ca
 */

class Sw_transaction_details_ext {
	
	public $settings 		= array();
	public $description		= 'Once the order is placed we email the people who paid by credit card their tax receipt.';
	public $docs_url		= '';
	public $name			= 'Transaction details';
	public $settings_exist	= 'n';
	public $version			= '1.0';
	
	private $EE;
	
	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	public function __construct($settings = '')
	{
		$this->EE =& get_instance();
		$this->settings = $settings;
	}// ----------------------------------------------------------------------
	
	/**
	 * Activate Extension
	 *
	 * This function enters the extension into the exp_extensions table
	 *
	 * @see http://codeigniter.com/user_guide/database/index.html for
	 * more information on the db class.
	 *
	 * @return void
	 */
	public function activate_extension()
	{
		// Setup custom settings in this array.
		$this->settings = array();
		
		$data = array(
			'class'		=> __CLASS__,
			'method'	=> 'email',
			'hook'		=> 'store_transaction_update_end',
			'settings'	=> serialize($this->settings),
			'version'	=> $this->version,
			'enabled'	=> 'y'
		);

		$this->EE->db->insert('extensions', $data);			
		
	}

	// ----------------------------------------------------------------------
	
	/**
	* check
	*
	* @param
	* @return
	*/

	public function email($transaction, $response)
	{
		if($transaction['payment_method'] == 'moneris') {
			if($response->isSuccessful()) {

				$entryID = $this->getEntryId($transaction->order['id']);

				// Load the email library
				ee()->load->library('email');
				//Load the email Helper
				ee()->load->helper('text');

				ee()->load->helper('date');

				$datestring = "%F %d, %Y at %h:%i %a";

				$body = '<strong>On behalf of</strong>: '.$transaction->order["billing_first_name"].' '.$transaction->order["billing_last_name"].' <br />';
				$body = $body.'<strong>Tax receipt to</strong>: '.$transaction->order["order_custom3"].' <br />';
				$body = $body.'<strong>Company</strong>: '.$transaction->order["order_custom2"].'<br />';
				$body = $body.'<strong>Address</strong>: '.$transaction->order["billing_address1"].' ' .$transaction->order["billing_address2"].'<br />';
				$body = $body.'<strong>City</strong>: '.$transaction->order["billing_city"].'<br />';
				$body = $body.'<strong>State/Province</strong>: '.$transaction->order["billing_state"].'<br />';
				$body = $body.'<strong>Country</strong>: '.$transaction->order["billing_country"].'<br />';
				$body = $body.'<strong>ZipCode</strong>: '.$transaction->order["billing_postcode"].'<br />';
				$body = $body.'<strong>Tel</strong>: '.$transaction->order["billing_phone"].'<br />';
				$body = $body.'<strong>Email</strong>: <a href="mailto:'.$transaction->order["order_email"].'">'.$transaction->order["order_email"].'</a><br />'; 


				$body = $body.'<strong>Method</strong>: Credit Card <br />';
				$body = $body.'<strong>Card Type</strong>: '.$response->getCardDetails()->getBrand().'<br />';
				$body = $body.'<strong>Name on Card</strong>: '.$response->getCardDetails()->getName().' <br />';


				$body = $body.'<strong>Donate Date</strong>: '.mdate($datestring, $transaction->order["order_date"]).'<br />';
				$body = $body.'<strong>Donation</strong>: $'.number_format($transaction->order["order_total"], 2, ".", "").'<br />';

				$body = $body.'<strong>Donation for</strong>: '.$this->getTeamName($entryID).' <br />';
                $body = $body.'<strong>Moneris Order No.</strong>: SR-ORD'.$transaction->order['id'].'<br />';
                
				ee()->email->wordwrap = true;
				ee()->email->mailtype = 'html';
				ee()->email->from($transaction->order["order_email"], $transaction->order["billing_first_name"].' '.$transaction->order["billing_last_name"]);
				ee()->email->to('info@sinairally.org');
				ee()->email->cc('kara.m@sympatico.ca,kara.maritzer@gmail.com');
				ee()->email->bcc('transactions@signatureweb.ca,linda.kurbel@mountsinaifoundation.org');


				ee()->email->subject('Sinai Rally Donation Details - '.$this->getTeamName($entryID));

				ee()->email->message($body);
				ee()->email->Send();

			}
		} else {
			$entryID = $this->getEntryId($transaction->order['id']);

			// Load the email library
			ee()->load->library('email');
			//Load the email Helper
			ee()->load->helper('text');

			ee()->load->helper('date');

			$datestring = "%F %d, %Y at %h:%i %a";

			$body = '<strong>On behalf of</strong>: '.$transaction->order["billing_first_name"].' '.$transaction->order["billing_last_name"].' <br />';
			$body = $body.'<strong>Tax receipt to</strong>: '.$transaction->order["order_custom3"].' <br />';
			$body = $body.'<strong>Company</strong>: '.$transaction->order["order_custom2"].'<br />';
			$body = $body.'<strong>Address</strong>: '.$transaction->order["billing_address1"].' ' .$transaction->order["billing_address2"].'<br />';
			$body = $body.'<strong>City</strong>: '.$transaction->order["billing_city"].'<br />';
			$body = $body.'<strong>State/Province</strong>: '.$transaction->order["billing_state"].'<br />';
			$body = $body.'<strong>Country</strong>: '.$transaction->order["billing_country"].'<br />';
			$body = $body.'<strong>ZipCode</strong>: '.$transaction->order["billing_postcode"].'<br />';
			$body = $body.'<strong>Tel</strong>: '.$transaction->order["billing_phone"].'<br />';
			$body = $body.'<strong>Email</strong>: <a href="mailto:'.$transaction->order["order_email"].'">'.$transaction->order["order_email"].'</a><br />'; 


			$body = $body.'<strong>Method</strong>: Bill Me / I will pay by cheque <br />';

			$body = $body.'<strong>Donate Date</strong>: '.mdate($datestring, $transaction->order["order_date"]).'<br />';
			$body = $body.'<strong>Donation</strong>: $'.number_format($transaction->order["order_total"], 2, ".", "").'<br />';

			$body = $body.'<strong>Donation for</strong>: '.$this->getTeamName($entryID).' <br />';
            

			ee()->email->wordwrap = true;
			ee()->email->mailtype = 'html';
			ee()->email->from($transaction->order["order_email"], $transaction->order["billing_first_name"].' '.$transaction->order["billing_last_name"]);
			ee()->email->to('info@sinairally.org');
			ee()->email->cc('kara.m@sympatico.ca,kara.maritzer@gmail.com');
			ee()->email->bcc('transactions@signatureweb.ca,linda.kurbel@mountsinaifoundation.org');


			ee()->email->subject('Sinai Rally Donation Details - '.$this->getTeamName($entryID));

			ee()->email->message($body);
			ee()->email->Send();
		}
		
		
	}

	// ----------------------------------------------------------------------
	
	function getEntryId($orderID)
	{
		
		$this->EE->db->select('exp_store_order_items.entry_id');
		$this->EE->db->from('exp_store_order_items');
		$this->EE->db->join('exp_store_orders', 'exp_store_order_items.order_id = exp_store_orders.id', 'inner');
		$this->EE->db->where('exp_store_order_items.order_id', $orderID);

		$query = $this->EE->db->get();
		$results = $query->result_array();
		
		if($query->num_rows() > 0)
		{
			return $results[0]['entry_id'];
		}
		
		
	}

	function getTeamName($entryID)
	{
		
		$this->EE->db->select('exp_channel_data.field_id_50, 
			exp_channel_data.field_id_16, 
			exp_channel_data.field_id_17');
		$this->EE->db->from('exp_channel_data');
		$this->EE->db->where('exp_channel_data.entry_id', $entryID);

		$query = $this->EE->db->get();
		$results = $query->result_array();
		
		if($query->num_rows() > 0)
		{
			if($entryID != '7')
			{
				return $results[0]['field_id_50'].' ('.$results[0]['field_id_16'].' '.$results[0]['field_id_17'].')';
			} else {
				return $results[0]['field_id_50'];
			}
			
		}
	}

	/**
	 * Disable Extension
	 *
	 * This method removes information from the exp_extensions table
	 *
	 * @return void
	 */
	function disable_extension()
	{
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
	}

	// ----------------------------------------------------------------------

	/**
	 * Update Extension
	 *
	 * This function performs any necessary db updates when the extension
	 * page is visited
	 *
	 * @return 	mixed	void on update / false if none
	 */
	function update_extension($current = '')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
	}	
	
	// ----------------------------------------------------------------------
}

/* End of file ext.email_after_subscription.php */
/* Location: /system/expressionengine/third_party/email_after_subscription/ext.email_after_subscription.php */