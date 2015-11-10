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
				'pi_name'			=> 'Loop Plugin',
				'pi_version'		=> '1.4',
				'pi_author'			=> 'Ben Croker',
				'pi_author_url'		=> 'http://www.putyourlightson.net/',
				'pi_description'	=> 'Provides loop functionality in templates',
				'pi_usage'			=> For_loop::usage()
			);


class For_loop {

	var $return_data;

	
	/**
	  *  Constructor
	  */
	function __construct()
	{
		// make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();
		
		// define valid date formats
		$date_formats = array('day' => 'l', 'month' => 'F', 'day_short' => 'D', 'month_short' => 'M');
		
		// get parameters and assign defaults if not set
		$start = ($this->EE->TMPL->fetch_param('start') !== false) ? $this->EE->TMPL->fetch_param('start') : 1;
		$end = ($this->EE->TMPL->fetch_param('end') !== false) ? $this->EE->TMPL->fetch_param('end') : 100000;
		$increment = ($this->EE->TMPL->fetch_param('increment') !== false) ? $this->EE->TMPL->fetch_param('increment') : 1;
		$limit = ($this->EE->TMPL->fetch_param('limit') !== false) ? $this->EE->TMPL->fetch_param('limit') : 100000;
		$pad_zero = $this->EE->TMPL->fetch_param('pad_zero');
		
				
		// are we incrementing or decrementing
		$ascending = true;
		
		if ($increment < 0)
		{
			$ascending = false;
		}
		
		$count = 1;
	
		$return_data = "";
		
		$i = $start;
		
		for ($i = $start; (($ascending && $i <= $end) || (!$ascending && $i >= $end)) && $count <= $limit; $i = $i + $increment)
		{		
			$tagdata = $this->EE->TMPL->tagdata;
		
			foreach ($this->EE->TMPL->var_single as $key)
			{
				if ($key == "index") 
				{
					// pad zeros
					$index = ($pad_zero AND is_numeric($pad_zero)) ? str_pad($i, $pad_zero, '0', STR_PAD_LEFT) : $i;
					
					$tagdata = $this->EE->TMPL->swap_var_single($key, $index, $tagdata);
				}
				
				else if ($key == "loop_count") 
				{
					$tagdata = $this->EE->TMPL->swap_var_single($key, $count, $tagdata);
				}
				
				else if (isset($date_formats[$key]))
				{
					$time = substr_count($key, 'month') ? mktime(0, 0, 0, $i, 1) : mktime(0, 0, 0, 1, $i);
						
					$index = date($date_formats[$key], $time);
						
					$tagdata = $this->EE->TMPL->swap_var_single($key, $index, $tagdata);
				}
			}
			
			$count++;
			
			$return_data .= $tagdata;
		}
		
		$this->return_data = $return_data;
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

{exp:for_loop start="5" end="10" increment="1" limit="20" pad_zero="2"}

This loop has been executed {loop_count} times and is now on iteration number {index}.

{/exp:for_loop}

<?php
$buffer = ob_get_contents();
	
ob_end_clean(); 

return $buffer;
}
/* END */


}
// END CLASS

/* End of file pi.for_loop.php */
/* Location: ./system/expressionengine/third_party/for_loop/pi.for_loop.php */
?>