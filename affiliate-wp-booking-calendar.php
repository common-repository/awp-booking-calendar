<?php
/**
 * Plugin Name: AffiliateWP - Booking Calendar
 * Description: Track referrals from Booking Calendar
 * Version: 1.0.1
 * Author: qfnetwork, rahilwazir
 * Author URI: https://www.qfnetwork.org
 * Text Domain: awp-booking-calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add integration checkbox
add_filter(
	'affwp_integrations',
	function( $integrations ) {
		$integrations['booking_calendar'] = 'Booking Calendar';
		return $integrations;
	}
);

// Register integration class
add_filter(
	'affwp_integration_classes',
	function( $integration_classes ) {
		$integration_classes['booking_calendar'] = 'Affiliate_WP_Booking_Calendar';
		return $integration_classes;
	}
);

// Load the class
add_action(
	'affwp_integrations_load',
	function() {
		require_once 'includes/integrations/class-booking-calendar.php';
	}
);

// Register our referral type
add_action(
	'affwp_referral_type_init',
	/**
	 * @param \AffWP\Utils\Registry $registry
	 */
	function( $registry ) {
		$registry->register_type(
			'booking',
			[
				'label' => __( 'Booking', 'awp-booking-calendar' ),
			]
		);
	}
);
