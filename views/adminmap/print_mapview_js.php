<?php
/**
 * Main cluster js file.
 * 
 * Server Side Map Clustering
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author     Ushahidi Team <team@ushahidi.com> 
 * @package    Ushahidi - http://source.ushahididev.com
 * @module     API Controller
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */
?>

            $(function() {
                $('span[title]').hovertip();
            });
            
/**
*
* From http://osgeo-org.1803224.n2.nabble.com/How-to-print-map-area-with-openlayers-td4901023.html
*
*/
var print_wait_win = null;
 function stitchImage() {

			     //-- post a wait message
			    print_wait_win = window.open("<?php url::site();?>stitch/wait", "print_wait_win", "scrollbars=no, status=0, height=300, width=500, resizable=1");
			    
			    
					
 
				var print_url =  '<?php url::site();?>stitch/';
                var size = this.map.getSize(); 
                var tiles = []; 
                
                var nb_layers = this.map.layers.length; 
                var layeri = 0; 
                var nb_vector_layers = 0; 
                var features = []; 
                for (layeri = 0; layeri < nb_layers; layeri++) { 
                        // if the layer isn't visible at this range, or is turned off, skip it 
                        var layer = this.map.layers[layeri]; 
                        if (!layer.getVisibility()) continue; 
                        if (!layer.calculateInRange()) continue; 
                        if (layer.grid) { 
                                // iterate through their grid's tiles, collecting each tile's extent and pixel location at this moment 
                                var grid_length = layer.grid.length; 
                                var row_length = 0; 
                                var tilerow = 0; 
                                var tilei = 0; 
                                for (tilerow = 0; tilerow < grid_length; tilerow++) { 
                                        row_length = layer.grid[tilerow].length; 
                                        tilei = 0; 
                                        for (tilei = 0; tilei < row_length; tilei++) { 
                                                var tile     = layer.grid[tilerow][tilei]; 
                                                var url      = layer.getURL(tile.bounds); 
                                                var position = tile.position; 
                                                var opacity  = layer.opacity ? parseInt(100*layer.opacity) : 100; 
                                                var bounds = tile.bounds; 
                                                tiles[tiles.length] = {url:url, x:position.x, y:position.y, opacity:opacity, bounds:{left:bounds.left, right: bounds.right, top: bounds.top, bottom: bounds.bottom}}; 
                                        } 
                                } 
                        } else { 
                                // get the features of the layer 
                                var olFeatures = layer.features; 
                                var features_temp = []; 
                                var styles = {}; 
                                var nb_features = layer.features.length; 
                                var featuresi = 0; 
                                var nextId = 1; 
                                for (var i = 0; i < nb_features; i++) { 
                                        var feature = olFeatures[i]; 
                                        var style = feature.style || layer.style || layer.styleMap.createSymbolizer(feature, feature.renderIntent); 
                                        if(feature.geometry) { 
                                                if (feature.geometry.CLASS_NAME.search(/point$/i) >= 0) { 
                                                        var fpos = this.map.getLayerPxFromLonLat(new OpenLayers.LonLat(feature.geometry.x, feature.geometry.y)); 
                                                        if(fpos != null) { 
                                                                features_temp[featuresi] = {type: 'point', x: fpos.x, y:fpos.y, style: style}; 
                                                                featuresi++; 
                                                        } 
                                                }
                                                //checks for polygons
                                                if (feature.geometry.CLASS_NAME.search(/polygon$/i) >= 0) { 
                                                		var polyComponentArray = [];
                                                		var polyItemCount = 0;
                                                		//////////////////////////////////////////////////////////////
                                                		//loops over components of a polygon, most likely linear rings
                                                		//////////////////////////////////////////////////////////////
                                                        for(var polyComponentName in feature.geometry.components)
                                                        {
                                                        	var polygonComponent = feature.geometry.components[polyComponentName];
                                                        	//checks if it's a linear ring
                                                        	if(polygonComponent.CLASS_NAME.search(/LinearRing$/) >=0)
                                                        	{
                                                        		var linearRingComponentArray = [];
                                                        		var linRingItemCount = 0;
                                                        		
                                                        		//////////////////////////////////////////
                                                        		//loops over the points in a linear ring
                                                        		/////////////////////////////////////////
                                                        		for(var linearRingComponentName in polygonComponent.components)
                                                        		{
                                                        			var linearRingComponent = polygonComponent.components[linearRingComponentName];
                                                        			if (linearRingComponent.CLASS_NAME.search(/point$/i) >= 0) 
                                                        			{ 
	                                                        			var lrpos = this.map.getLayerPxFromLonLat(new OpenLayers.LonLat(linearRingComponent.x, linearRingComponent.y)); 
	                                                        			if(lrpos != null) 
	                                                        			{ 
	                                                                		linearRingComponentArray[linRingItemCount] = {type: 'point', x: lrpos.x, y:lrpos.y}; 
	                                                                		linRingItemCount++; 
	                                                        			}
	                                                        		}
                                                        		}
                                                        		
                                                        		polyComponentArray[polyItemCount] = {type: 'linearRing', components: linearRingComponentArray};
                                                        		polyItemCount++;
                                                        	}
                                                        }
                                                        
                                                        features_temp[featuresi] = {type: 'polygon', components: polyComponentArray, style: style}; 
                                                        featuresi++;
                                                }  
                                        } 
                                } 
                                features[nb_vector_layers] = features_temp; 
                                nb_vector_layers++; 
                        } 
                                        
                } 

                // hand off the list to our server-side script, which will do the heavy lifting 
                var tiles_json = JSON.stringify(tiles); 
                var features_json = JSON.stringify(features); 
                var viewport_left = parseInt(this.map.layerContainerDiv.style.left); 
                var viewport_top = parseInt(this.map.layerContainerDiv.style.top); 
                var viewport = {top: viewport_top, left: viewport_left}; 
                var viewport_json = JSON.stringify(viewport); 
                var scale = Math.round(this.map.getScale()); 
                OpenLayers.Request.POST( 
                  { url:print_url, 
                        data:OpenLayers.Util.getParameterString({width:size.w,height:size.h,scale:scale,viewport: viewport_json,tiles:tiles_json,features:features_json}), 
                        headers:{'Content-Type':'application/x-www-form-urlencoded'}, 
                        callback: function(request) {
         							print_wait_win.close();
           							window.open(request.responseText);
        							} 
                  } 
                ); 
        }            
            


            
            
            
            



