<?php

class UW_Location_Attributes {
  
  function __construct() {
    add_action('init', array($this, 'init'));
    add_shortcode('attributes', array($this, 'shortcode'));
  }

  function init() {
    register_taxonomy('location-attributes', 'page', array(
      'label' => 'Location Attributes',
      'hierarchical' => true,
    ));
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
