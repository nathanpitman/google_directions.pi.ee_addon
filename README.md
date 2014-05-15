google_directions.pi.ee_addon
=============================

Returns directions from the Google Directions API

The Google Directions plug-in allows you to pull data from the Google Directions API into your page templates. Please note that the relevant copyright is automatically appended to your output in order that this plug-in adheres to the Google Directions API terms of use.

```
{exp:google_directions}
```

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

```
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
```
