<?php

class Walker_Location_Attribute extends Walker_Category {

  public function __construct($location_attribute_meta = array()) {
    $this->location_attribute_meta = $location_attribute_meta;
  }

  public function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
    $meta = $this->location_attribute_meta[$category->term_id];

    /** This filter is documented in wp-includes/category-template.php */
    $cat_name = apply_filters(
      'list_cats',
      esc_attr( $category->name ),
      $category
      );

    // Don't generate an element if the category name is empty.
    if ( ! $cat_name ) {
      return;
    }

    $link = '';
    if ($meta['quantity'] > 1) {
      $link .= "{$meta['quantity']} ";
      $cat_name .= 's';
    }

    $link .= '<a href="' . esc_url( get_term_link( $category ) ) . '" ';
    if ( $args['use_desc_for_title'] && ! empty( $category->description ) ) {
      /**
       * Filter the category description for display.
       *
       * @since 1.2.0
       *
       * @param string $description Category description.
       * @param object $category    Category object.
       */
      $link .= 'title="' . esc_attr( strip_tags( apply_filters( 'category_description', $category->description, $category ) ) ) . '"';
    }

    $link .= '>';
    $link .= $cat_name . '</a>';

    if ( ! empty( $args['feed_image'] ) || ! empty( $args['feed'] ) ) {
      $link .= ' ';

      if ( empty( $args['feed_image'] ) ) {
        $link .= '(';
      }

      $link .= '<a href="' . esc_url( get_term_feed_link( $category->term_id, $category->taxonomy, $args['feed_type'] ) ) . '"';

      if ( empty( $args['feed'] ) ) {
        $alt = ' alt="' . sprintf(__( 'Feed for all posts filed under %s' ), $cat_name ) . '"';
      } else {
        $alt = ' alt="' . $args['feed'] . '"';
        $name = $args['feed'];
        $link .= empty( $args['title'] ) ? '' : $args['title'];
      }

      $link .= '>';

      if ( empty( $args['feed_image'] ) ) {
        $link .= $name;
      } else {
        $link .= "<img src='" . $args['feed_image'] . "'$alt" . ' />';
      }
      $link .= '</a>';

      if ( empty( $args['feed_image'] ) ) {
        $link .= ')';
      }
    }

    if ($meta['length'] || $meta['width']) {
      $link .= ' - ';
      if ($meta['length']) {
        if ((int)($meta['length'] / 12))
          $link .= (int)($meta['length'] / 12) . '&apos;';
        if ($meta['length'] % 12)
          $link .= $meta['length'] % 12 . '&quot;';
      }

      if ($meta['length'] && $meta['width'])
        $link .= ' x ';
      if ($meta['width']) {
        if ((int)($meta['width'] / 12))
          $link .= (int)($meta['width'] / 12) . '&apos;';
        if ($meta['width'] % 12)
          $link .= $meta['width'] % 12 . '&quot;';
      }
    }

    if ( ! empty( $args['show_count'] ) ) {
      $link .= ' (' . number_format_i18n( $category->count ) . ')';
    }
    if ( 'list' == $args['style'] ) {
      $output .= "\t<li";
      $css_classes = array(
        'cat-item',
        'cat-item-' . $category->term_id,
      );

      if ( ! empty( $args['current_category'] ) ) {
        $_current_category = get_term( $args['current_category'], $category->taxonomy );
        if ( $category->term_id == $args['current_category'] ) {
          $css_classes[] = 'current-cat';
        } elseif ( $category->term_id == $_current_category->parent ) {
          $css_classes[] = 'current-cat-parent';
        }
      }

      /**
       * Filter the list of CSS classes to include with each category in the list.
       *
       * @since 4.2.0
       *
       * @see wp_list_categories()
       *
       * @param array  $css_classes An array of CSS classes to be applied to each list item.
       * @param object $category    Category data object.
       * @param int    $depth       Depth of page, used for padding.
       * @param array  $args        An array of wp_list_categories() arguments.
       */
      $css_classes = implode( ' ', apply_filters( 'category_css_class', $css_classes, $category, $depth, $args ) );

      $output .=  ' class="' . $css_classes . '"';
      $output .= ">$link\n";
    } else {
      $output .= "\t$link<br />\n";
    }
  }

}


class UW_Location_Attributes {
  
  function __construct() {
    add_action('init', array($this, 'init'));
    add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));
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
    wp_register_script('location-attributes',
      plugin_dir_url(__FILE__) . 'location-attributes.js',
      array('jquery'));
    wp_register_style('location-attributes',
      plugin_dir_url(__FILE__) . 'location-attributes.css');
  }

  function wp_enqueue_scripts($hook_suffix) {
    wp_enqueue_script('location-attributes');
    wp_enqueue_style('location-attributes');
  }

  function admin_init() {
    wp_register_script('location-attributes-admin',
      plugin_dir_url(__FILE__) . 'location-attributes-admin.js',
      array('jquery'));
    wp_register_style('location-attributes-admin',
      plugin_dir_url(__FILE__) . 'location-attributes-admin.css');
  }

  function admin_enqueue_scripts($hook_suffix) {
    if ($hook_suffix != 'post.php')
      return;

    wp_localize_script('location-attributes-admin', 'location_attributes',
      get_post_meta(get_the_ID(), 'uw-location-attributes', true));
    wp_enqueue_script('location-attributes-admin');
    wp_enqueue_style('location-attributes-admin');
  }

  function shortcode() {
    global $post;

    get_location_attributes();

    $post_terms = wp_get_object_terms( $post->ID, 'location-attributes', array( 'fields' => 'ids' ) );

    $location_attribute_meta = get_post_meta($post->ID, 'uw-location-attributes', true);

    $walker = new Walker_Location_Attribute($location_attribute_meta);

    return
      '<ul class="location-attributes">' .
      wp_list_categories(array(
        'echo' => false,
        'taxonomy' => 'location-attributes',
        'title_li' => '',
        'include' => $post_terms,
        'walker' => $walker,
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
