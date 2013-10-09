<?php
/*
Plugin Name: Listing Page Descriptions
Description: Adds a place to enter a description for your custom post types which you can display anywhere in your template.
Author: Evan Stein
License: GPL v3
Text Domain: cptdescriptions
Domain Path: /localization/

CPT Descriptions
Copyright (C) 2013, Evan Stein - admin@vanpop.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/****************************************************
 *
 * Load the text domain
 *
 ****************************************************/
load_plugin_textdomain(
    'cptdescriptions',
    false,
    dirname( plugin_basename( __FILE__ ) ) . '/localization/'
);

/****************************************************
 *
 * Get post types for plugin, filterable by users
 *
 ****************************************************/

function cptd_get_post_types() {
  $args = array(
    'public'   => true,
    '_builtin' => false
  );
  $post_types = apply_filters( 'cptd_post_types', get_post_types( $args ) );

  return $post_types;
}

/**
 * Output filterable name of Settings page
 * 
 * @param string $post_type name of post type for the page
 * @param 'label'|'name' $pt_val whether $post_type is the label (default) or name
 * @return name for settings page
 */
function cptd_settings_page_title( $post_type, $pt_val = 'label' ) {
  if( $pt_val == 'name' ) {
    $post_type_info = get_post_types( array( 'name' => $post_type ), 'objects' );
    $post_type = $post_type_info[$post_type]->labels->name;
  }
  $settings_page_title = sprintf( __( 'Description of the %1$s Custom Post Type', 'cptdescriptions' ), $post_type );
  $settings_page_title = apply_filters( 'cptd_admin_title', $settings_page_title, $post_type );
  return $settings_page_title;
}

function cptd_settings_menu_label( $post_type, $pt_val = 'label' ) {
  if( $pt_val == 'name' ) {
    $post_type_info = get_post_types( array( 'name' => $post_type ), 'objects' );
    $post_type = $post_type_info[$post_type]->labels->name;
  }
  $settings_page_menu_label = __( 'Description', 'cptdescriptions' );
  $settings_page_menu_label = apply_filters( 'cptd_menu_label', $settings_page_menu_label, $post_type );
  return $settings_page_menu_label;
}

/****************************************************
 * 
 * Register Menu Pages, Settings, and Callbacks
 * 
 ****************************************************/

/**
 * Register admin pages for description field
 */
add_action( 'admin_menu', 'post_type_desc_enable_pages' );
function post_type_desc_enable_pages() {

  $post_types = cptd_get_post_types();

  foreach ( $post_types as $post_type ) {

    if( post_type_exists( $post_type ) ) {

      add_submenu_page(
        'edit.php?post_type=' . $post_type, // $parent_slug
        cptd_settings_page_title( $post_type, 'name' ), // $page_title
        cptd_settings_menu_label( $post_type, 'name' ), // $menu_label
        cptd_capability(), // $capability
        $post_type . '-description', // $menu_slug
        'cptd_settings_page' // $function
      );

    } // end if

  } // end foreach

}

/**
 * Register Setting, Settings Section, and Settings Field
 */
add_action( 'admin_init', 'post_type_desc_register_settings' );
function post_type_desc_register_settings() {

  $post_types = cptd_get_post_types();

  // A single option will hold all our descriptions
  register_setting(
    'cptd_descriptions', // $option_group
    'cptd_descriptions', // $option_name
    'cptd_sanitize_inputs' // $sanitize_callback
  );


  // add a settings section and field for each $post_type
  foreach ( $post_types as $post_type ) {

    if( post_type_exists( $post_type ) ) {

      // Register settings and call sanitization functions
      add_settings_section(
        'cptd_settings_section_' . $post_type, // $id
        '', // $title
        'cptd_settings_section_callback', // $callback
        $post_type . '-description' // $page
      );

      // Field for our setting
      add_settings_field(
        'cptd_setting_' . $post_type, // $id
        __( 'Description Text', 'cptddescriptions' ), // $title
        'cptd_editor_field', // $callback
        $post_type . '-description', // $page
        'cptd_settings_section_' . $post_type, // $section
        array( // $args
          'post_type' => $post_type,
          'field_name' => 'cptd_descriptions[' . $post_type . ']',
          'label_for' => 'cptd_descriptions[' . $post_type . ']'
        )
      );

    } // endif

  } // end foreach

}

// There is no need for this function at this time.
function cptd_settings_section_callback() {}

