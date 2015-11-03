<?php

class UW_Location_Attributes {
  
  function __construct() {
    add_action('init', array($this, 'init'));
    add_action('admin_init', array($this, 'admin_init'));
    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    add_shortcode('attributes', array($this, 'shortcode'));
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
  }

  function admin_enqueue_scripts($hook_suffix) {
    if ($hook_suffix != 'post.php')
      return;

    wp_enqueue_script( 'location-attributes' );
    echo '<style type="text/css">#taxonomy-location-attributes .attribute-dropdown { float: right; }</style>';
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
}

new UW_Location_Attributes();
