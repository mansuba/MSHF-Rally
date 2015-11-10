<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Low_grid extends Low_variables_type {

	public $info = array(
		'name'         => 'Grid',
		'version'      => LOW_VAR_VERSION,
		'var_requires' => array(
			'ee'   => '2.7.0',
			'grid' => '1.0'
		)
	);

	/**
	 * The fieldtype instance
	 */
	private $ft;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		ee()->load->library('api');
		ee()->api->instantiate('channel_fields');
		ee()->api_channel_fields->include_handler('grid');
		$this->ft = ee()->api_channel_fields->setup_handler('grid', TRUE);
	}

	/**
	 * Display settings sub-form for this variable type
	 *
	 * @param      mixed     $var_id        The id of the variable: 'new' or numeric
	 * @param      array     $var_settings  The settings of the variable
	 * @return     array
	 */
	function display_settings($var_id, $var_settings)
	{
		// New is null
		if ($var_id == 'new') $var_id = NULL;

		// init the fieldtype
		$this->_init_ft($var_id);

		ee()->load->library('table');
		ee()->lang->loadfile('admin_content');

		return $this->ft->display_settings($var_settings);
	}

	/**
	 * Return the settings to save
	 */
	public function save_settings($var_id, $var_settings)
	{
		$this->_init_ft($var_id);

		// Get the keys
		foreach (array('grid_min_rows', 'grid_max_rows') AS $key)
		{
			$var_settings[$key] = ee()->input->post($key);
		}

		$var_settings = $this->ft->save_settings($var_settings);

		return $var_settings;
	}

	/**
	 * post_save_settings
	 */
	public function post_save_settings($var_id, $var_settings)
	{
		$this->_init_ft($var_id);
		$this->ft->settings['entry_id'] = $var_id;
		$this->ft->post_save_settings($var_settings);
	}

	/**
	 * Display Low Variables field
	 *
	 * @param mixed $data the variable data
	 *
	 * @return string    the field's display
	 */
	public function display_input($var_id, $var_data, $var_settings)
	{
		$this->insert_css($this->_css());
		$this->insert_js($this->_js());

		// Load JS dependencies
		// Local cache
		static $loaded;

		//add the RTE js if it hasn't already been added
		if ($loaded !== TRUE)
		{
			// Load JS lib
			ee()->load->library('javascript');

			// Add FileManager JS to CP
			ee()->load->library(array('filemanager', 'file_field'));
			ee()->file_field->browser();

			// Add datepicker
			ee()->cp->add_js_script(array('ui' => 'datepicker'));

			$loaded = TRUE;
		}

		$var_settings['field_name'] = $this->ft->field_name = "var[{$var_id}]";

		$this->ft->settings = $var_settings;

		$this->_init_ft($var_id, $var_settings['field_name']);
		$this->ft->settings['entry_id'] = $var_id;
		return $this->ft->display_field($var_data);
	}

	/**
	 * Save Low Variable field
	 *
	 * @param mixed $data the var data
	 *
	 * @return string    the data to save to the database
	 */
	public function save_input($var_id, $var_data, $var_settings)
	{
		$var_settings['field_name'] = $this->ft->field_name = "var[{$var_id}]";

		// Initiate fieldtype
		$this->_init_ft($var_id, $var_settings['field_name']);

		// Validate it
		ee()->load->library('form_validation');
		$result = $this->ft->validate($var_data);
		$return = '';

		// Get the validated results
		if (isset($result['value']))
		{
			$var_data = $result['value'];
		}

		// Is there an error?
		if ( ! empty($result['error']))
		{
			$this->error_msg = $result['error'];
			$return = FALSE;
		}

		$this->ft->save($var_data);

		return $return;
	}

	/**
	 * Post processing after save
	 */
	public function post_save_input($var_id, $var_data, $var_settings)
	{
		$this->_init_ft($var_id, "var[{$var_id}]");
		$this->ft->settings['entry_id'] = $var_id;
		return $this->ft->post_save($var_data);
	}

	/**
	 * Display template tag output
	 *
	 * @param	string	$tagdata	Tagdata of template tag
	 * @param	array	$data		Data of the variable, containing id, data, settings...
	 * @return	mixed				String if successful, FALSE if not
	 */
	public function display_output($tagdata, $data)
	{
		$this->_init_ft(
			$data['variable_id'],
			$data['variable_name']
		);

		$row = array_merge(
			array(
				'entry_id' => $data['variable_id'],
				'channel_html_formatting' => 'all',
				'channel_auto_link_urls' => 'n',
				'channel_allow_img_urls' => 'y'
			),
			$data
		);

		$this->ft->_init(compact('row'));

		// Alternative method?
		$modifier = 'replace_' . ee()->TMPL->fetch_param('modifier', 'tag');

		if (method_exists($this->ft, $modifier))
		{
			return $this->ft->$modifier($data['variable_data'], ee()->TMPL->tagparams, $tagdata);
		}
		else
		{
			ee()->TMPL->log_item("Low Variables: modifier {$modifier} does not exist in Grid");
		}
	}


	/**
	 * Clean up after yourself
	 */
	public function delete($var_id)
	{
		$this->_init_ft($var_id);
		$this->ft->settings_modify_column(array(
			'field_id'  => $var_id,
			'ee_action' => 'delete'
		));
	}

	/**
	 * Initiate grid fieldtype
	 */
	private function _init_ft($var_id, $var_name = NULL)
	{
		ee()->load->add_package_path(PATH_FT.'grid');

		$data = array(
			'id'           => $var_id,
			'name'         => $var_name,
			'content_id'   => $var_id,
			'content_type' => LOW_VAR_PACKAGE
		);

		$this->ft->_init($data);
	}

	/**
	 * Adds a .low-grid class to parent cell
	 */
	private function _js()
	{
		return "$('.grid_field_container').parent().addClass('low-grid');";
	}

	/**
	 * Make sure Grid looks okay in LV
	 */
	private function _css()
	{
		return <<<EOCSS

		#low-varlist .low-grid {
			padding:0;
		}

		#low-varlist .low-grid table {
			margin:0;
		}

		#low-varlist .low-grid .grid_field_container > tbody > tr > td {
			border:0;
		}

		#low-varlist .low-grid .grid_field_container > tbody > tr > td,
		#low-varlist .low-grid .grid_field th,
		#low-varlist .low-grid .grid_field td {
			border-right:0 !important;
			border-bottom:0 !important;
		}

		#low-varlist .low-grid .grid_field td:not(.grid_handle) {
			background:#fff;
			padding:0;
		}

		#low-varlist .low-grid .grid_field_container > tbody > tr:last-child > td,
		#low-varlist .low-grid .grid_field_container_cell {
			padding:7px;
		}

		#low-varlist .low-grid th {
			background-image:none;
		}

		#low-varlist td[data-fieldtype="checkboxes"] label,
		#low-varlist td[data-fieldtype="radio"] label {
			display:block;
			white-space:nowrap;
			margin-bottom:3px;
		}

		#low-varlist .low-grid td.empty_field.first {
			padding:20px 0;
		}

EOCSS;
	}
}
// End of vt.low_grid.php