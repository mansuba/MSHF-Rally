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

class Sw_new_team_email_ext {
	
	public $settings 		= array();
	public $description		= 'Email sinai of a new team signup';
	public $docs_url		= '';
	public $name			= 'New Team Signup';
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
			'hook'		=> 'zoo_visitor_register_end',
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

	public function email($member_data, $member_id)
	{
		
		// Load the email library
		ee()->load->library('email');
		//Load the email Helper
		ee()->load->helper('text');
		
		ee()->load->helper('date');
				
		$body = '<h3>New Team Registration</h3>';
		$body = $body.'<p><strong>Team Name</strong>: '.$member_data['team_name'].'<br />';
		$body = $body.'<strong>Member Name</strong>: '.$member_data['member_firstname'].' '.$member_data['member_lastname'].'<br />';
		$body = $body.'<strong>Email Address</strong>: <a href="mailto:'.$member_data['email'].'">'.$member_data['email'].'</a></p>';
		$body = $body.'<strong>Language of registration</strong>: '.$member_data['language_correspondence'].'</p>';
		$body = $body.'<p><strong>Important</strong>: Check in the admin panel to ensure that the team accepts the email, once done their status will change from pending to Member. Only then will donors be able to donate in support of their team.</p>';
		
		ee()->email->wordwrap = true;
		ee()->email->mailtype = 'html';
		ee()->email->from($member_data['email'], $member_data['member_firstname'].' '.$member_data['member_lastname']);
		ee()->email->to('info@sinairally.org');
		ee()->email->cc('kara.m@sympatico.ca,kara.maritzer@gmail.com,linda.kurbel@mountsinaifoundation.org');
		/*ee()->email->to('transactions@signatureweb.ca');*/
		ee()->email->bcc('transactions@signatureweb.ca');
		
		
		ee()->email->subject('New Sinai Rally Team Registration - '.$member_data['team_name']);
		
		ee()->email->message($body);
		ee()->email->Send();
		

	}

	// ----------------------------------------------------------------------

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