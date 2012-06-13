<?php # -*- coding: utf-8 -*-
declare( encoding = 'UTF-8' );
/**
 * Plugin Name: Public Contact Data
 * Plugin URI:  https://github.com/toscho/Public-Contact-Data
 * Text Domain: plugin_pcd
 * Domain Path: /lang
 * Description: Adds new fields to settings/general: email address, phone number and social network URIs.
 * Version:     2012.02.21
 * Required:    3.3
 * Author:      Thomas Scholz <info@toscho.de>
 * Author URI:  http://toscho.de
 * License:     MIT
 * License URI: http://www.opensource.org/licenses/mit-license.php
 *
 * Copyright (c) 2012 Thomas Scholz
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

// Not a WordPress context? Stop.
! defined( 'ABSPATH' ) and exit;

register_deactivation_hook(
	__FILE__,
	array ( 'Public_Contact_Data', 'deactivate' )
);

// Wait until all needed functions are loaded.
add_action(
	'after_setup_theme',
	array ( 'Public_Contact_Data', 'instance' )
);

class Public_Contact_Data
{
	/**
	 * Reusable object instance.
	 *
	 * @type object
	 */
	protected static $instance = NULL;

	/**
	 * Internal prefix
	 *
	 * @type string
	 */
	protected $prefix = 'pcd';

	/**
	 * Basename of this file.
	 *
	 * @see __construct()
	 * @type string
	 */
	protected $base_name = '';

	/**
	 * Option name
	 *
	 * @type string
	 */
	protected $option_name = 'public_contact_data';

	/**
	 * Editable fields.
	 *
	 * @type array
	 */
	protected $fields = array();

	/**
	 * Fallback for missing email address.
	 *
	 * @see __construct()
	 * @type string
	 */
	protected $admin_mail = '';

	/**
	 * Creates a new instance. Called on 'after_setup_theme'.
	 * May be used to access class methods from outside.
	 *
	 * @see    __construct()
	 * @return void
	 */
	public static function instance()
	{
		NULL == self::$instance and self::$instance = new self;
		return self::$instance;
	}

	/**
	 * Set actions, filters and basic variables, load language.
	 *
	 * @see   load_language_files()
	 * @see   set_fields()
	 * @uses  add_filter() on 'plugin_row_meta', 'admin_init'
	 * @uses  add-action() on 'pcd'
	 * @uses  add_shortcode() on 'public_mail', 'public_phone'
	 * @param string $context 'normal' or 'deactivate'
	 */
	public function __construct( $context = 'normal' )
	{
		if ( 'deactivate' === $context )
		{
			delete_option( $this->option_name );
			return;
		}
		// Used by add_settings_link() later.
		$this->base_name  = plugin_basename( __FILE__ );

		// We need this as a replacement for a missing or invalid email address.
		$this->admin_mail = get_option( 'admin_email' );

		$this->load_language_files();
		$this->set_fields();
		$this->set_hooks();
	}

	/**
	 * Add filters and register the shortcodes.
	 *
	 * @see    __construct()
	 * @return void
	 */
	protected function set_hooks()
	{
		add_filter(
			'plugin_row_meta',
			array( $this, 'add_settings_link' ),
			10,
			2
		);
		add_filter(
			'admin_init',
			array( $this, 'add_contact_fields' )
		);
		// Public interface
		add_action(
			'pcd',
			array( $this, 'action_handler' ),
			10,
			2
		);
	}

	/**
	 * Load language file if available.
	 *
	 * @return void
	 */
	public function load_language_files()
	{
		$lang_dir = basename( __DIR__ ) . '/lang';
		// We need the files in 'wp-admin' only.
		is_admin() and load_plugin_textdomain( 'plugin_pcd', FALSE, $lang_dir );
	}

	/**
	 * Fill $fields with translated strings.
	 *
	 * @see    $fields
	 * @uses   apply_filters() on $fields
	 * @uses   add_shortcode()
	 * @see    shortcode_handler()
	 * @return void
	 */
	protected function set_fields()
	{
		// Pairs of 'key' => 'label'
		$this->fields = array (
			'email'      => __( 'Public mail address', 'plugin_pcd' ),
			'phone'      => __( 'Public phone number', 'plugin_pcd' ),
			'googleplus' => __( 'Google Plus', 'plugin_pcd' ),
			'facebook'   => __( 'FaceBook', 'plugin_pcd' ),
			'twitter'    => __( 'Twitter', 'plugin_pcd' )
		);

		// You may extend or restrict the fields.
		$hook_name    = $this->prefix . '_fields';
		$this->fields = apply_filters( $hook_name, $this->fields );

		// Register shortcodes.
		foreach ( $this->fields as $key => $value )
		{
			add_shortcode( "public_$key", array( $this, 'shortcode_handler' ) );
		}
	}

	/**
	 * Adds a link to the settings to plugin list.
	 *
	 * @param  array  $links Already existing links.
	 * @return string
	 */
	public function add_settings_link( $links, $file )
	{
		if ( $this->base_name !== $file )
		{
			return $links;
		}

		$url  = admin_url( 'options-general.php' );
		$text = __( 'Set public data', 'plugin_pcd' );
		$link = "<a href='$url'>$text</a>";
		$links[] = sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url( 'options-general.php' ),
			__( 'Set public data', 'plugin_pcd' )
		);
		return $links;
		return array_merge( $links, array ( $link ) );
	}

	/**
	 * Register custom settings for the fields.
	 *
	 * @see    save_settings()
	 * @see    print_input_field()
	 * @return void
	 */
	public function add_contact_fields()
	{
		register_setting(
			'general',
			$this->option_name,
			array ( $this, 'save_settings' )
		);

		foreach ( $this->fields as $type => $desc )
		{
			$handle   = $this->option_name . "_$type";
			$args     = array (
				'label_for' => $handle,
				'type'      => $type
			);
			$callback = array ( $this, 'print_input_field' );

			add_settings_field(
				$handle,
				$desc,
				$callback,
				'general',
				'default',
				$args
			);
		}
	}

	/**
	 * Callback for 'register_setting()'.
	 *
	 * @see    add_contact_fields()
	 * @param  array $settings
	 * @return array $settings
	 */
	public function save_settings( array $settings = array () )
	{
		$default  = get_option( $this->option_name );
		$settings = array_map( 'trim', $settings );
		$settings = $this->prepare_mail_save( $settings, $default );
		$settings = $this->prepare_phone_save( $settings );

		return $settings;
	}

	/**
	 * Prepare the field 'phone' before saving it.
	 *
	 * @see    save_settings()
	 * @param  array $settings
	 * @return array
	 */
	protected function prepare_phone_save( $settings )
	{
		if ( '' === $settings['phone'] )
		{
			return $settings;
		}

		// Strip all characters which don’t fit in a 'tel:' link.
		// Still not optimal: we may want visible white spaces in our output …
		$new_phone = preg_replace( '~ +~', '-', $settings['phone'] );
		$new_phone = preg_replace( '~[^\d+-]~', '', $new_phone );

		if ( $settings['phone'] === $new_phone )
		{
			return $settings;
		}

		$msg = sprintf(
			__(
				'The phone number %1$s has been changed to %2$s.
				Please check if it is still okay.
				Replace spaces with %3$s if you need separators.',
				'plugin_pcd'
			),
			'<code>' . esc_html( $settings['phone'] ) . '</code>',
			'<code>' . $new_phone . '</code>',
			'<code>-</code>'
		);
		add_settings_error( $this->option_name, 'phone', $msg, 'updated' );
		$settings['phone'] = $new_phone;

		return $settings;
	}

	/**
	 * Prepare the field 'email' before saving it.
	 *
	 * @see    save_settings()
	 * @param  array $settings
	 * @return array
	 */
	protected function prepare_mail_save( $settings, $default )
	{
		if ( '' === $settings['email'] or is_email( $settings['email'] ) )
		{
			return $settings;
		}

		$msg = sprintf(
			__(
				'%1$s is not a valid email address. <br />
				Your admin email %2$s will be used instead.',
				'plugin_pcd'
			),
			'<code>' . $settings['email'] . '</code>',
			'<code>' . $this->admin_mail . '</code>'
		);
		add_settings_error( $this->option_name, 'email', $msg );

		$settings['email'] = isset ( $default['email'] )
			? $default['email'] : '';

		return $settings;
	}

	/**
	 * Input fields in 'wp-admin/options-general.php'
	 *
	 * @see    add_contact_fields()
	 * @param  array $args Arguments send by add_contact_fields()
	 * @return void
	 */
	public function print_input_field( array $args )
	{
		$type   = $args['type'];
		$id     = $args['label_for'];
		$data   = get_option( $this->option_name, array() );
		$value  = isset ( $data[ $type ] ) ? $data[ $type ] : '';

		'email' === $type and '' === $value and $value = $this->admin_mail;
		$value  = esc_attr( $value );
		$name   = $this->option_name . '[' . $type . ']';
		$desc   = $this->get_shortcode_help( $type );

		print "<input type='text' value='$value' name='$name' id='$id'
			class='regular-text code' /> <span class='description'>$desc</span>";
	}

	/**
	 * Usage hint for input fields.
	 *
	 * @param  string $type
	 * @return string
	 */
	protected function get_shortcode_help( $type )
	{
		$desc   = __(
			'You may use %s in editor fields to get this value.',
			'plugin_pcd'
		);
		return sprintf( $desc, "<code>[public_$type]</code>" );
	}

	/**
	 * Deletes the option.
	 *
	 * @see    register_deactivation_hook()
	 * @return void
	 */
	public function deactivate()
	{
		new self( 'deactivate' );
	}

