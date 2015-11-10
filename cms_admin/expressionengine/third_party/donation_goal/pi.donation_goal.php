<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Loop Plugin
 *
 * @package		Donation Goal
 * @category	Plugins
 * @author		Kevin Chatel
 * @link		http://www.signatureweb.ca
 */

$plugin_info = array(
				'pi_name'			=> 'Donation Goal',
				'pi_version'		=> '1.0',
				'pi_author'			=> 'Kevin Chatel',
				'pi_author_url'		=> 'http://www.signatureweb.ca/',
				'pi_description'	=> 'Gets the Percentage of goal completed',
				'pi_usage'			=> Donation_goal::usage()
			);


class Donation_goal {

	public $return_data = "";
	
    private $config_date = '';
    private $reset_date = '';
    
	/**
	  *  Constructor
	*/
	
	public function __construct()
	{
		$this->EE =& get_instance();
        $this->config_date = $this->EE->config->item('reset-date');;
        $this->reset_date = "exp_store_orders.order_completed_date > UNIX_TIMESTAMP('$this->config_date%')";	   
	}
	
	public function percentage()
	{
		$entryId = ee()->TMPL->fetch_param('entry_id');
		$fieldId = ee()->TMPL->fetch_param('field_id');
		
        
		//get the sum of price of orders from the database
		$collect_query = $this->EE->db->select_sum('exp_store_order_items.item_total')
			->from('exp_store_order_items')
			->where('exp_store_order_items.entry_id', $entryId)
			->where('exp_store_orders.order_completed_date >', 0)
            ->where('exp_store_orders.order_status_name','new')
            ->where($this->reset_date, NULL, FALSE)
			->join('exp_store_orders', 'exp_store_orders.id = exp_store_order_items.order_id')
			->get()->result_array();

		$collected = $collect_query[0]['item_total'];
		
		//get the goal amount
		$goal_query = $this->EE->db->select('field_id_'.$fieldId)
			->from('exp_channel_data')
			->where('entry_id', $entryId)
			->get()->result_array();
			
		$goal = $goal_query[0]['field_id_'.$fieldId];
		
		if($goal > 0) {
			$total = ceil((($collected/$goal)*100));
		} else {
			$total = 0;
		}
		
		if($total > 100) {
			return '100';
		} else {
			return $total;
		}
	}
	
	public function total_collected()
	{
		$entryId = ee()->TMPL->fetch_param('entry_id');
		$language = ee()->TMPL->fetch_param('language_code');
		
		//get the sum of price of orders from the database
		$collect_query = $this->EE->db->select_sum('exp_store_order_items.item_total')
			->from('exp_store_order_items')
			->where('exp_store_order_items.entry_id', $entryId)
			->where('exp_store_orders.order_completed_date >', 0)
            ->where('exp_store_orders.order_status_name','new')
            ->where($this->reset_date, NULL, FALSE)
			->join('exp_store_orders', 'exp_store_orders.id = exp_store_order_items.order_id')
			->get()->result_array();
		
		$collected = $collect_query[0]['item_total'];

		switch ($language)
		{
			case "en":
                return "$".money_format('%i',$collected);
                break;
			case "fr":
				return money_format('%i',$collected)."$";
				break;
			default:
				return "$".money_format('%i',$collected);
                break;
		}
		
	}
	
	public function goal() {
		$entryId = ee()->TMPL->fetch_param('entry_id');
		$fieldId = ee()->TMPL->fetch_param('field_id');
		$language = ee()->TMPL->fetch_param('language_code');
		
		//get the goal amount
		$goal_query = $this->EE->db->select('field_id_'.$fieldId)
			->from('exp_channel_data')
			->where('entry_id', $entryId)
			->get()->result_array();
			
		$goal = $goal_query[0]['field_id_'.$fieldId];
		
		switch ($language)
		{
			case "en":
	            return "$".money_format('%i',$goal);
	            break;
			case "fr":
				return money_format('%i',$goal)."$";
				break;
			default:
				return "$".money_format('%i',$goal);
	            break;
		}
	}
	
	/* END */
	
	
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

Get the Percentage
{exp:donation_goal:percentage entry_id="5" field_id="52"}
Returns 60

Get the Total Donations
{exp:donation_goal:total_collected entry_id="5" language_code="en"}
returns $1000.00 for language code "en"
returns 1000.00$ for language code "fr"

Get the Team Goal
{exp:donation_goal:goal entry_id="5" field_id="52" language_code="en"}
returns $1000.00 for language code "en"
returns 1000.00$ for language code "fr"
<?php
$buffer = ob_get_contents();
	
ob_end_clean(); 

return $buffer;
}
/* END */


}
// END CLASS

/* End of file pi.donation_complete.php */
/* Location: ./system/expressionengine/third_party/donation_complete/pi.donation_complete.php */
?>