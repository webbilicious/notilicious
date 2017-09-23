<?php
/*
Plugin Name: Notilicious
Description: Mark posts as old and display a notification message above the content.
Version:     1.0
Author:      Daniel Hånberg Alonso
Author URI:  http://webbilicious.se
License:     GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

defined( 'ABSPATH' ) or die();

/**
 * Main class for notilicious
 */
class Notilicious {

	function __construct() {
		
		$this->init();

	}

	/**
	 * Initiate all hooks, actions and filters. 
	 *	 	
	 * @since 1.0
	 */
	public function init() {

		// Translations
		load_plugin_textdomain( 'notilicious', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );		
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_css' ) );

		// Display default text
		add_filter( 'notilicious_default_text', array( $this, 'display_default_text' ) );

		// Display time difference
		add_filter( 'notilicious_time_diff', array( $this, 'display_time_diff' ) );		
		
		// Display Notilicious
		add_filter( 'notilicious_display', array( $this, 'display_notilicious' ) );
		
		// Add it to the content
		add_filter( 'the_content', array( $this, 'display_notilicious_on_content' ) );
			
		// CSS

		/* Only load the admin actions if you are in the admin  */
		if ( is_admin() ) {

			// Metabox on Edit screen
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
			
			// Settings Page
			add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );

			// Save custom data
			add_action( 'save_post', array( $this, 'save_details'), 10, 1 );

		}
	}
	
	/**
	 * Add a metabox with custom fields
	 *
	 * @since 1.0
	 */
	function add_meta_boxes( $post ) {
	    add_meta_box( 
	        'notilicious',
	        __( 'Notilicious', 'notilicious' ),
	        array ($this, 'details'),
	        'post',
	        'normal',
	        'high'
	    );
	}

	/**
	 * Create metabox with custom fields
	 *
	 * @since 1.0
	 */
	 function create_metaboxes( $meta_boxes ) {

		$meta_boxes[] = array(
			'id' => 'notilicious',
			'title' => __( 'Notilicious', 'notilicious' ),
			'pages' => apply_filters( 'notilicious_post_types', array('post') ), 
			'context' => 'normal',
			'priority' => 'high',
			'show_names' => true, 
			'fields' => array(
				array(
					'name' => __( 'Mark as Old', 'notilicious' ),
					'desc' => '',
					'id' => 'notilicious_marked_old',
					'type' => 'checkbox'
				),
				array(
					'name' => __( 'Custom Message', 'notilicious' ),
					'desc' => __( 'You can set the default in Settings > Notilicious', 'notilicious' ),
					'id' => 'notilicious_custom_message',
					'type' => 'wysiwyg',
					'options' => array( 'textarea_rows' => 5 ),
				)
			),
		);
		
		return $meta_boxes;
	}

	/**
	 * Add custom fields
	 *
	 * @since 1.0
	 */
	function details(){
		global $post;

		wp_nonce_field('save_notilicious', 'noti_edit_nonce');

		$custom = get_post_custom($post->ID);
		$notilicious_marked_old = isset( $custom["notilicious_marked_old"][0] ) ? 'checked' : '';
		$notilicious_custom_message = isset( $custom["notilicious_custom_message"][0] ) ? $custom["notilicious_custom_message"][0] : '';

		?>
		<p><label><?php _e('Mark as Old', 'notilicious'); ?>:</label><br />
		<input type="checkbox" class="listo-input" name="notilicious_marked_old" <?php echo esc_attr( $notilicious_marked_old ); ?> /></p>
		<p><label><?php _e('Custom Message', 'notilicious'); ?>:</label><br />
		<?php _e( 'You can set the default in Settings > Notilicious', 'notilicious' ); ?><br />
		<?php
		echo wp_editor( 
			esc_attr( $notilicious_custom_message ), 'notilicious_custom_message', 
			array( 'textarea_name' => 'notilicious_custom_message', 'textarea_rows' => 4)
		);
	} 

	/**
	 * Save/update the new custom fields
	 *
	 * @since 1.0
	 */
	function save_details($post_id){
		global $post;

		if ( ! empty( $_POST ) ) {

			//check nonce set
			if(!isset($_POST['noti_edit_nonce'])){
			    return false;
			}

			//verify nonce
			if(!wp_verify_nonce($_POST['noti_edit_nonce'], 'save_notilicious')){
			    return false;
			}
			
		 	$notilicious_marked_old = isset( $_POST['notilicious_marked_old'] ) ? $_POST['notilicious_marked_old'] : '';
		 	$notilicious_custom_message = isset( $_POST['notilicious_custom_message'] ) ? sanitize_text_field( $_POST['notilicious_custom_message'] ) : '';

			update_post_meta( $post_id, "notilicious_marked_old", $notilicious_marked_old );
			update_post_meta( $post_id, "notilicious_custom_message", $notilicious_custom_message );

		}
	}
	
	/**
	 * Add Settings Page
	 *
	 * @link http://codex.wordpress.org/Function_Reference/add_options_page
	 *
	 * @since 1.0
	 */
	function add_settings_page() {
		add_options_page( __( 'Notilicious', 'notilicious' ), __( 'Notilicious', 'notilicious' ), 'manage_options', 'notilicious',  array( $this, 'settings_page' ) );
	}
	
	/**
	 * Build Settings Page
	 *
	 * @since 1.0
	 */
	function settings_page() {
		echo '<div class="wrap">';
		echo '<div id="icon-options-general" class="icon32"><br></div>';
		echo '<h2>' . __( 'Notilicious', 'notilicious' ) . '</h2>';
		echo '<form action="options.php" method="post">';
		settings_fields('notilicious_options');
		do_settings_sections('notilicious');
		
		echo '<input name="Submit" type="submit" class="button-primary" value="' . __( 'Save Changes', 'notilicious' ) . '" />';
		echo '</form></div>';
	}
	
	/**
	 * Register Settings
	 *
	 * @since 1.0
	 */
	function register_settings(){
		
		register_setting( 'notilicious_options', 'notilicious_options', array( $this, 'default_text_validate' ) );

		add_settings_section(
			'notilicious_default', 
			__( 'Default Text', 'notilicious' ), 
			array( $this, 'default_text_intro' ), 
			'notilicious'
		);

		add_settings_field(
			'notilicious_default_checkbox', 
			__( 'Default Text', 'notilicious' ), 
			array( $this, 'default_text_checkbox' ), 
			'notilicious', 
			'notilicious_default'
		);

		add_settings_field(
			'notilicious_default_text', 
			__( 'Custom Text', 'notilicious' ), 
			array( $this, 'default_text_field' ), 
			'notilicious', 
			'notilicious_default'
		);
	}	

	/**
	 * Default Text intro
	 *
	 * @since 1.0
	 */
	function default_text_intro() {
		echo wpautop( __( 'This is the default text that is displayed at the top of the post when the post is marked as old. You can override this on a per-post basis.', 'notilicious' ) );
	}
	
	/**
	 * Default Text checkbox
	 *
	 * @since 1.0
	 */
	function default_text_checkbox($args) {
	    $options = get_option('notilicious_options');
	    $value = isset( $options['notilicious_default_checkbox'] ) ? 'checked' : '';
	    $output = '<input type="checkbox" id="notilicious_default_checkbox" name="notilicious_options[notilicious_default_checkbox]" ' . $value . ' />';
		$output .= __( 'This post was published [years] ago.', 'notilicious' );
		echo $output;
	}

	/**
	 * Custom Text field
	 *
	 * @since 1.0
	 */
	function default_text_field() {
		
		echo wp_editor( 
			apply_filters( 'notilicious_default_text', '' ), 'notilicious_default_text', 
			array( 'textarea_name' => 'notilicious_options[notilicious_default_text]', )
		);
	}
	
	/**
	 * Default Text validate
	 *
	 * @since 1.0
	 */
	function default_text_validate( $input ) {
		return wp_kses_post( $input );
	}

	/**
	 * Display custom text
	 * 
	 * @since 1.0
	 */
	function display_default_text( $text ) {
		$options = get_option( 'notilicious_options' );
		$default = wpautop( __( 'This post has been marked as old.', 'notilicious' ) );
		$text = isset( $options['notilicious_default_text'] ) ? $options['notilicious_default_text'] : $default;			
		return $text;
	}	

	/**
	 * Display time difference in years
	 * 
	 * @since 1.0
	 */
	function display_time_diff( $text ) {
		$d1 = new DateTime(get_the_time('Y-m-d'));
		$d2 = new DateTime(current_time('Y-m-d'));
		$diff = $d2->diff($d1);
		$year = ($diff->y > 1) ? __('years', 'notilicious') : __('year', 'notilicious');
		$diff = sprintf( _x( '%s %s ago', '%s = human-readable time difference', 'notilicious' ), $diff->y, $year );
		return $diff;
	}

	/**
	 * Display Notilicious
	 *
	 * @since 1.0
	 */
	function display_notilicious( $notice ) {
		if( !is_singular() )
			return;
			
		global $post;
		$old = get_post_meta( $post->ID, 'notilicious_marked_old', true );
		if( 'on' !== $old )
			return;
		$time = apply_filters( 'notilicious_time_diff', '' );
		$time = wpautop( __( 'This post was published '. $time .'.', 'notilicious' ) );
		$custom = apply_filters( 'notilicious_default_text', '' );
		$options = get_option( 'notilicious_options' );
		$default = isset( $options['notilicious_default_checkbox'] ) ? $time : $custom;
		$override = get_post_meta( $post->ID, 'notilicious_custom_message', true );
		
		$notice = !empty( $override ) ? $override : $default;
		return '<div class="notilicious">' . wpautop( $notice ) . '</div>';
	}
	
	/**
	 * Display Notilicious on Content
	 *
	 * @since 1.0
	 */
	function display_notilicious_on_content( $content ) {
		return apply_filters( 'notilicious_display', '' ) . $content;
	}

	/**
	 * Enqueue CSS
	 *
	 * @since 1.0
	 */
	function enqueue_css() {
		wp_enqueue_style( 'notilicious', plugins_url( 'css/notilicious.css', __FILE__ ) );
	}	
}

$Notilicious = new Notilicious;