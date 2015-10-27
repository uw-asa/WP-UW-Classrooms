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

require 'class.uw-servicenowclient.php';


add_filter('pigen_filter_convert_imageMagick', 'uw_classrooms_pigen_filter_convert_imageMagick', 10, 3);
function uw_classrooms_pigen_filter_convert_imageMagick($imageMagick, $old, $new)
{
  # trim extra whitespace around schematics
  $imageMagick .= "; mogrify -trim {$new}";
  return $imageMagick;
}


add_filter('uw_campus_map_buildingcode', 'uw_classrooms_uw_campus_map_buildingcode');
function uw_classrooms_uw_campus_map_buildingcode($buildingCode)
{
  global $post;

  if ($code = get_location_meta($post->ID, 'u_fac_code'))
    return $code;
    
  return $buildingCode;
}


add_filter('widget_text', 'uw_classrooms_widget_text_schematic_image', 10, 2);
function uw_classrooms_widget_text_schematic_image($text, $instance)
{
  if ( $instance['title'] == 'Schematic' && ($link = get_schematic_link()) )
    return $link . '<p><a href="http://www.cte.uw.edu/pdf/electkey.pdf">Key for electrical symbols</a></p>';

  return $text;
}


add_action('init', 'uw_classrooms_init');
function uw_classrooms_init()
{
  global $uw_snclient, $uw_classrooms_options;

  $uw_classrooms_options = get_option('uw_classrooms_options');

  register_taxonomy('location-type', 'page', array(
						   'label' => 'Location Type',
						   'hierarchical' => true,
						   ));

  register_taxonomy('location-attributes', 'page', array(
							 'label' => 'Attributes',
							 'hierarchical' => true,
							 ));

  $uw_snclient = new UW_ServiceNowClient(array(
					       'base_url' => $uw_classrooms_options['servicenow_url'],
					       'username' => $uw_classrooms_options['servicenow_user'],
					       'password' => $uw_classrooms_options['servicenow_pass'],
					       ));

  wp_enqueue_style( 'uw-classrooms', plugins_url('uw-classrooms/style.css'));
}


function init_building_page($sn_building)
{
  $building_page = array(
			 'comment_status' => 'open',
			 'ping_status' =>  'closed',
			 'post_name' => $sn_building['u_fac_code'],
			 'post_status' => 'publish',
			 'post_title' => $sn_building['u_long_name'],
			 'post_type' => 'page',
			 );

  $id = wp_insert_post($building_page, false);

  wp_set_object_terms($id, 'Building', 'location-type');
  update_post_meta($id, 'uw-location-sys-id', $sn_building['sys_id']);
  update_post_meta($id, 'uw-location-data', $sn_building);

  $building_page = get_post($id);

  return $building_page;
}


function init_room_page($sn_room)
{
  global $uw_snclient;

  $pages = get_pages(array('meta_key' => 'uw-location-sys-id', 'meta_value' => $sn_room['parent']));
  if (count($pages))
    $building_page = $pages[0];

  else {
    $result = json_decode($uw_snclient->get('cmn_location', $sn_room['parent']), true);
    $sn_building = $result['records'][0];

    $building_page = init_building_page($sn_building);
  }

  $room_page = array(
		     'comment_status' => 'open',
		     'ping_status' =>  'closed',
		     'post_name' => $sn_room['name'],
		     'post_status' => 'publish',
		     'post_title' => "{$building_page->post_title} {$sn_room['u_room_number']}", #$sn_room['u_long_name']
		     'post_type' => 'page',
		     'post_parent' => $building_page->ID,
		     );

  $id = wp_insert_post($room_page, false);

  wp_set_object_terms($id, 'Classroom', 'location-type');
  update_post_meta($id, 'uw-location-sys-id', $sn_room['sys_id']);
  update_post_meta($id, 'uw-location-data', $sn_room);

  return $room_page;
}


