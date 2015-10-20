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


add_action('admin_menu', 'uw_classrooms_menu');
function uw_classrooms_menu() {
  add_options_page('UW Classrooms Options', 'UW Classrooms', 'manage_options', 'uw-classrooms-options', 'uw_classrooms_options' );
}


function uw_classrooms_options() {
  $hidden_field_name = 'uw_submit_hidden';

  // variables for the field and option names
  $url = 'uw_SN_URL';
  $data_url = 'uw_SN_URL';
  $user = 'uw_SN_USER';
  $data_user = 'uw_SN_USER';
  $pass = 'uw_SN_PASS';
  $data_pass = 'uw_SN_PASS';

  // Read in existing option value from database
  $url_val = get_option( $url );
  $user_val = get_option( $user );
  $pass_val = get_option( $pass );

  // See if the user has posted us some information
  // If they did, this hidden field will be set to 'Y'
  if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) { 
    // Read their posted value
    $url_val = $_POST[ $data_url ];
    $user_val = $_POST[ $data_user ];
    $pass_val = $_POST[ $data_pass ];

    // Save the posted value in the database
    update_option( $url, $url_val );
    update_option( $user, $user_val );
    update_option( $pass, $pass_val );

    flush_rewrite_rules();
?>
      <div class="updated"><p><strong><?php _e('settings saved.', 'menu' ); ?></strong></p></div>
<?php
										  }
  // Now display the settings editing screen
  echo '<div class="wrap">';
  // header
  echo "<h2>" . __( 'UW Classrooms Plugin Settings', 'menu' ) . "</h2>";
  // settings form
  ?>

<form name="form1" method="post" action="">
<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

   <p><?php _e("ServiceNow URL:", 'menu' ); ?>
<input type="text" name="<?php echo $data_url; ?>" value="<?php echo $url_val; ?>" size="20">
</p><hr />

   <p><?php _e("ServiceNow User:", 'menu' ); ?>
<input type="text" name="<?php echo $data_user; ?>" value="<?php echo $user_val; ?>" size="20">
</p><hr />

   <p><?php _e("ServiceNow Pass:", 'menu' ); ?>
<input type="password" name="<?php echo $data_pass; ?>" value="<?php echo $pass_val; ?>" size="20">

</p><hr /><p class="submit">
<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
</p>

</form>
</div>
<?php
}



function get_location_sys_id()
{
  global $post, $uw_snclient;

  if ( $location_sys_id = get_post_meta($post->ID, 'uw-location-sys-id', true) )
    return $location_sys_id;

  if ( !$building = get_post_meta($post->ID, 'uw-building-code', true) )
    return false;

  if ( !$room = get_post_meta($post->ID, 'uw-room-number', true) )
    return false;

  $result = json_decode($uw_snclient->get_records('cmn_location', "parent.u_fac_code={$building}^u_room_number={$room}"), true);

  if (count($result['records']) != 1)
    return false;

  $location_sys_id = $result['records'][0]['sys_id'];

  add_post_meta($post->ID, 'uw-location-sys-id', $location_sys_id, true);

  return $location_sys_id;
}


function get_location_assets()
{
  global $post, $uw_snclient;

  if ( $location_assets = get_post_meta($post->ID, 'uw-location-assets', true) )
    return $location_assets;

  if ( !$location_sys_id = get_location_sys_id())
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

  if ( !$building = get_post_meta($post->ID, 'uw-building-code', true) )
    return false;

  if ( !$room = get_post_meta($post->ID, 'uw-room-number', true) )
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

  $content .= get_location_asset_list();

  $content .= '<p><a href="http://www.cte.uw.edu/pdf/electkey.pdf">Key for electrical symbols</a></p>';

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


register_activation_hook(__FILE__, 'update_classrooms');
function update_classrooms() {

  uw_classrooms_init();

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
      $new_room = get_post($room_id);

      if ($post['ID'] && $room_id != $post['ID'])
	die("duplicate room $room_id? : ". print_r($post, true));

      if ($new_room->post_name != sanitize_title($codenum))
	die("incorrect room post_name {$new_room->post_name}? : " . print_r($post, true));

      $types = array('Classroom');
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