/**
* Creates a URL for the way the map currently looks
*/
function setURL()
{

	$.address.parameter("catId", currentCat);
	$.address.parameter("startDate", $("#startDate").val());
	$.address.parameter("endDate", $("#endDate").val());
	$.address.parameter("z", gMap.getZoom());
	
	var center = gMap.getCenter();
	if(center != null)
	{
		$.address.parameter("lat", center.lat);
		$.address.parameter("lon", center.lon);
	}
	
	if($("#key").is(":hidden"))
	{
		$.address.parameter("hk","1");
	}
	else
	{
		$.address.parameter("hk","");
	}
	
	if($("#key").hasClass("left"))
	{
		$.address.parameter("left","1");
	}
	else
	{
			$.address.parameter("left","");
	}
	
	if($("#key").hasClass("right"))
	{
		$.address.parameter("right","1");
	}
	else
	{
		$.address.parameter("right","");
	}
	
	if($("#key").hasClass("top"))
	{
		$.address.parameter("top","1");
	}
	else
	{
		$.address.parameter("top","");
	}
	
	if($("#key").hasClass("bottom"))
	{
		$.address.parameter("bottom","1");
	}
	else
	{
			$.address.parameter("bottom","");
	}
	
	if($("#printpage").hasClass("landscape"))
	{
		$.address.parameter("orientation", "landscape");
	}
	else
	{
		$.address.parameter("orientation", "portrait");
	}
			
	$.address.parameter("currStatus", currentStatus);
	$.address.parameter("currColorStatus", colorCurrentStatus);
	$.address.parameter("logic", currentLogicalOperator);
	
	//setting the KML/KMZ layers, just search for all the "layer_" ids that have class active.
	var layerStr = "";
	var i = 0;
	$("a[id^='layer_']").each(function(index){
		if($(this).hasClass("active"))
		{
			i++;
			if(i > 1)
			{
				layerStr += ",";
			}
			//get the id number
			var id = $(this).attr("id").substring(6);
			layerStr += id;
		}
	});
	if(layerStr != "")
	{
		$.address.parameter("layer", layerStr);
	}
	else
	{
		$.address.parameter("layer", "");
	}
	
	
	//get the URL and put it in our text box, plus a real URL param
	$("#urlText").val($.address.baseURL() + "?pdf=print#/?" + $.address.queryString());
	var theFullUrl = $.address.baseURL() + "?pdf=print#/?" + $.address.queryString();
	
	$("#mapUrlText").val($.address.baseURL() + "#/?" + $.address.queryString());

	
	var embedUrl = "<?php echo url::site();?>";
	
	$("#embedMapUrlText").val('<iframe src="' + embedUrl + "iframemap#/?" + $.address.queryString() + '" width="515px" height="430px"></iframe>');
	
	
	
	

	
	
	
	
}
        


/**
* Pass in a string of either "landscape" or something else, and this will
* change the orientation of the map to eithe landscape or portrait
*
*/
function changeOrientation(orientation)
{
	
	var width;
	var height;
	if(orientation == "landscape")
	{
		$("#printpage").removeClass("portrait");
		$("#printpage").addClass("landscape");
	}
	else
	{	
		$("#printpage").removeClass("landscape");	
		$("#printpage").addClass("portrait");
	}
	

	map.updateSize();
	map.pan(1,0);
}

/**
* Shows or hides the map key according to the check state of the #showKeyCheckBox
*
*/
function showHideKey()
{
	if($("#showKeyCheckbox:checked").val())
	{
		$("#key").show();
		$("#keyPlacement").show();
	}
	else
	{
		$("#key").hide();
		$("#keyPlacement").hide();
	}
	
}


/**
* Takes a string as input and from that determines what class to assign to the map key
* and thus what positioning the key will have over the map.
*/
function changeLeftRight(direction)
{
	if(direction == "left")
	{
		$("#key").removeClass("right");
		$("#key").addClass("left");
	}
	else
	{
		$("#key").removeClass("left");
		$("#key").addClass("right");
	}
}


/**
* Takes a string as input and from that determines what class to assign to the map key
* and thus what positioning the key will have over the map.
*/
function changeLeftRight(direction)
{
	if(direction == "left")
	{
		$("#key").removeClass("right");
		$("#key").addClass("left");
	}
	else
	{
		$("#key").removeClass("left");
		$("#key").addClass("right");
	}
}

