<?php

class Affiliate_WP_Booking_Calendar extends Affiliate_WP_Base {

	/**
	 * This is to stop race condition
	 * between non IPN vs IPN requests
	 * @var bool
	 */
	private $once_added = false;

	/**
	 * Get things started
	 *
	 * @access  public
	 */
	public function init() {

		$this->context       = 'booking_calendar';
		$this->referral_type = 'booking';

		add_bk_action( 'wpbc_auto_approve_or_cancell_and_redirect', [ $this, 'insert_referral' ] );
		add_bk_filter( 'wpdev_bk_get_option', [ $this, 'insert_referral_ipn' ] );

		add_filter( 'affwp_referral_reference_column', [ $this, 'reference_link' ], 10, 2 );
	}

	/**
	 * Hack throug the *_get_option filter
	 * in order to verify through IPN
	 * @param string $no_values
	 * @param string $option
	 * @param mixed $default
	 * @return mixed
	 */
	public function insert_referral_ipn( $no_values = 'no-values', $option, $default ) {
		if ( ! defined( 'WP_BK_RESPONSE_IPN_MODE' ) || ! WP_BK_RESPONSE_IPN_MODE ) {
			return $no_values;
		}

		$pay_sys             = filter_input( INPUT_GET, 'pay_sys', FILTER_SANITIZE_STRING );
		$booking_payment_sys = "booking_{$pay_sys}_is_auto_approve_cancell_booking";

		if ( $booking_payment_sys !== $option ) {
			return $no_values;
		}

		$booking_id = filter_input( INPUT_GET, 'payed_booking', FILTER_SANITIZE_NUMBER_INT );
		if ( $booking_id && ! $this->once_added ) {
			$this->insert_referral( $pay_sys, null, $booking_id );
			$this->once_added = true;
		}

		return $no_values;
	}

	/**
	 * Create a referral during payment update
	 * @access  public
	*/
	public function insert_referral( $pay_system, $status, $booking_id ) {
		// Assuming referral already added
		if ( $this->once_added ) {
			return;
		}

		if ( ! $this->was_referred() ) {
			return;
		}

		$bookings_objs = wpbc_get_bookings_objects( [ 'wh_booking_id' => $booking_id ] );
		$bookings      = $bookings_objs['bookings'];

		if ( empty( $bookings ) ) {
			return;
		}

		$booking = $bookings[ $booking_id ];

		if ( ! $booking ) {
			return;
		}

		$formdata = $booking->form_data;
		$resource = $bookings_objs['resources'][ $booking->booking_type ];

		$currency    = get_bk_option( 'booking_currency' );
		$amount      = $booking->cost;
		$description = "Booking Calendar - {$resource->title}";
		$this->email = $formdata['email'];

		if ( $this->is_affiliate_email( $this->email, $this->affiliate_id ) ) {

			$this->log( 'Referral not created because affiliate\'s own account was used.' );

			return;

		}

		$referral_total = $this->calculate_referral_amount( $amount, $booking->booking_id );
		$referral_id    = $this->insert_pending_referral( $referral_total, $booking->booking_id, $description );

		if ( $referral_id ) {

			$this->log( 'Pending referral created successfully during insert_referral()' );

			// Hack: To prevent early call of permalink by AWP and avoid fatals!
			if ( ! isset( $GLOBALS['wp_rewrite'] ) || empty( $GLOBALS['wp_rewrite'] ) ) {
				$GLOBALS['wp_rewrite'] = new WP_Rewrite();
			}

			if ( $this->complete_referral( $booking->booking_id ) ) {

				$this->log( 'Referral completed successfully during insert_referral()' );

				$this->once_added = true;

				return;

			}

			$this->log( 'Referral failed to be set to completed with complete_referral()' );

		} else {

			$this->log( 'Pending referral failed to be created during insert_referral()' );

		}
	}

	/**
	 * Sets up the reference link in the Referrals table
	 *
	 * @access  public
	 * @since   2.0
	*/
	public function reference_link( $reference = 0, $referral ) {

		if ( empty( $referral->context ) || 'booking_calendar' != $referral->context ) {

			return $reference;

		}

		$endpoint = false !== strpos( $reference, 'sub_' ) ? 'subscriptions' : 'payments';

		$url = admin_url(
			sprintf(
				'admin.php?page=wpbc&wh_booking_id=%d&view_mode=vm_listing&tab=actions',
				$referral->reference
			)
		);

		return '<a href="' . esc_url( $url ) . '">' . $reference . '</a>';
	}

}

if ( class_exists( 'Booking_Calendar' ) ) {
	new Affiliate_WP_Booking_Calendar;
}
