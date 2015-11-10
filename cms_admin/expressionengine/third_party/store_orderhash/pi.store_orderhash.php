<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Loop Plugin
 *
 * @package		Loop Plugin
 * @category	Plugins
 * @author		Ben Croker
 * @link		http://www.putyourlightson.net/loop-plugin
 */

$plugin_info = array(
				'pi_name'			=> 'Store Orderhash',
				'pi_version'		=> '1.0',
				'pi_author'			=> 'Kevin Chatel',
				'pi_author_url'		=> 'http://www.signatureweb.ca/',
				'pi_description'	=> 'Providing an order_id you can retrieve the orderhash',
				'pi_usage'			=> Store_orderhash::usage()
			);

/**
 * Store Orderhash Plugin Class
 *
 * @package        Store Orderhash
 * @author         Kevin Chatel (SignatureWEB)
 * @link           http://www.signatureweb.ca
 * @license        http://creativecommons.org/licenses/by-sa/3.0/
 */
class Store_orderhash {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Plugin return data
	 */
	public $return_data;

	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->EE =& get_instance();
		
		$orderID = $this->EE->TMPL->fetch_param('order_id');
		
		// Create the Query
		$query = $this->EE->db->select('*')
			->from('exp_store_orders')
			->where('exp_store_orders.id', $orderID)
			->get()->result_array();
		
		$this->return_data = $query[0]['order_hash'];
	}
	
	// --------------------------------------------------------------------

	// ----------------------------------------
	//  Plugin Usage
	// ----------------------------------------

	// This function describes how the plugin is used.
	//  Make sure and use output buffering

	function usage()
	{
		ob_start(); 
		?>
		Use as follows:

		{exp:store_orderhash order_id="22"}

		<?php
		$buffer = ob_get_contents();

		ob_end_clean(); 

		return $buffer;
	}
	/* END */


}
// END CLASS

/* End of file pi.for_loop.php */
/* Location: ./system/expressionengine/third_party/store_orderhash/pi.store_orderhash.php */
?>