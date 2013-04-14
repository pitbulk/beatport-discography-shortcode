<?php
/*
Plugin Name: Beatport Discography shortcode
Plugin URI: http://www.federicogiust.com/
Description: Embed Beatport Discography using shortcodes
Version: 1.1.1
Author: Federico Giust
Author URI: http://www.federicogiust.com
License: GPL2

Copyright 2013 Federico Giust  (email : info@federicogiust.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/


if (!class_exists('BeatportDiscography_shortcode')):

function sort_tracks($track1, $track2) {

	if($track1->trackNumber ==  $track2->trackNumber) { 
		return 0 ; 
	}
	else {
		return ($track1->trackNumber < $track2->trackNumber) ? -1 : 1;
	}
}


class BeatportDiscography_shortcode {

	var $plugin_version = '111'; // version 1.1.1

	/**
	* Constructor / Initialize the plugin
	*
	* Pass the shortcode parameters to output_html to generate the output
	*
	* Get the beatport button for the admin side or the html for the public side
	*/
	function BeatportDiscography_shortcode()
	{
		// Load our shortcode only on the frontend
		if ( !is_admin() )
		{
			// Execute output_html to replace shortcode
			add_shortcode('beatport_discography_sc', array($this, 'output_html'));
			// Add css to the output
			add_action('wp_enqueue_scripts', array($this, 'output_css'));
			add_action('wp_enqueue_scripts', array($this, 'output_js'));
		}
		// Allow the button to appear on any rich text editor (i.e. text editor in a widget)
		else
		{
			// If we are on the dashboard enable the beatport button
			add_action('admin_init', array($this, 'setup_tinymce_button'));
		}
	}

	/**
	* Output CSS
	* This gets the name of the CSS file we use to add some styling to the list of items
	*/
	function output_css()
	{
		wp_register_style('BeatportDiscographyShortcode', plugins_url('beatport-discography-shortcode.css', __FILE__));
		wp_register_style('BeatportDiscographyShortcode2', plugins_url('sushi.css', __FILE__));
		wp_enqueue_style('BeatportDiscographyShortcode');
		wp_enqueue_style('BeatportDiscographyShortcode2');
	}
	
	function output_js()
	{

		wp_register_script('BeatportDiscographyShortcode', plugins_url('soundManager.js', __FILE__), array( 'jquery' ));
		wp_enqueue_script('BeatportDiscographyShortcode');
	}


	function pretty_print_release($dataArray) {
		$beatport_url = 'http://www.beatport.com/';

		if (empty($dataArray['results'])) {
			return 'Release not found';
		}

		$release = $dataArray['results'][0];
		
		$output = '';
		
		$dynamicImg = urldecode($release->dynamicImages->main->url);

		$dynamicImg = str_replace('{hq}', '', $dynamicImg);
		$img500 = str_replace('{w}x{h}','500x500', $dynamicImg);
		$img212 = str_replace('{w}x{h}','212x212', $dynamicImg);

		$artist_list = array();
		$artist_names = array();
		foreach ($release->artists as $artist) {
			$artist_list[] = '<a target="_new" href="'.$beatport_url.'artist/'.$artist->slug.'/'.$artist->id.'" >'.$artist->name.'</a>';
			$artist_names[] = $artist->name;
		}
		$artist_output = implode(' ', $artist_list);
		$artist_names_output = implode(',  ', $artist_names);
 
		$genre_list = array();
		foreach ($release->genres as $genre) {
			$genre_list[] = '<a target="_new" href="'.$beatport_url.'genre/'.$genre->slug.'/'.$genre->id.'" >'.$genre->name.'</a>';
		}
		$genre_output = implode(' ', $genre_list);

		$price = $release->price->value;

		$price_output = intval($price / 100);
		if ($price % 100 != 0) {
			$price_output .= ','.(($price % 100));
		}

		$output .= '';

		$output .= '<div class="release-detail">';

		$output .= 		'<div class="release-detail-metadata">
							<div class="album-intro">
								<div class="album-title"><h1>'.$release->name.'</h1></div>
								<div class="album-artist">'.$artist_output.'</div>
							</div>
							<div class="tile-image-wrapper">
								<a target="_new" href="'.$beatport_url.'release/'.$release->slug.'/'.$release->id.'" data-full-image-url="'.$img500.'">
									<img class="tile-image" src="'.$img212.'" alt="'.$artist_names_output.' - '.$release->name.'" width="212" height="212">
								</a>
							</div>';
		$output .= 			'<div class="description">
								<table class="meta-data">
									<colgroup>
										<col class="col1">
										<col class="col2">
									</colgroup>
								<tbody>
									<tr>
										<td class="meta-data-label">Release Date</td>
										<td class="meta-data-value">'.$release->releaseDate.'</td>
										</tr>
									<tr>
										<td class="meta-data-label">Sello</td>
										<td class="meta-data-value"><a target="_new" href="'.$beatport_url.'/label/'.$release->label->slug.'/'.$release->label->id.'">'.$release->label->name.'</a></td>
									</tr>
									<tr><td class="meta-data-label">Catálogo #</td>
										<td class="meta-data-value">'.$release->catalogNumber.'</td>
									</tr>
									<tr><td class="meta-data-label">Precio</td>
										<td class="meta-data-value">'.$release->price->symbol.' '.$price_output.'</td>
									</tr>
									<tr><td class="meta-data-label">Genero</td>
										<td class="meta-data-value">'.$genre_output.'</td>
									</tr>
								</tbody>
								</table>						
							</div>
							<div class="description-album">'.$release->description.'</div>
						</div>
					</div>
					<div style="clear:both;"></div>
					';

		return $output;
	}


	function pretty_print_tracks(array $dataArray, $short_info = False) {
		$beatport_url = 'http://www.beatport.com/';

		if (empty($dataArray['results'])) {
			return 'Release not found';
		}

		$output = '';

		$tracks = $dataArray['results'];

		$output .= '<div class="release-tracks">';
		$output .= '<table class="tracks">';

		usort($tracks, "sort_tracks");

		$i = 0;

		foreach ($tracks as $track) {

			$title = $track->title;
			if (isset($track->mixName)) {
				$title = str_replace('('.$track->mixName.')', '', $title);
			}
			$title_href = $beatport_url.'track/'.$track->slug.'/'.$track->id;
			$title_output = '<a target="_new" href="'.$title_href.'"><b>'.$title.'</b></a>';
			if (isset($track->mixName)) {
				$title_output .= ' <a target="_new" style="color:grey;" href="'.$title_href.'"> ( '.$track->mixName.' )</a>';
			}

			if (!$short_info) {

				$artist_list = array();
				foreach ($track->artists as $artist) {
					$artist_list[] = '<a target="_new" href="'.$beatport_url.'artist/'.$artist->slug.'/'.$artist->id.'" >'.$artist->name.'</a>';
				}
				$artist_output = implode(' ', $artist_list);

				$genre_list = array();
				foreach ($track->genres as $genre) {
					$genre_list[] = '<a target="_new" href="'.$beatport_url.'genre/'.$genre->slug.'/'.$genre->id.'" >'.$genre->name.'</a>';
				}
				$genre_output = implode(' ', $genre_list);
			}

			$tr_class = ($i % 2 == 0) ? 'even' : 'odd';
	
			$output .= '	<tr class="track-'.$tr_class.'">
								<td class="track-number">'.$track->trackNumber.'</td>
								<td colspan="2" class="track-title">'.$title_output.'</td>';

			if (!$short_info) {
				$output .= '	<td class="track-timing">'.$track->length.' / '.$track->bpm.' BPM</td>		
							</tr>
							<tr class="track-'.$tr_class.'">
								<td colspan="2" class="track-artists">'.$artist_output.'</td>
								<td class="track-generes">'.$genre_output.'</td>
								<td class="track-price">'.$track->price->display.'</td>';
				$output .= '</tr>';
			}
			$i++;
		}
		$output .= '</table>';
  		$output .= '</div>';
		return $output;
	}


	/**
	* Generate unordered list with the items from the feed.
	* Depending on the type of items we want, we generate the corresponding list.
	* This function takes three parameters
	* $items - Is the type of items to render (tracks or releases)
	* $feed - Wich feed are we using, artist or label
	* $dataArray - Array with the data we got from the API
	*/
	function getRenderedFeed($items, $feed, array $dataArray){
		$output = '';
		if ($feed == 'id') {
			if ($items == 'releases') {
				$tracks = $dataArray['tracks'];
				unset($dataArray['tracks']);
				$output = $this->pretty_print_release($dataArray);
				$output .= $this->pretty_print_tracks($tracks);
			}
			else if ($items == 'tracks') {
				$output .= $this->pretty_print_tracks($dataArray);
			}
			else {
				$output .= 'An error has ocurred.';
			}
		}
		else {

			$output .= '<ul class="releaselist">' . PHP_EOL;

			if($items == 'releases'){
				/** If we want the releases we then use the releases object from the API (http://api.beatport.com/releases.html) */

				for ($i = 0; $i < count($dataArray['results']); $i++){
					$artistsTemp = (array) $dataArray['results'][$i] -> artists; 
					$output .= '<li class="releaserow">' . PHP_EOL;
					$output .= '<div id="release' . $dataArray['results'][$i] -> catalogNumber . '" class="release">' . PHP_EOL;
					$output .= '<div class="releaseart">' . PHP_EOL;
				
					$output .= '<img src="' . $dataArray['results'][$i] -> images -> medium -> url . '"/>' . PHP_EOL;

					$output .= '</div>' . PHP_EOL;
					$output .= '<div class="releaseinfo">' . PHP_EOL;
					$output .= '<span class="releasename">' . PHP_EOL;
					$output .= $dataArray['results'][$i] -> name . PHP_EOL;
				
					$output .= '</span>' . PHP_EOL;
					$output .= '<br />' . PHP_EOL;
					$output .= '<span class="releaseartists">' . PHP_EOL;
					for($j = 0; $j < count($artistsTemp); $j++){
						$output .= $artistsTemp[$j] -> name;
						if(count($artistsTemp)>0 && $j < count($artistsTemp)-1){
							$output .= ', ';
						}
					}
				
					$output .= '</span>' . PHP_EOL;
					$output .= '<br />' . PHP_EOL;
					$output .= '<span class="releasemoreinfo">' . PHP_EOL;
					$output .= $dataArray['results'][$i] -> catalogNumber . ' | ';
					$output .= $dataArray['results'][$i] -> label -> name . ' | ';
					$output .= $dataArray['results'][$i] -> releaseDate . ' | ' . PHP_EOL;
					$output .= '<a href="https://www.beatport.com/release/' . $dataArray['results'][$i] -> slug . '/' . $dataArray['results'][$i] -> id . '" target="_new">Buy</a>';
					$output .= $dataArray['results'][$i] -> genres -> name;
					$output .= '</span>' . PHP_EOL;
					$output .= '</div>' . PHP_EOL;
					$output .= '</div>' . PHP_EOL;
					$output .= '</li>' . PHP_EOL;
				}

			}elseif ($items == 'tracks'){
				/** If we want the tracks, we then use the tracks object (http://api.beatport.com/tracks.html) */
				for ($i = 0; $i < count($dataArray['results']); $i++){
					$artistsTemp = (array) $dataArray['results'][$i] -> artists; 
					$genreTemp = (array) $dataArray['results'][$i] -> genres;
				
					$output .= '<li class="releaserow">' . PHP_EOL;
					$output .= '<div id="release' . $dataArray['results'][$i] -> catalogNumber . '" class="release">' . PHP_EOL;
					$output .= '<div class="releaseart">' . PHP_EOL;
					$output .= '<a href="' . $dataArray['results'][$i] -> sampleUrl . '" class="beatportsample">' . PHP_EOL;
					$output .= '<div class="coveroverlay"></div>' . PHP_EOL;
				
					$output .= '</a>' . PHP_EOL;
				
					$output .= '<img src="' . $dataArray['results'][$i] -> images -> medium -> url . '"/>' . PHP_EOL;
								
					$output .= '</div>' . PHP_EOL;
					$output .= '<div class="releaseinfo">' . PHP_EOL;
					$output .= '<span class="releasename">' . PHP_EOL;
				
					$output .= $dataArray['results'][$i] -> title . ' [' . $dataArray['results'][$i] -> length . ']' . PHP_EOL;
				
					$output .= '</span>' . PHP_EOL;
					$output .= '<br />' . PHP_EOL;		
					$output .= '<span class="releaseartists">' . PHP_EOL;
					for($j = 0; $j < count($artistsTemp); $j++){
						$output .= $artistsTemp[$j] -> name;
						if(count($artistsTemp)>0 && $j < count($artistsTemp)-1){
							$output .= ', ';
						}
					}
					$output .= '</span>' . PHP_EOL;
					$output .= '<br />' . PHP_EOL;
					$output .= '<span class="releasemoreinfo">' . PHP_EOL;
					for($j = 0; $j < count($genreTemp); $j++){
						$output .= $genreTemp[$j] -> name;
						if(count($genreTemp)>0 && $j < count($genreTemp)-1){
							$output .= ', ';
						}
					}
					$output .= ' | ';		
					$output .= $dataArray['results'][$i] -> label -> name . ' | ';
					$output .= $dataArray['results'][$i] -> releaseDate . ' | ' . PHP_EOL;
					$output .= '<a href="https://www.beatport.com/track/' . $dataArray['results'][$i] -> slug . '/' . $dataArray['results'][$i] -> id . '" target="_new">Buy</a>' . PHP_EOL;
					$output .= '</span>' . PHP_EOL;
					$output .= '<br />' . PHP_EOL;

					$output .= '</div>' . PHP_EOL;
					$output .= '</div>' . PHP_EOL;
					$output .= '</li>' . PHP_EOL;
				}

			}else{
				$output .= 'An error has ocurred.';
			}
			$output .= '</ul>' . PHP_EOL;
		}
		return $output;
	}

	function getData($url, $qrystring){
		$json = file_get_contents($url . $qrystring);        
		$data = json_decode($json);
		$dataArray = (array) $data;
		return $dataArray;
	}

	function output_html( $atts, $content = null )
	{

		if ( !isset($this->is_feed) )
		{
			$this->is_feed = is_feed();
		}

		extract( shortcode_atts( array(
			// custom parameters
			'feed' => '',
			'artist' => '',
			'label' => '',
			'id' => '',
   			'items' => ''

		), $atts ) );

		// HTML OUTPUT
		$output = '';

		$url = 'http://' . $this -> get_server_host();

		if($atts['feed'] == 'id') {
			// release id

			if ($atts['items'] == 'releases') {
				$qrystring1 = 'releases?id=' . str_replace(' ', '+', $atts['id']) . '&sortBy=publishDate%20desc&perPage=150';
				$qrystring2 = 'tracks?releaseId=' . str_replace(' ', '+', $atts['id']) . '&sortBy=publishDate%20desc&perPage=150';
				$dataArray = $this->getData($url, $qrystring1);
				$tracks = $this->getData($url, $qrystring2);
				$dataArray['tracks'] = $tracks;
			}
			else if ($atts['items'] == 'tracks') {
				$qrystring = 'tracks?id=' . str_replace(' ', '+', $atts['id']) ;
				$dataArray = $this->getData($url, $qrystring);
			}
		}
		else {

			if ($atts['items'] == 'releases') {
				$url .= 'releases';
			} else if ($atts['items'] == 'tracks') {
				$url .= 'tracks';
			}

			if ($atts['feed'] == 'artist'){
				$qrystring = '?facets[]=performerName:' . str_replace(' ', '+', $atts['artist']) . '&sortBy=publishDate%20desc&perPage=150';
				$dataArray = $this->getData($url, $qrystring);
			
			} else if($atts['feed'] == 'label'){ 
				$url .= '';
				$qrystring = '?facets[]=labelName:' . str_replace(' ', '+', $atts['label']) . '&sortBy=publishDate%20desc&perPage=150';
				$dataArray = $this->getData($url, $qrystring);
			}
		}

		$output .= $this->getRenderedFeed($atts['items'], $atts['feed'], $dataArray);
		return $output;

	}


	function get_server_host()
	{
		return 'api.beatport.com/catalog/3/';
	}

	
	// TinyMCE Button

	// Set up our TinyMCE button
	function setup_tinymce_button()
	{
		if (get_user_option('rich_editing') == 'true' && current_user_can('edit_posts')) {
			add_action('admin_print_scripts', array($this, 'output_tinymce_dialog_vars'));
			add_filter('mce_external_plugins', array($this, 'add_tinymce_button_script'));
			add_filter('mce_buttons', array($this, 'register_tinymce_button'));
		}
	}


	// Register our TinyMCE button
	function register_tinymce_button($buttons) {
		array_push($buttons, '|', 'BeatportDiscographyShortcodeButton');
		return $buttons;
	}


	// Register our TinyMCE Script
	function add_tinymce_button_script($plugin_array) {
		$plugin_array['BeatportDiscographyShortcode'] = plugins_url('tinymcebutton.js', __FILE__);
		return $plugin_array;
	}


	function output_tinymce_dialog_vars()
	{
		$data = array(
			'pluginVersion' => $this->plugin_version,
			'includesUrl' => includes_url(),
			'pluginsUrl' => plugins_url()
		);

		?>
		<script type="text/javascript">
		// <![CDATA[
			window.beatportDiscographyShortcodeDialogData = <?php echo json_encode($data); ?>;
		// ]]>
		</script>
		<?php
	}

}

// Create just one instance per request
new BeatportDiscography_shortcode();

endif;
