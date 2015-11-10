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

class Sw_form_submit_ext {
	
	public $settings 		= array();
	public $description		= 'Once the update is done, update the inventory.';
	public $docs_url		= '';
	public $name			= 'Update Inventory on Update';
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
			'method'	=> 'update',
			'hook'		=> 'channel_form_submit_entry_end',
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

	public function update($data)
	{
        
        $entry_id = $data->entry['entry_id'];
        
        //echo $this->user($data->entry['author_id']);
        
       if($this->EE->input->post('activation_chk') != $this->EE->input->post('activate_team')) {
            $this->email($data);
        };      
        
        
        //echo '<pre>';
        //print_r($data);
        //echo '</pre>';
        
        //die();
                		
		
		if(!$this->has_inventory($entry_id)){
			$this->updateInventory($entry_id);
		};
		
	}

	// ----------------------------------------------------------------------
	
	function activation_val($entry_id)
    {
        $query = $this->EE->db->select('*')
			->from('exp_channel_data')
			->where('exp_channel_data.entry_id', $entry_id)
			->get()->result_array();

		$activation = $query[0]['field_id_85'];
        
        return $activation;
        
    }
        
    
    function has_inventory($entry_id)
	{
		
		$this->EE->db->select('*');
		$this->EE->db->from('exp_store_stock');
		$this->EE->db->where('exp_store_stock.entry_id', $entry_id);

		$query = $this->EE->db->get();
		$results = $query->result_array();
		
		if($query->num_rows() > 0)
		{
			return TRUE;
			
		} else {
			
			return FALSE;
		}
		
		
	}

	function updateInventory($entry_id)
	{
		
		$this->EE->db->insert(
		'exp_store_stock',
		array(
			'entry_id'  => $entry_id,
			'stock_level' => NULL,
			'min_order_qty'   => NULL
			)
		);
	}
    
    function email($member_data)
	{
		
		// Load the email library
		ee()->load->library('email');
		//Load the email Helper
		ee()->load->helper('text');
		
		ee()->load->helper('date');
				
		$body = '<h3>Team Activation Info</h3>';
		$body = $body.'<p><strong>Team Name</strong>: '.$member_data->entry['team_name'].'<br />';
		$body = $body.'<strong>Member Name</strong>: '.$member_data->entry['member_firstname'].' '.$member_data->entry['member_lastname'].'<br />';
		$body = $body.'<strong>Email Address</strong>: <a href="mailto:'.$this->user($member_data->entry['author_id']).'">'.$this->user($member_data->entry['author_id']).'</a></p>';
		
		ee()->email->wordwrap = true;
		ee()->email->mailtype = 'html';
		ee()->email->from($this->user($member_data->entry['author_id']), $member_data->entry['member_firstname'].' '.$member_data->entry['member_lastname']);
		ee()->email->to('info@sinairally.org');
		ee()->email->cc('kara.m@sympatico.ca,kara.maritzer@gmail.com');
		//ee()->email->to('transactions@signatureweb.ca');
		ee()->email->bcc('transactions@signatureweb.ca');
		
		
		ee()->email->subject('Sinai Rally Team Activation - '.$member_data->entry['team_name']);
		
		ee()->email->message($body);
		ee()->email->Send();
		

	}
    
    function user($author_id) {
        $query = $this->EE->db->select('*')
			->from('exp_members')
			->where('exp_members.member_id', $author_id)
			->get()->result_array();

		$member = $query[0]['email'];
        
        return $member; 
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