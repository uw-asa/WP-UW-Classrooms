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
require 'location-attributes.php';


add_filter('pigen_filter_convert_imageMagick', 'uw_classrooms_pigen_filter_convert_imageMagick', 10, 3);
function uw_classrooms_pigen_filter_convert_imageMagick($imageMagick, $old, $new)
{
  # trim extra whitespace around schematics
  $imageMagick .= "; mogrify -trim {$new}";
  return $imageMagick;
}


add_filter('uw_campus_map_buildingcode', 'do_shortcode');

add_shortcode('location', 'location_handler');
function location_handler($atts)
{
  $atts = shortcode_atts(array('field' => null), $atts);

  if ( !$atts['field'] )
    return '';

  if ( !($data = get_post_meta(get_the_ID(), 'uw-location-data', true)) )
    return '';

  if ( !isset($data[$atts['field']]) )
    return '';

  return $data[$atts['field']];
}


#enable shortcode in widget text
add_filter( 'widget_text', 'shortcode_unautop' );
add_filter( 'widget_text', 'do_shortcode' );


add_action('init', 'uw_classrooms_init');
function uw_classrooms_init()
{
  global $uw_snclient, $uw_classrooms_options;

  $uw_classrooms_options = get_option('uw_classrooms_options');

  register_taxonomy('location-type', 'page', array(
						   'label' => 'Location Type',
						   'hierarchical' => true,
						   ));

  register_taxonomy('document-type', 'attachment', array(
							 'label' => 'Document Types',
							 'hierarchical' => false,
							 ));

  $uw_snclient = new UW_ServiceNowClient(array(
					       'base_url' => $uw_classrooms_options['servicenow_url'],
					       'username' => $uw_classrooms_options['servicenow_user'],
					       'password' => $uw_classrooms_options['servicenow_pass'],
					       ));

  wp_enqueue_style('uw-classrooms', plugin_dir_url(__FILE__) . 'style.css');
}


function init_building_page($sn_building)
{
  $existing_page = null;
  $pages = get_pages(array('meta_key' => 'uw-location-sys-id', 'meta_value' => $sn_building['sys_id'], 'hierarchical' => false));
  if (count($pages))
    $existing_page = $pages[0];

  $building_page = array(
			 'comment_status' => 'open',
			 'ping_status' =>  'closed',
			 'post_name' => $sn_building['u_fac_code'],
			 'post_status' => 'publish',
			 'post_title' => $sn_building['u_long_name'],
			 'post_type' => 'page',
			 'post_content' => "[classrooms]\n\n* - External links are not maintained by CTE and the URLs may change or stop working without notice.\n",
			 );

  if ($existing_page)
    $building_page['ID'] = $existing_page->ID;

  $id = wp_insert_post($building_page, false);

  wp_set_object_terms($id, 'Building', 'location-type');
  update_post_meta($id, 'uw-location-sys-id', $sn_building['sys_id']);
  update_post_meta($id, 'uw-location-data', $sn_building);

  return get_post($id);
}


function init_room_page($sn_room)
{
  $existing_page = null;
  $pages = get_pages(array('meta_key' => 'uw-location-sys-id', 'meta_value' => $sn_room['sys_id'], 'hierarchical' => false));
  if (count($pages))
    $existing_page = $pages[0];

  $pages = get_pages(array('meta_key' => 'uw-location-sys-id', 'meta_value' => $sn_room['parent'], 'hierarchical' => false));
  $building_page = $pages[0];

  $room_page = array(
		     'comment_status' => 'open',
		     'ping_status' =>  'closed',
		     'post_name' => $sn_room['name'],
		     'post_status' => 'publish',
		     'post_title' => "{$building_page->post_title} {$sn_room['u_room_number']}",
		     'post_type' => 'page',
		     'post_parent' => $building_page->ID,
		     'post_content' => "[photoalbum]\n\n[instructions]\n\n[assets]\n\n[attributes]\n",
		     );

  if ($existing_page)
    $room_page['ID'] = $existing_page->ID;

  $id = wp_insert_post($room_page, false);

  wp_set_object_terms($id, 'Classroom', 'location-type');
  update_post_meta($id, 'uw-location-sys-id', $sn_room['sys_id']);
  update_post_meta($id, 'uw-location-data', $sn_room);

  return get_post($id);
}


