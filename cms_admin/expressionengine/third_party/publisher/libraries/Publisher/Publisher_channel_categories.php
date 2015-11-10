<?php

class Publisher_channel_categories extends Channel
{
    /**
     * This is ridiculous... All this just to alter a query.
     * @return string
     */
    public function categories()
    {
        // PUBLISHER ADDITION
        $this->temp_array = array();

        $sql = "SELECT DISTINCT cat_group, channel_id FROM exp_channels WHERE site_id IN ('".implode("','", ee()->TMPL->site_ids)."') ";

        if ($channel = ee()->TMPL->fetch_param('channel'))
        {
            $sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('channel'), 'channel_name');
        }

        $cat_groups = ee()->db->query($sql);

        if ($cat_groups->num_rows() == 0)
        {
            return;
        }

        $channel_ids = array();
        $group_ids = array();
        foreach ($cat_groups->result_array() as $group)
        {
            $channel_ids[] = $group['channel_id'];
            $group_ids[] = $group['cat_group'];
        }

        // Combine the group IDs from multiple channels into a string
        $group_ids = implode('|', $group_ids);

        if ($category_group = ee()->TMPL->fetch_param('category_group'))
        {
            if (substr($category_group, 0, 4) == 'not ')
            {
                $x = explode('|', substr($category_group, 4));

                $groups = array_diff(explode('|', $group_ids), $x);
            }
            else
            {
                $x = explode('|', $category_group);

                $groups = array_intersect(explode('|', $group_ids), $x);
            }

            if (count($groups) == 0)
            {
                return '';
            }
            else
            {
                $group_ids = implode('|', $groups);
            }
        }

        $parent_only = (ee()->TMPL->fetch_param('parent_only') == 'yes') ? TRUE : FALSE;

        $path = array();

        if (preg_match_all("#".LD."path(=.+?)".RD."#", ee()->TMPL->tagdata, $matches))
        {
            for ($i = 0; $i < count($matches[0]); $i++)
            {
                if ( !isset($path[$matches[0][$i]]))
                {
                    $path[$matches[0][$i]] = ee()->functions->create_url(ee()->functions->extract_path($matches[1][$i]));
                }
            }
        }

        // PUBLISHER ADDITION
        if (ee()->publisher_setting->url_translations() && !ee()->publisher_lib->is_default_language)
        {
            foreach ($path as $p => $v)
            {
                $path[$p] = ee()->publisher_helper_url->get_translated_url($v);
            }
        }

        $str = '';
        $strict_empty = (ee()->TMPL->fetch_param('restrict_channel') == 'no') ? 'no' : 'yes';

