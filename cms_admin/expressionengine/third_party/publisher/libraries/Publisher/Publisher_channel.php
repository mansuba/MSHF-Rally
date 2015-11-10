<?php

class Publisher_channel extends Channel
{
	public function __construct()
	{
		parent::__construct();

		$this->lang_id = ee()->publisher_lib->lang_id;
		$this->status  = ee()->publisher_lib->status;
	}

	/** ---------------------------------------
	/**  Next / Prev entry tags
	/** ---------------------------------------*/

	public function next_entry()
	{
		return $this->next_prev_entry('next');
	}

	public function prev_entry()
	{
		return $this->next_prev_entry('prev');
	}

	private function get_entry($qstring = '', $lang_id = NULL, $status = NULL)
	{
		$lang_id = $lang_id ? $lang_id : $this->lang_id;
		$status  = $status  ? $status  : $this->status;

		ee()->db->select('t.entry_id, t.entry_date')
			->from('publisher_titles AS t')
			->where('t.publisher_status', $status)
			->where('t.publisher_lang_id', $lang_id)
			->join('channels AS w', 'w.channel_id = t.channel_id', 'left');

		// url_title parameter
		if ($url_title = ee()->TMPL->fetch_param('url_title'))
		{
			ee()->db->where('t.url_title', $url_title);
		}
		else
		{
			// Found entry ID in query string
			if (is_numeric($qstring))
			{
				ee()->db->where('t.entry_id', $qstring);
			}
			// Found URL title in query string
			else
			{
				ee()->db->where('t.url_title', $qstring);
			}
		}

		ee()->db->where_in('w.site_id', ee()->TMPL->site_ids);

		// Channel paremter
		if ($channel_name = ee()->TMPL->fetch_param('channel'))
		{
			ee()->functions->ar_andor_string($channel_name, 'channel_name', 'w');
		}

		return ee()->db->get();
	}