register_activation_hook(__FILE__, 'uw_classrooms_activate');
function uw_classrooms_activate()
{
  global $uw_snclient;

  uw_classrooms_init();

  # init location hierarchy
  if ( !($term = term_exists('Building', 'location-type')) )
    $term = wp_insert_term('Building', 'location-type');
  $building_term_id = intval($term['term_id']);

  if ( !($term = term_exists('Classroom', 'location-type')) )
    $term = wp_insert_term('Classroom', 'location-type');
  $classroom_term_id = intval($term['term_id']);

  foreach (array('Auditorium', 'Breakout Room', 'Case Study Classroom', 'Computer Classroom', 'Seminar Room') as $type)
    if ( !term_exists($type, 'location-type', $classroom_term_id) )
      wp_insert_term($type, 'location-type', array('parent' => $classroom_term_id));

  # init attribute hierarchy
  foreach (array('Furnishings', 'Dimensions', 'Accessibility', 'Instructor Area', 'Student Seating') as $section)
    if ( !term_exists($section, 'location-attributes') )
      wp_insert_term($section, 'location-attributes');

  # init document types
  foreach (array('Instructions', 'Schematic') as $type)
    if ( !term_exists($type, 'document-type') )
      wp_insert_term($type, 'document-type');

  # init front and home pages
  foreach (get_pages(array('hierarchical' => false)) as $page) {
    if ($page->post_name == 'classrooms')
      $front_page_id = $page->ID;
    elseif ($page->post_name == 'updates')
      $home_page_id = $page->ID;
  }

  $page = array(
		'comment_status' => 'open',
		'ping_status' =>  'closed',
		'post_name' => 'Classrooms',
		'post_status' => 'publish',
		'post_title' => 'Classrooms',
		'post_type' => 'page',
		'post_content' => "[buildings]\n",
		);
  if ($front_page_id)
    $page['ID'] = $front_page_id;

  $front_page_id = wp_insert_post($page, false);
  update_option('show_on_front', 'page');
  update_option('page_on_front', $front_page_id);

  $page = array(
		'comment_status' => 'open',
		'ping_status' =>  'closed',
		'post_name' => 'Updates',
		'post_status' => 'publish',
		'post_title' => 'Updates',
		'post_type' => 'page',
		);
  if ($home_page_id)
    $page['ID'] = $home_page_id;

  $home_page_id = wp_insert_post($page, false);
  update_option('page_for_posts', $home_page_id);

  # init building and room pages
  $result = json_decode($uw_snclient->get_records('cmn_location', "u_cte_managed_room=true"), true);
  $sn_rooms = $result['records'];

  $buildings = array();
  foreach ($sn_rooms as $sn_room)
    $buildings[$sn_room['parent']] = $sn_room['parent'];

  foreach ($buildings as $sys_id) {
    $result = json_decode($uw_snclient->get('cmn_location', $sys_id), true);
    $sn_building = $result['records'][0];
    
    init_building_page($sn_building);
  }

  foreach ($sn_rooms as $sn_room)
    init_room_page($sn_room);

  $widget_conditions_main = array('action' => 'show',
				  'rules' => array(0 => array('major' => 'page', 'minor' => 'front'),
						   1 => array('major' => 'page', 'minor' => 'post_type-post')));
  $widget_conditions_building = array('action' => 'show',
				      'rules' => array(0 => array('major' => 'taxonomy',
								  'minor' => 'location-type_tax_' . $building_term_id)));
  $widget_conditions_classroom = array('action' => 'show',
				      'rules' => array(0 => array('major' => 'taxonomy',
								  'minor' => 'location-type_tax_' . $classroom_term_id)));
  # init widgets
  update_option( 'widget_uw-recent', array(2 => array('title' => 'Updates', 'items' => 5, 'more' => false, 'conditions' => $widget_conditions_main), '_multiwidget' => 1) );
  update_option( 'widget_archives', array(2 => array('title' => '', 'count' => 0, 'dropdown' => 0, 'conditions' => $widget_conditions_main), '_multiwidget' => 1) );
  update_option( 'widget_categories', array(2 => array('title' => '', 'count' => 0, 'hierarchical' => 0, 'dropdown' => 0, 'conditions' => $widget_conditions_main), '_multiwidget' => 1) );
  update_option( 'widget_uw-campus-map', array ( 2 => array ( 'title' => 'Map', 'buildingCode' => '[location field=u_fac_code]', 'hierarchical' => 0, 'dropdown' => 0, 'conditions' => $widget_conditions_building), '_multiwidget' => 1) );
  update_option( 'widget_text', array(2 => array('title' => '', 'text' => "[accessibility]", 'filter' => false, 'conditions' => $widget_conditions_building),
				      3 => array('title' => 'Schematic', 'text' => "[schematic]\n\n<p><a href=\"http://www.cte.uw.edu/pdf/electkey.pdf\">Key for electrical symbols</a></p>", 'filter' => false, 'conditions' => $widget_conditions_classroom), '_multiwidget' => 1) );
  update_option( 'sidebars_widgets', array('wp_inactive_widgets' => array(), 'sidebar' => array ( 0 => 'uw-recent-2', 1 => 'archives-2', 2 => 'categories-2', 3 => 'uw-campus-map-2', 4 => 'text-2', 5 => 'text-3'), 'array_version' => 3) );

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


function get_location_data($post = null, $force = false)
{
  global $uw_snclient;

  if ( !($post instanceof WP_Post) )
    $post = get_post($post);

  if ( !$force &&
       ($location_data = get_post_meta($post->ID, 'uw-location-data', true)) )
    return $location_data;

  if ( !$location_sys_id = get_post_meta($post->ID, 'uw-location-sys-id', true) )
    return false;

  $result = json_decode($uw_snclient->get('cmn_location', $location_sys_id), true);
  $location_data = $result['records'][0];

  update_post_meta($post->ID, 'uw-location-data', $location_data);
}


function get_location_meta($id, $field)
{
  if ( ! ($data = get_post_meta($id, 'uw-location-data', true)) )
    return false;

  if ( !isset($data[$field]) )
    return false;

  return $data[$field];
}


function get_location_assets($post = null, $force = false)
{
  global $uw_snclient;

  if ( !($post instanceof WP_Post) )
    $post = get_post($post);

  if ( !$force &&
       ($location_assets = get_post_meta($post->ID, 'uw-location-assets', true)) )
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

  update_post_meta($post->ID, 'uw-location-assets', $location_assets);

  return $location_assets;
}


add_shortcode('assets', 'get_location_asset_list');
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


require_once(ABSPATH . 'wp-admin/includes/image.php');

function import_attachments($room_import)
{
  global $post;

  $uploaddir = wp_upload_dir();

  foreach (array('instructions', 'schematic') as $type) {

    if (!isset($room_import["{$type}_url"]))
      continue;

    $url = $room_import["{$type}_url"];

    $filename = basename(parse_url($url, PHP_URL_PATH));

    $uploadfile = $uploaddir['path'] . '/' . $filename;

    $contents= file_get_contents($url);
    $savefile = fopen($uploadfile, 'w');
    fwrite($savefile, $contents);
    fclose($savefile);

    $wp_filetype = wp_check_filetype(basename($filename), null);

    $attachment = array(
      'post_mime_type' => $wp_filetype['type'],
      'post_title' => $filename,
      'post_content' => '',
      'post_status' => 'inherit'
    );

    $attach_id = wp_insert_attachment( $attachment, $uploadfile, $post->ID );
    wp_set_object_terms($attach_id, $type, 'document-type');

    $imagenew = get_post( $attach_id );
    $fullsizepath = get_attached_file( $imagenew->ID );
    $attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
    wp_update_attachment_metadata( $attach_id, $attach_data );
  }

}


function get_location_attributes($post = null, $force = false)
{
  if ( !($post instanceof WP_Post) )
    $post = get_post($post);

  $codenum = get_location_meta($post->ID, 'name');

  if ( !$force &&
       ($location_attribute_meta = get_post_meta($post->ID, 'uw-location-attributes', true)) )
    return;

  $room_import = json_decode(file_get_contents("http://www.cte.uw.edu/room/" . urlencode($codenum) . "?json"), true);

  wp_set_object_terms($post->ID, NULL, 'location-attributes');
  wp_set_object_terms($post->ID, NULL, 'location-type');

  import_attachments($room_import);

  if ($term = term_exists('Classroom', 'location-type'))
    $classroom_term_id = intval($term['id']);

  if ( !$term = term_exists($room_import['room_type'], 'location-type') )
    $term = wp_insert_term($room_import['room_type'], 'location-type', array('parent' => $classroom_term_id));
  wp_set_object_terms($post->ID, intval($term['term_id']), 'location-type', true);
  add_all_parent_terms('location-type', $post->ID);

  foreach ($room_import['attribute_list'] as $section => $attributes) {
    $section = trim($section);

    if ( !($term = term_exists($section, 'location-attributes') ) )
      continue;

    $section_id = intval($term['term_id']);

    wp_set_object_terms($post->ID, $section_id, 'location-attributes', true);
    foreach ($attributes as $attribute => $properties) {
      $attribute = trim($attribute);
      if (empty($attribute))
        continue;
      if ($attr = term_exists($attribute, 'location-attributes'))
        wp_update_term($attr['term_id'], 'location-attributes', array('parent' => $section_id));
      else
        if (is_wp_error($attr = wp_insert_term($attribute,
          'location-attributes', array('parent' => $section_id))))
          die(__FILE__ . ":" . __LINE__ . ' ' . $attr->get_error_message());
      wp_set_object_terms($post->ID, intval($attr['term_id']), 'location-attributes', true);

      foreach ($properties as $property => $value)
          $location_attribute_meta[$attr['term_id']][$property] = $properties[$property];
    }
  }

  if (count($location_attribute_meta))
    update_post_meta($post->ID, 'uw-location-attributes', $location_attribute_meta);
}


add_shortcode('accessibility', 'get_access_link');
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


add_shortcode('photoalbum', 'get_album_link');
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


function get_attached_document($document_type)
{
  $posts = get_posts(array(
			   'post_type' => 'attachment',
			   'post_parent' => get_the_ID(),
			   'numberposts' => 1,
			   'tax_query' => array(array(
						      'taxonomy' => 'document-type',
						      'field' => 'slug',
						      'terms' => $document_type,
						      'include_children' => false,
						      ))
			   ));

  if (!count($posts))
    return false;

  return $posts[0];
}


add_shortcode('instructions', 'get_instructions_link');
function get_instructions_link()
{
  if ( ($instructions = get_attached_document('instructions')) )
    return '<h3 class="instructions-link">' .
      wp_get_attachment_link($instructions->ID, null, false, false, 'Room Instructions') .
      '</h3>';
}


add_shortcode('schematic', 'get_schematic_link');
function get_schematic_link()
{
  if ( ($schematic = get_attached_document('schematic')) )
    return '<div class="schematic-link">' .
      wp_get_attachment_link($schematic->ID, array(320, 480)) .
      '</div>';
}


class Walker_Page_FirstLetters extends Walker_Page {
  public function __construct(){
    $this->current_letter = null;
  }

  public function start_el( &$output, $page, $depth = 0, $args = array(), $current_page = 0 ) {
    $letter = substr($page->post_title, 0, 1);
    if ($letter != $this->current_letter) {
      $cb_args = array_merge( array(&$output, $depth), $args);
      call_user_func_array(array($this, 'end_lvl'), $cb_args);
      call_user_func_array(array($this, 'start_lvl'), $cb_args);
      $this->current_letter = $letter;
    }
    parent::start_el($output, $page, $depth, $args, $current_page);
  }
}


add_shortcode('buildings', 'buildings_handler');
function buildings_handler()
{
  $walker = new Walker_Page_FirstLetters;

  $building_list = get_posts(array(
				   'post_type' => 'page',
				   'numberposts' => -1,
				   'orderby' => 'post_title',
				   'tax_query' => array(
							array(
							      'taxonomy' => 'location-type',
							      'field' => 'slug',
							      'terms' => 'building',
							      'include_children' => false,
							      )
							)
				   ));

  echo '<div class="buildings"><ul>';
  wp_list_pages(array('include' => array_map(function($page) { return $page->ID; }, $building_list),
		      'title_li' => '',
		      'walker' => $walker,
		      ));
  echo '</ul></div>';
}


add_shortcode('classrooms', 'uw_classrooms_building_content');
function uw_classrooms_building_content()
{
  global $post;

  $building_show_capacity = false; // Need accurate numbers

  if ( !has_term('building', 'location-type') )
    return $content;

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
          <th> </th>
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
      </table>';

  return $content;
}


add_action('save_post_page', 'action_save_post_page', 10, 2);
function action_save_post_page($post_id, $post) {
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
    return $post_id;

  if ( ! current_user_can( 'edit_page', $post_id ) )
    return $post_id;

  add_all_parent_terms('location-type', $post_id);

  if ( isset($_POST['uw_location_refresh_nonce']) &&
       wp_verify_nonce($_POST['uw_location_refresh_nonce'], 'uw_location_refresh_action') &&
       isset($_POST['uw_location_refresh']) ) {
    switch ($_POST['uw_location_refresh']) {
    case 'data':
      get_location_data($post_id, true);
      break;
    case 'assets':
      get_location_assets($post_id, true);
      break;
    case 'attributes':
      get_location_attributes($post_id, true);
      break;
    }
  }
}


function add_all_parent_terms($taxonomy, $post_id) {
  $terms = wp_get_post_terms($post_id, $taxonomy);
  foreach($terms as $term){
    while( $term->parent != 0 && !has_term($term->parent, $taxonomy, $post) ) {
      // move upward until we get to 0 level terms
      wp_set_post_terms($post_id, array($term->parent), $taxonomy, true);
      $term = get_term($term->parent, $taxonomy);
    }
  }
}


add_action('add_meta_boxes_page', 'action_add_meta_boxes_page');
function action_add_meta_boxes_page() {
  add_meta_box('uw-location-refresh',
	       "Location Metadata",
	       function($post) {
		 // Add a nonce field so we can check for it later.
		 wp_nonce_field('uw_location_refresh_action', 'uw_location_refresh_nonce');

		 echo '<pre>' . json_encode(get_post_meta($post->ID, 'uw-location-data', true), JSON_PRETTY_PRINT) . '</pre>';
		 echo '<button id="uw_location_refresh_data" name="uw_location_refresh" value="data"><span>Refresh Location Data</span></button>';

		 echo '<pre>' . json_encode(get_post_meta($post->ID, 'uw-location-assets', true), JSON_PRETTY_PRINT) . '</pre>';
		 echo '<button id="uw_location_refresh_assets" name="uw_location_refresh" value="assets"><span>Refresh Location Assets</span></button>';

		 echo '<button id="uw_location_refresh_attributes" name="uw_location_refresh" value="attributes"><span>Re-import Location Attributes</span></button>';
	       });
}
