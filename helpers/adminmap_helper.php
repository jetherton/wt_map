<?php
/**
 * Admin helper
 * 
 */
class adminmap_helper_Core {

	// Table Prefix
	protected static $table_prefix;
	
	/******************************************************************
	 * 					HARD CODED STUFF
	 *****************************************************************/	
	//Where can we find the project completion date field?
	public static $project_completion_date_field_id = 10;
	//The id for the WaterTracker Trained category
	public static $watertracker_trained = 54;
	//the id for the WaterTracker no Trained category
	public static $watertracker_not_trained = 55;
	//the ids of the functioning status
	public static $func_status_functioning = 25;
	public static $func_status_malfunction = 26;
	public static $func_status_failed = 27;
	public static $func_status_restored = 89;
	

	static function init()
	{
		// Set Table Prefix
		self::$table_prefix = Kohana::config('database.default.table_prefix');
	}

	/**************************************************************************************************************
      * Given all the parameters returns a list of incidents that meet the search criteria
      */
	public static function setup_adminmap($map_controller, $map_view = "adminmap/mapview", $map_css = "adminmap/css/adminmap")		
	{
	
		//set the CSS for this
		if($map_css != null)
		{
			plugin::add_stylesheet($map_css);
		}
		
		plugin::add_javascript("adminmap/js/jquery.flot");
		plugin::add_javascript("adminmap/js/excanvas.min");
		plugin::add_javascript("adminmap/js/timeline");
		plugin::add_javascript("adminmap/js/jquery.hovertip-1.0");

		
		$map_controller->template->content = new View($map_view);
		
		// Get Default Color
		$map_controller->template->content->default_map_all = Kohana::config('settings.default_map_all');
	}
	
	
	/****
	* Sets up the overlays and shares
	*/
	public static function set_overlays_shares($map_controller)
	{
				// Get all active Layers (KMZ/KML)
		$layers = array();
		$config_layers = Kohana::config('map.layers'); // use config/map layers if set
		if ($config_layers == $layers) {
			foreach (ORM::factory('layer')
					  ->where('layer_visible', 1)
					  ->find_all() as $layer)
			{
				$layers[$layer->id] = array($layer->layer_name, $layer->layer_color,
					$layer->layer_url, $layer->layer_file);
			}
		} else {
			$layers = $config_layers;
		}
		$map_controller->template->content->layers = $layers;

		// Get all active Shares
		$shares = array();
		foreach (ORM::factory('sharing')
				  ->where('sharing_active', 1)
				  ->find_all() as $share)
		{
			$shares[$share->id] = array($share->sharing_name, $share->sharing_color);
		}
		$map_controller->template->content->shares = $shares;
	}
	
	
	/*
	* this makes the map for this plugin
	*/
	public static function set_map($template, $themes, $json_url, $json_timeline_url, $javascript_view = 'adminmap/mapview_js',
							$div_map_view = 'adminmap/main_map', $div_timeline_view = 'adminmap/main_timeline')
	{
	
		////////////////////////////////////////////////////////////////Map and Slider Blocks////////////////////////////////////////////////////////////////////////////
		$div_map = new View($div_map_view);
		$div_timeline = new View($div_timeline_view);
			// Filter::map_main - Modify Main Map Block
			Event::run('ushahidi_filter.map_main', $div_map);
			// Filter::map_timeline - Modify Main Map Block
			Event::run('ushahidi_filter.map_timeline', $div_timeline);
		$template->content->div_map = $div_map;
		$template->content->div_timeline = $div_timeline;

	
		///////////////////////////////////////////////////////////////SETUP THE DATES////////////////////////////////////////////////////////////////////////////
        // Get The START, END and Incident Dates
        $startDate = "";
		$endDate = "";
		$display_startDate = 0;
		$display_endDate = 0;

		$db = new Database();
		// Next, Get the Range of Years
		$query = $db->query('SELECT DATE_FORMAT(incident_date, \'%Y-%c\') AS dates FROM '.self::$table_prefix.'incident WHERE incident_active = 1 GROUP BY DATE_FORMAT(incident_date, \'%Y-%c\') ORDER BY incident_date');

		$first_year = date('Y');
		$last_year = date('Y');
		$first_month = 1;
		$last_month = 12;
		$i = 0;

		foreach ($query as $data)
		{
			$date = explode('-',$data->dates);

			$year = $date[0];
			$month = $date[1];

			// Set first year
			if($i == 0)
			{
				$first_year = $year;
				$first_month = $month;
			}

			// Set last dates
			$last_year = $year;
			$last_month = $month;

			$i++;
		}
		//check if this is later than changes to the categories
		$query = $db->query('SELECT * FROM  `'.self::$table_prefix.'versioncategories` ORDER BY TIME DESC LIMIT 1');
		foreach($query as $data)
		{
			$date = explode('-',$data->time);
			//update the year
			if(intval($last_year) < intval($date[0]))
			{
				$last_year = $date[0];
				$last_month = $date[1];
			}
			//update the month
			elseif(intval($last_month) < intval($date[1]))
			{
				$last_month = $date[1];
			}
		}
		

		$show_year = $first_year;
		$selected_start_flag = TRUE;
		while($show_year <= $last_year)
		{
			$startDate .= "<optgroup label=\"".$show_year."\">";

			$s_m = 1;
			if($show_year == $first_year)
			{
				// If we are showing the first year, the starting month may not be January
				$s_m = $first_month;
			}

			$l_m = 12;
			if($show_year == $last_year)
			{
				// If we are showing the last year, the ending month may not be December
				$l_m = $last_month;
			}

			for ( $i=$s_m; $i <= $l_m; $i++ )
			{
				if ( $i < 10 )
				{
					// All months need to be two digits
					$i = "0".$i;
				}
				$startDate .= "<option value=\"".strtotime($show_year."-".$i."-01")."\"";
				if($selected_start_flag == TRUE)
				{
					$display_startDate = strtotime($show_year."-".$i."-01");
					$startDate .= " selected=\"selected\" ";
					$selected_start_flag = FALSE;
				}
				$startDate .= ">".date('M', mktime(0,0,0,$i,1))." ".$show_year."</option>";
			}
			$startDate .= "</optgroup>";

			$endDate .= "<optgroup label=\"".$show_year."\">";
			for ( $i=$s_m; $i <= $l_m; $i++ )
			{
				if ( $i < 10 )
				{
					// All months need to be two digits
					$i = "0".$i;
				}
				$endDate .= "<option value=\"".strtotime($show_year."-".$i."-".date('t', mktime(0,0,0,$i,1))." 23:59:59")."\"";

                if($i == $l_m AND $show_year == $last_year)
				{
					$display_endDate = strtotime($show_year."-".$i."-".date('t', mktime(0,0,0,$i,1))." 23:59:59");
					$endDate .= " selected=\"selected\" ";
				}
				$endDate .= ">".date('M', mktime(0,0,0,$i,1))." ".$show_year."</option>";
			}
			$endDate .= "</optgroup>";

			// Show next year
			$show_year++;
		}

		Event::run('ushahidi_filter.active_startDate', $display_startDate);
		Event::run('ushahidi_filter.active_endDate', $display_endDate);
		Event::run('ushahidi_filter.startDate', $startDate);
		Event::run('ushahidi_filter.endDate', $endDate);
		
		$template->content->div_timeline->startDate = $startDate;
		$template->content->div_timeline->endDate = $endDate;
		///////////////////////////////////////////////////////////////MAP JAVA SCRIPT////////////////////////////////////////////////////////////////////////////
		
		//turn the map on, also turn on the timeline
		//$template->flot_enabled = TRUE; //this is done using our own custom .js files in the adminmap/js folder.
		$themes->map_enabled = true;
		
		//check if we're on the front end, if we are then the template and themese will be different
		if($themes != $template)
		{
			$themes->main_page = true;
		}
		
		$themes->js = new View($javascript_view);
		$themes->js->default_map = Kohana::config('settings.default_map');
		$themes->js->default_zoom = Kohana::config('settings.default_zoom');
		

		// Map Settings
		$clustering = Kohana::config('settings.allow_clustering');
		$marker_radius = Kohana::config('map.marker_radius');
		$marker_opacity = Kohana::config('map.marker_opacity');
		$marker_stroke_width = Kohana::config('map.marker_stroke_width');
		$marker_stroke_opacity = Kohana::config('map.marker_stroke_opacity');

		// pdestefanis - allows to restrict the number of zoomlevels available
		$numZoomLevels = Kohana::config('map.numZoomLevels');
		$minZoomLevel = Kohana::config('map.minZoomLevel');
	   	$maxZoomLevel = Kohana::config('map.maxZoomLevel');

		// pdestefanis - allows to limit the extents of the map
		$lonFrom = Kohana::config('map.lonFrom');
		$latFrom = Kohana::config('map.latFrom');
		$lonTo = Kohana::config('map.lonTo');
		$latTo = Kohana::config('map.latTo');

		
		$themes->js->json_url = $json_url;
		$themes->js->json_timeline_url  = $json_timeline_url;
		$themes->js->marker_radius =
			($marker_radius >=1 && $marker_radius <= 10 ) ? $marker_radius : 5;
		$themes->js->marker_opacity =
			($marker_opacity >=1 && $marker_opacity <= 10 )
			? $marker_opacity * 0.1  : 0.9;
		$themes->js->marker_stroke_width =
			($marker_stroke_width >=1 && $marker_stroke_width <= 5 ) ? $marker_stroke_width : 2;
		$themes->js->marker_stroke_opacity =
			($marker_stroke_opacity >=1 && $marker_stroke_opacity <= 10 )
			? $marker_stroke_opacity * 0.1  : 0.9;

		// pdestefanis - allows to restrict the number of zoomlevels available
		$themes->js->numZoomLevels = $numZoomLevels;
		$themes->js->minZoomLevel = $minZoomLevel;
		$themes->js->maxZoomLevel = $maxZoomLevel;

		// pdestefanis - allows to limit the extents of the map
		$themes->js->lonFrom = $lonFrom;
		$themes->js->latFrom = $latFrom;
		$themes->js->lonTo = $lonTo;
		$themes->js->latTo = $latTo;

		$themes->js->default_map = Kohana::config('settings.default_map');
		$themes->js->default_zoom = Kohana::config('settings.default_zoom');
		$themes->js->latitude = Kohana::config('settings.default_lat');
		$themes->js->longitude = Kohana::config('settings.default_lon');
		$themes->js->default_map_all = Kohana::config('settings.default_map_all');
		$themes->js->active_startDate = $display_startDate;
		$themes->js->active_endDate = $display_endDate;
		


	}
	