	public function next_prev_entry($which = 'next')
	{
		$which = ($which != 'next' AND $which != 'prev') ? 'next' : $which;
		$sort = ($which == 'next') ? 'ASC' : 'DESC';

		// Don't repeat our work if we already know the single entry page details
		if ( !isset(ee()->session->cache['channel']['single_entry_id']) OR !isset(ee()->session->cache['channel']['single_entry_date']))
		{
			// no query string?  Nothing to do...
			if (($qstring = $this->query_string) == '')
			{
				return;
			}

			/** --------------------------------------
			/**  Remove page number
			/** --------------------------------------*/

			if (preg_match("#/P\d+#", $qstring, $match))
			{
				$qstring = ee()->functions->remove_double_slashes(str_replace($match[0], '', $qstring));
			}

			/** --------------------------------------
			/**  Remove "N"
			/** --------------------------------------*/

			if (preg_match("#/N(\d+)#", $qstring, $match))
			{
				$qstring = ee()->functions->remove_double_slashes(str_replace($match[0], '', $qstring));
			}

			if (strpos($qstring, '/') !== FALSE)
			{
				$qstring = substr($qstring, 0, strpos($qstring, '/'));
			}

			if (in_array($qstring, ee()->publisher_session->language_codes))
	        {
	        	$segments = ee()->uri->segments;
	        	$qstring = array_pop($segments);
	        }

			/** ---------------------------------------
			/**  Query for the entry id and date
			/** ---------------------------------------*/

			$query = $this->get_entry($qstring);

			if ($query->num_rows() == 0)
			{
				$query = $this->get_entry($qstring, ee()->publisher_lib->default_lang_id, PUBLISHER_STATUS_OPEN);

				if ($query->num_rows() != 0)
				{
					$this->lang_id = ee()->publisher_lib->default_lang_id;
					$this->status  = PUBLISHER_STATUS_OPEN;
				}
			}

			// no results or more than one result?  Buh bye!
			if ($query->num_rows() != 1)
			{
				ee()->TMPL->log_item('Channel Next/Prev Entry tag error: Could not resolve single entry page id.');
				return;
			}

			$row = $query->row_array();

			ee()->session->cache['channel']['single_entry_id'] = $row['entry_id'];
			ee()->session->cache['channel']['single_entry_date'] = $row['entry_date'];
		}

		/** ---------------------------------------
		/**  Find the next / prev entry
		/** ---------------------------------------*/

		$ids = '';

		// Get included or excluded entry ids from entry_id parameter
		if (($entry_id = ee()->TMPL->fetch_param('entry_id')) != FALSE)
		{
			$ids = ee()->functions->sql_andor_string($entry_id, 't.entry_id').' ';
		}

		$sql = 'SELECT t.entry_id, t.title, t.url_title
				FROM (exp_publisher_titles AS t)
				LEFT JOIN exp_channel_titles as ct ON ct.entry_id = t.entry_id
				LEFT JOIN exp_channels AS w ON w.channel_id = t.channel_id ';

		/* --------------------------------
		/*  We use LEFT JOIN when there is a 'not' so that we get
		/*  entries that are not assigned to a category.
		/* --------------------------------*/

		if ((substr(ee()->TMPL->fetch_param('category_group'), 0, 3) == 'not' OR substr(ee()->TMPL->fetch_param('category'), 0, 3) == 'not') && ee()->TMPL->fetch_param('uncategorized_entries') !== 'no')
		{
			$sql .= 'LEFT JOIN exp_publisher_category_posts ON t.entry_id = exp_publisher_category_posts.entry_id
					 LEFT JOIN exp_publisher_categories ON exp_publisher_category_posts.cat_id = exp_publisher_categories.cat_id ';
		}
		elseif(ee()->TMPL->fetch_param('category_group') OR ee()->TMPL->fetch_param('category'))
		{
			$sql .= 'INNER JOIN exp_publisher_category_posts ON t.entry_id = exp_publisher_category_posts.entry_id
					 INNER JOIN exp_publisher_categories ON exp_publisher_category_posts.cat_id = exp_publisher_categories.cat_id ';
		}

		$sql .= ' WHERE t.entry_id != '.ee()->session->cache['channel']['single_entry_id'].' '.$ids;
		$sql .= ' AND t.publisher_status = \''. $this->status .'\' AND t.publisher_lang_id = '. $this->lang_id;

		$timestamp = (ee()->TMPL->cache_timestamp != '') ? ee()->TMPL->cache_timestamp : ee()->localize->now;

	    if (ee()->TMPL->fetch_param('show_future_entries') != 'yes')
	    {
	    	$sql .= " AND t.entry_date < {$timestamp} ";
	    }

		// constrain by date depending on whether this is a 'next' or 'prev' tag
		if ($which == 'next')
		{
			$sql .= ' AND t.entry_date >= '.ee()->session->cache['channel']['single_entry_date'].' ';
			$sql .= ' AND IF (t.entry_date = '.ee()->session->cache['channel']['single_entry_date'].', t.entry_id > '.ee()->session->cache['channel']['single_entry_id'].', 1) ';
		}
		else
		{
			$sql .= ' AND t.entry_date <= '.ee()->session->cache['channel']['single_entry_date'].' ';
			$sql .= ' AND IF (t.entry_date = '.ee()->session->cache['channel']['single_entry_date'].', t.entry_id < '.ee()->session->cache['channel']['single_entry_id'].', 1) ';
		}

	    if (ee()->TMPL->fetch_param('show_expired') != 'yes')
	    {
			$sql .= " AND (ct.expiration_date = 0 OR ct.expiration_date > {$timestamp}) ";
	    }

		$sql .= " AND w.site_id IN ('".implode("','", ee()->TMPL->site_ids)."') ";

		if ($channel_name = ee()->TMPL->fetch_param('channel'))
		{
			$sql .= ee()->functions->sql_andor_string($channel_name, 'channel_name', 'w')." ";
		}

		if ($status = ee()->TMPL->fetch_param('status'))
	    {
			$status = str_replace('Open',   'open',   $status);
			$status = str_replace('Closed', 'closed', $status);

			$sql .= ee()->functions->sql_andor_string($status, 'ct.status')." ";
		}
		else
		{
			$sql .= "AND ct.status = 'open' ";
		}

		/**------
	    /**  Limit query by category
	    /**------*/

	    if (ee()->TMPL->fetch_param('category'))
	    {
	    	if (stristr(ee()->TMPL->fetch_param('category'), '&'))
	    	{
	    		/** --------------------------------------
	    		/**  First, we find all entries with these categories
	    		/** --------------------------------------*/

	    		$for_sql = (substr(ee()->TMPL->fetch_param('category'), 0, 3) == 'not') ? trim(substr(ee()->TMPL->fetch_param('category'), 3)) : ee()->TMPL->fetch_param('category');

	    		$csql = "SELECT exp_publisher_category_posts.entry_id, exp_publisher_category_posts.cat_id, ".
						str_replace('SELECT', '', $sql).
						ee()->functions->sql_andor_string(str_replace('&', '|', $for_sql), 'exp_publisher_categories.cat_id');

	    		//exit($csql);

	    		$results = ee()->db->query($csql);

	    		if ($results->num_rows() == 0)
	    		{
					return;
	    		}

	    		$type = 'IN';
	    		$categories	 = explode('&', ee()->TMPL->fetch_param('category'));
	    		$entry_array = array();

	    		if (substr($categories[0], 0, 3) == 'not')
	    		{
	    			$type = 'NOT IN';

	    			$categories[0] = trim(substr($categories[0], 3));
	    		}

				foreach($results->result_array() as $row)
	    		{
	    			$entry_array[$row['cat_id']][] = $row['entry_id'];
	    		}

	    		if (count($entry_array) < 2 OR count(array_diff($categories, array_keys($entry_array))) > 0)
	    		{
					return;
	    		}

	    		$chosen = call_user_func_array('array_intersect', $entry_array);

	    		if (count($chosen) == 0)
	    		{
					return;
	    		}

	    		$sql .= "AND t.entry_id ".$type." ('".implode("','", $chosen)."') ";
	    	}
	    	else
	    	{
	    		if (substr(ee()->TMPL->fetch_param('category'), 0, 3) == 'not' && ee()->TMPL->fetch_param('uncategorized_entries') !== 'no')
	    		{
	    			$sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category'), 'exp_publisher_categories.cat_id', '', TRUE)." ";
	    		}
	    		else
	    		{
	    			$sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category'), 'exp_publisher_categories.cat_id')." ";
	    		}
	    	}
	    }

	    if (ee()->TMPL->fetch_param('category_group'))
	    {
	        if (substr(ee()->TMPL->fetch_param('category_group'), 0, 3) == 'not' && ee()->TMPL->fetch_param('uncategorized_entries') !== 'no')
			{
				$sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category_group'), 'exp_categories.group_id', '', TRUE)." ";
			}
			else
			{
				$sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category_group'), 'exp_categories.group_id')." ";
			}
	    }

		$sql .= " ORDER BY t.entry_date {$sort}, t.entry_id {$sort} LIMIT 1";

		$query = ee()->db->query($sql);

		if ($query->num_rows() == 0)
		{
			return;
		}

		/** ---------------------------------------
		/**  Replace variables
		/** ---------------------------------------*/

		ee()->load->library('typography');

		if (strpos(ee()->TMPL->tagdata, LD.'path=') !== FALSE)
		{
			$path  = (preg_match("#".LD."path=(.+?)".RD."#", ee()->TMPL->tagdata, $match)) ? ee()->functions->create_url($match[1]) : ee()->functions->create_url("SITE_INDEX");
			$path .= '/'.$query->row('url_title');
			ee()->TMPL->tagdata = preg_replace("#".LD."path=.+?".RD."#", reduce_double_slashes($path), ee()->TMPL->tagdata);
		}

		if (strpos(ee()->TMPL->tagdata, LD.'id_path=') !== FALSE)
		{
			$id_path  = (preg_match("#".LD."id_path=(.+?)".RD."#", ee()->TMPL->tagdata, $match)) ? ee()->functions->create_url($match[1]) : ee()->functions->create_url("SITE_INDEX");
			$id_path .= '/'.$query->row('entry_id');

			ee()->TMPL->tagdata = preg_replace("#".LD."id_path=.+?".RD."#", reduce_double_slashes($id_path), ee()->TMPL->tagdata);
		}

		if (strpos(ee()->TMPL->tagdata, LD.'url_title') !== FALSE)
		{
			ee()->TMPL->tagdata = str_replace(LD.'url_title'.RD, $query->row('url_title'), ee()->TMPL->tagdata);
		}

		if (strpos(ee()->TMPL->tagdata, LD.'entry_id') !== FALSE)
		{
			ee()->TMPL->tagdata = str_replace(LD.'entry_id'.RD, $query->row('entry_id'), ee()->TMPL->tagdata);
		}

		if (strpos(ee()->TMPL->tagdata, LD.'title') !== FALSE)
		{
			ee()->TMPL->tagdata = str_replace(LD.'title'.RD, ee()->typography->format_characters($query->row('title')), ee()->TMPL->tagdata);
		}

		if (strpos(ee()->TMPL->tagdata, '_entry->title') !== FALSE)
		{
			ee()->TMPL->tagdata = preg_replace('/'.LD.'(?:next|prev)_entry->title'.RD.'/',
													ee()->typography->format_characters($query->row('title')),
													ee()->TMPL->tagdata);
		}

		return ee()->TMPL->tagdata;
	}
}