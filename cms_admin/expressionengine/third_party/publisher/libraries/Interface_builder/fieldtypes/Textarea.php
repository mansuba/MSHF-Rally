<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

class Textarea_IBField extends IBFieldtype {

	public function display_field($data = '')
	{
		$attributes = isset($this->settings['attributes']) ? ' '. implode(' ', $this->settings['attributes']) : '';

		return '<textarea name="'.$this->name.'" id="'.$this->id.'"'. $attributes .'>'.$data.'</textarea>';
	}

}