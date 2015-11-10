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
 * Email After Subscription Extension
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Extension
 * @author		Kevin Chatel
 * @link		http://www.signatureweb.ca
 */

class Sw_check_stock_ext {
	
	public $settings 		= array();
	public $description		= 'Checks to see if stocks have been removed for Expresso:Store during Zoo Visitor Update';
	public $docs_url		= '';
	public $name			= 'Check Stock';
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
			'method'	=> 'check',
			'hook'		=> 'zoo_visitor_update_end',
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

	public function check($member_data, $member_id)
	{

		if(!$this->validate_stock($member_data[entry_id]))
		{
			$this->reset_stock($member_data[entry_id]);
		}
	}


	function validate_stock($entryID)
	{
		ee()->db->select('entry_id');
		ee()->db->from('exp_store_stock');

		ee()->db->where('entry_id', $entryID);

		$query = ee()->db->get();

		if($query->num_rows() > 0)
		{
			return TRUE;
			
		} else {
			
			return FALSE;
		}
	}


	function reset_stock($entryID)
	{
		$data = array(
			'entry_id' => $entryID,
			'sku' => ''
			);

		ee()->db->insert('exp_store_stock', $data);
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