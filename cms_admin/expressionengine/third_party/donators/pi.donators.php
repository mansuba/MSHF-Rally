<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// include config file
include(PATH_THIRD.'donators/config.php');

$plugin_info = array(
	'pi_name'        => SW_NAME,
	'pi_version'     => SW_VERSION,
	'pi_author'      => SW_AUTHOR,
	'pi_author_url'  => SW_DOCS,
	'pi_description' => 'List the donators of each team.',
	'pi_usage'       => 'Use as follows:

	Get listing
	{exp:donators entry_id="5"}'
);

/**
 * Donators Plugin Class
 *
 * @package        Donators
 * @author         Kevin Chatel (SignatureWEB)
 * @link           http://www.signatureweb.ca
 * @license        http://creativecommons.org/licenses/by-sa/3.0/
 */
class Donators {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Plugin return data
	 */
	public $return_data;
	public $entryID;
    
    private $config_date = '';
    private $reset_date = '';
	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 */
	public function __construct()
	{
		/*$this->entryID = ee()->TMPL->fetch_param('entry_id');*/
		
        
        
		if ( ! ($orders = $this->_get_orders()))
		{
			$this->return_data = ee()->TMPL->no_results();
			return;
		}
				
		$variables = array();
		
		if(ee()->TMPL->fetch_param('language_code')) {
			$language = ee()->TMPL->fetch_param('language_code');
		} else {
			$language = 'en';
		}
		

		foreach ($orders as $row)
		{
			
			switch ($language)
			{
				case "en":
		            $curreny = "$".money_format('%i',$row['order_total']);
		            break;
				case "fr":
					$curreny = money_format('%i',$row['order_total'])."$";
					break;
				default:
					$curreny = "$".money_format('%i',$row['order_total']);
		            break;
			}
			

			$variable_row = array(
				'name'  => $row['billing_first_name'].' '.$row['billing_last_name'],
				'amount'  => $curreny,
				'display' => $row['order_custom4'],
				'option'  => $row['order_custom6'],
				'order_date' => $row['order_date'],
				'display_name' => $row['order_custom4']
		    );

		    $variables[] = $variable_row;
		}
		
		$this->return_data = ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $variables);
		
	}
	
	// --------------------------------------------------------------------

	/**
	 * Query DB for Orders
	 *
	 * @access     private
	 * @return     array
	 */
	private function _get_orders()
	{
		
        $this->config_date = ee()->config->item('reset-date');;
        $this->reset_date = "exp_store_orders.order_completed_date > UNIX_TIMESTAMP('$this->config_date%')";
        
		// -------------------------------------
		// Start building query to get orders
		// -------------------------------------

		ee()->db->select('*')
			->from('exp_store_order_items')
			->where('exp_store_order_items.entry_id', ee()->TMPL->fetch_param('entry_id'))
			->where('exp_store_orders.order_completed_date >', 0)
			->where('exp_store_orders.order_status_name','new')
            ->where($this->reset_date, NULL, FALSE)
			->join('exp_store_orders', 'exp_store_orders.id = exp_store_order_items.order_id')
			->order_by('exp_store_orders.order_completed_date', 'desc');
			
		$query = ee()->db->get();
		
		return $query->result_array();
	}
	
	
	/**
	 * Flatten results
	 *
	 * Given a DB result set, this will return an (associative) array
	 * based on the keys given
	 *
	 * @param      array
	 * @param      string    key of array to use as value
	 * @param      string    key of array to use as key (optional)
	 * @return     array
	 */
	private function _flatten_results($resultset, $val, $key = FALSE)
	{
		$array = array();

		foreach ($resultset AS $row)
		{
			if ($key !== FALSE)
			{
				$array[$row[$key]] = $row[$val];
			}
			else
			{
				$array[] = $row[$val];
			}
		}

		return $array;
	}
	
}
// END CLASS