register_activation_hook(__FILE__, 'uw_classrooms_activate');
function uw_classrooms_activate()
{
  global $uw_snclient;

  uw_classrooms_init();

  # init attribute hierarchy
  foreach (array('Furnishings', 'Dimensions', 'Accessibility', 'Instructor Area', 'Student Seating') as $section)
    if ( !term_exists($section, 'location-attributes') )
      wp_insert_term($section, 'location-attributes');

  foreach (get_pages() as $page)
    wp_delete_post($page->ID, true);

  $index_page = array(
		     'comment_status' => 'open',
		     'ping_status' =>  'closed',
		     'post_name' => 'Classrooms',
		     'post_status' => 'publish',
		     'post_title' => 'Classrooms',
		     'post_type' => 'page',
		     );

  $id = wp_insert_post($index_page, false);
  update_option('show_on_front', 'page');
  update_option('page_on_front', $id);

  # init room pages
  $result = json_decode($uw_snclient->get_records('cmn_location', "u_cte_managed_room=true"), true);
  $sn_rooms = $result['records'];

  foreach ($sn_rooms as $sn_room) {
    $pages = get_pages(array('meta_key' => 'uw-location-sys-id', 'meta_value' => $sn_room['sys_id']));

    if (!count($pages))
      init_room_page($sn_room);
  }

}


add_action('admin_menu', 'uw_classrooms_admin_menu');
function uw_classrooms_admin_menu()
{
  add_options_page('UW Classrooms Options', 'UW Classrooms', 'manage_options', __FILE__, 'uw_classrooms_options' );
}
function uw_classrooms_options()
{

?>
<div class="wrap">
  <h2>UW Classrooms Settings</h2>
  <form method="post" action="options.php">
    <?php settings_fields( 'uw_classrooms_options' ); ?>
    <?php do_settings_sections( __FILE__ ); ?>
    <?php submit_button(); ?>
  </form>
</div>
<?php

}


add_action( 'admin_init', 'uw_classrooms_admin_init' );
function uw_classrooms_admin_init()
{
  register_setting('uw_classrooms_options', 'uw_classrooms_options');

  add_settings_section('uw_classrooms_servicenow', 'UW Connect Settings', 'uw_classrooms_servicenow_settings', __FILE__);
  add_settings_field('servicenow_url', 'URL', 'uw_classrooms_setting_text',
		     __FILE__, 'uw_classrooms_servicenow',
		     array('label_for' => 'servicenow_url'));
  add_settings_field('servicenow_user', 'Username', 'uw_classrooms_setting_text',
		     __FILE__, 'uw_classrooms_servicenow',
		     array('label_for' => 'servicenow_user'));
  add_settings_field('servicenow_pass', 'Password', 'uw_classrooms_setting_password',
		     __FILE__, 'uw_classrooms_servicenow',
		     array('label_for' => 'servicenow_pass'));
}
function uw_classrooms_servicenow_settings()
{
}
function uw_classrooms_setting_text($args)
{
  global $uw_classrooms_options;

?>
<input type="text" name="uw_classrooms_options[<?= $args['label_for'] ?>]" value="<?= $uw_classrooms_options[$args['label_for']] ?>" />
<?php

}
function uw_classrooms_setting_password($args)
{
  global $uw_classrooms_options;

?>
<input type="password" name="uw_classrooms_options[<?= $args['label_for'] ?>]" value="<?= $uw_classrooms_options[$args['label_for']] ?>" />
<?php

}


function get_location_meta($id, $field)
{
  if ( ! ($data = get_post_meta($id, 'uw-location-data', true)) )
    return false;

  if ( !isset($data[$field]) )
    return false;

  return $data[$field];
}


