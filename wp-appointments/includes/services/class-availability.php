<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculates available booking slots for a given date and service.
 *
 * Algorithm:
 *   1. Validate inputs — return [] for invalid dates, inactive services, etc.
 *   2. Load the weekly availability template for that day of week.
 *   3. Load blocked slots and existing bookings for that date.
 *   4. For each open time window, generate consecutive candidate slots of
 *      `service.duration_mins` length.
 *   5. Discard candidates that overlap any obstacle (blocked slot or booking).
 *   6. Return the survivors.
 *
 * All time comparisons use 'H:i' (zero-padded, 24-hour) strings throughout.
 * DB values stored as 'H:i:s' are normalized with substr(0, 5) before use.
 */
class WPAPPT_Service_Availability {

	private WPAPPT_Model_Service      $service_model;
	private WPAPPT_Model_Availability $availability_model;
	private WPAPPT_Model_Booking      $booking_model;

	public function __construct(
		?WPAPPT_Model_Service      $service_model      = null,
		?WPAPPT_Model_Availability $availability_model = null,
		?WPAPPT_Model_Booking      $booking_model      = null
	) {
		$this->service_model      = $service_model      ?? new WPAPPT_Model_Service();
		$this->availability_model = $availability_model ?? new WPAPPT_Model_Availability();
		$this->booking_model      = $booking_model      ?? new WPAPPT_Model_Booking();
	}

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Return available slots for a date + service combination.
	 *
	 * @return array<int, array{start_time: string, end_time: string}>
	 *         Each element is ['start_time' => 'H:i', 'end_time' => 'H:i'].
	 *         Empty array means nothing is available (or inputs are invalid).
	 */
	public function get_available_slots( string $date, int $service_id ): array {
		if ( ! $this->is_valid_future_date( $date ) ) {
			return [];
		}

		$service = $this->service_model->find( $service_id );
		if ( ! $service || ! $service['is_active'] ) {
			return [];
		}

		$duration    = (int) $service['duration_mins'];
		$day_of_week = (int) ( new \DateTime( $date ) )->format( 'w' ); // 0=Sun … 6=Sat

		// Open time windows for this day of week.
		$windows = array_filter(
			$this->availability_model->find_by_day( $day_of_week ),
			fn( array $w ): bool => (bool) $w['is_available']
		);

		if ( empty( $windows ) ) {
			return [];
		}

		// Load all obstacles for the day in two queries (not N queries per slot).
		$blocked = $this->availability_model->find_blocked_for_date( $date );
		$booked  = $this->booking_model->find_by_date( $date );

		$slots = [];

		foreach ( $windows as $window ) {
			$candidates = $this->generate_candidates(
				substr( $window['start_time'], 0, 5 ),
				substr( $window['end_time'],   0, 5 ),
				$duration
			);

			foreach ( $candidates as $candidate ) {
				if ( $this->is_slot_free( $candidate['start_time'], $candidate['end_time'], $blocked, $booked ) ) {
					$slots[] = $candidate;
				}
			}
		}

		return $slots;
	}