	public static function set_categories($map_controller, $on_backend = false, $group = false)
	{
	
		// Check for localization of parent category
		// Get locale
		$l = Kohana::config('locale.language.0');
	
		$parent_categories = array();
	
		///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		//Check to see if we're dealing with a group, and thus
		//should show group specific categories
		///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		if($group != false)
		{
			//check and make sure the simpel groups category is installed
			$plugin = ORM::factory('plugin')
				->where('plugin_name', 'simplegroups')
				->where('plugin_active', '1')
				->find();
			if(!$plugin)
			{
				throw new Exception("A group was set in adminmap_helper::set_categories() when the SimpleGroupl plugin is not installed");
			}
		
			$cats = ORM::factory('simplegroups_category');
			if(!$on_backend)
			{	
				$cats = $cats->where('category_visible', '1');
			}
			$cats = $cats->where('parent_id', '0');
			$cats = $cats->where('applies_to_report', 1);
			$cats = $cats->where('simplegroups_groups_id', $group->id)
				->find_all() ;
			foreach ($cats as $category)
			{				
				/////////////////////////////////////////////////////////////////////////////////////////////
				// Get the children
				/////////////////////////////////////////////////////////////////////////////////////////////
				$children = array();
				foreach ($category->children as $child)
				{
					// Check for localization of child category

					$translated_title = Simplegroups_category_lang_Model::simplegroups_category_title($child->id,$l);

					if($translated_title)
					{
						$display_title = $translated_title;
					}
					else
					{
						$display_title = $child->category_title;
					}

					$children["sg_".$child->id] = array(
						$display_title,
						$child->category_color,
						$child->category_image
					);
					
				}

				

				$translated_title = Simplegroups_category_lang_Model::simplegroups_category_title($category->id,$l);

				if($translated_title)
				{
					$display_title = $translated_title;
				}else{
					$display_title = $category->category_title;
				}

				// Put it all together				
				$parent_categories["sg_".$category->id] = array(
					$display_title,
					$category->category_color,
					$category->category_image,
					$children
				);				
			}
		}

		/////////////////////////////////////////////////////////////////////////////////////////////
        // Get all active top level categories
		/////////////////////////////////////////////////////////////////////////////////////////////
		
		$cats = ORM::factory('category');
		if(!$on_backend)
		{	
			$cats = $cats->where('category_visible', '1');
		}
		$cats = $cats->where('parent_id', '0')
			->find_all() ;
		foreach ($cats as $category)
		{
			/////////////////////////////////////////////////////////////////////////////////////////////
			// Get the children
			/////////////////////////////////////////////////////////////////////////////////////////////
			$children = array();
			foreach ($category->children as $child)
			{
				// Check for localization of child category

				$translated_title = Category_Lang_Model::category_title($child->id,$l);

				if($translated_title)
				{
					$display_title = $translated_title;
				}
				else
				{
					$display_title = $child->category_title;
				}

				$children[$child->id] = array(
					$display_title,
					$child->category_color,
					$child->category_image
				);

				if ($child->category_trusted)
				{ // Get Trusted Category Count
					$trusted = ORM::factory("incident")
						->join("incident_category","incident.id","incident_category.incident_id")
						->where("category_id",$child->id);
					if ( ! $trusted->count_all())
					{
						unset($children[$child->id]);
					}
				}
			}

			

			$translated_title = Category_Lang_Model::category_title($category->id,$l);

			if($translated_title)
			{
				$display_title = $translated_title;
			}else{
				$display_title = $category->category_title;
			}

			// Put it all together
			$parent_categories[$category->id] = array(
				$display_title,
				$category->category_color,
				$category->category_image,
				$children
			);

			if ($category->category_trusted)
			{ // Get Trusted Category Count
				$trusted = ORM::factory("incident")
					->join("incident_category","incident.id","incident_category.incident_id")
					->where("category_id",$category->id);
				if ( ! $trusted->count_all())
				{
					unset($parent_categories[$category->id]);
				}
			}
		}
		
		
		
		
		$map_controller->template->content->categories = $parent_categories;
	}//end method
	
	
	



	/////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////METHODS FOR the JSON CONTROLLER
	///////////////////////////////////////////////////////////////////////////////////////////////////
	
	
	/**
	* Generate JSON in NON-CLUSTER mode
	* $edit_report_path is used to set where the link to edit/view a report should be set to
	* $on_the_back_end sets whether or not this user is viewing this data from the backend
	*/
	public static function json_index($json_controller, $edit_report_path = 'admin/reports/edit/', $on_the_back_end = true,
		$extra_where_text = "",
		$joins = array(),
		$custom_category_to_table_mapping = array(),
		$link_target = "_self")
	{
		$json = "";
		$json_item = "";
		$json_array = array();
		$cat_array = array();
		$color = Kohana::config('settings.default_map_all');
		$default_color = Kohana::config('settings.default_map_all');
		$icon = "";

		$category_ids = array();
		$incident_id = "";
		$neighboring = "";
		$media_type = "";
		$show_unapproved="3"; //1 show only approved, 2 show only unapproved, 3 show all
		$logical_operator = "or";

		if( isset($_GET['c']) AND ! empty($_GET['c']) )
		{
			//check if there are any ',' in the category
			if((strpos($_GET['c'], ",")===false) && is_numeric($_GET['c']))
			{
				$category_ids = array($_GET['c']);	
			}
			else
			{
				$category_ids = explode(",", $_GET['c'],-1); //get rid of that trailing ";"
			}
		}
		else
		{
			$category_ids = array("0");
		}
		$is_all_categories = false;
		If(count($category_ids) == 0 || $category_ids[0] == '0')
		{
			$is_all_categories = true;
		}

		
		$approved_text = "";
		if( $on_the_back_end)
		{
			//figure out if we're showing unapproved stuff or what.
			if (isset($_GET['u']) AND !empty($_GET['u']))
			{
			    $show_unapproved = (int) $_GET['u'];
			}		
			if($show_unapproved == 1)
			{
				$approved_text = "incident.incident_active = 1 ";
			}
			else if ($show_unapproved == 2)
			{
				$approved_text = "incident.incident_active = 0 ";
			}
			else if ($show_unapproved == 3)
			{
				$approved_text = " (incident.incident_active = 0 OR incident.incident_active = 1) ";
			}
		}
		else
		{
			$approved_text = "incident.incident_active = 1 ";
		}
		

		
		
		//should we color unapproved reports a different color?
		$color_unapproved = 2;
		if (isset($_GET['uc']) AND !empty($_GET['uc']))
		{
		    $color_unapproved = (int) $_GET['uc'];
		}
		
		if (isset($_GET['lo']) AND !empty($_GET['lo']))
		{
		    $logical_operator =  $_GET['lo'];
		}
		
		
		
		

		if (isset($_GET['i']) AND !empty($_GET['i']))
		{
		    $incident_id = (int) $_GET['i'];
		}

		if (isset($_GET['n']) AND !empty($_GET['n']))
		{
		    $neighboring = (int) $_GET['n'];
		}

		$where_text = '';
		// Do we have a media id to filter by?
		/* we don't use this anymore
		if (isset($_GET['m']) AND !empty($_GET['m']) AND $_GET['m'] != '0')
		{
		    $media_type = (int) $_GET['m'];
		    $where_text .= " AND ".self::$table_prefix."media.media_type = " . $media_type;
		}
		*/

		if (isset($_GET['s']) AND !empty($_GET['s']))
		{
		    $start_date = (int) $_GET['s'];
		    $where_text .= " AND UNIX_TIMESTAMP(".self::$table_prefix."incident.incident_date) >= '" . $start_date . "'";
		}

		if (isset($_GET['e']) AND !empty($_GET['e']))
		{
		    $end_date = (int) $_GET['e'];
		    $where_text .= " AND UNIX_TIMESTAMP(".self::$table_prefix."incident.incident_date) <= '" . $end_date . "'";
		}

		
		//get our new custom color based on the categories we're working with
		$color = self::merge_colors($category_ids, $custom_category_to_table_mapping);

		$incidents = adminmap_reports::get_reports_list_by_cat($category_ids, 
			$approved_text . ' '. $where_text. " ". $extra_where_text, 
			$logical_operator,
			"incident.id",
			"asc");

		$curr_id = "not a number";
		$colors = array();
		    
		$json_item_first = "";  // Variable to store individual item for report detail page
		foreach ($incidents as $marker)
		{
			$json_item = "{";
			$json_item .= "\"type\":\"Feature\",";
			$json_item .= "\"properties\": {";
			$json_item .= "\"id\": \"".$marker['id']."\", \n";
			$cat_names_txt = "";
			$count = 0;
			
			$json_item .= "\"name\":\"" .date("n/j/Y", strtotime($marker['incident_date'])).":<br/>". str_replace(chr(10), ' ', str_replace(chr(13), ' ', "<a target='".$link_target."' href='" . url::base() . $edit_report_path . $marker['id'] . "'>" . htmlentities($marker['incident_title']) . "</a>".$cat_names_txt)) . "\",";
			$json_item .= "\"description\":\"" .date("n/j/Y", strtotime($marker['incident_date'])).":<br/>". str_replace(chr(10), ' ', str_replace(chr(13), ' ', "<a target='".$link_target."' href='" . url::base() . $edit_report_path . $marker['id'] . "'>" . htmlentities($marker['incident_title']) . "</a>".$cat_names_txt)) . "\",";
			//for compatiblity with the InfoWindows plugin
			$json_item .= "\"link\":\"" .url::base(). "$edit_report_path{$marker['id']}\",";

			if (isset($category)) 
			{
				$json_item .= "\"category\":[" . $category_id . "], ";
			} 
			else 
			{
				$json_item .= "\"category\":[0], ";
			}

			//check if it's a unapproved/unactive report
			if($marker['incident_active'] == 0 && $color_unapproved==2)
			{
				$item_color = "000000";
				$json_item .= "\"color\": \"000000\", \n";
				$json_item .= "\"icon\": \"".$icon."\", \n";
			}
			//check if we're looking at all categories
			elseif(count($category_ids) == 0 || $category_ids[0] == '0')
			{	
				$item_color = $default_color;
				$json_item .= "\"color\": \"".$default_color."\", \n";
				$json_item .= "\"icon\": \"".$icon."\", \n";
			}
			//check if we're using AND
			elseif($logical_operator=="and")
			{					
				$item_color = $color;
				$json_item .= "\"color\": \"".$color."\", \n";
				$json_item .= "\"icon\": \"".$icon."\", \n";
			}
			//else we're using OR to combine categories
			else
			{
				$color = self::merge_colors_for_dots($colors);
				$item_color = $color;
				$json_item .= "\"color\": \"".$color."\", \n";
				$json_item .= "\"icon\": \"".$icon."\", \n";
			}

			$json_item .= "\"timestamp\": \"" . strtotime($marker['incident_date']) . "\"";
			$json_item .= "},";
			$json_item .= "\"geometry\": {";
			$json_item .= "\"type\":\"Point\", ";
			
			$lon = $marker['lon'] ? $marker['lon'] : "0";
			$lat = $marker['lat'] ? $marker['lat'] : "0";
			
			$json_item .= "\"coordinates\":[" . $lon . ", " . $lat . "]";
			$json_item .= "}";
			$json_item .= "}";

			

			array_push($json_array, $json_item);
			
			
			
			//reset the variables
			$cat_names = array();
			$colors = array();
			
		}//end loop
		
		if ($json_item_first)
		{ // Push individual marker in last so that it is layered on top when pulled into map
		    array_push($json_array, $json_item_first);
		}
		$json = implode(",", $json_array);

		header('Content-type: application/json');
		$json_controller->template->json = $json;
	}
	
	
	
	
	
	
	