function get_location_assets()
{
  global $post, $uw_snclient;

  if ( $location_assets = get_post_meta($post->ID, 'uw-location-assets', true) )
    return $location_assets;

  if ( !$location_sys_id = get_post_meta($post->ID, 'uw-location-sys-id', true) )
    return false;

  $result = json_decode($uw_snclient->get_records('alm_hardware', "location={$location_sys_id}^install_status=1^u_publish=true"), true);

  if (count($result['records']) < 1)
    return false;

  //Hack to establish order of asset sections
  $assets['Control System'][0] = '';
  $assets['Equipment'][0] = '';
  $assets['Conferencing Unit'][0] = '';
  $assets['Lecture Capture'][0] = '';

  foreach ($result['records'] as $record) {
    //Pull in model info
    $result = json_decode($uw_snclient->get('cmdb_model', $record['model']), true);

    $record['model'] = $result['records'][0];

    if ( !$record['u_short_description'] ) {
      $record['u_short_description'] = $record['model']['short_description'];
    }

    if ( $record['model']['short_description'] == 'Conferencing Unit' ) {
      $record['Type'] = $record['model']['short_description'];
    }

    if ( $record['u_short_description'] == 'Automated Panopto Recorder' ||
	 $record['u_short_description'] == 'Audio/Video Bridge' ) {
      $record['Type'] = 'Lecture Capture';
    }

    if ( !$record['Type'] ) {
      $record['Type'] = $record['model']['u_sub_category'];
    }

    if ( !$record['Type'] ) {
      $record['Type'] = $record['dv_model_category'];
    }


    if ($record['Type'] == 'Control System'
      || $record['Type'] == 'Conferencing Unit'
	|| $record['Type'] == 'Lecture Capture') {
      $assets[$record['Type']][$record['u_number']] = $record;
    }
    else {
      $assets['Equipment'][$record['u_number']] = $record;
    }
  }

  unset($assets['Control System'][0]);
  unset($assets['Equipment'][0]);
  unset($assets['Conferencing Unit'][0]);
  unset($assets['Lecture Capture'][0]);

  if (!count($assets))
    return false;

  foreach ($assets as $type => $items) {

    $equipment = array();
    $quantity = array();

    if (!count($items))
      continue;

    foreach($items as $item) {
      if ($item['u_short_description']) {
	$item['Name'] = $item['u_short_description'];
      } elseif ($item['model']['short_description']) {
	$item['Name'] = $item['model']['short_description'];
      } elseif ($item['dv_model']) {
	$item['Name'] = $item['dv_model'];
      } else {
	$item['Name'] = $item['display_name'];
      }
      $equipment[$item['Name']] = $item;
      if (!isset($quantity[$item['Name']]))
	$quantity[$item['Name']] = 0;

      $quantity[$item['Name']]++;
    }

    foreach($equipment as $row) {

      $location_assets[$type][$row['Name']]['type'] = $row['Type'];
      $location_assets[$type][$row['Name']]['quantity'] = $quantity[$row['Name']];

    }
  }

  add_post_meta($post->ID, 'uw-location-assets', $location_assets, true);

  return $location_assets;
}


function get_location_asset_list()
{
  global $post;

  if ( !$building = get_location_meta($post->ID, 'u_fac_code') )
    return false;

  if ( !$room = get_location_meta($post->ID, 'u_room_number') )
    return false;

  if ( !$location_assets = get_location_assets())
    return false;

  $content = '';
  foreach ($location_assets as $type => $items) {
    $content .= "<h3>{$type}</h3><ul>";
    foreach ($items as $item => $meta) {
      $content .= '<li><a href="http://www.cte.uw.edu/equipment/?room=' . urlencode("{$building} {$room}") . '&type=' . $meta['type'] . '">';
      if ($meta['quantity'] == 1)
	$content .= $item;
      else
	$content .= "{$meta['quantity']} {$item}s";
      $content .= '</a></li>';
    }
    $content .= "</ul>";
  }

  return $content;
}


function get_location_attributes_list()
{
  global $post;

  return
    '<ul class="location-attributes">' .
    wp_list_categories(array(
			     'echo' => false,
			     'taxonomy' => 'location-attributes',
			     'title_li' => '',
			     )) .
    '</ul>';
}


