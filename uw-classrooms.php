<?php
/*
Plugin Name: UW Classrooms
Plugin URI:  https://github.com/uw-it-cte/WP-UW-Classrooms
Description: Display information about classrooms
Version:     0
Author:      Bradley Bell
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

ini_set('display_errors', 1);

require 'class.uw-servicenowclient.php';


add_action('init', 'uw_classrooms_init');
function uw_classrooms_init()
{
  global $uw_snclient;

  register_taxonomy('location-type', 'page', array(
						   'label' => 'Location Type',
						   'hierarchical' => true,
						   ));

  $uw_snclient = new UW_ServiceNowClient(array(
					       'base_url' => get_option('uw_SN_URL'),
					       'username' => get_option('uw_SN_USER'),
					       'password' => get_option('uw_SN_PASS'),
					       ));

  wp_enqueue_style( 'uw-classrooms', plugins_url('uw-classrooms/style.css'));

}


function get_location_sys_id($post)
{
  global $uw_snclient;

  if ( $location_sys_id = get_post_meta($post->ID, 'uw-location-sys-id', true) )
    return $location_sys_id;

  if ( !$building = get_post_meta($post->ID, 'uw-building-code', true) )
    return false;

  if ( !$room = get_post_meta($post->ID, 'uw-room-number', true) )
    return false;

  $result = json_decode($uw_snclient->get_records('cmn_location', "parent.u_fac_code={$building}^u_room_number={$room}"), false);

  if (count($result['records']) != 1)
    return false;

  $location_sys_id = $result['records'][0]['sys_id'];

  add_post_meta($post->ID, 'uw-location-sys-id', $location_sys_id, true);

  return $location_sys_id;
}


function get_map_link()
{
  global $post;

  if ( ! ($map_url = get_post_meta($post->ID, 'uw-map-url', true)) ) {

    if ( !$building = get_post_meta($post->ID, 'uw-building-code', true) )
      return false;

    $map_url = 'http://uw.edu/maps/?' . strtolower($building);

    add_post_meta($post->ID, 'uw-map-url', $map_url, true);

  }

  return '<h3 class="map-link"><a href="' . $map_url . '" target="_blank">Map Location*</a></h3>';
}


function get_access_link()
{
  global $post;

  if ( ! ($access_url = get_post_meta($post->ID, 'uw-access-url', true)) ) {

    if ( !$building = get_post_meta($post->ID, 'uw-building-code', true) )
      return false;

    $url = 'http://assetmapper.fs.washington.edu/ada/uw.ada/buildlist.aspx';

    $html = file_get_contents($url);

    $doc = new DOMDocument();

    libxml_use_internal_errors(true);

    $doc->loadHTML($html);

    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    $building_name = get_the_title();
    $nodelist = $xpath->query("//a[contains(@title,'{$building_name}')]");

    if ($nodelist->length < 1 && $building == 'CHL') {
      $nodelist = $xpath->query("//a[contains(@title,'Chemistry Library')]");
    }

    if ($nodelist->length < 1 && $building == 'FSH') {
      $nodelist = $xpath->query("//a[contains(@title,'Fisheries Sciences')]");
    }

    if ($nodelist->length < 1 && $building == 'PAA') {
      $nodelist = $xpath->query("//a[contains(@title,'Physics/Astronomy Building')]");
    }

    if ($nodelist->length < 1)
      return false;

    $link = $nodelist->item(0);

    $access_url = $link->getAttribute('href');

    add_post_meta($post->ID, 'uw-access-url', $access_url, true);
  }

  return '<h3 class="access-link"><a href="' . $access_url . '" target="_blank">Accessibility*</a></h3>';
}


function get_album_link()
{
  global $post;

  if ( !($album_url = get_post_meta($post->ID, 'uw-album-url', true)) ) {

    if ( !$building = get_post_meta($post->ID, 'uw-building-code', true) )
      return false;

    if ( !$room = get_post_meta($post->ID, 'uw-room-number', true) )
      return false;

    $album_url = 'http://www.flickr.com/photos/52503205@N07/tags/' . $building . ' ' . $room;

  }

  add_post_meta($post->ID, 'uw-album-url', $album_url, true);

  return '<h3 class="album-link"><a href="' . $album_url . '">Photo Album</a></h3>';
}


function get_instructions_link()
{
  $pdfs = get_attached_media('application/pdf');

  foreach ($pdfs as $pdf) {
    if (stristr($pdf->post_name, 'instructions')) {
      return '<h3 class="instructions-link">' . wp_get_attachment_link($pdf->ID, null, false, false, 'Room Instructions') . '</h3>';
    }
  }
}


function get_schematic_link()
{
  $pdfs = get_attached_media('application/pdf');

  foreach ($pdfs as $pdf) {
    if (stristr($pdf->post_name, 'schematic')) {
      return '<div class="schematic-link">' . wp_get_attachment_link($pdf->ID, 'medium') . '</div>';
    }
  }
}


add_filter('the_content', 'uw_classrooms_building_content');
function uw_classrooms_building_content($content)
{
  global $post;

  $building_show_capacity = false; // Need accurate numbers

  if ( !has_term('building', 'location-type') )
    return $content;

  if ( !$building = get_post_meta($post->ID, 'uw-building-code', true) )
      return $content;

  $content .= get_map_link();

  $content .= get_access_link();

  $room_list = get_pages(array('child_of' => $post->ID));

  $content .= '
      <table>
        <tr>
          <th>Room </th>';
  if ($building_show_capacity)
    $content .= '
          <th>Capacity </th>';
  $content .= '
          <th>Room Type </th>
        </tr>';

  foreach ( $room_list as $page ) {
    $room_number = get_post_meta($page->ID, 'uw-room-number', true);
    $room_capacity = get_post_meta($page->ID, 'uw-room-capacity', true);
    $room_type = get_the_term_list($page->ID, 'location-type', '', ', ');
    $room_floor = substr($room_number, 0, 1);

    if (!isset($current_floor))
      $current_floor = $room_floor;

    if ( $room_floor != $current_floor ) {
      $current_floor = $room_floor;
      $content .= '
      </table>
      <br />
      <table>
        <tr>
          <th>Room </th>';
      if ($building_show_capacity)
	$content .= '
          <th>Capacity </th>';
      $content .= '
          <th>Room Type </th>
          <th> </th>
        </tr>';

    }

    $content .= '
        <tr>
          <td>
            <a href="' . get_permalink($page->ID) . '">' . get_the_title($page->ID) . '</a>
          </td>';
    if ($building_show_capacity)
      $content .= '
          <td>' . $room_capacity . '</td>';
    $content .= '
          <td>' . $room_type . '</td>
          <td>' . $room_notes . '</td>
        </tr>';

  }

  $content .= '
      </table>
      <br />
      <p><a name="external link">*</a> - External links are not maintained by CTE and the URLs may change or stop working without notice.</p>';

  return $content;
}


add_filter('the_content', 'uw_classrooms_room_content');
function uw_classrooms_room_content($content)
{
  global $post;

  if ( !has_term('classroom', 'location-type') )
    return $content;

  if ( !$building = get_post_meta($post->ID, 'uw-building-code', true) )
      return $content;

  if ( !$room = get_post_meta($post->ID, 'uw-room-number', true) )
      return $content;

  $content .= get_album_link();

  $content .= get_instructions_link();

  $content .= get_schematic_link();

  $content .= '<p><a href="http://www.cte.uw.edu/pdf/electkey.pdf">Key for electrical symbols</a></p>';

  $content .= file_get_contents("http://www.cte.uw.edu/room/$building+$room");

  return $content;
}


function get_page_by_name($pagename)
{
  $pages = get_pages();
  foreach ($pages as $page) {
    if ($page->post_name == sanitize_title($name)) {
      return $page;
    }
  }

  return false;
}


register_activation_hook(__FILE__, 'update_classrooms');
function update_classrooms() {
  $buildings_import = json_decode(file_get_contents("http://www.cte.uw.edu/buildings?json"), true);

  foreach ($buildings_import as $code => $building) {

    $building_import = json_decode(file_get_contents("http://www.cte.uw.edu/building/$code?json"), true);

    $post = array(
		  'comment_status' => 'open',
		  'ping_status' =>  'closed',
		  'post_name' => $code,
		  'post_status' => 'publish',
		  'post_title' => $building['building_name'],
		  'post_type' => 'page',
		  'post_content' => '',
		  );

    if ($b = get_page_by_name($code)) {
      $post['ID'] = $b->ID;
    }

    $building_id = wp_insert_post($post, false);

    wp_set_object_terms($building_id, 'Building', 'location-type');
    update_post_meta($building_id, 'uw-building-code', $code);


    foreach ($building_import['room_list'] as $codenum => $room) {

#      $room_import = json_decode(file_get_contents("http://www.cte.uw.edu/room/$codenum?json"), true);

      $roomname = "{$building['building_name']} {$room['room_number']}";
      if ($room['room_name'])
	$roomname .= " ({$room['room_name']})";

      $post = array(
		    'comment_status' => 'open',
		    'ping_status' =>  'closed',
		    'post_name' => $codenum,
		    'post_status' => 'publish',
		    'post_title' => $roomname,
		    'post_type' => 'page',
		    'post_content' => $room['room_notes'],
		    'post_parent' => $building_id,
		    );

      if ($r = get_page_by_name($codenum))
        $post['ID'] = $r->ID;

      $room_id = wp_insert_post($post, false);

      $types = array('classroom');
      if ($room['room_type'] != 'Classroom')
	$types[] = $room['room_type'];

#      print_r($types);
      wp_set_object_terms($room_id, $types, 'location-type');

      update_post_meta($room_id, 'uw-building-code', $room['building_code']);
      update_post_meta($room_id, 'uw-room-number', $room['room_number']);
      update_post_meta($room_id, 'uw-room-name', $room['room_name']);
      update_post_meta($room_id, 'uw-room-capacity', $room['room_capacity']);

    }

  }
}
