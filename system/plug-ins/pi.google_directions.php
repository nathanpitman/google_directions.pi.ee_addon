<?php

$plugin_info = array(
	'pi_name'			=> 'Google Directions',
	'pi_version'		=> '0.1',
	'pi_author'			=> 'Nathan Pitman',
	'pi_author_url'		=> 'http://ninefour.co.uk/labs/',
	'pi_description'	=> 'Return directions from the Google Directions API',
	'pi_usage'			=> google_directions::usage()
);


class google_directions {

	var $return_data;
	var $base_url 		= "http://maps.googleapis.com/maps/api/directions/json?";
	var $cache_name		= 'google_directions_cache';
	var $cache_expired	= FALSE;
	var $refresh		= 7; // Period between cache refreshes, in days - roads don't change often.

	function google_directions()
	{
		
		global $FNS, $REGX, $TMPL;
		
		// Define vars
		$this->status = "REQUIRED_PARAMETERS_MISSING";
		$this->cached = "No";
		$this->origin = "";
		$this->destination = "";

		$this->origin = urlencode($REGX->xss_clean($TMPL->fetch_param('origin')));
		$this->destination = urlencode($REGX->xss_clean($TMPL->fetch_param('destination')));
		$this->mode = urlencode($REGX->xss_clean($TMPL->fetch_param('mode')));
		
		if ($_POST) {
			if ((isset($_POST['directions_origin'])) OR (isset($_POST['directions_destination'])) OR (isset($_POST['directions_mode']))) {				
				if (!empty($_POST['directions_origin'])) {
					$this->origin = urlencode($REGX->xss_clean($_POST['directions_origin']));
				}
				if (!empty($_POST['directions_destination'])) {
					$this->destination = urlencode($REGX->xss_clean($_POST['directions_destination']));
				}
				if (!empty($_POST['directions_mode'])) {
					$this->mode = urlencode($REGX->xss_clean($_POST['directions_mode']));
				}
			}	
		}
		
		$tagdata = $TMPL->tagdata;
				
		if ((!empty($this->origin)) AND (!empty($this->destination))) {
			
			if (!empty($this->mode)) {
				$api_call = $this->base_url."origin=".$this->origin."&destination=".$this->destination."&sensor=false&mode=".$this->mode;
			} else {
				$api_call = $this->base_url."origin=".$this->origin."&destination=".$this->destination."&sensor=false";
			}

			// caching
			if (($rawjson = $this->_check_cache($api_call.$this->origin.$this->destination)) === FALSE) {
				$this->cache_expired = TRUE;
				$TMPL->log_item("Fetching directions from Google.com");
		    	$rawjson = file_get_contents($api_call);
			} else {
				$this->cached = "Yes";
			}
			
			if ($rawjson == '') {
				$TMPL->log_item("Error: Unable to retrieve directions from Google.com");
				$this->return_data = '';
				return;
			} else {
				
				$response = json_decode($rawjson);
				$this->status = $response->status;
				
				if ($this->status == TRUE) {
					
					// Did the response include any directions?
			    	if ($response->status == "OK") {
			    	
						$TMPL->log_item("Writing directions to local cache");
						$this->_write_cache($rawjson, $api_call.$this->origin.$this->destination);			
			    	
			    	} else {
			    	
			    		$TMPL->log_item("Directions empty, not written to local cache");
			    	
			    	}
					
				}
				
			}
			
			//parse template
			$swap = array();
			
			// Look for data within {routes} and {/routes}
      		preg_match("/".LD."routes".RD."(.*?)".LD.SLASH.'routes'.RD."/s", $tagdata, $routes_tagdata);

			if (!empty($routes_tagdata)) {
			
				$routes_tagdata_new = '';
				$route_count = 1;
				
				// Conditionals
				if (!empty($response->routes)) {
					$cond['routes'] = 1;
				} else {
					$cond['routes'] = 0;
				}
				
				foreach ($response->routes as $route) {
			
					$routes_swap['route_count'] = $route_count;
					$routes_swap['route_summary'] = $route->summary;
					$routes_swap['copyrights'] = $route->copyrights;
					
					$routes_tagdata_new .= $FNS->var_swap($routes_tagdata[1], $routes_swap);
                	$route_count++;
                	
                	// Look for data within {legs} and {/legs}
      				preg_match("/".LD."legs".RD."(.*?)".LD.SLASH.'legs'.RD."/s", $tagdata, $legs_tagdata);
					
					if (!empty($legs_tagdata)) {
					
						$legs_tagdata_new = '';
						$leg_count = 1;
					
						foreach ($route->legs as $leg) {
						
							$legs_swap['leg_count'] = $leg_count;
							$legs_swap['leg_duration_text'] = $leg->duration->text;
							$legs_swap['leg_distance_text'] = $leg->distance->text;
							$legs_swap['leg_start_address'] = $leg->start_address;
							$legs_swap['leg_end_address'] = $leg->end_address;
							//$legs_swap['leg_start_location'] = $leg->start_location;
							//$legs_swap['leg_end_location'] = $leg->end_location;

							$legs_tagdata_new .= $FNS->var_swap($legs_tagdata[1], $legs_swap);
							$leg_count++;
						
						}
						
						// Look for data within {steps} and {/steps}
	      				preg_match("/".LD."steps".RD."(.*?)".LD.SLASH.'steps'.RD."/s", $tagdata, $steps_tagdata);
						
						if (!empty($steps_tagdata)) {
						
							$steps_tagdata_new = '';
							$step_count = 1;
						
							foreach ($leg->steps as $step) {
							
								$steps_swap['step_count'] = $step_count;
								$steps_swap['step_travel_mode'] = ucfirst(strtolower($step->travel_mode));
								$steps_swap['step_duration_text'] = $step->duration->text;
								$steps_swap['step_html_instructions'] = $step->html_instructions;
								//$steps_swap['leg_start_location'] = $step->start_location;
								//$steps_swap['leg_end_location'] = $step->end_location;
	
								$steps_tagdata_new .= $FNS->var_swap($steps_tagdata[1], $steps_swap);
								$step_count++;
							
							}
							
							//print_r("boo");
							//exit;
							
							// search_for, replace_with, apply_to
							$legs_tagdata_new = str_replace($steps_tagdata[0], $steps_tagdata_new, $legs_tagdata_new);
						
						}
						
						// search_for, replace_with, apply_to
						$routes_tagdata_new = str_replace($legs_tagdata[0], $legs_tagdata_new, $routes_tagdata_new);
					
					}
					
					//finish up, add required copyright data
					$tagdata .= '<p class="google_directions_copyright">'.$route->copyrights.'</p>';
                
				}
				
				// search_for, replace_with, apply_to
				$tagdata = str_replace($routes_tagdata[0], $routes_tagdata_new, $tagdata);
			
			}
			
		} else {
		
			$cond['routes'] = 0;
				
		}
		
		foreach ($TMPL->var_single as $key => $val) {
			
			if($key == 'status') {
			$tagdata = $TMPL->swap_var_single(
				$key, 
				$this->status, 
				$tagdata
				);
			}
			
			if($key == 'cached') {
			$tagdata = $TMPL->swap_var_single(
				$key, 
				$this->cached, 
				$tagdata
				);
			}
			
			if($key == 'origin') {
			$tagdata = $TMPL->swap_var_single(
				$key, 
				urldecode($this->origin), 
				$tagdata
				);
			}
			
			if($key == 'destination') {
			$tagdata = $TMPL->swap_var_single(
				$key, 
				urldecode($this->destination), 
				$tagdata
				);
			}
			
			if($key == 'mode') {
			$tagdata = $TMPL->swap_var_single(
				$key, 
				$this->mode, 
				$tagdata
				);
			}
			
		}
		
		// Prep conditionals
		$tagdata = $FNS->prep_conditionals($tagdata, $cond);
			
		$this->return_data = $tagdata;
		
		//return
		return $this->return_data;
		
	}

