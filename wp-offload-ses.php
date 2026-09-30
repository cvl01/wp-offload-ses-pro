<?php
/*
Plugin Name: WP Offload SES
Description: Automatically send WordPress mail through Amazon SES (Simple Email Service), with resend/retry controls, engagement reporting, and priority support.
Author: Delicious Brains
Version: 1.8.2
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 8.1
Author URI: https://deliciousbrains.com/
Plugin URI: https://deliciousbrains.com/
Update URI: false
Network: True
Text Domain: wp-offload-ses
Domain Path: /languages/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

// Copyright (c) 2018 Delicious Brains. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************
*/

use DeliciousBrains\WP_Offload_SES\Utils;
use DeliciousBrains\WP_Offload_SES\WP_Offload_SES;
use DeliciousBrains\WP_Offload_SES\Compatibility_Check;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- wposes is the plugin's established prefix.
$GLOBALS['wposes_meta']['wp-offload-ses']['version'] = '1.8.2';

if ( ! defined( 'WPOSES_FILE' ) ) {
	// Defines the path to the main plugin file. This also acts as the guard the Lite
	// plugin checks for (`defined( 'WPOSES_FILE' )`) to avoid double-loading when both
	// plugins are present, e.g. as must-use plugins.
	define( 'WPOSES_FILE', __FILE__ );

	// Defines the path to be used for includes.
	define( 'WPOSES_PATH', wposes_get_plugin_dir_path() );
}

if ( ! class_exists( 'DeliciousBrains\WP_Offload_SES\Compatibility_Check' ) ) {
	require_once WPOSES_PATH . 'classes/Compatibility-Check.php';
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- wposes is the plugin's established prefix.
global $wposes_compat_check;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$wposes_compat_check = new DeliciousBrains\WP_Offload_SES\Compatibility_Check(
	'WP Offload SES',
	'wp-offload-ses',
	WPOSES_FILE
);

add_action( 'activated_plugin', array( $wposes_compat_check, 'deactivate_other_instances' ) );

/**
 * Initiate the WP Offload SES (Pro) plugin.
 *
 * This is hooked on `plugins_loaded` rather than `init`, deliberately earlier than
 * the Lite plugin's own bootstrap (`wp_offload_ses_lite_init()`, hooked on `init`
 * priority 1). Since `plugins_loaded` always finishes, for every active plugin,
 * before `init` fires for any plugin, this guarantees our
 * `DeliciousBrains\WP_Offload_SES\WP_Offload_SES` class is already defined by the
 * time Lite's own `init` callback runs its `class_exists()` guard - so Lite quietly
 * steps aside and this plugin wins, regardless of plugin load order.
 *
 * @return WP_Offload_SES|null
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function wp_offload_ses_init() {
	if ( class_exists( 'DeliciousBrains\WP_Offload_SES\WP_Offload_SES' ) ) {
		/** @var WP_Offload_SES $wp_offload_ses */
		global $wp_offload_ses;

		return $wp_offload_ses;
	}

	/** @var Compatibility_Check $wposes_compat_check */
	global $wposes_compat_check;

	if ( ! $wposes_compat_check->is_compatible() ) {
		return null;
	}

	// Prevent error in Guzzle when PHP doesn't have the intl extension.
	if ( ! function_exists( 'idn_to_ascii' ) && ! defined( 'IDNA_DEFAULT' ) ) {
		define( 'IDNA_DEFAULT', 0 );
	}

	// Load autoloaders.
	require_once WPOSES_PATH . 'vendor/Aws3/aws-autoloader.php';
	require_once WPOSES_PATH . 'classes/Autoloader.php';
	new DeliciousBrains\WP_Offload_SES\Autoloader( 'WP_Offload_SES', WPOSES_PATH );

	// Load compatibility functions for older PHP (< 8.0) and WordPress (< 5.9).
	require_once WPOSES_PATH . 'includes/compat.php';

	// Kick off the plugin.
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	global $wp_offload_ses;
	$wp_offload_ses = new DeliciousBrains\WP_Offload_SES\WP_Offload_SES( WPOSES_FILE );

	return $wp_offload_ses;
}

add_action( 'plugins_loaded', 'wp_offload_ses_init', 5 );

/**
 * Gets the path to the plugin files.
 *
 * @return string
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- wposes is the plugin's established prefix.
function wposes_get_plugin_dir_path() {
	$file       = defined( 'WPOSES_FILE' ) ? WPOSES_FILE : __FILE__;
	$abspath    = wp_normalize_path( dirname( $file ) );
	$mu_path    = $abspath . '/wp-offload-ses';
	$core_class = '/classes/WP-Offload-SES.php';

	// Is entry point file in directory above where plugin's files are?
	if ( file_exists( $mu_path . $core_class ) ) {
		$abspath = $mu_path;
	}

	return trailingslashit( $abspath );
}

/**
 * Check whether we should send mail via SES.
 *
 * @return bool
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- wposes is the plugin's established prefix.
function wposes_sending_enabled() {
	if ( defined( 'WPOSES_SETTINGS' ) ) {
		require_once WPOSES_PATH . 'classes/Utils.php';
		$defined_settings = Utils::maybe_unserialize( constant( 'WPOSES_SETTINGS' ) );

		if ( isset( $defined_settings['send-via-ses'] ) ) {
			return (bool) $defined_settings['send-via-ses'];
		}
	}

	$settings = get_option( 'wposes_settings' );

	// Single sites & multisite subsites.
	if ( isset( $settings['send-via-ses'] ) ) {
		return (bool) $settings['send-via-ses'];
	}

	// If subsite isn't configured with an override, go with network setting.
	if ( is_multisite() ) {
		$network_settings = get_site_option( 'wposes_settings' );

		if ( isset( $network_settings['send-via-ses'] ) ) {
			return (bool) $network_settings['send-via-ses'];
		}
	}

	return false;
}

// Override `wp_mail()` if sending via SES is enabled.
if ( ! function_exists( 'wp_mail' ) ) {
	if ( wposes_sending_enabled() ) {
		/**
		 * Send mail via Amazon SES.
		 *
		 * @param string|array $to          Array or comma-separated list of email addresses.
		 * @param string       $subject     Email subject.
		 * @param string       $message     Email message.
		 * @param string|array $headers     Optional. Additional headers.
		 * @param string|array $attachments Optional. Files to attach.
		 *
		 * @return bool
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Intentional wp_mail() override for SES integration.
		function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
			/** @var WP_Offload_SES $wp_offload_ses */
			global $wp_offload_ses;

			if ( is_null( $wp_offload_ses ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				$wp_offload_ses = wp_offload_ses_init();
			}

			// Could not initialize plugin.
			if ( is_null( $wp_offload_ses ) ) {
				return false;
			}

			return $wp_offload_ses->mail_handler( $to, $subject, $message, $headers, $attachments );
		}
	}
} else {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global.
	global $pagenow;

	if ( ! in_array( $pagenow, array( 'plugins.php', 'update-core.php' ), true ) ) {
		require_once WPOSES_PATH . 'classes/Error.php';
		new DeliciousBrains\WP_Offload_SES\Error(
			DeliciousBrains\WP_Offload_SES\Error::$mail_function_exists,
			'Mail function already overridden.'
		);
	}
}