// --- Public action and shortcode handlers ------------------------------------

	/**
	 * Handler for action 'pcd'
	 *
	 * @see    shortcode_handler()
	 * @param  string $field    Key of a registered field.
	 * @param  array  $options  'before', 'after', 'link' and 'print'.
	 * @return string
	 */
	public function action_handler( $field, $options = array () )
	{
		$args = $this->set_action_args( $options );
		$out  = '';

		// Unknown field requested.
		if ( ! isset ( $this->fields[ $field ] ) )
		{
			$out = $this->invalid_action_field( $field );
			$args->print and print $out;
			return $out;
		}

		$option = get_option( $this->option_name, '' );
		$data   = esc_attr( $option[ $field ] );
		$data   = $this->prepare_mail_output( $data, $field );

		if ( $args->pattern )
		{
			$data = str_replace( '%value%', $data, $args->pattern );
		}
		elseif ( $args->link )
		{
			$data = $this->link_data( $data, $field );
		}

		// Add 'before' and 'after' not to an empty string.
		'' !== $data and $out = $args->before . $data . $args->after;

		$args->print and print $out;
		return $out;
	}

	protected function prepare_mail_output( $data, $field )
	{
		if ( 'email' !== $field )
		{
			return $data;
		}
		'' === $data and $data = $this->admin_mail;
		return antispambot( $data );
	}

	/**
	 * Parse arguments passed to action_handler() and return a data object.
	 *
	 * @see    action_handler()
	 * @param  array  $options
	 * @return object stdClass
	 */
	protected function set_action_args( array $options )
	{
		$defaults = array (
			'before'  => '',
			'after'   => '',
			'link'    => TRUE,
			'print'   => TRUE,
			'pattern' => FALSE
		);
		return (object) array_merge( $defaults, $options );
	}

	/**
	 * Create a useful error message for an invalid field.
	 *
	 * @param  string $field Field name
	 * @return string
	 */
	protected function invalid_action_field( $field )
	{
		// The translation will currently not work:
		// We load the language files in wp-admin only
		// to reduce performance overhead.
		$msg          = __(
			'Invalid field: %1$s. Allowed fields: %2$s.',
			'plugin_pcd'
		);
		$valid_fields = implode( ', ', $this->fields );
		return sprintf( $msg, esc_html( $field ), $valid_fields );
	}

	/**
	 * Link a string.
	 *
	 * @param  string $data
	 * @param  string $field
	 * @return string
	 */
	protected function link_data( $data, $field )
	{
		if ( '' === $data )
		{
			return $data;
		}

		$prefix   = '';
		'email' === $field and $prefix = 'mailto:';
		'phone' === $field and $prefix = 'tel:';
		$data = "<a href='$prefix$data'>$data</a>";

		return $data;
	}

	/**
	 * Handler for all shortcodes.
	 *
	 * @see    set_fields() The shortcodes are registered there.
	 * @param  array  $args
	 * @param  string $content Not used.
	 * @param  string $shortcode Name of the current shortcode.
	 * @return string
	 */
	public function shortcode_handler(
		$args      = array (),
		$content   = '',
		$shortcode = ''
	)
	{
		$key = $this->current_shortcode_key( $shortcode );
		// Shortcodes don’t print. They return strings only.
		$args['print'] = FALSE;
		return $this->action_handler( $key, $args );
	}

	/**
	 * Returns the currently used shortcode.
	 *
	 * @link   http://wordpress.stackexchange.com/q/42882/73
	 * @return string
	 */
	protected function current_shortcode_key( $shortcode )
	{
		return substr( $shortcode, 7 );
	}
}