	// --------------------------------------------------------------------
	
	/**
	 * Check Cache
	 *
	 * Check for cached data
	 *
	 * @access	public
	 * @param	string
	 * @return	mixed - string if pulling from cache, FALSE if not
	 */

	function _check_cache($url)
	{	
		global $TMPL;
			
		/** ---------------------------------------
		/**  Check for cache directory
		/** ---------------------------------------*/
		
		$dir = PATH_CACHE.$this->cache_name.'/';
		
		if ( ! @is_dir($dir))
		{
			return FALSE;
		}

        $file = $dir.md5($url);
		
		if ( ! file_exists($file) OR ! ($fp = @fopen($file, 'rb')))
		{
			return FALSE;
		}
		       
		flock($fp, LOCK_SH);
                    
		$cache = @fread($fp, filesize($file));
                    
		flock($fp, LOCK_UN);
        
		fclose($fp);
        
		$eol = strpos($cache, "\n");
		
		$timestamp = substr($cache, 0, $eol);
		$cache = trim((substr($cache, $eol)));
		
		if (time() > ($timestamp + ($this->refresh * 60 * 60 * 24)))
		{
			return FALSE;
		}
		
		$TMPL->log_item("Directions retrieved from cache");
		
        return $cache;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Write Cache
	 *
	 * Write the cached data
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function _write_cache($data, $url)
	{
		/** ---------------------------------------
		/**  Check for cache directory
		/** ---------------------------------------*/
		
		$dir = PATH_CACHE.$this->cache_name.'/';

		if ( ! @is_dir($dir))
		{
			if ( ! @mkdir($dir, 0777))
			{
				return FALSE;
			}
			
			@chmod($dir, 0777);            
		}
		
		// add a timestamp to the top of the file
		$data = time()."\n".$data;
		
		/** ---------------------------------------
		/**  Write the cached data
		/** ---------------------------------------*/
		
		$file = $dir.md5($url);
	
		if ( ! $fp = @fopen($file, 'wb'))
		{
			return FALSE;
		}

		flock($fp, LOCK_EX);
		fwrite($fp, $data);
		flock($fp, LOCK_UN);
		fclose($fp);
        
		@chmod($file, 0777);		
	}


	function usage()
	{
	ob_start(); 
	?>
	The Google Directions plug-in allows you to pull data from the Google Directions API into your page templates. Please note that the relevant copyright is automatically appended to your output in order that this plug-in adheres to the Google Directions API terms of use.

{exp:google_directions}

Parameters:
-------------------------------------
	
	mode = (optional) the mode of transport you want to display directions for. Can be driving, walking or cycling.
	origin = the start location for the journey, can be either a lat/long pair, zip/postcode or location name.
	destination = the end location for the journey, can be either a lat/long pair, zip/postcode or location name.

Single variables:
-------------------------------------

	{status} - Display the status value returned by the Google Directions API. Usually 'OK'.
	{cached} - Informs you as to whether the data was pulled from the API or your local file cache.


Tag pairs:
-------------------------------------

ROUTES - {routes}

Loops over the available routes for the journey. Routes have the following available single variables:

	{route_summary} - A short summary of the route
	{route_count} - The current route number.

LEGS - {legs}

Journeys can have multiple legs, but not always. Legs have the following available single variables:

	{leg_count} - The current leg number.
	{leg_duration_text} -
	{leg_distance_text} -
	{leg_start_address} -
	{leg_end_address} -

STEPS - {steps}

This is where the fun happens, journeys have a number of steps. Steps have the following available single variables:

	{step_count} - The current step number.
	{step_travel_mode} - The travel mode (Note this is appears to always imply mimic the travel mode you specify)
	{step_duration_text} - How long does this step take, in hours/minutes.
	{step_html_instructions} - Instructions for this step.

Example usage:
-------------------------------------

{exp:google_directions mode="walking" origin="London,UK" destination="rg457ah, uk"}

<form method="post">
	<fieldset>
	<legend>Origin and Destination</legend>
		<input name="directions_origin" value="{origin}" />&nbsp;<input name="directions_destination" value="{destination}" />&nbsp;<select name="directions_mode"><option value="driving">Driving</option><option value="walking">Walking</option><option value="bicycling">Cycling</option></select><button>Get Directions</button>
	</fieldset>
</form>

<p><strong>Status</strong>: {status} <strong>From Cache</strong>: {cached}</p>

{if routes}

{routes}

<h1>Summary: {route_summary}, Count: {route_count}</h1>

	<table>
		{legs}
		<tr class="leg">
			<td>Leg {leg_count}</td>
			<td>{leg_duration_text}</td>
			<td>{leg_distance_text}</td>
			<td>{leg_start_address} > {leg_end_address}</td>
		</tr>
		
		{steps}
		<tr class="steps">
			<td>Step: {step_count}</td>
			<td>{step_travel_mode}</td>
			<td>{step_duration_text}</td>
			<td>{step_html_instructions}</td>
		</tr>	
		{/steps}
	
		{/legs}
	</table>

{/routes}

{if:else}

<p>Sorry, we couldn't return directions for that query.</p>

{/if}

{/exp:google_directions}

CHANGE LOG
0.1 - Initial release

	<?php
	$buffer = ob_get_contents();
		
	ob_end_clean(); 
	
	return $buffer;
	}


} // END CLASS