	/************************************************************************************************
	* Function, this'll merge colors. Given an array of category IDs it'll return a hex string
	* of all the colors merged together
	*/
	public static function merge_colors($category_ids_temp, $custom_category_to_table_mapping = array())
	{
		//because I might unset some of the values in the $category_ids array
		$category_ids = adminmap_reports::array_copy($category_ids_temp);
		
		//check if we're looking at category 0
		if(count($category_ids) == 0 || $category_ids[0] == '0')
		{
			return Kohana::config('settings.default_map_all');
		}
		
		$red = 0;
		$green = 0;
		$blue = 0;
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		//Lets handle custom categories
		foreach($custom_category_to_table_mapping as $cat_name=>$custom_cats)
		{
			$where_str_color = ""; //to get the colors we're gonna use
			$i = 0;
			foreach($category_ids as $key=>$id)
			{
				//check if we have a custom cateogry ID
				$delimiter_pos = strpos($id, "_");
				if($delimiter_pos !== false)
				{
					//get the custom category name
					$custom_cat_name = substr($id, 0, $delimiter_pos);
					//get the custom category's numeric id
					$custom_cat_id = substr($id,$delimiter_pos + 1);
					
					//does the custom_cat_name match our current custom cat
					if($cat_name == $custom_cat_name)
					{
						$i++;
						if($i > 1)
						{
							$where_str_color = $where_str_color . " OR ";
						}
						$where_str_color = $where_str_color . "id = ".$custom_cat_id;
						
						unset($category_ids[$key]);			
					}
				}
			}
			if($where_str_color != "")
			{
				//get the custom categories themselves and add up their colors:
				// Retrieve all the categories with their colors
				$categories = ORM::factory($custom_cats['child'])
				    ->where($where_str_color)
				    ->find_all();

				//now for each color break it into RGB, add them up, then normalize
				
				foreach($categories as $category)
				{
					$color = $category->category_color;
					$numeric_colors = self::_hex2RGB($color);
					$red = $red + $numeric_colors['red'];
					$green = $green + $numeric_colors['green'];
					$blue = $blue + $numeric_colors['blue'];
				}
			}
		}//end loop through all custom categorie sources
		
		

		////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		//Next lets handle regular categories		
		//first lets figure out the composite color that we're gonna usehere
		if(count($category_ids) > 0)
		{
			$where_str_color = ""; //to get the colors we're gonna use
			$i = 0;
			foreach($category_ids as $id)
			{
				$i++;
				if($i > 1)
				{
					$where_str_color = $where_str_color . " OR ";
				}
				$where_str_color = $where_str_color . "id = ".$id;
			}


			// Retrieve all the categories with their colors
			$categories = ORM::factory('category')
			    ->where($where_str_color)
			    ->find_all();

			//now for each color break it into RGB, add them up, then normalize
			
			foreach($categories as $category)
			{
				$color = $category->category_color;
				$numeric_colors = self::_hex2RGB($color);
				$red = $red + $numeric_colors['red'];
				$green = $green + $numeric_colors['green'];
				$blue = $blue + $numeric_colors['blue'];
			}
		}
		
		//now normalize
		$color_length = sqrt( ($red*$red) + ($green*$green) + ($blue*$blue));
	
		//make sure there's no divide by zero
		if($color_length == 0)
		{
			$color_length = 255;
		}
		$red = ($red / $color_length) * 255;
		$green = ($green / $color_length) * 255;
		$blue = ($blue / $color_length) * 255;
	
		
		//pad with zeros if there's too much space
		$red = dechex($red);
		if(strlen($red) < 2)
		{
			$red = "0".$red;
		}
		$green = dechex($green);
		if(strlen($green) < 2)
		{
			$green = "0".$green;
		}
		$blue = dechex($blue);
		if(strlen($blue) < 2)
		{
			$blue = "0".$blue;
		}
		//now put the color back together and return it
		return $red.$green.$blue;
		
	}//end method merge colors



/************************************************************************************************
	* Function, this'll merge colors. Given an array of category IDs it'll return a hex string
	* of all the colors merged together
	*/
	public static function merge_colors_for_dots($colors)
	{
		//check if we're dealing with just one color
		if(count($colors)==1)
		{
			foreach($colors as $color)
			{
				return $color;
			}
		}
		//now for each color break it into RGB, add them up, then normalize
		$red = 0;
		$green = 0;
		$blue = 0;
		foreach($colors as $color)
		{
			$numeric_colors = self::_hex2RGB($color);
			$red = $red + $numeric_colors['red'];
			$green = $green + $numeric_colors['green'];
			$blue = $blue + $numeric_colors['blue'];
		}
		//now normalize
		$color_length = sqrt( ($red*$red) + ($green*$green) + ($blue*$blue));
	
		//make sure there's no divide by zero
		if($color_length == 0)
		{
			$color_length = 255;
		}
		$red = ($red / $color_length) * 255;
		$green = ($green / $color_length) * 255;
		$blue = ($blue / $color_length) * 255;
	
		
		//pad with zeros if there's too much space
		$red = dechex($red);
		if(strlen($red) < 2)
		{
			$red = "0".$red;
		}
		$green = dechex($green);
		if(strlen($green) < 2)
		{
			$green = "0".$green;
		}
		$blue = dechex($blue);
		if(strlen($blue) < 2)
		{
			$blue = "0".$blue;
		}
		//now put the color back together and return it
		return $red.$green.$blue;
		
	}//end method merge colors



	private static function _hex2RGB($hexStr, $returnAsString = false, $seperator = ',') 
	{
		$hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $hexStr); // Gets a proper hex string
		$rgbArray = array();
		if (strlen($hexStr) == 6) 
		{ //If a proper hex code, convert using bitwise operation. No overhead... faster
			$colorVal = hexdec($hexStr);
			$rgbArray['red'] = 0xFF & ($colorVal >> 0x10);
			$rgbArray['green'] = 0xFF & ($colorVal >> 0x8);
			$rgbArray['blue'] = 0xFF & $colorVal;
		} 
		elseif (strlen($hexStr) == 3) 
		{ //if shorthand notation, need some string manipulations
			$rgbArray['red'] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
			$rgbArray['green'] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
			$rgbArray['blue'] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
		} 
		else 
		{
			return false; //Invalid hex color code
		}
		return $returnAsString ? implode($seperator, $rgbArray) : $rgbArray; // returns the rgb string or the associative array
	}







	
	
	

