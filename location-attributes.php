<?php

class UW_Location_Attributes {
  
  function __construct() {
    add_action('init', array($this, 'init'));
    add_action('admin_init', array($this, 'admin_init'));
    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    add_shortcode('attributes', array($this, 'shortcode'));
    add_action('save_post', array($this, 'save_post'));
  }

  function init() {
    register_taxonomy('location-attributes', 'page', array(
      'label' => 'Location Attributes',
      'hierarchical' => true,
    ));
  }

  function admin_init() {
    wp_register_script('location-attributes',
      plugin_dir_url(__FILE__) . 'location-attributes.js',
      array('jquery'));
    wp_register_style('location-attributes',
      plugin_dir_url(__FILE__) . 'location-attributes.css');
  }

  function admin_enqueue_scripts($hook_suffix) {
    if ($hook_suffix != 'post.php')
      return;

    wp_enqueue_script('location-attributes');
    wp_enqueue_style('location-attributes');
  }

  function shortcode() {
    global $post;

    get_location_attributes();

    $post_terms = wp_get_object_terms( $post->ID, 'location-attributes', array( 'fields' => 'ids' ) );

    return
      '<ul class="location-attributes">' .
      wp_list_categories(array(
        'echo' => false,
        'taxonomy' => 'location-attributes',
        'title_li' => '',
        'include' => $post_terms,
        )) .
      '</ul>';
  }

  function save_post( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
      return $post_id;

    if ( 'page' != $_POST['post_type'] )
      return $post_id;

    if ( ! current_user_can( 'edit_page', $post_id ) )
      return $post_id;

    // Update the meta field.
    update_post_meta( $post_id, 'uw-location-attributes', $_POST['uw-location-attributes'] );
  }

}

new UW_Location_Attributes();
