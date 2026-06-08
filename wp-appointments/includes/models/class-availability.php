<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD wrapper for:
 *   {prefix}appointments_availability   — weekly template
 *   {prefix}appointments_blocked_slots  — one-off date overrides
 */
class WPAPPT_Model_Availability {

	private $db;
	private string $availability_table;
	private string $blocked_table;

	public function __construct( $db = null ) {
		global $wpdb;
		$this->db                 = $db ?? $wpdb;
		$this->availability_table = $this->db->prefix . 'appointments_availability';
		$this->blocked_table      = $this->db->prefix . 'appointments_blocked_slots';
	}

	// =========================================================================
	// Weekly availability template
	// =========================================================================

	/**
	 * Return all availability rows ordered by day then start time.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_all(): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db->get_results(
			"SELECT * FROM {$this->availability_table} ORDER BY day_of_week ASC, start_time ASC",
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Return availability rows for a specific day of week (0=Sun … 6=Sat).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_by_day( int $day_of_week ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->availability_table}
				  WHERE day_of_week = %d
				  ORDER BY start_time ASC",
				$day_of_week
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Replace all availability rows for a given day of week.
	 *
	 * Deletes existing rows for that day, then inserts the provided slots.
	 * An empty $slots array effectively marks the day as unavailable.
	 *
	 * @param int                           $day_of_week 0–6
	 * @param array<int, array<string, mixed>> $slots
	 */
	public function save_day( int $day_of_week, array $slots ): void {
		// Delete existing rows for this day.
		$this->db->delete(
			$this->availability_table,
			[ 'day_of_week' => $day_of_week ],
			[ '%d' ]
		);

		foreach ( $slots as $slot ) {
			$this->db->insert(
				$this->availability_table,
				[
					'day_of_week'    => $day_of_week,
					'start_time'     => $slot['start_time'],
					'end_time'       => $slot['end_time'],
					'is_available'   => isset( $slot['is_available'] ) ? (int) $slot['is_available'] : 1,
					'label'          => $slot['label'] ?? null,
				],
				[ '%d', '%s', '%s', '%d', '%s' ]
			);
		}
	}

	// =========================================================================
	// Blocked slots
	// =========================================================================

	/**
	 * Return blocked slots for a specific date.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_blocked_for_date( string $date ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->blocked_table}
				  WHERE blocked_date = %s
				  ORDER BY start_time ASC",
				$date
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Return all blocked slots for a given month.
	 *
	 * @param  string $year_month 'YYYY-MM'
	 * @return array<int, array<string, mixed>>
	 */
	public function find_blocked_for_month( string $year_month ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->blocked_table}
				  WHERE blocked_date LIKE %s
				  ORDER BY blocked_date ASC, start_time ASC",
				$year_month . '%'
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Return all blocked slots on or after today, for admin display.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_upcoming_blocked(): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->blocked_table}
				  WHERE blocked_date >= %s
				  ORDER BY blocked_date ASC, start_time ASC",
				gmdate( 'Y-m-d' )
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Insert a blocked slot. Returns the new row ID, or false on failure.
	 *
	 * @param array<string, mixed> $data
	 * @return int|false
	 */
	public function add_blocked_slot( array $data ): int|false {
		$result = $this->db->insert(
			$this->blocked_table,
			[
				'blocked_date' => $data['blocked_date'],
				'start_time'   => $data['start_time'],
				'end_time'     => $data['end_time'],
				'reason'       => $data['reason'] ?? null,
			],
			[ '%s', '%s', '%s', '%s' ]
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $this->db->insert_id;
	}

	/**
	 * Delete a blocked slot by ID.
	 */
	public function delete_blocked_slot( int $id ): bool {
		$result = $this->db->delete(
			$this->blocked_table,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Insert multiple blocked slots and link them with a shared series_id.
	 *
	 * All rows are inserted individually; series_id is back-filled in one UPDATE
	 * using the first and last inserted IDs (safe because this is a single-admin
	 * panel with no concurrent writers).
	 *
	 * Note: no DB transaction is used. A mid-batch failure leaves already-inserted
	 * rows as individual slots (series_id = NULL). This is acceptable for a
	 * single-admin tool where DB errors during inserts are extremely rare.
	 *
	 * @param array<int, array<string, mixed>> $slots
	 * @return int|false ID of the first inserted row, or false on failure / empty input.
	 */
	public function add_blocked_slots_batch( array $slots ): int|false {
		if ( empty( $slots ) ) {
			return false;
		}

		$first_id = null;
		$last_id  = null;

		foreach ( $slots as $slot ) {
			$result = $this->db->insert(
				$this->blocked_table,
				[
					'blocked_date' => $slot['blocked_date'],
					'start_time'   => $slot['start_time'],
					'end_time'     => $slot['end_time'],
					'reason'       => $slot['reason'] ?? null,
				],
				[ '%s', '%s', '%s', '%s' ]
			);

			if ( false === $result ) {
				return false;
			}

			$id = (int) $this->db->insert_id;
			if ( null === $first_id ) {
				$first_id = $id;
			}
			$last_id = $id;
		}

		// Back-fill series_id for all inserted rows.
		$this->db->query(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$this->blocked_table} SET series_id = %d WHERE id BETWEEN %d AND %d",
				$first_id,
				$first_id,
				$last_id
			)
		);

		return $first_id;
	}

	/**
	 * Delete all blocked slots belonging to a series.
	 *
	 * @return bool True if at least one row was deleted.
	 */
	public function delete_blocked_series( int $series_id ): bool {
		$result = $this->db->delete(
			$this->blocked_table,
			[ 'series_id' => $series_id ],
			[ '%d' ]
		);

		return false !== $result && $result > 0;
	}
}