function get_access_link()
{
  global $post;

  if ( ! ($access_url = get_post_meta($post->ID, 'uw-access-url', true)) ) {

    if ( !$building = get_location_meta($post->ID, 'u_fac_code') )
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

    if ( !$building = get_location_meta($post->ID, 'u_fac_code') )
      return false;

    if ( !$room = get_location_meta($post->ID, 'u_room_number') )
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
    $room_number = get_location_meta($page->ID, 'u_room_number');
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

  if ( !$building = get_location_meta($post->ID, 'u_fac_code') )
      return $content;

  if ( !$room = get_location_meta($post->ID, 'u_room_number') )
      return $content;

  $content .= get_album_link();

  $content .= get_instructions_link();

  $content .= get_location_asset_list();

  $content .= get_location_attributes_list();

  $content .= file_get_contents("http://www.cte.uw.edu/room/$building+$room");

  return $content;
}


function get_page_by_name($pagename)
{
  $pages = get_pages();
  foreach ($pages as $page) {
    if ($page->post_name == sanitize_title($pagename)) {
      return $page;
    }
  }

  return false;
}





function import_classrooms()
{

  $path = plugin_dir_path(__FILE__);

#  $buildings_import = json_decode(file_get_contents("http://www.cte.uw.edu/buildings?json"), true);
#  foreach ($buildings_import as $code => $b) { $buildings_import[$code] = $b['building_name']; }
#  file_put_contents("{$path}initialdata/buildings.php", var_export($buildings_import, true));

  eval('$buildings_import = ' . file_get_contents("{$path}initialdata/buildings.php") . ';');

  foreach ($buildings_import as $code => $building_name) {

#    $building_import = json_decode(file_get_contents("http://www.cte.uw.edu/building/$code?json"), true);
#    file_put_contents("{$path}initialdata/{$code}.php", var_export($building_import, true));

    eval('$building_import = ' . file_get_contents("{$path}initialdata/{$code}.php") . ';');

    $post = array(
		  'comment_status' => 'open',
		  'ping_status' =>  'closed',
		  'post_name' => $code,
		  'post_status' => 'publish',
		  'post_title' => $building_name,
		  'post_type' => 'page',
		  'post_content' => '',
		  );

    if ($b = get_page_by_name($code))
      $post['ID'] = $b->ID;

    $building_id = wp_insert_post($post, false);
    $new_building = get_post($building_id);

    if ($post['ID'] && $building_id != $post['ID'])
      die("duplicate building $building_id? : ". print_r($post, true));

    if ($new_building->post_name != sanitize_title($code))
      die("incorrect building post_name {$new_building->post_name}? : " . print_r($post, true));

    wp_set_object_terms($building_id, 'Building', 'location-type');
    update_post_meta($building_id, 'uw-building-code', $code);


    foreach ($building_import['room_list'] as $codenum => $room) {

#      $room_import = json_decode(file_get_contents("http://www.cte.uw.edu/room/" . urlencode($codenum) . "?json"), true);
#      file_put_contents("{$path}initialdata/{$codenum}.php", var_export($room_import, true));

      eval('$room_import = ' . file_get_contents("{$path}initialdata/{$codenum}.php") . ';');

      $roomname = "{$building_name} {$room['room_number']}";
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
      $new_room = get_post($room_id);

      if ($post['ID'] && $room_id != $post['ID'])
	die("duplicate room $room_id? : ". print_r($post, true));

      if ($new_room->post_name != sanitize_title($codenum))
	die("incorrect room post_name {$new_room->post_name}? : " . print_r($post, true));

      $types = array('Classroom');
      if ($room['room_type'] != 'Classroom') {
	$types[] = $room['room_type'];
	$all_room_types[$room['room_type']] = true;
      }

#      print_r($types);
      wp_set_object_terms($room_id, $types, 'location-type');

      update_post_meta($room_id, 'uw-building-code', $room['building_code']);
      update_post_meta($room_id, 'uw-room-number', $room['room_number']);
      update_post_meta($room_id, 'uw-room-name', $room['room_name']);
      update_post_meta($room_id, 'uw-room-capacity', $room['room_capacity']);

      wp_set_object_terms($room_id, NULL, 'location-attributes');
      foreach ($room_import['attribute_list'] as $section => $attributes) {
	$section = trim($section);
	echo "section: $section\n";
	if ( !array_key_exists($section, $attr_sections) )
	  continue;
	wp_set_object_terms($room_id, intval($attr_sections[$section]['term_id']), 'location-attributes', true);
	foreach ($attributes as $attribute => $properties) {
	  $attribute = trim($attribute);
	  echo " $attribute\n";
	  if ( empty($attribute) )
	    continue;
	  if ( $attr = term_exists($attribute, 'location-attributes') )
	    wp_update_term($attr['term_id'], 'location-attributes', array('parent' => $attr_sections[$section]['term_id']));
	  else
	    if ( is_wp_error( $attr = wp_insert_term($attribute, 'location-attributes', array('parent' => $attr_sections[$section]['term_id'])) ) )
	      die(__FILE__.":".__LINE__.' '.$attr->get_error_message());
	  wp_set_object_terms($room_id, intval($attr['term_id']), 'location-attributes', true);
	}
      }

    }

  }


  // Organize term heirarchy

  $classroom = get_term_by('name', 'Classroom', 'location-type');

  foreach (array_keys($all_room_types) as $type) {
    $room_type = get_term_by('name', $type, 'location-type');
    wp_update_term($room_type->term_id, 'location-type', array('parent' => $classroom->term_id));
  }
}