/**
 * Output a wp_editor instance for use by our settings fields
 */
function cptd_editor_field( $args ) {

  $post_type = $args['post_type'];

  $descriptions = (array) get_option( 'cptd_descriptions' );

  if( array_key_exists($post_type, $descriptions) ) {
    $description = $descriptions[$post_type];
  } else {
    $description = '';
  }

  $editor_settings = array(
    'textarea_name' => $args['field_name'],
    'textarea_rows' => 15,
    'media_buttons' => true
  );
  wp_editor( $description, 'cptddescription', $editor_settings );

}

/**
 * Output our Settings Pages
 */
function cptd_settings_page() {
  $screen = get_current_screen();
  $post_type = $screen->post_type;
  ?>
  <div class="wrap">
    <?php screen_icon(); ?>
    <h2><?php echo cptd_settings_page_title( $post_type, 'name' ); ?></h2>
    <form action="options.php" method="POST">
        <?php settings_fields( 'cptd_descriptions' ); ?>
        <?php do_settings_sections( $post_type . '-description' ); ?>
        <?php submit_button(); ?>
    </form>
  </div> <?php
}

function cptd_sanitize_inputs( $input ) {
  // get all descriptions
  $all_descriptions = (array) get_option( 'cptd_descriptions' );
  // sanitize input
  foreach( $input as $post_type => $description ) {
    $sanitized_input[$post_type] = wp_kses_post( $description );
  }
  // merge with other descriptions into array setting
  $input = array_merge( $all_descriptions, $sanitized_input );

  return $input;
}

/**
 * Allow editors to save Post Type Descriptions
 * 
 * See: http://core.trac.wordpress.org/ticket/14365
 */
function cptd_capability() {
  $default_cap = 'edit_posts';
  $cap = apply_filters( 'cptd_capability', $default_cap );
  return $cap;
}
add_filter( 'option_page_capability_cptd_descriptions', 'cptd_capability' );


/****************************************************
 * 
 * Functions to get Description Page Content
 * 
 ****************************************************/
function the_post_type_description( $post_type = '' ) {
  echo get_post_type_description( $post_type );
}

function get_post_type_description( $post_type = '' ) {
  
  // get global $post_type if not specified
  if ( '' == $post_type ) {
    global $post_type;
  }

  $all_descriptions = (array) get_option( 'cptd_descriptions' );
  if( array_key_exists($post_type, $all_descriptions) ) {
    $post_type_x = $all_descriptions[$post_type];
  } else {
    $post_type_x = '';
  }
  $description = apply_filters( 'the_content', $post_type_x );

  return $description;

}


/****************************************************
 * 
 * Add Edit / View Links to the WordPress Admin Bar
 * 
 * Props: blog.rutwick.com/add-items-anywhere-to-the-wp-3-3-admin-bar
 * 
 ****************************************************/

function cptd_admin_bar_links( $admin_bar ) {

  if( !is_admin() && is_post_type_archive() ) {
    global $post_type;
    $post_type_object = get_post_type_object( $post_type );
    $post_type_name = $post_type_object->labels->name;

    $link_text = sprintf( __( 'Edit %1$s Description', 'cptdescriptions' ), $post_type_name );
    $link_text = apply_filters( 'cptd_edit_description_link', $link_text, $post_type_name );

    $args = array(
      'id'    => 'wp-admin-bar-edit',
      'title' => $link_text,
      'href'  => admin_url( 'edit.php?post_type=' . $post_type . '&page=' . $post_type . '-description' )
    );
    $admin_bar->add_menu( $args );
  }

  if( is_admin() ) {

    $screen = get_current_screen();
    $post_type = $screen->post_type;
    $description_page = $post_type . '_page_' . $post_type . '-description';

    if( $screen->base == $description_page ) {
      $post_type_object = get_post_type_object( $post_type );
      $post_type_name = $post_type_object->labels->name;

      $link_text = sprintf( __( 'View %1$s Archive', 'cptdescriptions' ), $post_type_name );
      $link_text = apply_filters( 'cptd_view_archive_link', $link_text, $post_type_name );

      $post_type_object = get_post_type_object( $post_type );
      $args = array(
        'id'    => 'wp-admin-bar-edit',
        'title' => $link_text,
        'href'  => get_post_type_archive_link( $post_type )
      );
      $admin_bar->add_menu( $args );
    }
  }

}
add_action('admin_bar_menu', 'cptd_admin_bar_links',  100);