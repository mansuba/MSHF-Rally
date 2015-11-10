<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Protected Entries Extension file
 *
 * @package     Protected Entries
 * @category    Modules
 * @author      Rein de Vries <info@reinos.nl>
 * @link		http://dmlogic.com/blog/protecting-expression-engine-entries-from-accidental-deletion/
 * @link        http://reinos.nl/add-ons/protected-entries
 * @copyright   Copyright (c) 2013 Reinos.nl Internet Media
 */

include(PATH_THIRD.'protected_entries/config.php'); 
 
class Protected_entries_ext {
 
    public $settings = array();
    public $name = PROTECTED_ENTRIES_NAME;
    public $version = PROTECTED_ENTRIES_VERSION;
    public $description = PROTECTED_ENTRIES_DESCRIPTION;
    public $settings_exist = 'y';
    public $docs_url = PROTECTED_ENTRIES_DOCS;

    // -----------------------------------------------------------------
 
    public function __construct($settings='') 
    {
         $this->settings = $settings;	
    }
 
 	// -----------------------------------------------------------------
 
    /**
     * settings
     *
     * Abstracted Settings Form and Processing¶
     */
	public function settings()
	{
		//set theme url
        $this->theme_url = URL_THIRD_THEMES.PROTECTED_ENTRIES_MAP.'/';		
	 	ee()->cp->add_to_foot('<script type="text/javascript" src="'.$this->theme_url.'select2/select2.js"></script>');
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="'.$this->theme_url.'select2/select2.css" />');   
		ee()->cp->add_to_head('<style type="text/css" media="screen">.select2-container-multi, .select2-input{width:100% !important;}</style>');
		ee()->cp->add_to_foot('<script type="text/javascript" >$(function(){$("select[name=\'entries[]\'], select[name=\'channels[]\']").select2()});</script>');
		
	    $settings = array();

	    // The entries
	    $entries = $this->_get_entries();
	    $settings['entries']      = array('ms', !empty($entries) ? $entries : array(''));
		
		// or whole channels
		$channels = $this->_get_channels();
	    $settings['channels']      = array('ms', !empty($channels) ? $channels : array(''));
	
	    // General pattern:
	    //
	    // $settings[variable_name] => array(type, options, default);
	    //
	    // variable_name: short name for the setting and the key for the language file variable
	    // type:          i - text input, t - textarea, r - radio buttons, c - checkboxes, s - select, ms - multiselect
	    // options:       can be string (i, t) or array (r, c, s, ms)
	    // default:       array member, array of members, string, nothing
		
	    return $settings;
	}
	
	// -----------------------------------------------------------------
 
    /**
     * delete_entries_start
     *
     * Halts the entry delete routine if a match found against protected entries
     */
    public function cp_js_end() 
    {
    	$js = ee()->extensions->last_call;
		
		//get all protected entries
    	$protected_entries = $this->_collect_all_protected_entries();
		if(!empty($protected_entries))
		{
			//build js array of entryt_ids
			$js .= 'var entry_ids = [];';
			foreach($protected_entries as $entry_id)
			{
				$js .= 'entry_ids.push('.$entry_id.');';
			}
			
			//js logic
			$js .= <<<EOF
		    $('#structure-ui #page-ui li').each(function(){
		    	var entry_id = $(this).find('.item-wrapper .page-title a').attr('href');
		    	if(typeof(entry_id) != 'undefined') {
			    	var correct_entry_id = entry_id.match(/entry_id(.*?)&/);
			    	var correct_entry_id = correct_entry_id[1].replace('=','');
			    	if($.inArray(parseInt(correct_entry_id), entry_ids) != -1) {
			    		$(this).children('.item-wrapper').find('.page-title').addClass('page-title-disabled');
			    		$(this).children('.item-wrapper').find('.page-controls .control-del').hide();
			    	}
			    }
		    });
EOF;
		}
		
		return $js;
	}
 
    // -----------------------------------------------------------------
 