/***************************************************************************************************************
     * Generate JSON in CLUSTER mode
     * $edit_report_path sets the path to the link to edit/view a report
     * $list_report_path sets the path to view a cluster of reports
     * $on_the_back_end sets whether or not this user is looking at this from the front end or back end
     */
    public static function json_cluster($controller, 
	$edit_report_path = 'admin/reports/edit/', 
	$list_reports_path = "admin/adminmap_reports/index/",
	$on_the_back_end = true,
	$extra_where_text = "",
	$joins = array(),
	$custom_category_to_table_mapping = array(),
	$link_target = "_self")
    {

    	//check to see how we're adding GET params 
    	$url_param_join_character = (strpos($list_reports_path, "?") === false) ? "?" : "&";
    	
        // Database
        $db = new Database();

        $json = "";
        $json_item = "";
        $json_array = array();
		$geometry_array = array();

        $color = Kohana::config('settings.default_map_all');
		$default_color = Kohana::config('settings.default_map_all');
        $icon = "";
		$logical_operator = "or";
	
		$show_unapproved="3"; //1 show only approved, 2 show only unapproved, 3 show all
		if($on_the_back_end)
		{
			//figure out if we're showing unapproved stuff or what.
			if (isset($_GET['u']) AND !empty($_GET['u']))
			{
			    $show_unapproved = (int) $_GET['u'];
			}
			$approved_text = "";
			if($show_unapproved == 1)
			{
				$approved_text = "incident.incident_active = 1 ";
			}
			else if ($show_unapproved == 2)
			{
				$approved_text = "incident.incident_active = 0 ";
			}
			else if ($show_unapproved == 3)
			{
				$approved_text = " (incident.incident_active = 0 OR incident.incident_active = 1) ";
			}	
		}
		else
		{
			$approved_text = "incident.incident_active = 1 ";
			$show_unapproved = 1;
		}
		
		
		//should we color unapproved reports a different color?
		$color_unapproved = 2;
	        if (isset($_GET['uc']) AND !empty($_GET['uc']))
	        {
		    $color_unapproved = (int) $_GET['uc'];
	        }
		
	
		if (isset($_GET['lo']) AND !empty($_GET['lo']))
	        {
		    $logical_operator =  $_GET['lo'];
	        }
	
	
	        // Get Zoom Level
	        $zoomLevel = (isset($_GET['z']) AND !empty($_GET['z'])) ?
	            (int) $_GET['z'] : 8;
	
	        //$distance = 60;
	        $distance = ((10000000 >> $zoomLevel) / 100000) / 2;
		
	
	        // Category ID
		$is_all_categories = false;
		$category_ids=array();
	    if( isset($_GET['c']) AND ! empty($_GET['c']) )
		{
			//check if there are any ',' in the category
			if((strpos($_GET['c'], ",")===false) && is_numeric($_GET['c']))
			{
				$category_ids = array($_GET['c']);	
			}
			else
			{
				$category_ids = explode(",", $_GET['c'],-1); //get rid of that trailing ";"
			}
		}
		else
		{
			$category_ids = array("0");
		}
		If(count($category_ids) == 0 || $category_ids[0] == '0')
		{
			$is_all_categories = true;
		}
	
	        // Start Date
	        $start_date = (isset($_GET['s']) AND !empty($_GET['s'])) ?
	            (int) $_GET['s'] : "0";
	
	        // End Date
	        $end_date = (isset($_GET['e']) AND !empty($_GET['e'])) ?
	            (int) $_GET['e'] : "0";
	
	        // SouthWest Bound
	        $southwest = (isset($_GET['sw']) AND !empty($_GET['sw'])) ?
	            $_GET['sw'] : "0";
	
	        $northeast = (isset($_GET['ne']) AND !empty($_GET['ne'])) ?
	            $_GET['ne'] : "0";
	
	        $filter = "";
	        $filter .= ($start_date) ?
	            " AND incident.incident_date >= '" . date("Y-m-d H:i:s", $start_date) . "'" : "";
	        $filter .= ($end_date) ?
	            " AND incident.incident_date <= '" . date("Y-m-d H:i:s", $end_date) . "'" : "";
	
	        if ($southwest AND $northeast)
	        {
	            list($latitude_min, $longitude_min) = explode(',', $southwest);
	            list($latitude_max, $longitude_max) = explode(',', $northeast);
	
	            $filter .= " AND location.latitude >=".(float) $latitude_min.
	                " AND location.latitude <=".(float) $latitude_max;
	            $filter .= " AND location.longitude >=".(float) $longitude_min.
	                " AND location.longitude <=".(float) $longitude_max;
	        }
	        
	        
	    ///////////////////////Limit the bounding box/////////////////////////
	    //if we're at zoom level 10 or greater than limit the bounding box
	    //first figure out how long and tall the view port is in miles
	    //then multiply that by some number proportional to the zoom level and that's 
	    //the bounding box
	    ///////////////////////////////////////////////////////////////////////
	    $bounding_box_sql = '';
	
	    if($zoomLevel >= 10 AND isset($_GET['north'])) //since if north is there, the rest will be there I'm cool with this
	    {
	    	
	    	$window_size_factor = 5;
	    	
	    	$north = floatval($_GET['north']);
	    	$south = floatval($_GET['south']);
	    	$west = floatval($_GET['west']);
	    	$east = floatval($_GET['east']);
	    	
	    	$width = self::lat_lon_distance($north, $east, $north,$west);
	    	$height = self::lat_lon_distance($north, $west, $south,$west);
	    	
	    	if(isset($_GET['debug'])){	    		
	    		echo "\r\ncurrent window height: $height width: $width\r\n";
	    	}
	    		    	
	    	$bounding_width = $width * $window_size_factor;
	    	$bounding_height = $height * $window_size_factor;
	    	
	    	if(isset($_GET['debug'])){
	    		echo "\r\nExpanded window height: $bounding_height width: $bounding_width\r\n\r\n";
	    	}
	    	
	    	
	    	//get new north
	    	if(isset($_GET['debug'])){echo "\r\n old north $north ";}
	    	$bounding_north = self::destinationPoint($north, $west, 0, $bounding_height);
	    	$bounding_north = $bounding_north['lat'];
	    	if(isset($_GET['debug'])){echo " new north $bounding_north\r\n";}
	    	//new south
	    	$bounding_south = self::destinationPoint($south, $west, 180, $bounding_height);
	    	$bounding_south = $bounding_south['lat'];
	    	//get new east
	    	$bounding_east = self::destinationPoint($north, $east, 90, $bounding_width);
	    	$bounding_east = $bounding_east['lon'];
	    	//new west
	    	$bounding_west = self::destinationPoint($north, $west, 270, $bounding_width);
	    	$bounding_west = $bounding_west['lon'];
	    	
	    	$bounding_box_sql = ' AND ( location.latitude > '. $bounding_south . ' AND location.latitude < '.$bounding_north . 
	    		' AND location.longitude > '.$bounding_west. ' AND location.longitude < '.$bounding_east.') ';
	    }
	
		//stuff john just added
		$normal_color = self::merge_colors($category_ids, $custom_category_to_table_mapping);
	
		$t = microtime(true);
		$markers = adminmap_reports::get_reports_list_by_cat($category_ids, 
			$approved_text. ' '. $bounding_box_sql .$filter ,
			$logical_operator,
			"incident.id",
			"asc");
		if(isset($_GET['debug'])){
		$t1 = microtime(true);      
		echo ($t1-$t);
		$t = $t1;
		echo "\r\n\r\n";
		} 

		 
		/**
		 * **********************************************************************************************************************
		 * ********************************************************************************************************************** 
		 * We have the incidents, now process them
		 */

	
		
		
		
		////////////////////////////////////////////////////////////////////////////////////////////////////////
		//                             Let the clustering begin
		////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	        $clusters = array();    // Clustered
	        $singles = array();     // Non Clustered
	        
	        if(isset($_GET['debug'])){echo "\r\n\r\nNumber of points: " . count($markers);}
	        
	        $round_number = 0;
	        switch ($zoomLevel )
	        {
	        	case 0:
	        	case 1:
	        		$round_number=-4;
	        	case 2:
	        	case 3:
	        		$round_number=-2;
	        		break;
	        	case 4:
	        		$round_number=-1;
	        		break;
	        	case 5:
	        	case 6:
	        	case 7:
	        		$round_number=0;
	        		break;
	        	case 8:
	        	case 9:
	        		$round_number=1;
	        		break;
        		case 10:
        		case 11:
        			$round_number=2;
        			break;
        		case 12:        		
        			$round_number=2;
        			break;        		
        		case 13:
        			$round_number=3;
        			break;        			
        		case 14:
        			$round_number=4;
        			break;
        		case 15:
        			$round_number=5;
        		case 16:
       			case 17:
   				case 18:
				case 19:
				case 20:
					$round_number=22;
        			break;
        			
	        }
	        
	        foreach($markers as $marker)
	        {
	        	
	        	//create a key
	        	$key = "";
	        	if($zoomLevel >= 5)
	        	{
	        		$key = round($marker['lat'],$round_number) . round($marker['lon'],$round_number).$marker['province_cat'];
	        	}
	        	else
	        	{
	        		$key = round($marker['lat'],$round_number) . round($marker['lon'],$round_number);
	        	}
	        	//check if there's already an entry for this cluster? If not make it
	        	if(!isset($clusters[$key])) 
	        	{
	        		$cluster = array();
	        		$cluster['center_lon'] = 0;
	        		$cluster['center_lat'] = 0; 
	        		$cluster['center'] = null;
	        		$cluster['sw'] = null;
	        		$cluster['ne'] = null;
	        		$cluster['north'] = $marker['lat'];
	        		$cluster['south'] = $marker['lat'];
	        		$cluster['east'] = $marker['lon'];
	        		$cluster['west'] = $marker['lon'];
	        		$cluster['count'] = 0;
	        		$cluster['province_id'] = $marker['province_cat'];
	        		$cluster['starter'] = $marker;

	        		$clusters[$key] = $cluster;
	        	}
	        	//update the cluster
	        	$clusters[$key]['count']++;
	        	$clusters[$key]['center_lon'] += $marker['lon'];
	        	$clusters[$key]['center_lat'] += $marker['lat'];
	        	
	        	if($marker['lat'] < $clusters[$key]['south'])
	        		$clusters[$key]['south'] = $marker['lat'];
	        	if($marker['lat'] > $clusters[$key]['north'])
	        		$clusters[$key]['north'] = $marker['lat'];
	        	if($marker['lon'] < $clusters[$key]['west'])
	        		$clusters[$key]['west'] = $marker['lon'];
	        	if($marker['lon'] > $clusters[$key]['east'])
	        		$clusters[$key]['east'] = $marker['lon'];
	        	
	        		        	        	
	        }
	        foreach($clusters as $key=>$cluster)
	        {
	        	$lon = $cluster['center_lon'] / $cluster['count'];
	        	$lat = $cluster['center_lat'] / $cluster['count'];
	        	$clusters[$key]['center'] = $lon. ','. $lat;
	        	$clusters[$key]['sw'] = $cluster['west'].','.$cluster['south'];
	        	$clusters[$key]['ne'] = $cluster['east'].','.$cluster['north'];
	        }
	        
	        /*
			$miss = 0;
	        // Loop until all markers have been compared
	        while (count($markers))
	        {
	        
	        	$marker  = array_pop($markers);
	        	//to keep track of the geometry of a cluster
	        	$lat = $marker['lat'];
	        	$lon = $marker['lon'];
	        	$north = $south = $marker['lat'];
	        	$east = $west = $marker['lon'];
	        	$count = 1;
	        	
				
			    $cluster = array();
			    $cluster['province_id'] = $marker['province_cat'];
		        $marker_cat = $marker['province_cat'];
		        $marker_lat = $marker['lat'];
		        $marker_lon = $marker['lon'];
	            foreach ($markers as $key => $target)
	            {
					
			        
					$pixels = abs($marker_lon - $target['lon']) + abs($marker_lat - $target['lat']);
	
	                // If two markers are closer than defined distance, remove compareMarker from array and add to cluster.
	                // and they're from the same province
	                if ($pixels < $distance AND ($zoomLevel < 5 OR $marker_cat == $target['province_cat']))
	                {
						//since we found a home for the target, remove it from circulation
	                    unset($markers[$key]);
	                   	//update the gemetry
	                   	$lat += $target['lat'];
	                   	$lon += $target['lon'];
	                   	if($target['lat'] < $south)
							$south = $target['lat'];
						if($target['lat'] > $north)
							$north = $target['lat'];
						if($target['lon'] < $west)
							$west = $target['lon'];
						if($target['lon'] > $east)
							$east = $target['lon'];
						$count++;
	                    
	                    
	                }//end if the the two points are close
	                else
	                {
	                	$miss++;
	                }
	                //trying to minizmie memory use
	                unset($target);
	            }//end for loop
	            
	            // If a marker was added to cluster, also add the marker we were comparing to.
	            //if (count($cluster) > 0)
	            if ($count > 1)
	            {
					$lat = $lat / $count;
					$lon = $lon / $count;
					//geometry
					$cluster['center'] = $lon.','.$lat;
					$cluster['sw'] = $west.','.$south;
					$cluster['ne'] = $east.','.$north;
					$cluster['count'] = $count;
					
					$clusters[] = $cluster;
	            }
	            //it's a loner
	            else
	            {
					$singles[] = $marker;
	            }
	        }

	        
	    if(isset($_GET['debug'])){echo "\r\n\r\nmiss count ".$miss;
	    echo "\r\n\r\n";}
	    */

		//echo "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\r\n\r\n";
		
		//now that we've made our clusters, lets get the skinny on the province colors
		$sql = 'select id, category_color, category_title FROM '. adminmap_reports::$table_prefix . 'category WHERE parent_id = '.adminmap_reports::$province_cat_parent_id;
		$db = new Database();
		$query = $db->query($sql);
		$category_colors = array();
		//we're also going to see if a province related category is at 
		//play in the current set of categories
		$is_province_at_play = false;
		foreach($query as $q)
		{
			$category_colors[$q->id] = $q;
			if(!$is_province_at_play)
			{
				foreach($category_ids as $c)
				{
					if($c == $q->id)
					{
						$is_province_at_play = true;
					}
				}
			}
		}
		
		
		//finally check if the parent province category is at play
		if(!$is_province_at_play)
		{
			foreach($category_ids as $c)
			{
				if($c == adminmap_reports::$province_cat_parent_id)
				{
					$is_province_at_play = true;
				}
			}
		}
		
		
	
		$i = 0;
        // Create Json
        foreach ($clusters as $key=>$cluster)
        {
			$province_id = $cluster['province_id'];
			if($is_province_at_play)
			{
				$color = $category_colors[$province_id]->category_color;
			}
			else
			{
				$color = $normal_color;
			}
			$province_name = $category_colors[$province_id]->category_title;
							
			
			//make category description string		
			$category_str = "";
			/*
			if( (!$is_all_categories) && ($logical_operator!="and"))
			{
				foreach($cluster_cat_count as $cat_id => $cat_count)
				{
					$category_str .= "<li>".$cluster_cat_names[$cat_id]." (".$cat_count.")</li>";
				}
				$category_str = "<br/><br/> Categories in this cluster (number of reports):<ul>". $category_str."</ul>";
			}
			*/
			
			//make the categories string for the URL
			//if we're on the backend do it the old fashioned way
			$categories_str = "";
			if($on_the_back_end)
			{
				$categories_str = implode(",", $category_ids);
				$categories_str = "c=" . $categories_str;
			}
			//otherwise do it the new fangled fancy way, which is better, but it's a pain to change the way i do everything.
			else
			{
				foreach($category_ids as $c)
				{
					$categories_str .= "&c%5B%5D=" . mysql_real_escape_string($c); 
				}
			}
					
	    	// get cluster center
            $cluster_center = $cluster['center'];
            $southwest = $cluster['sw'];
            $northeast = $cluster['ne'];
            
        	$time_filter = "";
			if($start_date != "0" && $end_date != "0")
			{
				$time_filter = "&s=".$start_date."&e=".$end_date;
			}

            // Number of Items in Cluster
            $cluster_count = count($cluster);
            $cluster_count = $cluster['count'];
	    
            $description = "";
            $link = "";
            $dmText = "";
            //if it's a cluster of one
		    if($cluster['count'] == 1)
		    {
		    	$description = $cluster['starter']['incident_title'];
		    	$link = url::base().$edit_report_path.$cluster['starter']['id'];
		    }
		    //if it's a cluster that has been clustered by zoom level
		    elseif ($zoomLevel >= 5)
		    {
		    	$description = $cluster['count']. " Reports <br/> In ".$province_name;
		    	$dmText = "&dm=".$province_id;
		    	$link = url::base(). $list_reports_path . $url_param_join_character . $categories_str."&sw=".$southwest."&ne=".$northeast."&lo=".$logical_operator."&u=".$show_unapproved.$time_filter;
		    }
		    //if it's a cluster that has been clustered by location only
		    else
		    {
		    	$description = $cluster['count']. " Reports";
		    	$link = url::base(). $list_reports_path . $url_param_join_character . $categories_str."&sw=".$southwest."&ne=".$northeast."&lo=".$logical_operator."&u=".$show_unapproved.$time_filter;
		    }
            
	    
            $json_item = "{\"type\":\"Feature\",\"properties\": {";	    	
	    	$json_item .= "\"description\":\"" . str_replace(chr(10), ' ', str_replace(chr(13), ' ', "<a target='".$link_target."' href='". $link ."'>" . $description."</a> ".$category_str)) . "\",";
            $json_item .= "\"name\":\"" . str_replace(chr(10), ' ', str_replace(chr(13), ' ', "<a target='".$link_target."' href='" .$link."'>" . $description."</a> ".$category_str)) . "\",";
	    	$json_item .= "\"link\":\"" . url::base(). $list_reports_path . $url_param_join_character . $categories_str."&sw=".$southwest."&ne=".$northeast."&lo=".$logical_operator."&u=".$show_unapproved.$time_filter."\",";
            $json_item .= "\"category\":[0], ";
            /*
			if($contains_nonactive && $color_unapproved==2)
			{
				$json_item .= "\"color\": \"000000\", \n\"icon\": \"".$icon."\", \n";
			}
			//check if we're looking at all categories
			elseif($is_all_categories)
			{					
				$json_item .= "\"color\": \"".$default_color."\", \n\"icon\": \"".$icon."\", \n";
			}
			//check if we're using AND
			elseif($logical_operator=="and")
			{					
				$json_item .= "\"color\": \"".$color."\", \n\"icon\": \"".$icon."\", \n";
			}
			//else we're using OR to combine categories
			else
			{
				$dot_color = self::merge_colors_for_dots($cluster_cat_colors);
				$json_item .= "\"color\": \"".$dot_color."\", \n\"icon\": \"".$icon."\", \n";
			}
			*/
			$json_item .= "\"color\": \"".$color."\", \n\"icon\": \"".$icon."\", \n";            
            $json_item .= "\"timestamp\": \"0\", \"count\": \"" . $cluster_count . "\"},\"geometry\": {\"type\":\"Point\", \"coordinates\":[" . $cluster_center . "]}}";

            array_push($json_array, $json_item);
        }


		//do singles
        foreach ($singles as $single)
        {
			$province_id = $single['province_cat'];			
			if($is_province_at_play)
			{
				$color = $category_colors[$province_id]->category_color;
			}
			else
			{
				$color = $normal_color;
			}
			$province_name = $category_colors[$province_id]->category_title;
		

		
		//echo $single->incident_title."\n\r".Kohana::debug($single_cat_names)."\r\n\r\n";
		
		$category_description = "";
		/*
		if(!$is_all_categories && $logical_operator!="and")
		{
			$count = 0;
			foreach($single_cat_names as $cat_name)
			{
				$count++;
				if($count > 1)
				{
					$category_description .= ", ";
				}
				$category_description .= $cat_name;
			}
			
			$category_description = "<br/><br/>Falls under categories:<br/>".$category_description;
		}
		*/
		
		$json_item = "{";
		$json_item .= "\"type\":\"Feature\",";
		$json_item .= "\"properties\": {";
		$json_item .= "\"description\":\"" .date("n/j/Y", strtotime($single['incident_date'])).":<br/>". str_replace(chr(10), ' ', str_replace(chr(13), ' ', "<a target='".$link_target."' href='" . url::base() . $edit_report_path . $single['id'] . "'/>".str_replace('"','\"',$single['incident_title'])."</a>".$category_description)) . "\",";   
		$json_item .= "\"name\":\"" .date("n/j/Y", strtotime($single['incident_date'])).":<br/>". str_replace(chr(10), ' ', str_replace(chr(13), ' ', "<a target='".$link_target."' href='" . url::base() . $edit_report_path . $single['id'] . "'/>".str_replace('"','\"',$single['incident_title'])."</a>".$category_description)) . "\",";   
	    $json_item .= "\"link\":\"" .url::base(). "{$edit_report_path}{$single['id']}\","; 
            $json_item .= "\"category\":[0], ";
	    //check if it's a unapproved/unactive report
	    /*
		if($single->incident_active == 0 && $color_unapproved==2)
		{
			$json_item .= "\"color\": \"000000\", \n";
			$json_item .= "\"icon\": \"".$icon."\", \n";
		}
		//check if we're looking at all categories
		elseif($is_all_categories)
		{					
			$json_item .= "\"color\": \"".$default_color."\", \n";
			$json_item .= "\"icon\": \"".$icon."\", \n";
		}
		//check if we're using AND
		elseif($logical_operator=="and")
		{					
			$json_item .= "\"color\": \"".$color."\", \n";
			$json_item .= "\"icon\": \"".$icon."\", \n";
		}
		//else we're using OR to combine categories
		else
		{
			$dot_color = self::merge_colors_for_dots($single_colors);
			$json_item .= "\"color\": \"".$dot_color."\", \n";
			$json_item .= "\"icon\": \"".$icon."\", \n";
		}            
		*/
		$lon = $single['lon'] ? $single['lon'] : "0";
		$lat = $single['lat'] ? $single['lat'] : "0";
		
		$json_item .= "\"color\": \"".$color."\", \n";
	    $json_item .= "\"timestamp\": \"0\", ";
            $json_item .= "\"count\": \"" . 1 . "\"";
            $json_item .= "},";
            $json_item .= "\"geometry\": {";
            $json_item .= "\"type\":\"Point\", ";
            $json_item .= "\"coordinates\":[" . $lon . ", " . $lat . "]";
            $json_item .= "}";
            $json_item .= "}";

            array_push($json_array, $json_item);
        }
        
        if(isset($_GET['debug'])){
        $t1 = microtime(true);
        echo "\r\n\r\ntime to make clusters: ";      
		echo ($t1-$t);
		$t = $t1;
		echo "\r\n\r\n";}

        $json = implode(",", $json_array);
	if (count($geometry_array))
	{
		$json = implode(",", $geometry_array).",".$json;
	}

        header('Content-type: application/json');
        $controller->template->json = $json;

    }//end cluster method
	    
    
  
  
  
  
  
  
  
  
     /******************************************************************************************************************************************
     * Retrieve timeline JSON
     * $on_the_back_end is used to set if the user is looking at this on the backend or not
     ******************************************************************************************************************************************/
    public static function json_timeline( $controller, 
								$category_ids, 
								$on_the_back_end = true, 
								$extra_where_text = "", 
								$joins = array(),
								$custom_category_to_table_mapping = array())
    {
		
	$category_ids = explode(",", $category_ids,-1); //get rid of that trailing ","
	//a little flag to alert us to the presence of the "ALL CATEGORIES" category
	$is_all_categories = false;
	If(count($category_ids) == 0 || $category_ids[0] == '0')
	{
		$is_all_categories = true;
	}
	
	///Get the logical operator
	$logical_operator = "or";
	if (isset($_GET['lo']) AND !empty($_GET['lo']))
	{
		$logical_operator =  $_GET['lo'];
	}
	//for the special cases
	$is_or = true;
	if(strtolower($logical_operator) != "or")
	{
				$is_or = false;
	}
	
	$color = self::merge_colors($category_ids, $custom_category_to_table_mapping);
	
	//initialize this way up here 
	$well_counts = array();
	
	//////////////////////////////////////////////////////////
	//Now we're going to check if any special categories are
	//present, becuase if they are, they'll affect how we calculate
	//the timeline, we'll also need to remove some of them
	//from the list of cats we search by
	/////////////////////////////////////////////////////////
	$func_status_functioning_found = false;
	$func_status_malfunctioning_found = false;
	$func_status_restored_found = false;
	$watertracker_trained_found = false;
	$watertracker_not_trained_found = false;
	$non_special_cats_found = false;
	$non_special_cats = array();
	foreach($category_ids as $cat)
	{
		if($cat == adminmap_helper::$func_status_functioning)
		{
			$func_status_functioning_found = true;
		}
		elseif($cat == adminmap_helper::$func_status_malfunction)
		{
			$func_status_malfunctioning_found = true;
		}
		elseif($cat == adminmap_helper::$func_status_restored)
		{
			$func_status_restored_found = true;	
		}
		/*
		elseif($cat == adminmap_helper::$watertracker_trained)
		{
			$watertracker_trained_found = true;
		}
		elseif($cat == adminmap_helper::$watertracker_not_trained)
		{
			$watertracker_not_trained_found = true;
		}
		*/
		else
		{
			$non_special_cats_found = true;
			$non_special_cats[] = $cat;
		}

	}
		

	$controller->auto_render = FALSE;
	$db = new Database();
		
	
	$show_unapproved="3"; //1 show only approved, 2 show only unapproved, 3 show all
	$approved_text = " (1=1) ";
	if($on_the_back_end)
	{
		//figure out if we're showing unapproved stuff or what.
		if (isset($_GET['u']) AND !empty($_GET['u']))
		{
		    $show_unapproved = (int) $_GET['u'];
		}
		$approved_text = "";
		if($show_unapproved == 1)
		{
			$approved_text = "incident.incident_active = 1 ";
		}
		else if ($show_unapproved == 2)
		{
			$approved_text = "incident.incident_active = 0 ";
		}
		else if ($show_unapproved == 3)
		{
			$approved_text = " (incident.incident_active = 0 OR incident.incident_active = 1) ";
		}
	}
	else
	{
		$approved_text = "incident.incident_active = 1 ";
	}
	
	


        $interval = (isset($_GET["i"]) AND !empty($_GET["i"])) ?
            $_GET["i"] : "month";


        // Get the Counts
		$select_date_text = "DATE_FORMAT(incident_date, '%Y-%m-01')";		
		$groupby_date_text = "DATE_FORMAT(incident_date, '%Y%m')";
		$select_date_text_alt = "DATE_FORMAT(time, '%Y-%m-01')";
		$groupby_date_text_alt = "DATE_FORMAT(time, '%Y%m')";
		if ($interval == 'day')
		{
			$select_date_text = "DATE_FORMAT(incident_date, '%Y-%m-%d')";
			$groupby_date_text = "DATE_FORMAT(incident_date, '%Y%m%d')";
			$select_date_text_alt = "DATE_FORMAT(time, '%Y-%m-%d')";
			$groupby_date_text_alt = "DATE_FORMAT(time, '%Y%m%d')";
		}
		elseif ($interval == 'hour')
		{
			$select_date_text = "DATE_FORMAT(incident_date, '%Y-%m-%d %H:%M')";
			$groupby_date_text = "DATE_FORMAT(incident_date, '%Y%m%d%H')";
			$select_date_text_alt = "DATE_FORMAT(time, '%Y-%m-%d %H:%M')";
			$groupby_date_text_alt = "DATE_FORMAT(time, '%Y%m%d%H')";
		}
        
	
		//setup the graph data
        $graph_data = array();
        
	
		//////////////////////////////////////////////////////////////////////
		//figure out what incidents are at play
		//////////////////////////////////////////////////////////////////////
		
		//get the reports, just as they would be queired when rendinering dots
		$incidents = adminmap_reports::get_reports_list_by_cat($category_ids, 
		$approved_text." ".$extra_where_text ,
		$logical_operator);       
	
	     //create the approved IDs string just like when rendering the dots.
		$approved_IDs_str = "('-1')";
		if(count($incidents) > 0)
		{
			$i = 0;
			$approved_IDs_str = "(";
			foreach($incidents as $incident)
			{
				$i++;
				$approved_IDs_str = ($i > 1) ? $approved_IDs_str.', ' : $approved_IDs_str;
				$approved_IDs_str = $approved_IDs_str."'".$incident['id']."'";
			}
			$approved_IDs_str = $approved_IDs_str.") ";
		}
		
		//////////////////////////////////////////////////////////////////////
		//figure out what incidents are at for special categories
		//////////////////////////////////////////////////////////////////////
		if($func_status_functioning_found  OR $func_status_malfunctioning_found OR
			$func_status_restored_found OR $watertracker_trained_found OR
			$watertracker_not_trained_found )
		{
			//get the reports, just as they would be queired when rendinering dots
			$incidents = adminmap_reports::get_reports_list_by_cat($non_special_cats, 
			$approved_text." ".$extra_where_text ,
			$logical_operator);       
		
			 //create the approved IDs string just like when rendering the dots.
			$non_special_approved_IDs_str = "('-1')";
			if(count($incidents) > 0)
			{
				$i = 0;
				$non_special_approved_IDs_str = "(";
				foreach($incidents as $incident)
				{
					$i++;
					$non_special_approved_IDs_str .= ($i > 1) ? ', ' : '';
					$non_special_approved_IDs_str .= "'".$incident['id']."'";
				}
				$non_special_approved_IDs_str .= ") ";
			}
		}
		
		
		
		
		////////////////////////////////////////////////////
		//The default case
		////////////////////////////////////////////////////
		//if we're looking at all categories OR non-special categories AND
		//no special categories are found then do this:
		
		if(($is_all_categories OR $non_special_cats_found) AND !($func_status_functioning_found OR $func_status_malfunctioning_found OR $func_status_restored_found))
		{
			//get the dates
			$query = 'SELECT UNIX_TIMESTAMP('.$select_date_text.') AS time, COUNT(id) AS number FROM '.adminmap_helper::$table_prefix.'incident as response WHERE response.id in'.$approved_IDs_str.' GROUP BY '.$groupby_date_text;
			$query = $db->query($query);
			
			//now add in the data from the query of existing wells		
			foreach ( $query as $items )
			{
				$time = intval($items->time);
				if(!isset($well_counts[$time]))
				{
					$well_counts[$time] = 0;
				}
				$well_counts[$time] = $well_counts[$time] + $items->number;
			}
		
			//sort the array so it stays in chronological order
			ksort($well_counts);
			
			//set up the JSON array
			$graph_data[0] = array();
			$graph_data[0]['label'] = "Category Title"; //is this used for anything?
			$graph_data[0]['color'] = '#'. $color;
			$graph_data[0]['data'] = array();
			
			//now spit out the data into the JSON array
			$sum = 0;
			foreach($well_counts as $key=>$value)
			{
				$sum += $value;
				array_push($graph_data[0]['data'],array($key * 1000, $sum));
			}
        }//end default case
        
        
        
        
        
		////////////////////////////////////////////////////
		//Function status
		////////////////////////////////////////////////////
		//If we're looking at the functioning status		
		if($func_status_functioning_found)
		{
			$well_counts = array();
			//get the dates functioning was added
			$sql = 'SELECT UNIX_TIMESTAMP('.$select_date_text_alt.') AS time, COUNT(id) AS number FROM '.adminmap_helper::$table_prefix.'versioncategories as response ';
			$sql .= 'WHERE response.category_id = ' . self::$func_status_functioning . ' AND ';
			$sql .= 'response.type = 1 AND ';
			$sql .= 'response.incident_id in'.$non_special_approved_IDs_str.' GROUP BY '.$groupby_date_text_alt;
			$query = $db->query($sql);
			
			//now add in the data from when functioning was added as a category	
			foreach ( $query as $items )
			{
				$time = intval($items->time);
				if(!isset($well_counts[$time]))
				{
					$well_counts[$time] = 0;
				}
				$well_counts[$time] = $well_counts[$time] + $items->number;
			}
			
			//get the dates functioning was removed
			$sql = 'SELECT UNIX_TIMESTAMP('.$select_date_text_alt.') AS time, COUNT(id) AS number FROM '.adminmap_helper::$table_prefix.'versioncategories as response ';
			$sql .= 'WHERE response.category_id = ' . self::$func_status_functioning . ' AND ';
			$sql .= 'response.type = 0 AND ';
			$sql .= 'response.incident_id in'.$non_special_approved_IDs_str.' GROUP BY '.$groupby_date_text_alt;
			$query = $db->query($sql);
			
			//now add in the data from when functioning was removed as a category
			foreach ( $query as $items )
			{
				$time = intval($items->time);
				if(!isset($well_counts[$time]))
				{
					$well_counts[$time] = 0;
				}
				$well_counts[$time] = $well_counts[$time] - $items->number;
			}
			
	
			//sort the array so it stays in chronological order
			ksort($well_counts);
			
			//set up the JSON array
			$function_category = ORM::factory('category',self::$func_status_functioning);
			//setup the graph data
			$array_data = array();
			$array_data['label'] = "Category Title"; //is this used for anything?
			$array_data['color'] = '#'. $function_category->category_color;
			$array_data['data'] = array();
			
			//now spit out the data into the JSON array
			$sum = 0;
			foreach($well_counts as $key=>$value)
			{
				$sum += $value;
				array_push($array_data['data'],array($key * 1000, $sum));
			}
			$graph_data[] = $array_data;
        }//end the case when we're looking at functioning wells
        
        
        ////////////////////////////////////////////////////
		//Malfunction status
		////////////////////////////////////////////////////
		//If we're looking at the malfunctioning status		
		if($func_status_malfunctioning_found)
		{
			$well_counts = array();
			//get the dates functioning was added
			$sql = 'SELECT UNIX_TIMESTAMP('.$select_date_text_alt.') AS time, COUNT(id) AS number FROM '.adminmap_helper::$table_prefix.'versioncategories as response ';
			$sql .= 'WHERE response.category_id = ' . self::$func_status_malfunction . ' AND ';
			$sql .= 'response.type = 1 AND ';
			$sql .= 'response.incident_id in'.$non_special_approved_IDs_str.' GROUP BY '.$groupby_date_text_alt;
			$query = $db->query($sql);
			
			//now add in the data from the query of existing wells		
			foreach ( $query as $items )
			{
				$time = intval($items->time);
				if(!isset($well_counts[$time]))
				{
					$well_counts[$time] = 0;
				}
				$well_counts[$time] = $well_counts[$time] + $items->number;
			}
			
			//get the dates functioning was removed
			$sql = 'SELECT UNIX_TIMESTAMP('.$select_date_text_alt.') AS time, COUNT(id) AS number FROM '.adminmap_helper::$table_prefix.'versioncategories as response ';
			$sql .= 'WHERE response.category_id = ' . self::$func_status_malfunction . ' AND ';
			$sql .= 'response.type = 0 AND ';
			$sql .= 'response.incident_id in'.$non_special_approved_IDs_str.' GROUP BY '.$groupby_date_text_alt;
			$query = $db->query($sql);
			
			//now add in the data from the query of existing wells		
			foreach ( $query as $items )
			{
				$time = intval($items->time);
				if(!isset($well_counts[$time]))
				{
					$well_counts[$time] = 0;
				}
				$well_counts[$time] = $well_counts[$time] - $items->number;
			}
		
			//sort the array so it stays in chronological order
			ksort($well_counts);
			
			//set up the JSON array
			$category = ORM::factory('category',self::$func_status_malfunction);
			//setup the graph data
			$array_data = array();
			$array_data['label'] = "Category Title"; //is this used for anything?
			$array_data['color'] = '#'. $category->category_color;
			$array_data['data'] = array();
			
			//now spit out the data into the JSON array
			$sum = 0;
			foreach($well_counts as $key=>$value)
			{
				$sum += $value;
				array_push($array_data['data'],array($key * 1000, $sum));
			}
			$graph_data[] = $array_data;
        }//end the case when we're looking at malfunctioning wells
        
        
        
        ////////////////////////////////////////////////////
		//Restored status
		////////////////////////////////////////////////////
		//If we're looking at the restored status		
		if($func_status_restored_found)
		{
			$well_counts = array();
			//get the dates functioning was added
			$sql = 'SELECT UNIX_TIMESTAMP('.$select_date_text_alt.') AS time, COUNT(id) AS number FROM '.adminmap_helper::$table_prefix.'versioncategories as response ';
			$sql .= 'WHERE response.category_id = ' . self::$func_status_restored . ' AND ';
			$sql .= 'response.type = 1 AND ';
			$sql .= 'response.incident_id in'.$non_special_approved_IDs_str.' GROUP BY '.$groupby_date_text_alt;
			$query = $db->query($sql);
			
			//now add in the data from the query of existing wells		
			foreach ( $query as $items )
			{
				$time = intval($items->time);
				if(!isset($well_counts[$time]))
				{
					$well_counts[$time] = 0;
				}
				$well_counts[$time] = $well_counts[$time] + $items->number;
			}
			
			//get the dates functioning was removed
			$sql = 'SELECT UNIX_TIMESTAMP('.$select_date_text_alt.') AS time, COUNT(id) AS number FROM '.adminmap_helper::$table_prefix.'versioncategories as response ';
			$sql .= 'WHERE response.category_id = ' . self::$func_status_restored . ' AND ';
			$sql .= 'response.type = 0 AND ';
			$sql .= 'response.incident_id in'.$non_special_approved_IDs_str.' GROUP BY '.$groupby_date_text_alt;
			$query = $db->query($sql);
			
			//now add in the data from the query of existing wells		
			foreach ( $query as $items )
			{
				$time = intval($items->time);
				if(!isset($well_counts[$time]))
				{
					$well_counts[$time] = 0;
				}
				$well_counts[$time] = $well_counts[$time] - $items->number;
			}
		
			//sort the array so it stays in chronological order
			ksort($well_counts);
			
			//set up the JSON array
			$category = ORM::factory('category',self::$func_status_restored);
			//setup the graph data
			$array_data = array();
			$array_data['label'] = "Category Title"; //is this used for anything?
			$array_data['color'] = '#'. $category->category_color;
			$array_data['data'] = array();
			
			//now spit out the data into the JSON array
			$sum = 0;
			foreach($well_counts as $key=>$value)
			{
				$sum += $value;
				array_push($array_data['data'],array($key * 1000, $sum));
			}
			$graph_data[] = $array_data;
        }//end the case when we're looking at restored wells

		
		
		
		////////////////////////////////////////////////////
		//Water Tracker Trained
		////////////////////////////////////////////////////
		//If we're looking at the restored status		
		/*TURN THIS BACK ON ONCE WE HAVE WATER TRACKER TRAINED DATA
		if($watertracker_trained_found)
		{
			$well_counts = array();
			//get the dates functioning was added
			$sql = 'SELECT UNIX_TIMESTAMP('.$select_date_text_alt.') AS time, COUNT(id) AS number FROM '.adminmap_helper::$table_prefix.'versioncategories as response ';
			$sql .= 'WHERE response.category_id = ' . self::$watertracker_trained . ' AND ';
			$sql .= 'response.type = 1 AND ';
			$sql .= 'response.incident_id in'.$non_special_approved_IDs_str.' GROUP BY '.$groupby_date_text_alt;
			$query = $db->query($sql);
			
			//now add in the data from the query of existing wells		
			foreach ( $query as $items )
			{
				$time = intval($items->time);
				if(!isset($well_counts[$time]))
				{
					$well_counts[$time] = 0;
				}
				$well_counts[$time] = $well_counts[$time] + $items->number;
			}
			
			//get the dates functioning was removed
			$sql = 'SELECT UNIX_TIMESTAMP('.$select_date_text_alt.') AS time, COUNT(id) AS number FROM '.adminmap_helper::$table_prefix.'versioncategories as response ';
			$sql .= 'WHERE response.category_id = ' . self::$watertracker_trained . ' AND ';
			$sql .= 'response.type = 0 AND ';
			$sql .= 'response.incident_id in'.$non_special_approved_IDs_str.' GROUP BY '.$groupby_date_text_alt;
			$query = $db->query($sql);
			
			//now add in the data from the query of existing wells		
			foreach ( $query as $items )
			{
				$time = intval($items->time);
				if(!isset($well_counts[$time]))
				{
					$well_counts[$time] = 0;
				}
				$well_counts[$time] = $well_counts[$time] - $items->number;
			}
		
			//sort the array so it stays in chronological order
			ksort($well_counts);
			
			//set up the JSON array
			$category = ORM::factory('category',self::$watertracker_trained);
			//setup the graph data
			$array_data = array();
			$array_data['label'] = "Category Title"; //is this used for anything?
			$array_data['color'] = '#'. $category->category_color;
			$array_data['data'] = array();
			
			//now spit out the data into the JSON array
			$sum = 0;
			foreach($well_counts as $key=>$value)
			{
				$sum += $value;
				array_push($array_data['data'],array($key * 1000, $sum));
			}
			$graph_data[] = $array_data;
        }//end the case when we're looking at water tracker trained wells

		
		
		
		////////////////////////////////////////////////////
		//Water Tracker NOT Trained
		////////////////////////////////////////////////////
		//If we're looking at the restored status		
		if($watertracker_not_trained_found)
		{
			$well_counts = array();
			//get the dates functioning was added
			$sql = 'SELECT UNIX_TIMESTAMP('.$select_date_text_alt.') AS time, COUNT(id) AS number FROM '.adminmap_helper::$table_prefix.'versioncategories as response ';
			$sql .= 'WHERE response.category_id = ' . self::$watertracker_not_trained . ' AND ';
			$sql .= 'response.type = 1 AND ';
			$sql .= 'response.incident_id in'.$non_special_approved_IDs_str.' GROUP BY '.$groupby_date_text_alt;
			$query = $db->query($sql);
			
			//now add in the data from the query of existing wells		
			foreach ( $query as $items )
			{
				$time = intval($items->time);
				if(!isset($well_counts[$time]))
				{
					$well_counts[$time] = 0;
				}
				$well_counts[$time] = $well_counts[$time] + $items->number;
			}
			
			//get the dates functioning was removed
			$sql = 'SELECT UNIX_TIMESTAMP('.$select_date_text_alt.') AS time, COUNT(id) AS number FROM '.adminmap_helper::$table_prefix.'versioncategories as response ';
			$sql .= 'WHERE response.category_id = ' . self::$watertracker_not_trained . ' AND ';
			$sql .= 'response.type = 0 AND ';
			$sql .= 'response.incident_id in'.$non_special_approved_IDs_str.' GROUP BY '.$groupby_date_text_alt;
			$query = $db->query($sql);
			
			//now add in the data from the query of existing wells		
			foreach ( $query as $items )
			{
				$time = intval($items->time);
				if(!isset($well_counts[$time]))
				{
					$well_counts[$time] = 0;
				}
				$well_counts[$time] = $well_counts[$time] - $items->number;
			}
		
			//sort the array so it stays in chronological order
			ksort($well_counts);
			
			//set up the JSON array
			$category = ORM::factory('category',self::$watertracker_not_trained);
			//setup the graph data
			$array_data = array();
			$array_data['label'] = "Category Title"; //is this used for anything?
			$array_data['color'] = '#'. $category->category_color;
			$array_data['data'] = array();
			
			//now spit out the data into the JSON array
			$sum = 0;
			foreach($well_counts as $key=>$value)
			{
				$sum += $value;
				array_push($array_data['data'],array($key * 1000, $sum));
			}
			$graph_data[] = $array_data;
        }//end the case when we're looking at water tracker NOT trained wells
	*/

	
        echo json_encode($graph_data);
    }
    
    /**
     * Special thanks to http://www.planet-source-code.com/vb/scripts/ShowCode.asp?txtCodeId=46&lngWId=8 for this code
     * @param unknown_type $lat1
     * @param unknown_type $lng1
     * @param unknown_type $lat2
     * @param unknown_type $lng2
     * @param unknown_type $miles
     */
    public static function lat_lon_distance($lat1, $lng1, $lat2, $lng2, $miles = true)
    {
    	$pi = 3.1415926;
    	$rad = doubleval($pi/180.0);
    	$lon1 = doubleval($lng1)*$rad; 
    	$lat1 = doubleval($lat1)*$rad;
    	
    	$lon2 = doubleval($lng2)*$rad; 
    	$lat2 = doubleval($lat2)*$rad;
    	
    	$theta = $lon2 - $lon1;
    	$dist = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($theta));
    	if ($dist < 0) {
    		$dist += $pi;
    	}
    	$dist = $dist * 6371.2;
    	return $dist;
    }
    
    /**
     * Special thanks to http://stackoverflow.com/questions/11111077/find-latitude-longitude-point-in-php-given-initial-lat-lng-distance-and-bearin
     * for this code. God bless stack over flow
     * @param foat $lat
     * @param float $lng
     * @param flat $brng
     * @param float $dist in KM
     */
    public static function destinationPoint($lat, $lng, $brng, $dist) {
    	//$meters = $dist/3.2808399; // dist in meters
    	//$dist =  $meters/1000; // dist in km
    	$rad = 6371.2; // earths mean radius
    	$dist = $dist/$rad;  // convert dist to angular distance in radians
    	$brng = deg2rad($brng);  // conver to radians
    	$lat1 = deg2rad($lat);
    	$lon1 = deg2rad($lng);
    
    	$lat2 = asin(sin($lat1)*cos($dist) + cos($lat1)*sin($dist)*cos($brng) );
    	$lon2 = $lon1 + atan2(sin($brng)*sin($dist)*cos($lat1),cos($dist)-sin($lat1)*sin($lat2));
    	$lon2 = fmod($lon2 + 3*M_PI, 2*M_PI) - M_PI;
    	$lat2 = rad2deg($lat2);
    	$lon2 = rad2deg($lon2);
    
    	return array("lat"=>$lat2, "lon"=>$lon2);
    	
    }




}//end class adminmap_core


	adminmap_helper_Core::init();



