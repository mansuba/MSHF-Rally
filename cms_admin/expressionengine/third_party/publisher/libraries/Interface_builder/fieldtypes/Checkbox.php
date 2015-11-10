<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

class Checkbox_IBField extends IBFieldtype {

    public function display_field($data = '')
    {
        $html = array();

        foreach($this->settings['options'] as $option_value => $option_name)
        {
            $checked = (is_array($data) && in_array($option_value, $data)) ? 'checked="checked"' : NULL;

            $html[] = '<li><label><input type="checkbox" name="'.$this->name.'[]" id="'.$this->id.'" value="'.$option_value.'" '.$checked.' style="margin-right:.5em">'.$option_name.'</label></li>';
        }

        $out = implode('<br />', $html);

        // $out = '<table><tr>'."\n";

        // $i = 0;
        // foreach ($html as $row)
        // {
        //     if ($i % 2 === 0 && $i !== 0)
        //     {
        //         $out .= '</tr><tr>'."\n";
        //     }

        //     $out .= '<td>'. $i . $row .'</td>'."\n";

        //     $i++;
        // }

        // $out .= '</tr><table>'."\n";

        $col1 = array();
        $col2 = array();

        $i = 0;
        foreach ($html as $row)
        {
            if ($i % 2 === 0)
            {
                $col1[] = $row;
            }
            else
            {
                $col2[] = $row;
            }

            $i++;
        }

        $col1 = implode("\n", $col1);
        $col2 = implode("\n", $col2);

        $out = '<div>
            <ul style="float: left; width: 50%">
                '. $col1 .'
            </ul>
            <ul style="float: left; width: 50%">
                '. $col2 .'
            </ul>
            <div style="clear: both;"></div>
        </div>';

        return $out;
    }

}