    /**
     * delete_entries_start
     *
     * Halts the entry delete routine if a match found against protected entries
     */
    public function delete_entries_start() 
    {
		//to delete
        $to_delete = ee()->input->post('delete');
				
		//get all protected entries
		$protected_entries = $this->_collect_all_protected_entries();
 
 		//are there any ids which we cannot delete
        $result = array_intersect($to_delete, $protected_entries);

		//remove values from the S_POST array
 		$_POST['delete'] = array_diff($_POST['delete'], $result);
		
		//init the input class again to reindex the $_POST array
		$_input =& load_class('Input', 'core');
		
		//to delete
		$to_delete = ee()->input->post('delete');
		
		//nothing to delete anymore?
		if(empty($to_delete)) 
		{
			$edit_base_url	= BASE.AMP.'C=content_edit';
           	ee()->session->set_flashdata('message_failure', lang('Cannot delete the entries because they are protected.'));
			ee()->functions->redirect($edit_base_url);
        }

		//there are still entries to delete
        if(!empty($result)) 
        {
        	ee()->logger->log_action('You are attempting to delete protected entries: '.implode(', ', $result));
			ee()->session->set_flashdata('message_failure', lang('Cannot delete some entries because they are protected.'));
        }
    }
 
    // ----------------------------------------------------------------------
	
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
    	$this->settings = array(
	        'entries'   => array(),
	        'channels' => array(),
	    );
		
        ee()->db->insert('extensions', array(
            'class'    => __CLASS__,
            'method'   => 'delete_entries_start',
            'hook'     => 'delete_entries_start',
            'settings' => serialize($this->settings),
            'priority' => 1,
            'version'  => $this->version,
            'enabled'  => 'y'
        ));
		
		ee()->db->insert('extensions', array(
            'class'    => __CLASS__,
            'method'   => 'cp_js_end',
            'hook'     => 'cp_js_end',
            'settings' => serialize($this->settings),
            'priority' => 10,
            'version'  => $this->version,
            'enabled'  => 'y'
        ));
    }
 
    // ----------------------------------------------------------------------

	/**
	 * Disable Extension
	 *
	 * This method removes information from the exp_extensions table
	 *
	 * @return void
	 */
    public function disable_extension() 
    {
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('extensions');
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
    public function update_extension($current='') 
    {
        if ($current == '' OR $current == $this->version) {
            return FALSE;
        }
		
		//update to 1.0 versin
		if ($current < '1.1')
		{
			//add new cp_js_end hook
			ee()->db->insert('extensions', array(
	            'class'    => __CLASS__,
	            'method'   => 'cp_js_end',
	            'hook'     => 'cp_js_end',
	            'settings' => ee()->db->select('settings')->from('extensions')->where('class', 'Protected_entries_ext')->where('method', 'delete_entries_start')->get()->row()->settings,
	            'priority' => 10,
	            'version'  => $this->version,
	            'enabled'  => 'y'
	        ));
		}
 
        $data = array();
        $data['version'] = $this->version;
        ee()->db->where('class', __CLASS__);
        ee()->db->update('extensions', $data);
    }
	
	// -----------------------------------------------------------------
 
    /**
     * Get the entry ids
     *
     */
	private function _get_entries($channel_id = '', $only_ids = false)
	{
		//with channel_id
		if($channel_id != '')
		{
			ee()->db->where('channel_id', $channel_id);	
		}
		
		ee()->db->select('entry_id, title');
		ee()->db->from('channel_titles');
		$result = ee()->db->get();
		
		$entries = array();
		
		//any result
		if($result->num_rows())
		{
			foreach($result->result() as $row)
			{
				//get only the ids
				if($only_ids)
				{
					$entries[] = $row->entry_id;	
				}
				//get the $id=>$name
				else 
				{
					$entries[$row->entry_id] = $row->title;
				}
			}	
		}
		
		return $entries;
	}
	
	// -----------------------------------------------------------------
 
    /**
     * Get the channel ids
     *
     */
	private function _get_channels()
	{
		ee()->db->select('channel_id, channel_title');
		ee()->db->from('channels');
		$result = ee()->db->get();
		
		$channels = array();
		
		//any result
		if($result->num_rows())
		{
			foreach($result->result() as $row)
			{
				$channels[$row->channel_id] = $row->channel_title;
			}	
		}
		
		return $channels;
	}
	
	// -----------------------------------------------------------------
 
    /**
     * Get the channel ids
     *
     */
	private function _collect_all_protected_entries()
	{
		//all the entries
		$protected_entries = !empty($this->settings['entries']) ? $this->settings['entries'] : array();
		
		$channel_entries = array();
		if(!empty($this->settings['channels']))
		{
			foreach($this->settings['channels'] as $key=>$val)
			{
				//merge the arrays
				$channel_entries = $channel_entries + $this->_get_entries($val, true);
			}
		}
		
		//merge the arrays
		$protected_entries = $protected_entries + $channel_entries;
		
		return $protected_entries;
	}
	
	
	
	
	
}