/**
* Takes a string as input, and from that determines what class to assign to the map key
* and thus what positioning the key will have over the map.
*/
function changeTopBottom(direction)
{
	if(direction == "top")
	{
		$("#key").removeClass("bottom");
		$("#key").addClass("top");
	}
	else
	{
		$("#key").removeClass("top");
		$("#key").addClass("bottom");
	}
}

		
		
		// Map JS
		var canRedrawMapKey = false;
		//number of categories selcted
		var numOfCategoriesSelected = 0;
		//Max number of categories to show at once, if you have more than 1000 reports with lots of categories you might want to turn this down
		var maxCategories = 14;
		// Map Object
		var map;
		// Selected Category
		var currentCat;
		// Selected Status
		var currentStatus;
		// color the reports who's status is unapproved black?
		var colorCurrentStatus;
		//logical operator to use
		var currentLogicalOperator;
		// Selected Layer
		var thisLayer;
		// WGS84 Datum
		var proj_4326 = new OpenLayers.Projection('EPSG:4326');
		// Spherical Mercator
		var proj_900913 = new OpenLayers.Projection('EPSG:900913');
		// Change to 1 after map loads
		var mapLoad = 0;
		// /json or /json/cluster depending on if clustering is on
		var default_json_url = "<?php echo $json_url ?>";
		

		// Current json_url, if map is switched dynamically between json and json_cluster
		var json_url = default_json_url;
		
		
		/* 
		 - Part of #2168 fix
		 - Added by E.Kala <emmanuel(at)ushahidi.com>
		*/
		// Global list for current KML overlays in display
		var kmlOverlays = [];
		
		
		var baseUrl = "<?php echo url::base(); ?>";
		var longitude = <?php echo $longitude; ?>;
		var latitude = <?php echo $latitude; ?>;
		var defaultZoom = <?php echo $default_zoom; ?>;
		var markerRadius = <?php echo $marker_radius; ?>;
		var markerOpacity = "<?php echo $marker_opacity; ?>";
		var selectedFeature;

		var gMarkerOptions = {baseUrl: baseUrl, longitude: longitude,
		                     latitude: latitude, defaultZoom: defaultZoom,
							 markerRadius: markerRadius,
							 markerOpacity: markerOpacity,
							 protocolFormat: OpenLayers.Format.GeoJSON};
							
		/*
		Create the Markers Layer
		*/
		function addMarkers(catID,startDate,endDate, currZoom, currCenter,
			mediaType, thisLayerID, thisLayerType, thisLayerUrl, thisLayerColor)
		{
		
			// Get Current Status, and if we should color these reports black
			currStatus = $("#currentStatus").val();
			currColorStatus = $("#colorCurrentStatus").val();
			currLogicalOperator=$("#currentLogicalOperator").val();
		
			if(catID == "")
			{catID = "0";}
			if(startDate == "")
			{startDate = "1";}
			if(endDate == "")
			{endDate = "1";}
			//a poor man's attempt at thread safety
			if(canRedrawMapKey)
			{
				$.get("<?php echo url::site(); ?>printmapkey/getKey/" + catID + "/" + currLogicalOperator + "/" + startDate + "/" + endDate,
					function(data){
						$("#key").html(data);					
											
				});
			}
			else
			{
				return;
			}
			
			
			var retval = $.timeline({categoryId: catID,
			                   startTime: new Date(startDate * 1000),
			                   endTime: new Date(endDate * 1000),
							   mediaType: mediaType
							  }).addMarkers(
								startDate, endDate, gMap.getZoom(),
								gMap.getCenter(), thisLayerID, thisLayerType, 
								thisLayerUrl, thisLayerColor, json_url, currStatus, currColorStatus,
								currLogicalOperator);
			
			map.updateSize();
			return retval;															
			
		}



		/******
		* Removes the category ID from the string currentCat
		*******/
		function removeCategoryFilter(idToRemove, currentCat)
		{
			
			var cat_ids = currentCat.split(",");
			var newCurrentCat = "";
			//loop through the IDs
			for( tempId in cat_ids)
			{
				//take out blanks and the ID we're trying to remove
				if(cat_ids[tempId] != idToRemove && cat_ids[tempId] != "")
				{
					newCurrentCat += cat_ids[tempId]+",";
				}
			}
			//deactivate
			$("#cat_"+idToRemove).removeClass("active");
			
			return newCurrentCat;
		}//end removeCategoryFilter()


		/*
		Display loader as Map Loads
		*/
		function onMapStartLoad(event)
		{
			if ($("#loader"))
			{
				$("#loader").show();
			}

			if ($("#OpenLayers\\.Control\\.LoadingPanel_4"))
			{
				$("#OpenLayers\\.Control\\.LoadingPanel_4").show();
			}
		}

		/*
		Hide Loader
		*/
		function onMapEndLoad(event)
		{
			if ($("#loader"))
			{
				$("#loader").hide();
			}

			if ($("#OpenLayers\\.Control\\.LoadingPanel_4"))
			{
				$("#OpenLayers\\.Control\\.LoadingPanel_4").hide();
			}
		}

		/*
		Close Popup
		*/
		function onPopupClose(evt)
		{
			if(selectedFeature != null)
			{
				selectControl.unselect(selectedFeature); //this seemed to change things.
				selectedFeature = null;
			}
		}

		/*
		Display popup when feature selected
		*/
		function onFeatureSelect(event)
		{
			selectedFeature = event.feature;
			// Since KML is user-generated, do naive protection against
			// Javascript.

			zoom_point = event.feature.geometry.getBounds().getCenterLonLat();
			lon = zoom_point.lon;
			lat = zoom_point.lat;

			var content = "<div class=\"infowindow\"><div class=\"infowindow_list\">"+event.feature.attributes.name + "<div style=\"clear:both;\"></div></div>";
			content = content + "\n<div class=\"infowindow_meta\"><a href='javascript:zoomToSelectedFeature("+ lon + ","+ lat +", 1)'>Zoom&nbsp;In</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href='javascript:zoomToSelectedFeature("+ lon + ","+ lat +", -1)'>Zoom&nbsp;Out</a></div>";
			content = content + "</div>";			

			if (content.search("<script") != -1)
			{
				content = "Content contained Javascript! Escaped content below.<br />" + content.replace(/</g, "&lt;");
			}
			popup = new OpenLayers.Popup.FramedCloud("chicken", 
					event.feature.geometry.getBounds().getCenterLonLat(),
					new OpenLayers.Size(100,100),
					content,
					null, true, onPopupClose);
			event.feature.popup = popup;
			map.addPopup(popup);
		}

		/*
		Destroy Popup Layer
		*/
        function onFeatureUnselect(event)
		{
            map.removePopup(event.feature.popup);
            event.feature.popup.destroy();
            event.feature.popup = null;
        }

		// Refactor Clusters On Zoom
		// *** Causes the map to load json twice on the first go
		// *** Need to fix this!
		function mapZoom(event)
		{
			// Prevent this event from running on the first load
			if (mapLoad > 0)
			{
				// Get Current Category
				currCat = $("#currentCat").val();
				
				// Get Current Status
				currStatus = $("#currentStatus").val();

				// Get Current Start Date
				currStartDate = $("#startDate").val();

				// Get Current End Date
				currEndDate = $("#endDate").val();

				// Get Current Zoom
				currZoom = map.getZoom();

				// Get Current Center
				currCenter = map.getCenter();

				// Refresh Map
				addMarkers(currCat, currStartDate, currEndDate, currZoom, currCenter);
			}
		}

		function mapMove(event)
		{
			// Prevent this event from running on the first load
			if (mapLoad > 0)
			{
				// Get Current Category
				currCat = $("#currentCat").val();

				// Get Current Start Date
				currStartDate = $("#startDate").val();

				// Get Current End Date
				currEndDate = $("#endDate").val();

				// Get Current Zoom
				currZoom = map.getZoom();

				// Get Current Center
				currCenter = map.getCenter();

				// Refresh Map
				addMarkers(currCat, currStartDate, currEndDate, currZoom, currCenter);
			}
		}


		/*
		Refresh Graph on Slider Change
		*/
		function refreshGraph(startDate, endDate)
		{
			var currentCat = gCategoryId;
			// Get Current Status
			var currStatus = $("#currentStatus").val();
			
			//Get currentl logical operator
			var currLogicalOperator = $("#currentLogicalOperator").val();

			// refresh graph
			if (!currentCat || currentCat == '0')
			{
				currentCat = '0';
			}

			var startTime = new Date(startDate * 1000);
			var endTime = new Date(endDate * 1000);

			// daily
			var graphData = "";

			// plot hourly incidents when period is within 2 days
			if ((endTime - startTime) / (1000 * 60 * 60 * 24) <= 3)
			{
				$.getJSON("<?php echo url::site().$json_timeline_url; ?>"+currentCat+"?i=hour&u="+currStatus + 
				"&lo="+ currLogicalOperator, function(data) {
					graphData = data[0];

					gTimeline = $.timeline({categoryId: currentCat,
						startTime: new Date(startDate * 1000),
					    endTime: new Date(endDate * 1000), mediaType: gMediaType,
						markerOptions: gMarkerOptions,
						graphData: graphData
					});
					gTimeline.plot();
				});
			} 
			else if ((endTime - startTime) / (1000 * 60 * 60 * 24) <= 124)
			{
			    // weekly if period > 2 months
				$.getJSON("<?php echo url::site().$json_timeline_url; ?>"+currentCat+"?i=day&u="+currStatus+ 
				"&lo="+ currLogicalOperator, function(data) {
					graphData = data[0];

					gTimeline = $.timeline({categoryId: currentCat,
						startTime: new Date(startDate * 1000),
					    endTime: new Date(endDate * 1000), mediaType: gMediaType,
						markerOptions: gMarkerOptions,
						graphData: graphData
					});
					gTimeline.plot();
				});
			} 
			else if ((endTime - startTime) / (1000 * 60 * 60 * 24) > 124)
			{
				// monthly if period > 4 months
				$.getJSON("<?php echo url::site().$json_timeline_url; ?>"+currentCat+"?u="+currStatus+ 
				"&lo="+ currLogicalOperator, function(data) {
					graphData = data[0];

					gTimeline = $.timeline({categoryId: currentCat,
						startTime: new Date(startDate * 1000),
					    endTime: new Date(endDate * 1000), mediaType: gMediaType,
						markerOptions: gMarkerOptions,
						graphData: graphData
					});
					gTimeline.plot();
				});
			}

			// Get dailyGraphData for All Categories
			$.getJSON("<?php echo url::site().$json_timeline_url; ?>"+currentCat+"?i=day&u="+currStatus+ 
				"&lo="+ currLogicalOperator, function(data) {
				dailyGraphData = data[0];
			});

			// Get allGraphData for All Categories
			$.getJSON("<?php echo url::site().$json_timeline_url; ?>"+currentCat + "?u="+currStatus+ 
				"&lo="+ currLogicalOperator, function(data) {
				allGraphData = data[0];
			});

		}

		/*
		Zoom to Selected Feature from within Popup
		*/
		function zoomToSelectedFeature(lon, lat, zoomfactor)
		{
			var lonlat = new OpenLayers.LonLat(lon,lat);

			// Get Current Zoom
			currZoom = map.getZoom();
			// New Zoom
			newZoom = currZoom + zoomfactor;
			// Center and Zoom
			map.setCenter(lonlat, newZoom);
			// Remove Popups
			for (var i=0; i<map.popups.length; ++i)
			{
				map.removePopup(map.popups[i]);
			}
		}

		/*
		Add KML/KMZ Layers
		*/
		function switchLayer(layerID, layerURL, layerColor)
		{
			if ( $("#layer_" + layerID).hasClass("active") )
			{
				new_layer = map.getLayersByName("Layer_"+layerID);
				if (new_layer)
				{
					for (var i = 0; i < new_layer.length; i++)
					{
						map.removeLayer(new_layer[i]);
					}
				}
				$("#layer_" + layerID).removeClass("active");

			}
			else
			{
				$("#layer_" + layerID).addClass("active");

				// Get Current Zoom
				currZoom = map.getZoom();

				// Get Current Center
				currCenter = map.getCenter();

				// Add New Layer
				addMarkers('', '', '', currZoom, currCenter, '', layerID, 'layers', layerURL, layerColor);
				mapMove(null);
			}
		}

		/*
		Toggle Layer Switchers
		*/
		function toggleLayer(link, layer){
			if ($("#"+link).text() == "<?php echo Kohana::lang('ui_main.show'); ?>")
			{
				$("#"+link).text("<?php echo Kohana::lang('ui_main.hide'); ?>");
			}
			else
			{
				$("#"+link).text("<?php echo Kohana::lang('ui_main.show'); ?>");
			}
			$('#'+layer).toggle(500);
		}							

		jQuery(function() {
			var map_layer;
			markers = null;
			var catID = '';
			OpenLayers.Strategy.Fixed.prototype.preload=true;
			
			/*
			- Initialize Map
			- Uses Spherical Mercator Projection
			- Units in Metres instead of Degrees					
			*/
			var options = {
				units: "mi",
				numZoomLevels: 21,
				controls:[],
				projection: proj_900913,
				'displayProjection': proj_4326,
				eventListeners: {
						"zoomend": mapMove
				    },
				'theme': null
				};
			map = new OpenLayers.Map('map', options);
			map.addControl( new OpenLayers.Control.LoadingPanel({minSize: new OpenLayers.Size(573, 366)}) );
			
			<?php echo map::layers_js(FALSE); ?>
			map.addLayers(<?php echo map::layers_array(FALSE); ?>);
			
			
			// Add Controls
			map.addControl(new OpenLayers.Control.Navigation());
			map.addControl(new OpenLayers.Control.Attribution());
			map.addControl(new OpenLayers.Control.PanZoomBar());
			map.addControl(new OpenLayers.Control.MousePosition(
				{
					div: document.getElementById('mapMousePosition'),
					numdigits: 5
				}));    
			map.addControl(new OpenLayers.Control.Scale('mapScale'));
            map.addControl(new OpenLayers.Control.ScaleLine());
			map.addControl(new OpenLayers.Control.LayerSwitcher());
			
			// display the map projection
			document.getElementById('mapProjection').innerHTML = map.projection;
				
			gMap = map;



		
			//////////////////////////////////////////////////////////////////////////////////////
			// Parent Category opener
			$("a[id^='drop_cat_']").click(function()
			{
				//get the ID of the category we're dealing with
				var catID = this.id.substring(9);

				//if the kids aren't currenlty shown, show them
				if( !$("#child_"+catID).is(":visible"))
				{
					$("#child_"+catID).show();
					$(this).html("-");
					//since all we're doing is showing things we don't need to update the map
					// so just bounce
					
					$("a[id^='cat_']").addClass("forceRefresh"); //have to do this because IE sucks
					$("a[id^='cat_']").removeClass("forceRefresh"); //have to do this because IE sucks
					
					return false;
				}
				else //kids are shown, deactivate them.
				{
					var kids = $("#child_"+catID).find('a');
					kids.each(function(){
						if($(this).hasClass("active"))
						{
							//remove this category ID from the list of IDs to show
							var idNum = $(this).attr("id").substring(4);
							currentCat = removeCategoryFilter(idNum, currentCat);
						}
					});
					$("#child_"+catID).hide();
					$(this).html("+");
					return false;
				}
			});

			
			//////////////////////////////////////////////////////////////////////////////////////
			// Category Switch Action
			$("a[id^='cat_']").click(function()
			{
				//the id of the category that just changed
				var catID = this.id.substring(4);
				
				//the list of categories we're currently showing
				currentCat = $("#currentCat").val();
				numOfCategoriesSelected = currentCat.split(",").length;
				 
				 
				//First we check if the "All Categories" button was pressed. If so unselect everything else
				if( catID == 0)
				{
					if( !$("#cat_0").hasClass("active")) //it's being activated so unselect everything else
					{
						//unselect all other selected categories
						var activeIDs = currentCat.split(",");
						for (var i=0; i < activeIDs.length; i++)
						{
							currentCat = removeCategoryFilter(activeIDs[i], currentCat);
						}
					}
				}
				else
				{ //we're dealing wtih single categories or parents
				
				
					//first check and see if we're dealing with a parent category
					if( $("#child_"+catID).find('a').length > 0)
					{
				
						//we want to deactivate any kid categories.
						var kids = $("#child_"+catID).find('a');
						kids.each(function(){
							if($(this).hasClass("active"))
							{
								//remove this category ID from the list of IDs to show
								var idNum = $(this).attr("id").substring(4);
								currentCat = removeCategoryFilter(idNum, currentCat);
							}
						});
					
					}//end of if for dealing with parents
					
					//check if we're dealing with a child
					if($(this).attr("cat_parent"))
					{
						//get the parent ID
						parentID = $(this).attr("cat_parent");
						//if it's active deactivate it
						//first check and see if we're adding or removing this category
						if($("#cat_"+parentID).hasClass("active")) //it is active so make it unactive and remove this category from the list of categories we're looking at.
						{ 
							currentCat = removeCategoryFilter(parentID, currentCat);
						}
						
					}//end of dealing with kids
					
					//first check and see if we're adding or removing this category
					if($("#cat_"+catID).hasClass("active")) //it is active so make it unactive and remove this category from the list of categories we're looking at.
					{ 
						currentCat = removeCategoryFilter(catID, currentCat);
					}
					else //it isn't active so make it active
					{ 
						
						$("#cat_"+catID).addClass("active");
						
						//make sure the "all categories" button isn't active
						currentCat = removeCategoryFilter("0", currentCat);
						
						//add this category ID from the list of IDs to show
						var toAdd = catID+","; //we use , as the delimiter bewteen categories

						currentCat = currentCat + toAdd;
						
					}
				}
				
				
				//check to make sure something is selected. If nothing is selected then select "all gategories"
				if( currentCat.length == 0)
				{
					$("#cat_0").addClass("active");
					currentCat = currentCat + "0,";

				}
				$("#currentCat").val(currentCat);
				
				
				// Destroy any open popups
				onPopupClose();
				
				// Get Current Zoom
				currZoom = map.getZoom();
				
				// Get Current Center
				currCenter = map.getCenter();
				
				// Get Current Status
				currStatus = $("#currentStatus").val();
				
				//Get Current Logical Operator
				currLogicalOperator = $("#currentLogicalOperator").val();
				
				gCategoryId = currentCat;
				var startTime = new Date($("#startDate").val() * 1000);
				var endTime = new Date($("#endDate").val() * 1000);
				addMarkers(currentCat, $("#startDate").val(), $("#endDate").val(), currZoom, currCenter, gMediaType);
				
				var startDate = $("#startDate").val();
				var endDate = $("#endDate").val();
				refreshGraph(startDate, endDate);	
				
				return false;
			});
			
			
			
			//////////////////////////////////////////////////////
			//status switcher
			//////////////////////////////////////////////////////
			$("a[id^='status_']").click(function()
			{
				
				var statID = this.id.substring(7);
				//check and see if the just clicked element should have "active" class or not
				if( $("#status_" + statID).hasClass("active"))
				{
					//we have it so remove it
					$("#status_" + statID).removeClass("active"); // Remove All active
				}
				else
				{
					//we don't have it so add it
					$("#status_" + statID).addClass("active"); // Add Highlight
				}
			

				//both are active
				if($("#status_1").hasClass("active") && $("#status_2").hasClass("active"))
				{
					currentStatus = 3;
				}
				else if($("#status_1").hasClass("active") && !($("#status_2").hasClass("active")))
				{
					currentStatus = 2;
				}
				else if(!($("#status_1").hasClass("active")) && $("#status_2").hasClass("active"))
				{
					currentStatus = 1;
				}
				else //this shouldn't happen, so undo what was done above, can't have no reports showing. That's just silly
				{
					if( $("#status_" + statID).hasClass("active"))
					{
						//we have it so remove it
						$("#status_" + statID).removeClass("active"); // Remove All active
					}
					else
					{
						//we don't have it so add it
						$("#status_" + statID).addClass("active"); // Add Highlight
					}
					return false;
				}
				
				$("#currentStatus").val(currentStatus);
				
				//Get Logical Operator
				currLogicalOperator = $("#currentLogicalOperator").val();

	
				
				// Destroy any open popups
				onPopupClose();
				
				// Get Current Zoom
				currZoom = map.getZoom();
				
				// Get Current Center
				currCenter = map.getCenter();

				// Get Current Category
				gCategoryId = currentCat;
				var catID = currentCat;
				
				var startTime = new Date($("#startDate").val() * 1000);
				var endTime = new Date($("#endDate").val() * 1000);
				addMarkers(catID, $("#startDate").val(), $("#endDate").val(), currZoom, currCenter, gMediaType);
								
				var startDate = $("#startDate").val();
				var endDate = $("#endDate").val();
				refreshGraph(startDate, endDate);	
				
				return false;
			});
			






			//////////////////////////////////////////////////////
			//Color status switcher
			//////////////////////////////////////////////////////
			$("#color_status_1").click(function()
			{
				//switch the status
				if( $("#color_status_1").hasClass("active"))
				{
					//we have it so remove it
					$("#color_status_1").removeClass("active"); // make it not active
					colorCurrentStatus = 1;
				}
				else
				{
					$("#color_status_1").addClass("active"); // make it active
					colorCurrentStatus = 2;
				}
				$("#colorCurrentStatus").val(colorCurrentStatus);

	
				
				// Destroy any open popups
				onPopupClose();
				
				// Get Current Zoom
				currZoom = map.getZoom();
				
				// Get Current Center
				currCenter = map.getCenter();
				
				currentStatus  = $("#currentStatus").val();

				currLogicalOperator = $("#currentLogicalOperator").val();

				// Get Current Category
				gCategoryId = currentCat;
				var catID = currentCat;
				
				var startTime = new Date($("#startDate").val() * 1000);
				var endTime = new Date($("#endDate").val() * 1000);
				addMarkers(catID, $("#startDate").val(), $("#endDate").val(), currZoom, currCenter, gMediaType);
								
				var startDate = $("#startDate").val();
				var endDate = $("#endDate").val();
				refreshGraph(startDate, endDate);	

				return false;
			});
			
			
			
			currentLogicalOperator
			//////////////////////////////////////////////////////
			//Logical Operator switcher
			//////////////////////////////////////////////////////
			$("a[id^='logicalOperator_']").click(function()
			{
				
				//switch whatever the current setting is. 
				if( $("#logicalOperator_1").hasClass("active")) //was OR, now make it AND
				{
					$("#logicalOperator_1").removeClass("active"); // not OR
					$("#logicalOperator_2").addClass("active"); // is AND
					currentLogicalOperator = "and";
				}
				else //was AND, now make it OR
				{
					$("#logicalOperator_2").removeClass("active"); // not AND
					$("#logicalOperator_1").addClass("active"); // is OR
					currentLogicalOperator = "or";
				}


				$("#currentLogicalOperator").val(currentLogicalOperator);

				// Destroy any open popups
				onPopupClose();
				
				// Get Current Zoom
				currZoom = map.getZoom();
				
				// Get Current Center
				currCenter = map.getCenter();
				
				currentStatus  = $("#currentStatus").val();

				// Get Current Category
				gCategoryId = currentCat;
				var catID = currentCat;

				
				var startTime = new Date($("#startDate").val() * 1000);
				var endTime = new Date($("#endDate").val() * 1000);
				addMarkers(catID, $("#startDate").val(), $("#endDate").val(), currZoom, currCenter, gMediaType);
								
				var startDate = $("#startDate").val();
				var endDate = $("#endDate").val();
				refreshGraph(startDate, endDate);	
				
				return false;
			});

			
			// Sharing Layer[s] Switch Action
			$("a[id^='share_']").click(function()
			{
				var shareID = this.id.substring(6);
				
				if ( $("#share_" + shareID).hasClass("active") )
				{
					share_layer = map.getLayersByName("Share_"+shareID);
					if (share_layer)
					{
						for (var i = 0; i < share_layer.length; i++)
						{
							map.removeLayer(share_layer[i]);
						}
					}
					$("#share_" + shareID).removeClass("active");
					
				} 
				else
				{
					$("#share_" + shareID).addClass("active");
					
					// Get Current Zoom
					currZoom = map.getZoom();

					// Get Current Center
					currCenter = map.getCenter();
					
					// Add New Layer
					addMarkers('', '', '', currZoom, currCenter, '', shareID, 'shares');
				}
			});

			// Exit if we don't have any incidents
			if (!$("#startDate").val())
			{
				map.setCenter(new OpenLayers.LonLat(<?php echo $longitude ?>, <?php echo $latitude ?>), 5);
				return;
			}
			
			//Accessible Slider/Select Switch
			$("select#startDate, select#endDate").selectToUISlider({
				labels: 4,
				labelSrc: 'text',
				sliderOptions: {
					change: function(e, ui)
					{
						var startDate = $("#startDate").val();
						var endDate = $("#endDate").val();
						var currentCat = gCategoryId;
						
						// Get Current Category
						currCat = currentCat;
						
						// Get Current Zoom
						currZoom = map.getZoom();
						
						// Get Current Center
						currCenter = map.getCenter();
						
						// If we're in a month date range, switch to
						// non-clustered mode. Default interval is monthly
						var startTime = new Date(startDate * 1000);
						var endTime = new Date(endDate * 1000);
						if ((endTime - startTime) / (1000 * 60 * 60 * 24) <= 32)
						{
							json_url = default_json_url;
						} 
						else
						{
							json_url = default_json_url;
						}
						
						// Refresh Map
						addMarkers(currCat, startDate, endDate, '', '', gMediaType);
						
						refreshGraph(startDate, endDate);
					}
				}
			});
			
			var allGraphData = "";
			var dailyGraphData = "";
			
			var startTime = <?php echo $active_startDate ?>;	// Or in human readable format <?php echo date("F j, Y, g:i:a", $active_startDate); ?>
			
			var endTime = <?php echo $active_endDate ?>;	// Or in human readable format <?php echo date("F j, Y, g:i:a", $active_endDate); ?>
					
			// get the closest existing dates in the selection options
			options = $('#startDate > optgroup > option').map(function()
			{
				return $(this).val(); 
			});
			startTime = $.grep(options, function(n,i)
			{
			  var newVal = parseInt(n);
			  return newVal >= startTime;
			})[0];
			
			options = $('#endDate > optgroup > option').map(function()
			{
				return $(this).val(); 
			});
			endTime = $.grep(options, function(n,i)
			{
			  var newVal = parseInt(n);
			  return newVal >= endTime;
			})[0];
			
			gCategoryId = '0';
			gMediaType = 0;
			//$("#startDate").val(startTime);
			//$("#endDate").val(endTime);
			
			// Initialize Map
			addMarkers(gCategoryId, startTime, endTime, '', '', gMediaType);
			refreshGraph(startTime, endTime);
			
			// Media Filter Action
			$('.filters li a').click(function()
			{
				var startTimestamp = $("#startDate").val();
				var endTimestamp = $("#endDate").val();
				var startTime = new Date(startTimestamp * 1000);
				var endTime = new Date(endTimestamp * 1000);
				gMediaType = parseFloat(this.id.replace('media_', '')) || 0;
				
				// Get Current Zoom
				currZoom = map.getZoom();
					
				// Get Current Center
				currCenter = map.getCenter();
				
				// Refresh Map
				addMarkers(currentCat, startTimestamp, endTimestamp, 
				           currZoom, currCenter, gMediaType);
				
				$('.filters li a').attr('class', '');
				$(this).addClass('active');
				gTimeline = $.timeline({categoryId: gCategoryId, startTime: startTime, 
				    endTime: endTime, mediaType: gMediaType,
					url: "<?php echo url::site(); ?>json_url+'/timeline/'"
				});
				gTimeline.plot();
			});
			
			$('#playTimeline').click(function()
			{
			    gTimelineMarkers = gTimeline.addMarkers(gStartTime.getTime()/1000,
					$.dayEndDateTime(gEndTime.getTime()/1000), gMap.getZoom(),
					gMap.getCenter(),null,null,null,null,"json");
				gTimeline.playOrPause('raindrops');
			});
			
			
			///////////////////////////////////////////////////////////////////////////
			//check and see if we should update the map given the URL
			/////////////////////////////////////////////////////////////////////////
			
			//re center the map
			if($.address.parameter("lon") != null && $.address.parameter("lat"))
			{
				var lon = $.address.parameter("lon");
				var lat  = $.address.parameter("lat");
				var lonlat = new OpenLayers.LonLat(lon,lat);
				gMap.setCenter(lonlat);				
			}
			
			if($.address.parameter("z") != null)
			{
				gMap.zoomTo($.address.parameter("z"));
			}
			
			
			if($.address.parameter("startDate") != null)
			{
				startDate = $.address.parameter("startDate");
				$("#startDate").val(startDate);
				$("#startDate").trigger("change");
				
			}
			
			if($.address.parameter("endDate") != null)
			{
				endDate = $.address.parameter("endDate");
				$("#endDate").val(endDate);
				$("#endDate").trigger("change");
				
			}
			
			
			if($.address.parameter("currColorStatus") != null)
			{
				$("#color_status_1").trigger("click");
			}
			
			if($.address.parameter("logic") != null)
			{
				var logic = $.address.parameter("logic")
				if(logic == "and")
				{
					$("#logicalOperator_2").trigger("click");
				}
				else if (logic == "or")
				{
					//because OR is the default
					//$("#logicalOperator_1").trigger("click");
				}
			}
			
			if($.address.parameter("catId") != null)
			{
				var cats = $.address.parameter("catId")
				//first check if there are "," involved, if not just click and go
				if(cats.search(",") == -1)
				{
					$("#cat_"+cats).trigger("click");
				}
				else
				{
				
					var catsarray = cats.split(",",-1);
					for(i in catsarray)
					{
						
						//check if the given category is hidden under a parent
						if($("#cat_"+catsarray[i]).is(":hidden"))
						{
							//it's hidden so we need to show it
							//find it's drop_cat and go from there
							$("#cat_"+catsarray[i]).parents("div[id^='child_']").siblings("a[id^='drop_cat_']").trigger("click");
						}
						$("#cat_"+catsarray[i]).trigger("click");						
					}
				}
				
			}
			
			//handle the key
			if($.address.parameter("hk") != null)
			{
				$("#key").hide();
				$("#showKeyCheckbox").removeAttr("checked");
			}
			
			if($.address.parameter("left") != null)
			{
				$("#key").removeClass("right");
				$("#key").addClass("left");
				$("#leftPlacement").attr("checked","checked");
			}
			if($.address.parameter("right") != null)
			{
				$("#key").removeClass("left");
				$("#key").addClass("right");
				$("#rightPlacement").attr("checked","checked");
			}
			if($.address.parameter("top") != null)
			{
				$("#key").removeClass("bottom");
				$("#key").addClass("top");
				$("#topPlacement").attr("checked","checked");
			}
			if($.address.parameter("bottom") != null)
			{
				$("#key").removeClass("top");
				$("#key").addClass("bottom");
				$("#bottomPlacement").attr("checked","checked");
			}
			
			if($.address.parameter("orientation") != null)
			{
				var orientation = $.address.parameter("orientation");
				if(orientation == "landscape")
				{
					$("#orientation_landscape").trigger("change");
					$("#orientation_landscape").attr("checked", "checked");
				}
				else
				{
					$("#orientation_portrait").trigger("change");
					$("#orientation_portrait").attr("checked", "checked");
				}
			}
			

			//handle layers
			if($.address.parameter("layer") != null)
			{
				var layersArray = $.address.parameter("layer").split(",");
				for(i in layersArray)
				{
					$("#layer_" + layersArray[i]).trigger("click");
				}
			}
			
			$.get("<?php echo url::site(); ?>printmapkey/getKey/" + 
				$("#currentCat").val() + "/" + 
				$("#currentLogicalOperator").val() + "/" + 
				$("#startDate").val() + "/" + 
				$("#endDate").val(),
				
				function(data){
					$("#key").html(data);					
					canRedrawMapKey = true;
					
					
					// Get Current Category
					currCat = $("#currentCat").val();
		
					// Get Current Start Date
					currStartDate = $("#startDate").val();
		
					// Get Current End Date
					currEndDate = $("#endDate").val();
		
					// Get Current Zoom
					currZoom = map.getZoom();
		
					// Get Current Center
					currCenter = map.getCenter();
		
					// Refresh Map
					addMarkers(currCat, currStartDate, currEndDate, currZoom, currCenter);
										
			});
			
			
			
		});
		

		
		
		
		
		
		
		
		
		
	
	
	
	