	/**
	 * Return date strings for all days in a month that have at least one slot.
	 *
	 * Uses 3 DB queries for the whole month (weekly template + blocked + booked)
	 * instead of per-day queries.
	 *
	 * @return array<int, string> e.g. ['2026-06-09', '2026-06-10', ...]
	 */
	public function get_available_dates_in_month( int $year, int $month, int $service_id ): array {
		$service = $this->service_model->find( $service_id );
		if ( ! $service || ! $service['is_active'] ) {
			return [];
		}

		$duration   = (int) $service['duration_mins'];
		$year_month = sprintf( '%04d-%02d', $year, $month );
		$today      = new \DateTime( 'today' );

		// 3 bulk queries for the whole month.
		$template = $this->availability_model->find_all();
		$blocked  = $this->availability_model->find_blocked_for_month( $year_month );
		$booked   = $this->booking_model->find_by_month( $year_month );

		// Index weekly template by day_of_week for O(1) lookup per calendar day.
		$windows_by_dow = [];
		foreach ( $template as $row ) {
			if ( (bool) $row['is_available'] ) {
				$windows_by_dow[ (int) $row['day_of_week'] ][] = $row;
			}
		}

		$days_in_month = (int) ( new \DateTime( "{$year_month}-01" ) )->format( 't' );
		$available     = [];

		for ( $d = 1; $d <= $days_in_month; $d++ ) {
			$date_str = sprintf( '%04d-%02d-%02d', $year, $month, $d );
			$cell_dt  = new \DateTime( $date_str );

			if ( $cell_dt < $today ) {
				continue;
			}

			$dow = (int) $cell_dt->format( 'w' );

			if ( empty( $windows_by_dow[ $dow ] ) ) {
				continue;
			}

			// Filter pre-loaded obstacles down to this specific date.
			$day_blocked = array_filter(
				$blocked,
				fn( array $row ): bool => $row['blocked_date'] === $date_str
			);
			$day_booked = array_filter(
				$booked,
				fn( array $row ): bool => $row['appointment_date'] === $date_str
			);

			foreach ( $windows_by_dow[ $dow ] as $window ) {
				$candidates = $this->generate_candidates(
					substr( $window['start_time'], 0, 5 ),
					substr( $window['end_time'],   0, 5 ),
					$duration
				);

				foreach ( $candidates as $candidate ) {
					if ( $this->is_slot_free( $candidate['start_time'], $candidate['end_time'], $day_blocked, $day_booked ) ) {
						$available[] = $date_str;
						continue 3; // Date has at least one slot — move to next day.
					}
				}
			}
		}

		return $available;
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Validate that $date is a real calendar date and is not in the past.
	 */
	private function is_valid_future_date( string $date ): bool {
		$dt = \DateTime::createFromFormat( 'Y-m-d', $date );

		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $date ) {
			return false;
		}

		// Allow today but not yesterday or earlier.
		$today = new \DateTime( 'today' );
		return $dt >= $today;
	}

	/**
	 * Generate consecutive candidate slots within a single availability window.
	 *
	 * Slots are non-overlapping and exactly $duration_mins long. The last slot
	 * must end by $window_end — partial slots at the tail are discarded.
	 *
	 * @param  string $window_start 'H:i'
	 * @param  string $window_end   'H:i'
	 * @param  int    $duration_mins
	 * @return array<int, array{start_time: string, end_time: string}>
	 */
	private function generate_candidates( string $window_start, string $window_end, int $duration_mins ): array {
		$slots      = [];
		$cursor     = new \DateTime( $window_start );
		$window_end = new \DateTime( $window_end );

		while ( true ) {
			$slot_end = ( clone $cursor )->modify( "+{$duration_mins} minutes" );

			if ( $slot_end > $window_end ) {
				break;
			}

			$slots[] = [
				'start_time' => $cursor->format( 'H:i' ),
				'end_time'   => $slot_end->format( 'H:i' ),
			];

			$cursor = $slot_end;
		}

		return $slots;
	}

	/**
	 * Return true if the candidate slot does not overlap any obstacle.
	 *
	 * Overlap condition (standard interval intersection):
	 *   slot.start < obstacle.end  AND  slot.end > obstacle.start
	 *
	 * All times are normalised to 'H:i' before comparison. DB values arrive as
	 * 'H:i:s' so we take the first 5 characters.
	 *
	 * @param string                       $start   'H:i'
	 * @param string                       $end     'H:i'
	 * @param array<int, array<string, mixed>> $blocked Blocked slot rows from DB.
	 * @param array<int, array<string, mixed>> $booked  Booking rows from DB.
	 */
	private function is_slot_free( string $start, string $end, array $blocked, array $booked ): bool {
		foreach ( array_merge( $blocked, $booked ) as $obstacle ) {
			$obs_start = substr( $obstacle['start_time'], 0, 5 );
			$obs_end   = substr( $obstacle['end_time'],   0, 5 );

			if ( $start < $obs_end && $end > $obs_start ) {
				return false;
			}
		}

		return true;
	}
}