        if (ee()->TMPL->fetch_param('style') == '' OR ee()->TMPL->fetch_param('style') == 'nested')
        {
            $this->category_tree(array(
                'group_id'      => $group_ids,
                'channel_ids'   => $channel_ids,
                'template'      => ee()->TMPL->tagdata,
                'path'          => $path,
                'channel_array' => '',
                'parent_only'   => $parent_only,
                'show_empty'    => ee()->TMPL->fetch_param('show_empty'),
                'strict_empty'  => $strict_empty
            ));

            if (count($this->category_list) > 0)
            {
                $i = 0;

                $id_name = ( !ee()->TMPL->fetch_param('id')) ? 'nav_categories' : ee()->TMPL->fetch_param('id');
                $class_name = ( !ee()->TMPL->fetch_param('class')) ? 'nav_categories' : ee()->TMPL->fetch_param('class');

                $this->category_list[0] = '<ul id="'.$id_name.'" class="'.$class_name.'">'."\n";

                foreach ($this->category_list as $val)
                {
                    $str .= $val;
                }
            }
        }
        else
        {
            // fetch category field names and id's

            if ($this->enable['category_fields'] === TRUE)
            {
                $query = ee()->db->query("SELECT field_id, field_name FROM exp_category_fields
                                    WHERE site_id IN ('".implode("','", ee()->TMPL->site_ids)."')
                                    AND group_id IN ('".str_replace('|', "','", ee()->db->escape_str($group_ids))."')");

                if ($query->num_rows() > 0)
                {
                    foreach ($query->result_array() as $row)
                    {
                        $this->catfields[] = array('field_name' => $row['field_name'], 'field_id' => $row['field_id']);
                    }
                }

                $field_sqla = ", cg.field_html_formatting, fd.* ";
                $field_sqlb = " LEFT JOIN exp_category_field_data AS fd ON fd.cat_id = c.cat_id
                                LEFT JOIN exp_category_groups AS cg ON cg.group_id = c.group_id";

                $field_sqlb = " LEFT JOIN exp_category_field_data AS fd ON fd.cat_id = c.cat_id
                                LEFT JOIN exp_category_groups AS cg ON cg.group_id = c.group_id";

            }
            else
            {
                $field_sqla = '';
                $field_sqlb = '';
            }

            $show_empty = ee()->TMPL->fetch_param('show_empty');

            if ($show_empty == 'no')
            {
                // First we'll grab all category ID numbers

                $query = ee()->db->query("SELECT cat_id, parent_id
                                     FROM exp_categories
                                     WHERE group_id IN ('".str_replace('|', "','", ee()->db->escape_str($group_ids))."')
                                     ORDER BY group_id, parent_id, cat_order");

                $all = array();

                // No categories exist?  Let's go home..
                if ($query->num_rows() == 0)
                {
                    return FALSE;
                }

                foreach($query->result_array() as $row)
                {
                    $all[$row['cat_id']] = $row['parent_id'];
                }

                // Next we'l grab only the assigned categories

                // $sql = "SELECT DISTINCT(exp_categories.cat_id), parent_id FROM exp_categories
                //         LEFT JOIN exp_category_posts ON exp_categories.cat_id = exp_category_posts.cat_id
                //         LEFT JOIN exp_channel_titles ON exp_category_posts.entry_id = exp_channel_titles.entry_id
                //         WHERE group_id IN ('".str_replace('|', "','", ee()->db->escape_str($group_ids))."') ";

                $sql = "SELECT DISTINCT(exp_categories.cat_id), parent_id FROM exp_categories
                        LEFT JOIN exp_publisher_category_posts ON exp_categories.cat_id = exp_publisher_category_posts.cat_id
                        LEFT JOIN exp_channel_titles ON exp_publisher_category_posts.entry_id = exp_channel_titles.entry_id
                        WHERE group_id IN ('".str_replace('|', "','", ee()->db->escape_str($group_ids))."')
                        AND exp_publisher_category_posts.publisher_status = '". ee()->db->escape_str(ee()->publisher_lib->status) ."'
                        AND exp_publisher_category_posts.publisher_lang_id = '". ee()->db->escape_str(ee()->publisher_lib->lang_id) ."'";


                $sql .= "AND exp_publisher_category_posts.cat_id IS NOT NULL ";

                if ($strict_empty == 'yes')
                {
                    $sql .= "AND exp_channel_titles.channel_id IN ('".implode("','", $channel_ids)."') ";
                }
                else
                {
                    $sql .= "AND exp_channel_titles.site_id IN ('".implode("','", ee()->TMPL->site_ids)."') ";
                }

                if (($status = ee()->TMPL->fetch_param('status')) !== FALSE)
                {
                    $status = str_replace(array('Open', 'Closed'), array('open', 'closed'), $status);
                    $sql .= ee()->functions->sql_andor_string($status, 'exp_channel_titles.status');
                }
                else
                {
                    $sql .= "AND exp_channel_titles.status != 'closed' ";
                }

                /**------
                /**  We only select entries that have not expired
                /**------*/

                $timestamp = (ee()->TMPL->cache_timestamp != '') ? ee()->TMPL->cache_timestamp : ee()->localize->now;

                if (ee()->TMPL->fetch_param('show_future_entries') != 'yes')
                {
                    $sql .= " AND exp_channel_titles.entry_date < ".$timestamp." ";
                }

                if (ee()->TMPL->fetch_param('show_expired') != 'yes')
                {
                    $sql .= " AND (exp_channel_titles.expiration_date = 0 OR exp_channel_titles.expiration_date > ".$timestamp.") ";
                }

                if ($parent_only === TRUE)
                {
                    $sql .= " AND parent_id = 0";
                }

                $sql .= " ORDER BY group_id, parent_id, cat_order";

                $query = ee()->db->query($sql);

                // If no rows returned check for content fallback
                if (ee()->publisher_setting->show_fallback() && $query->num_rows() == 0)
                {
                    $query = ee()->publisher_query->modify(
                        "exp_publisher_category_posts.publisher_lang_id = '". ee()->db->escape_str(ee()->publisher_lib->lang_id) ."'",
                        "exp_publisher_category_posts.publisher_lang_id = '". ee()->db->escape_str(ee()->publisher_lib->default_lang_id) ."'",
                        $sql
                    );
                }

                if ($query->num_rows() == 0)
                {
                    return FALSE;
                }

                // All the magic happens here, baby!!

                $this->cat_full_array = array();

                foreach($query->result_array() as $row)
                {
                    if ($row['parent_id'] != 0)
                    {
                        $this->find_parent($row['parent_id'], $all);
                    }

                    $this->cat_full_array[] = $row['cat_id'];
                }

                $this->cat_full_array = array_unique($this->cat_full_array);

                $sql = "SELECT c.cat_id, c.parent_id, c.cat_name, c.cat_url_title, c.cat_image, c.cat_description {$field_sqla}
                FROM exp_categories AS c
                {$field_sqlb}
                WHERE c.cat_id IN (";

                foreach ($this->cat_full_array as $val)
                {
                    $sql .= $val.',';
                }

                $sql = substr($sql, 0, -1).')';

                $sql .= " ORDER BY c.group_id, c.parent_id, c.cat_order";

                $query = ee()->db->query($sql);

                if ($query->num_rows() == 0)
                {
                    return FALSE;
                }
            }
            else
            {
                $sql = "SELECT c.cat_name, c.cat_url_title, c.cat_image, c.cat_description, c.cat_id, c.parent_id {$field_sqla}
                        FROM exp_categories AS c
                        {$field_sqlb}
                        WHERE c.group_id IN ('".str_replace('|', "','", ee()->db->escape_str($group_ids))."') ";

                if ($parent_only === TRUE)
                {
                    $sql .= " AND c.parent_id = 0";
                }

                $sql .= " ORDER BY c.group_id, c.parent_id, c.cat_order";

                $query = ee()->db->query($sql);

                if ($query->num_rows() == 0)
                {
                    return '';
                }
            }

            // Here we check the show parameter to see if we have any
            // categories we should be ignoring or only a certain group of
            // categories that we should be showing.  By doing this here before
            // all of the nested processing we should keep out all but the
            // request categories while also not having a problem with having a
            // child but not a parent.  As we all know, categories are not asexual.

            if (ee()->TMPL->fetch_param('show') !== FALSE)
            {
                if (strncmp(ee()->TMPL->fetch_param('show'), 'not ', 4) == 0)
                {
                    $not_these = explode('|', trim(substr(ee()->TMPL->fetch_param('show'), 3)));
                }
                else
                {
                    $these = explode('|', trim(ee()->TMPL->fetch_param('show')));
                }
            }

            // PUBLISHER ADDITION
            $this->publisher_query($sql);

            foreach($query->result_array() as $row)
            {
                if (isset($not_these) && in_array($row['cat_id'], $not_these))
                {
                    continue;
                }
                elseif(isset($these) && !in_array($row['cat_id'], $these))
                {
                    continue;
                }

                // PUBLISHER ADDITION
                foreach ($this->publisher_query_result as $publisher_row)
                {
                    if ($publisher_row['cat_id'] == $row['cat_id'])
                    {
                        foreach ($publisher_row as $key => $val)
                        {
                            if ($val != '')
                            {
                                $row[$key] = $publisher_row[$key];
                            }
                        }
                    }
                }

                $this->temp_array[$row['cat_id']]  = array($row['cat_id'], $row['parent_id'], '1', $row['cat_name'], $row['cat_description'], $row['cat_image'], $row['cat_url_title']);

                foreach ($row as $key => $val)
                {
                    if (strpos($key, 'field') !== FALSE)
                    {
                        $this->temp_array[$row['cat_id']][$key] = $val;
                    }
                }
            }

            $this->cat_array = array();

            foreach($this->temp_array as $key => $val)
            {
                if (0 == $val[1])
                {
                    $this->cat_array[] = $val;
                    $this->process_subcategories($key);
                }
            }

            unset($this->temp_array);

            ee()->load->library('typography');
            ee()->typography->initialize(array(
                'convert_curly' => FALSE
            ));

            $this->category_count = 0;
            $total_results = count($this->cat_array);

            // Get category ID from URL for {if active} conditional
            ee()->load->helper('segment');
            $active_cat = parse_category($this->query_string);

            foreach ($this->cat_array as $key => $val)
            {
                $chunk = ee()->TMPL->tagdata;

                ee()->load->library('file_field');
                $cat_image = ee()->file_field->parse_field($val[5]);

                $cat_vars = array(
                    'category_name'         => $val[3],
                    'category_url_title'    => $val[6],
                    'category_description'  => $val[4],
                    'category_image'        => $cat_image['url'],
                    'category_id'           => $val[0],
                    'parent_id'             => $val[1],
                    'active'                => ($active_cat == $val[0] || $active_cat == $val[6])
                );

                // add custom fields for conditionals prep

                foreach ($this->catfields as $v)
                {
                    $cat_vars[$v['field_name']] = ( !isset($val['field_id_'.$v['field_id']])) ? '' : $val['field_id_'.$v['field_id']];
                }

                $cat_vars['count'] = ++$this->category_count;
                $cat_vars['total_results'] = $total_results;

                $chunk = ee()->functions->prep_conditionals($chunk, $cat_vars);

                $chunk = str_replace(
                    array(
                        LD.'category_name'.RD,
                        LD.'category_url_title'.RD,
                        LD.'category_description'.RD,
                        LD.'category_image'.RD,
                        LD.'category_id'.RD,
                        LD.'parent_id'.RD
                    ),
                    array(
                        $val[3],
                        $val[6],
                        $val[4],
                        $cat_image['url'],
                        $val[0],
                        $val[1]
                    ),
                    $chunk
                );

                // PUBLSHER ADDITION
                $chunk = $this->update_paths($chunk, $path, $val, $val[0]);

                // parse custom fields
                foreach($this->catfields as $cv)
                {
                    if (isset($val['field_id_'.$cv['field_id']]) AND $val['field_id_'.$cv['field_id']] != '')
                    {
                        $field_content = ee()->typography->parse_type(
                            $val['field_id_'.$cv['field_id']],
                            array(
                                'text_format'       => $val['field_ft_'.$cv['field_id']],
                                'html_format'       => $val['field_html_formatting'],
                                'auto_links'        => 'n',
                                'allow_img_url' => 'y'
                            )
                        );
                        $chunk = str_replace(LD.$cv['field_name'].RD, $field_content, $chunk);
                    }
                    else
                    {
                        // garbage collection
                        $chunk = str_replace(LD.$cv['field_name'].RD, '', $chunk);
                    }
                }

                /** --------------------------------
                /**  {count}
                /** --------------------------------*/

                if (strpos($chunk, LD.'count'.RD) !== FALSE)
                {
                    $chunk = str_replace(LD.'count'.RD, $this->category_count, $chunk);
                }

                /** --------------------------------
                /**  {total_results}
                /** --------------------------------*/

                if (strpos($chunk, LD.'total_results'.RD) !== FALSE)
                {
                    $chunk = str_replace(LD.'total_results'.RD, $total_results, $chunk);
                }

                $str .= $chunk;
            }

            if (ee()->TMPL->fetch_param('backspace'))
            {
                $str = substr($str, 0, - ee()->TMPL->fetch_param('backspace'));
            }
        }

        if (strpos($str, '{filedir_') !== FALSE)
        {
            ee()->load->library('file_field');
            $str = ee()->file_field->parse_string($str);
        }

        return $str;
    }

    public function category_heading()
    {
        if ($this->query_string == '')
        {
            return;
        }

        // PUBLISHER ADDITION
        $lang_id = ee()->TMPL->fetch_param('publisher_lang_id', ee()->publisher_lib->lang_id);
        $status  = ee()->TMPL->fetch_param('publisher_status', ee()->publisher_lib->status);

        $qstring = $this->query_string;

        /** --------------------------------------
        /**  Remove page number
        /** --------------------------------------*/

        if (preg_match("#/P\d+#", $qstring, $match))
        {
            $qstring = reduce_double_slashes(str_replace($match[0], '', $qstring));
        }

        /** --------------------------------------
        /**  Remove "N"
        /** --------------------------------------*/
        if (preg_match("#/N(\d+)#", $qstring, $match))
        {
            $qstring = reduce_double_slashes(str_replace($match[0], '', $qstring));
        }

        if (isset(ee()->publisher_model->current_language['cat_url_indicator']) &&
            ee()->publisher_model->current_language['cat_url_indicator'] != '')
        {
            $this->reserved_cat_segment = ee()->publisher_model->current_language['cat_url_indicator'];
        }

        // Is the category being specified by name?
        if ($qstring != '' AND $this->reserved_cat_segment != '' AND in_array($this->reserved_cat_segment, explode("/", $qstring)) AND $this->EE->TMPL->fetch_param('channel'))
        {
            $qstring = preg_replace("/(.*?)\/".preg_quote($this->reserved_cat_segment)."\//i", '', '/'.$qstring);

            $sql = "SELECT DISTINCT cat_group FROM exp_channels WHERE site_id IN ('".implode("','", $this->EE->TMPL->site_ids)."') AND ";

            $xsql = $this->EE->functions->sql_andor_string($this->EE->TMPL->fetch_param('channel'), 'channel_name');

            if (substr($xsql, 0, 3) == 'AND') $xsql = substr($xsql, 3);

            $sql .= ' '.$xsql;

            $query = $this->EE->db->query($sql);

            if ($query->num_rows() > 0)
            {
                $valid = 'y';
                $valid_cats  = explode('|', $query->row('cat_group') );

                foreach($query->result_array() as $row)
                {
                    if ($this->EE->TMPL->fetch_param('relaxed_categories') == 'yes')
                    {
                        $valid_cats = array_merge($valid_cats, explode('|', $row['cat_group']));
                    }
                    else
                    {
                        $valid_cats = array_intersect($valid_cats, explode('|', $row['cat_group']));
                    }

                    $valid_cats = array_unique($valid_cats);

                    if (count($valid_cats) == 0)
                    {
                        $valid = 'n';
                        break;
                    }
                }
            }
            else
            {
                $valid = 'n';
            }

            if ($valid == 'y')
            {
                // the category URL title should be the first segment left at this point in $qstring,
                // but because prior to this feature being added, category names were used in URLs,
                // and '/' is a valid character for category names.  If they have not updated their
                // category url titles since updating to 1.6, their category URL title could still
                // contain a '/'.  So we'll try to get the category the correct way first, and if
                // it fails, we'll try the whole $qstring

                $cut_qstring = array_shift($temp = explode('/', $qstring));

                // var_dump("SELECT cat_id FROM exp_publisher_categories
                //                       WHERE cat_url_title='".$this->EE->db->escape_str($cut_qstring)."'
                //                       AND publisher_lang_id = ". ee()->publisher_lib->lang_id ."
                //                       AND publisher_status = '". ee()->publisher_lib->status ."'
                //                       AND group_id IN ('".implode("','", $valid_cats)."')");

                $result = $this->EE->db->query("SELECT cat_id FROM exp_publisher_categories
                                      WHERE cat_url_title='".$this->EE->db->escape_str($cut_qstring)."'
                                      AND publisher_lang_id = ". $lang_id ."
                                      AND publisher_status = '". $status ."'
                                      AND group_id IN ('".implode("','", $valid_cats)."')");

                if ($result->num_rows() == 1)
                {
                    $qstring = str_replace($cut_qstring, 'C'.$result->row('cat_id') , $qstring);
                }
                else
                {
                    // var_dump("SELECT cat_id FROM exp_publisher_categories
                    //                       WHERE cat_url_title = '".$this->EE->db->escape_str($qstring)."'
                    //                       AND publisher_lang_id = ". ee()->publisher_lib->lang_id ."
                    //                       AND publisher_status = '". ee()->publisher_lib->status ."'
                    //                       AND group_id IN ('".implode("','", $valid_cats)."')");

                    // give it one more try using the whole $qstring
                    $result = $this->EE->db->query("SELECT cat_id FROM exp_publisher_categories
                                          WHERE cat_url_title = '".$this->EE->db->escape_str($qstring)."'
                                          AND publisher_lang_id = ". $lang_id ."
                                          AND publisher_status = '". $status ."'
                                          AND group_id IN ('".implode("','", $valid_cats)."')");

                    if ($result->num_rows() == 1)
                    {
                        $qstring = 'C'.$result->row('cat_id') ;
                    }
                    // Finally look to the default table for untranslated categories, or if Publisher Lite
                    else
                    {
                        $result = $this->EE->db->query("SELECT cat_id FROM exp_categories
                                          WHERE cat_url_title = '".$this->EE->db->escape_str($qstring)."'
                                          AND group_id IN ('".implode("','", $valid_cats)."')");

                        if ($result->num_rows() == 1)
                        {
                            $qstring = 'C'.$result->row('cat_id') ;
                        }
                    }
                }
            }
        }

        // Is the category being specified by ID?

        if ( !preg_match("#(^|\/)C(\d+)#", $qstring, $match))
        {
            return $this->EE->TMPL->no_results();
        }

        // fetch category field names and id's

        if ($this->enable['category_fields'] === TRUE)
        {
            // limit to correct category group
            $gquery = $this->EE->db->query("SELECT group_id FROM exp_categories WHERE cat_id = '".$this->EE->db->escape_str($match[2])."'");

            if ($gquery->num_rows() == 0)
            {
                return $this->EE->TMPL->no_results();
            }

            $query = $this->EE->db->query("SELECT field_id, field_name
                                FROM exp_category_fields
                                WHERE site_id IN ('".implode("','", $this->EE->TMPL->site_ids)."')
                                AND group_id = '".$gquery->row('group_id')."'");

            if ($query->num_rows() > 0)
            {
                foreach ($query->result_array() as $row)
                {
                    $this->catfields[] = array('field_name' => $row['field_name'], 'field_id' => $row['field_id']);
                }
            }

            $field_sqla = ", cg.field_html_formatting, fd.*, pc.*";
            $field_sqlb = " LEFT JOIN exp_category_field_data AS fd ON fd.cat_id = c.cat_id
                            LEFT JOIN exp_category_groups AS cg ON cg.group_id = c.group_id ";
        }
        else
        {
            $field_sqla = ', pc.*';
            $field_sqlb = '';
        }

        // var_dump("SELECT pc.cat_name, c.parent_id, pc.cat_url_title, pc.cat_description, pc.cat_image {$field_sqla}
        //                     FROM exp_categories AS c
        //                     LEFT JOIN exp_publisher_categories AS pc ON pc.cat_id = c.cat_id
        //                     {$field_sqlb}
        //                     WHERE c.cat_id = '".$this->EE->db->escape_str($match[2])."'
        //                     AND pc.publisher_lang_id = ". ee()->publisher_lib->lang_id ."
        //                     AND pc.publisher_status = '". ee()->publisher_lib->status ."'");

        $query = $this->EE->db->query("SELECT pc.cat_name, c.parent_id, pc.cat_url_title, pc.cat_description, pc.cat_image {$field_sqla}
                            FROM exp_categories AS c
                            LEFT JOIN exp_publisher_categories AS pc ON pc.cat_id = c.cat_id
                            {$field_sqlb}
                            WHERE c.cat_id = '".$this->EE->db->escape_str($match[2])."'
                            AND pc.publisher_lang_id = ". $lang_id ."
                            AND pc.publisher_status = '". $status ."'");

        if ($query->num_rows() == 0)
        {
            // Try one more time to see if a category hasn't been translated, or if its Publisher Lite
            $field_sqla = ", cg.field_html_formatting, fd.*";
            $field_sqlb = " LEFT JOIN exp_category_field_data AS fd ON fd.cat_id = c.cat_id
                            LEFT JOIN exp_category_groups AS cg ON cg.group_id = c.group_id ";

            $query = $this->EE->db->query("SELECT c.cat_name, c.parent_id, c.cat_url_title, c.cat_description, c.cat_image {$field_sqla}
                            FROM exp_categories AS c
                            {$field_sqlb}
                            WHERE c.cat_id = '".$this->EE->db->escape_str($match[2])."'");

            if ($query->num_rows() == 0)
            {
                return $this->EE->TMPL->no_results();
            }
        }

        $row = $query->row_array();

        $this->EE->load->library('file_field');
        $cat_image = $this->EE->file_field->parse_field($query->row('cat_image'));

        $cat_vars = array('category_name'           => $query->row('cat_name'),
                          'category_description'    => $query->row('cat_description'),
                          'category_image'          => $cat_image['url'],
                          'category_id'             => $match[2],
                          'parent_id'               => $query->row('parent_id'));

        // add custom fields for conditionals prep
        foreach ($this->catfields as $v)
        {
            $cat_vars[$v['field_name']] = ($query->row('field_id_'.$v['field_id'])) ? $query->row('field_id_'.$v['field_id']) : '';
        }

        $this->EE->TMPL->tagdata = $this->EE->functions->prep_conditionals($this->EE->TMPL->tagdata, $cat_vars);

        $this->EE->TMPL->tagdata = str_replace( array(LD.'category_id'.RD,
                                            LD.'category_name'.RD,
                                            LD.'category_url_title'.RD,
                                            LD.'category_image'.RD,
                                            LD.'category_description'.RD,
                                            LD.'parent_id'.RD),
                                      array($match[2],
                                            $query->row('cat_name'),
                                            $query->row('cat_url_title'),
                                            $cat_image['url'],
                                            $query->row('cat_description'),
                                            $query->row('parent_id')),
                                      $this->EE->TMPL->tagdata);

        // Check to see if we need to parse {filedir_n}
        if (strpos($this->EE->TMPL->tagdata, '{filedir_') !== FALSE)
        {
            $this->EE->load->library('file_field');
            $this->EE->TMPL->tagdata = $this->EE->file_field->parse_string($this->EE->TMPL->tagdata);
        }

        // parse custom fields
        $this->EE->load->library('typography');
        $this->EE->typography->initialize(array(
                'convert_curly' => FALSE)
                );

        // parse custom fields
        foreach($this->catfields as $ccv)
        {
            if ($query->row('field_id_'.$ccv['field_id']) AND $query->row('field_id_'.$ccv['field_id']) != '')
            {
                $field_content = $this->EE->typography->parse_type($query->row('field_id_'.$ccv['field_id']),
                                                            array(
                                                                  'text_format'     => $query->row('field_ft_'.$ccv['field_id']),
                                                                  'html_format'     => $query->row('field_html_formatting'),
                                                                  'auto_links'      => 'n',
                                                                  'allow_img_url'   => 'y'
                                                                )
                                                        );
                $this->EE->TMPL->tagdata = str_replace(LD.$ccv['field_name'].RD, $field_content, $this->EE->TMPL->tagdata);
            }
            else
            {
                // garbage collection
                $this->EE->TMPL->tagdata = str_replace(LD.$ccv['field_name'].RD, '', $this->EE->TMPL->tagdata);
            }
        }

        // Stop the rest of channel:category_heading from processing
        $this->EE->extensions->end_script = TRUE;

        return $this->EE->TMPL->tagdata;
    }

    /**
      *  Category Tree
      *
      * This function and the next create a nested, hierarchical category tree
      */
    public function category_tree($cdata = array())
    {
        $default = array('group_id', 'channel_ids', 'path', 'template', 'depth', 'channel_array', 'parent_only', 'show_empty', 'strict_empty');

        foreach ($default as $val)
        {
            $$val = ( !isset($cdata[$val])) ? '' : $cdata[$val];
        }

        if ($group_id == '')
        {
            return FALSE;
        }

        if ($this->enable['category_fields'] === TRUE)
        {
            $query = ee()->db->query("SELECT field_id, field_name
                                FROM exp_category_fields
                                WHERE site_id IN ('".implode("','", ee()->TMPL->site_ids)."')
                                AND group_id IN ('".str_replace('|', "','", ee()->db->escape_str($group_id))."')");

            if ($query->num_rows() > 0)
            {
                foreach ($query->result_array() as $row)
                {
                    $this->catfields[] = array('field_name' => $row['field_name'], 'field_id' => $row['field_id']);
                }
            }

            $field_sqla = ", cg.field_html_formatting, fd.* ";

            $field_sqlb = " LEFT JOIN exp_category_field_data AS fd ON fd.cat_id = c.cat_id
                            LEFT JOIN exp_category_groups AS cg ON cg.group_id = c.group_id";
        }
        else
        {
            $field_sqla = '';
            $field_sqlb = '';
        }

        /** -----------------------------------
        /**  Are we showing empty categories
        /** -----------------------------------*/

        // If we are only showing categories that have been assigned to entries
        // we need to run a couple queries and run a recursive function that
        // figures out whether any given category has a parent.
        // If we don't do this we will run into a problem in which parent categories
        // that are not assigned to a channel will be supressed, and therefore, any of its
        // children will be supressed also - even if they are assigned to entries.
        // So... we will first fetch all the category IDs, then only the ones that are assigned
        // to entries, and lastly we'll recursively run up the tree and fetch all parents.
        // Follow that?  No?  Me neither...

        if ($show_empty == 'no')
        {
            // First we'll grab all category ID numbers

            $query = ee()->db->query("SELECT cat_id, parent_id FROM exp_categories
                                 WHERE group_id IN ('".str_replace('|', "','", ee()->db->escape_str($group_id))."')
                                 ORDER BY group_id, parent_id, cat_order");

            $all = array();

            // No categories exist?  Back to the barn for the night..
            if ($query->num_rows() == 0)
            {
                return FALSE;
            }

            foreach($query->result_array() as $row)
            {
                $all[$row['cat_id']] = $row['parent_id'];
            }

            // Next we'l grab only the assigned categories

            $sql = "SELECT DISTINCT(exp_categories.cat_id), parent_id
                    FROM exp_categories
                    LEFT JOIN exp_publisher_category_posts ON exp_categories.cat_id = exp_publisher_category_posts.cat_id
                    LEFT JOIN exp_channel_titles ON exp_publisher_category_posts.entry_id = exp_channel_titles.entry_id ";

            $sql .= "WHERE group_id IN ('".str_replace('|', "','", ee()->db->escape_str($group_id))."') ";

            $sql .= "AND exp_publisher_category_posts.cat_id IS NOT NULL ";

            if (count($channel_ids) && $strict_empty == 'yes')
            {
                $sql .= "AND exp_channel_titles.channel_id IN ('".implode("','", $channel_ids)."') ";
            }
            else
            {
                $sql .= "AND exp_channel_titles.site_id IN ('".implode("','", ee()->TMPL->site_ids)."') ";
            }

            if (($status = ee()->TMPL->fetch_param('status')) !== FALSE)
            {
                $status = str_replace(array('Open', 'Closed'), array('open', 'closed'), $status);
                $sql .= ee()->functions->sql_andor_string($status, 'exp_channel_titles.status');
            }
            else
            {
                $sql .= "AND exp_channel_titles.status != 'closed' ";
            }

            /**------
            /**  We only select entries that have not expired
            /**------*/

            $timestamp = (ee()->TMPL->cache_timestamp != '') ? ee()->TMPL->cache_timestamp : ee()->localize->now;

            if (ee()->TMPL->fetch_param('show_future_entries') != 'yes')
            {
                $sql .= " AND exp_channel_titles.entry_date < ".$timestamp." ";
            }

            if (ee()->TMPL->fetch_param('show_expired') != 'yes')
            {
                $sql .= " AND (exp_channel_titles.expiration_date = 0 OR exp_channel_titles.expiration_date > ".$timestamp.") ";
            }

            if ($parent_only === TRUE)
            {
                $sql .= " AND parent_id = 0";
            }

            $sql .= " ORDER BY group_id, parent_id, cat_order";

            $query = ee()->db->query($sql);

            if ($query->num_rows() == 0)
            {
                return FALSE;
            }

            // All the magic happens here, baby!!

            foreach($query->result_array() as $row)
            {
                if ($row['parent_id'] != 0)
                {
                    $this->find_parent($row['parent_id'], $all);
                }

                $this->cat_full_array[] = $row['cat_id'];
            }

            $this->cat_full_array = array_unique($this->cat_full_array);

            $sql = "SELECT c.cat_id, c.parent_id, c.cat_name, c.cat_url_title, c.cat_image, c.cat_description {$field_sqla}
            FROM exp_categories AS c
            {$field_sqlb}
            WHERE c.cat_id IN (";

            foreach ($this->cat_full_array as $val)
            {
                $sql .= $val.',';
            }

            $sql = substr($sql, 0, -1).')';

            $sql .= " ORDER BY c.group_id, c.parent_id, c.cat_order";

            // PUBLISHER ADDITION
            $this->publisher_query($sql);

            $query = ee()->db->query($sql);

            if ($query->num_rows() == 0)
            {
                return FALSE;
            }
        }
        else
        {
            $sql = "SELECT DISTINCT(c.cat_id), c.parent_id, c.cat_name, c.cat_url_title, c.cat_image, c.cat_description {$field_sqla}
                    FROM exp_categories AS c
                    {$field_sqlb}
                    WHERE c.group_id IN ('".str_replace('|', "','", ee()->db->escape_str($group_id))."') ";

            if ($parent_only === TRUE)
            {
                $sql .= " AND c.parent_id = 0";
            }

            $sql .= " ORDER BY c.group_id, c.parent_id, c.cat_order";

            // PUBLISHER ADDITION
            $this->publisher_query($sql);

            $query = ee()->db->query($sql);

            if ($query->num_rows() == 0)
            {
                return FALSE;
            }
        }

        // Here we check the show parameter to see if we have any
        // categories we should be ignoring or only a certain group of
        // categories that we should be showing.  By doing this here before
        // all of the nested processing we should keep out all but the
        // request categories while also not having a problem with having a
        // child but not a parent.  As we all know, categories are not asexual

        if (ee()->TMPL->fetch_param('show') !== FALSE)
        {
            if (strncmp(ee()->TMPL->fetch_param('show'), 'not ', 4) == 0)
            {
                $not_these = explode('|', trim(substr(ee()->TMPL->fetch_param('show'), 3)));
            }
            else
            {
                $these = explode('|', trim(ee()->TMPL->fetch_param('show')));
            }
        }

        // PUBLISHER ADDITION
        $this->publisher_query($sql);

        foreach($query->result_array() as $row)
        {
            if (isset($not_these) && in_array($row['cat_id'], $not_these))
            {
                continue;
            }
            elseif(isset($these) && !in_array($row['cat_id'], $these))
            {
                continue;
            }

            // PUBLISHER ADDITION
            foreach ($this->publisher_query_result as $publisher_row)
            {
                if ($publisher_row['cat_id'] == $row['cat_id'])
                {
                    foreach ($publisher_row as $key => $val)
                    {
                        if ($val != '')
                        {
                            $row[$key] = $publisher_row[$key];
                        }
                    }
                }
            }

            // if ( !empty($this->publisher_query_result))
            // {
                $this->cat_array[$row['cat_id']] = array($row['parent_id'], $row['cat_name'], $row['cat_image'], $row['cat_description'], $row['cat_url_title']);

                foreach ($row as $key => $val)
                {
                    if (strpos($key, 'field') !== FALSE)
                    {
                        $this->cat_array[$row['cat_id']][$key] = $val;
                    }
                }
            // }
        }

        $this->temp_array = $this->cat_array;

        $open = 0;

        ee()->load->library('typography');
        ee()->typography->initialize(array(
                'convert_curly' => FALSE)
                );

        $this->category_count = 0;
        $total_results = count($this->cat_array);

        // Get category ID from URL for {if active} conditional
        ee()->load->helper('segment');
        $active_cat = parse_category($this->query_string);

        $this->category_subtree(array(
            'parent_id'     => '0',
            'path'          => $path,
            'template'      => $template,
            'channel_array'     => $channel_array
        ));
    }

    private function publisher_query($sql, $return = FALSE)
    {
        $this->cat_array = array();
        $this->publisher_query_result = array();

        $lang_id = ee()->TMPL->fetch_param('publisher_lang_id', ee()->publisher_lib->lang_id);
        $status  = ee()->TMPL->fetch_param('publisher_status', ee()->publisher_lib->status);

        // Only run this if the following are true. Open/Published status and current language has the same data in the exp_category_data table
        if ($status == PUBLISHER_STATUS_DRAFT || !ee()->publisher_lib->is_default_language)
        {
            // Original query
            // SELECT DISTINCT(c.cat_id), c.parent_id, c.cat_name, c.cat_url_title, c.cat_image, c.cat_description , cg.field_html_formatting, fd.*
            //            FROM exp_categories AS c
            //             LEFT JOIN exp_category_field_data AS fd ON fd.cat_id = c.cat_id
            //                    LEFT JOIN exp_category_groups AS cg ON cg.group_id = c.group_id
            //            WHERE c.group_id IN ('1')  ORDER BY c.group_id, c.parent_id, c.cat_order

            $sql = str_replace('FROM', ', pc.* FROM', $sql);
            $sql = str_replace('WHERE', ' LEFT JOIN exp_publisher_categories AS pc ON c.cat_id = pc.cat_id AND pc.publisher_lang_id = '. $lang_id .' AND pc.publisher_status = "'. $status .'" WHERE', $sql);

            // var_dump($sql);

            if ($return)
            {
                return ee()->db->query($sql);
            }
            else
            {
                $this->publisher_query_result = ee()->db->query($sql)->result_array();
            }
        }
        else if ($return)
        {
            return ee()->db->query($sql);
        }
    }

    // ------------------------------------------------------------------------

    /**
      *  Category Sub-tree
      */
    public function category_subtree($cdata = array())
    {
        $default = array('parent_id', 'path', 'template', 'depth', 'channel_array', 'show_empty');

        foreach ($default as $val)
        {
            $$val = ( !isset($cdata[$val])) ? '' : $cdata[$val];
        }

        $open = 0;

        if ($depth == '')
                $depth = 1;

        $tab = '';
        for ($i = 0; $i <= $depth; $i++)
            $tab .= "\t";

        $total_results = count($this->cat_array);

        // Get category ID from URL for {if active} conditional
        ee()->load->helper('segment');
        $active_cat = parse_category($this->query_string);

        foreach($this->cat_array as $key => $val)
        {
            if ($parent_id == $val[0])
            {
                if ($open == 0)
                {
                    $open = 1;
                    $this->category_list[] = "\n".$tab."<ul>\n";
                }

                $chunk = $template;

                ee()->load->library('file_field');
                $cat_image = ee()->file_field->parse_field($val[2]);

                $cat_vars = array('category_name'           => $val[1],
                                  'category_url_title'      => $val[4],
                                  'category_description'    => $val[3],
                                  'category_image'          => $cat_image['url'],
                                  'category_id'             => $key,
                                  'parent_id'               => $val[0],
                                  'active'                  => ($active_cat == $key || $active_cat == $val[4]));

                // add custom fields for conditionals prep
                foreach ($this->catfields as $v)
                {
                    $cat_vars[$v['field_name']] = ( !isset($val['field_id_'.$v['field_id']])) ? '' : $val['field_id_'.$v['field_id']];
                }

                $cat_vars['count'] = ++$this->category_count;
                $cat_vars['total_results'] = $total_results;

                $chunk = ee()->functions->prep_conditionals($chunk, $cat_vars);

                $chunk = str_replace( array(LD.'category_id'.RD,
                                            LD.'category_name'.RD,
                                            LD.'category_url_title'.RD,
                                            LD.'category_image'.RD,
                                            LD.'category_description'.RD,
                                            LD.'parent_id'.RD),
                                      array($key,
                                            ee()->functions->encode_ee_tags($val[1]),
                                            $val[4],
                                            $cat_image['url'],
                                            ee()->functions->encode_ee_tags($val[3]),
                                            $val[0]),
                                      $chunk);

                // PUBLSHER ADDITION
                $chunk = $this->update_paths($chunk, $path, $val, $key);

                // parse custom fields
                foreach($this->catfields as $ccv)
                {
                    if (isset($val['field_id_'.$ccv['field_id']]) AND $val['field_id_'.$ccv['field_id']] != '')
                    {
                        $field_content = ee()->typography->parse_type($val['field_id_'.$ccv['field_id']],
                                                                    array(
                                                                          'text_format'     => $val['field_ft_'.$ccv['field_id']],
                                                                          'html_format'     => $val['field_html_formatting'],
                                                                          'auto_links'      => 'n',
                                                                          'allow_img_url'   => 'y'
                                                                        )
                                                                );
                        $chunk = str_replace(LD.$ccv['field_name'].RD, $field_content, $chunk);
                    }
                    else
                    {
                        // garbage collection
                        $chunk = str_replace(LD.$ccv['field_name'].RD, '', $chunk);
                    }
                }


                /** --------------------------------
                /**  {count}
                /** --------------------------------*/

                if (strpos($chunk, LD.'count'.RD) !== FALSE)
                {
                    $chunk = str_replace(LD.'count'.RD, $this->category_count, $chunk);
                }

                /** --------------------------------
                /**  {total_results}
                /** --------------------------------*/

                if (strpos($chunk, LD.'total_results'.RD) !== FALSE)
                {
                    $chunk = str_replace(LD.'total_results'.RD, $total_results, $chunk);
                }

                $this->category_list[] = $tab."\t<li>".$chunk;

                if (is_array($channel_array))
                {
                    $fillable_entries = 'n';

                    foreach($channel_array as $k => $v)
                    {
                        $k = substr($k, strpos($k, '_') + 1);

                        if ($key == $k)
                        {
                            if ( !isset($fillable_entries) OR $fillable_entries == 'n')
                            {
                                $this->category_list[] = "\n{$tab}\t\t<ul>\n";
                                $fillable_entries = 'y';
                            }

                            $this->category_list[] = "{$tab}\t\t\t$v";
                        }
                    }
                }

                if (isset($fillable_entries) && $fillable_entries == 'y')
                {
                    $this->category_list[] = "{$tab}\t\t</ul>\n";
                }

                $t = '';

// var_dump(array(
// 'parent_id'     => $key,
// 'path'          => $path,
// 'template'      => $template,
// 'depth'             => $depth + 2,
// 'channel_array'     => $channel_array
// ));

                if ($this->category_subtree(
                                            array(
                                                    'parent_id'     => $key,
                                                    'path'          => $path,
                                                    'template'      => $template,
                                                    'depth'             => $depth + 2,
                                                    'channel_array'     => $channel_array
                                                  )
                                    ) != 0 );

            if (isset($fillable_entries) && $fillable_entries == 'y')
            {
                $t .= "$tab\t";
            }

                $this->category_list[] = $t."</li>\n";

                unset($this->temp_array[$key]);

                $this->close_ul($parent_id, $depth + 1);
            }
        }
        return $open;
    }

    // ------------------------------------------------------------------------

    /**
      *  Close </ul> tags
      *
      * This is a helper function to the above
      */
    public function close_ul($parent_id, $depth = 0)
    {
        $count = 0;

        $tab = "";
        for ($i = 0; $i < $depth; $i++)
        {
            $tab .= "\t";
        }

        foreach ($this->temp_array as $val)
        {
            if ($parent_id == $val[0])

            $count++;
        }

        if ($count == 0)
            $this->category_list[] = $tab."</ul>\n";
    }

    /**
     * Update the paths with translated values
     *
     * @param  string $chunk
     * @param  array  $path
     * @return string
     */
    private function update_paths($chunk, $path, $val, $category_id)
    {
        // omg this code is so bad. Not this specifically, its a side
        // effect of the monstrosity above. Really? Why does the url_title
        // value change depending on which method is calling it???
        $url_title = !isset($val[6]) ? $val[4] : $val[6];

        foreach($path as $k => $v)
        {
            if ($this->use_category_names == TRUE)
            {
                // PUBLISHER ADDITION
                if (ee()->publisher_setting->url_translations() && !ee()->publisher_lib->is_default_language)
                {
                    $cat_url_indicator = ee()->publisher_model->get_language(ee()->publisher_lib->lang_id, 'cat_url_indicator');
                    foreach ($path as $p => $v)
                    {
                        $url = $v.'/'.$cat_url_indicator.'/'.ee()->publisher_entry->get_translated_url_title($url_title);
                        $chunk = str_replace($k, $url, $chunk);
                    }
                }
                // Original
                else
                {
                    $chunk = str_replace($k, reduce_double_slashes($v.'/'.$this->reserved_cat_segment.'/'.$url_title), $chunk);
                }
            }
            else
            {
                $chunk = str_replace($k, reduce_double_slashes($v.'/C/'.$category_id), $chunk);
            }
        }

        return $chunk;
    }

}