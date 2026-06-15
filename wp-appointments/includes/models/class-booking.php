<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD wrapper for {prefix}appointments_bookings and {prefix}appointments_audit_log.
 *
 * Status transitions always go through update_status() so the audit log
 * is written consistently and tokens are nulled on cancellation.
 */
class WPAPPT_Model_Booking {

	/** Valid booking statuses — used for whitelisting. */
	const STATUSES = [ 'pending', 'confirmed', 'cancelled' ];

	private $db;
	private string $table;
	private string $audit_table;

	/** Whitelisted columns for ORDER BY in find_all(). */
	private const SORTABLE_COLUMNS = [
		'id',
		'appointment_date',
		'created_at',
		'status',
		'customer_name',
	];

	public function __construct( $db = null ) {
		global $wpdb;
		$this->db          = $db ?? $wpdb;
		$this->table       = $this->db->prefix . 'appointments_bookings';
		$this->audit_table = $this->db->prefix . 'appointments_audit_log';
	}

	// =========================================================================
	// Read
	// =========================================================================

	/**
	 * Return a single booking by ID, or null if not found.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Return a booking by its reschedule token hash.
	 *
	 * The query enforces that the token is not null and has not expired,
	 * preventing matches against nulled tokens or stale links.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find_by_token_hash( string $hash ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table}
				  WHERE reschedule_token IS NOT NULL
				    AND reschedule_token = %s
				    AND token_expires_at > %s",
				$hash,
				gmdate( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Return a paginated, filtered list of bookings for the admin panel.
	 *
	 * Accepted $args keys:
	 *   status   string  Filter by status ('' = all).
	 *   search   string  Partial match on customer_name or customer_email.
	 *   orderby  string  Column to sort by (whitelisted).
	 *   order    string  'ASC' or 'DESC'.
	 *   per_page int     Rows per page (default 20).
	 *   page     int     1-based page number (default 1).
	 *
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public function find_all( array $args = [] ): array {
		[ $where, $values ] = $this->build_where( $args );
		[ $order_sql ]      = $this->build_order( $args );
		[ $limit_sql, $limit_values ] = $this->build_limit( $args );

		$all_values = array_merge( $values, $limit_values );

		if ( ! empty( $all_values ) ) {
			$sql = $this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table}{$where}{$order_sql}{$limit_sql}",
				...$all_values
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT * FROM {$this->table}{$where}{$order_sql}{$limit_sql}";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->db->get_results( $sql, ARRAY_A );

		return $rows ?: [];
	}

	/**
	 * Return the total row count matching the same filters (for pagination).
	 *
	 * @param array<string, mixed> $args
	 */
	public function count_all( array $args = [] ): int {
		[ $where, $values ] = $this->build_where( $args );

		if ( ! empty( $values ) ) {
			$sql = $this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->table}{$where}",
				...$values
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT COUNT(*) FROM {$this->table}{$where}";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->db->get_var( $sql );
	}

	/**
	 * Return all non-cancelled bookings for a specific date.
	 *
	 * Used by the availability service to load the day's obstacles in one query
	 * rather than hitting the DB once per candidate slot.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_by_date( string $date ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT start_time, end_time FROM {$this->table}
				  WHERE appointment_date = %s
				    AND status != 'cancelled'",
				$date
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Return non-cancelled bookings for a given month.
	 *
	 * @param  string $year_month 'YYYY-MM'
	 * @return array<int, array<string, mixed>>
	 */
	public function find_by_month( string $year_month ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT appointment_date, start_time, end_time FROM {$this->table}
				  WHERE appointment_date LIKE %s
				    AND status != 'cancelled'",
				$year_month . '%'
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Return confirmed bookings whose appointment date is exactly $days_ahead
	 * days from today and for which a reminder has not yet been sent.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_due_for_reminder( int $days_ahead ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table}
				  WHERE status = 'confirmed'
				    AND appointment_date = DATE(NOW() + INTERVAL %d DAY)
				    AND reminder_sent_at IS NULL",
				$days_ahead
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Stamp reminder_sent_at on a booking so it is not reminded again.
	 */
	public function mark_reminder_sent( int $id ): bool {
		$result = $this->db->update(
			$this->table,
			[
				'reminder_sent_at' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at'       => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Count bookings that overlap a given date + time range.
	 *
	 * Used by the availability service to determine whether a slot is taken.
	 * Excludes a specific booking ID to allow a reschedule to "release" its
	 * own current slot before checking the new one.
	 */
	public function count_overlapping(
		string $date,
		string $start_time,
		string $end_time,
		int $exclude_id = 0
	): int {
		return (int) $this->db->get_var(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->table}
				  WHERE appointment_date = %s
				    AND status != 'cancelled'
				    AND id != %d
				    AND start_time < %s
				    AND end_time   > %s",
				$date,
				$exclude_id,
				$end_time,
				$start_time
			)
		);
	}

	/**
	 * Same as count_overlapping() but appends FOR UPDATE to lock matching rows
	 * for the duration of the calling transaction, preventing concurrent inserts
	 * for the same slot.
	 *
	 * Must be called inside an active InnoDB transaction.
	 */
	public function count_overlapping_for_update(
		string $date,
		string $start_time,
		string $end_time,
		int $exclude_id = 0
	): int {
		return (int) $this->db->get_var(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->table}
				  WHERE appointment_date = %s
				    AND status != 'cancelled'
				    AND id != %d
				    AND start_time < %s
				    AND end_time   > %s
				  FOR UPDATE",
				$date,
				$exclude_id,
				$end_time,
				$start_time
			)
		);
	}

	// =========================================================================
	// Write
	// =========================================================================

	/**
	 * Insert a new booking. Returns the new row ID, or false on failure.
	 *
	 * @param array<string, mixed> $data
	 * @return int|false
	 */
	public function create( array $data ): int|false {
		$result = $this->db->insert(
			$this->table,
			[
				'service_id'       => (int) $data['service_id'],
				'status'           => 'pending',
				'appointment_date' => $data['appointment_date'],
				'start_time'       => $data['start_time'],
				'end_time'         => $data['end_time'],
				'customer_name'    => $data['customer_name'],
				'customer_email'   => $data['customer_email'],
				'customer_phone'   => $data['customer_phone'],
				'injury_notes'     => $data['injury_notes'] ?? null,
				'comments'         => $data['comments'] ?? null,
				'created_at'       => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $this->db->insert_id;
	}

	/**
	 * Transition a booking to a new status.
	 *
	 * - Writes an audit log entry.
	 * - Nulls the reschedule token when status becomes 'cancelled'.
	 *
	 * Returns false if the status is invalid or the booking is not found.
	 */
	public function update_status( int $id, string $new_status, string $actor ): bool {
		if ( ! in_array( $new_status, self::STATUSES, true ) ) {
			return false;
		}

		$booking = $this->find( $id );
		if ( null === $booking ) {
			return false;
		}

		$old_status = $booking['status'];

		$fields  = [
			'status'     => $new_status,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		];
		$formats = [ '%s', '%s' ];

		// Null the token immediately on cancellation so outstanding
		// reschedule links cannot be used after a booking is cancelled.
		if ( 'cancelled' === $new_status ) {
			$fields['reschedule_token']  = null;
			$fields['token_expires_at']  = null;
			$formats[]                   = '%s';
			$formats[]                   = '%s';
		}

		$result = $this->db->update(
			$this->table,
			$fields,
			[ 'id' => $id ],
			$formats,
			[ '%d' ]
		);

		if ( false === $result ) {
			return false;
		}

		$this->write_audit( $id, $old_status, $new_status, $actor );

		return true;
	}

	/**
	 * Update arbitrary booking fields (e.g. date/time on reschedule, admin notes).
	 *
	 * Does not touch status — use update_status() for state transitions.
	 *
	 * @param array<string, mixed> $data
	 */
	public function update( int $id, array $data ): bool {
		$allowed = [
			'appointment_date' => '%s',
			'start_time'       => '%s',
			'end_time'         => '%s',
			'admin_notes'      => '%s',
		];

		$fields  = [];
		$formats = [];

		foreach ( $allowed as $key => $format ) {
			if ( array_key_exists( $key, $data ) ) {
				$fields[ $key ] = $data[ $key ];
				$formats[]      = $format;
			}
		}

		if ( empty( $fields ) ) {
			return false;
		}

		$fields['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$formats[]            = '%s';

		$result = $this->db->update(
			$this->table,
			$fields,
			[ 'id' => $id ],
			$formats,
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Hard-delete a booking row by ID.
	 */
	public function delete( int $id ): bool {
		$result = $this->db->delete(
			$this->table,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Store a new reschedule token hash on a booking.
	 *
	 * The caller (WPAPPT_Service_Token) is responsible for computing the hash
	 * and formatting the expiry datetime string.
	 */
	public function set_reschedule_token( int $id, string $token_hash, string $expires_at ): bool {
		$result = $this->db->update(
			$this->table,
			[
				'reschedule_token' => $token_hash,
				'token_expires_at' => $expires_at,
				'updated_at'       => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	// =========================================================================
	// Query builders (private helpers)
	// =========================================================================

	/**
	 * Build the WHERE clause and its placeholder values.
	 *
	 * @param array<string, mixed> $args
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function build_where( array $args ): array {
		$conditions = [];
		$values     = [];

		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$conditions[] = 'status = %s';
			$values[]     = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like         = '%' . $this->db->esc_like( $args['search'] ) . '%';
			$conditions[] = '(customer_name LIKE %s OR customer_email LIKE %s)';
			$values[]     = $like;
			$values[]     = $like;
		}

		$where = $conditions ? ' WHERE ' . implode( ' AND ', $conditions ) : '';

		return [ $where, $values ];
	}

	/**
	 * Build a safe ORDER BY clause from args.
	 *
	 * Column is whitelisted; direction is forced to ASC or DESC.
	 *
	 * @param array<string, mixed> $args
	 * @return array{0: string}
	 */
	private function build_order( array $args ): array {
		$col   = in_array( $args['orderby'] ?? '', self::SORTABLE_COLUMNS, true )
			? $args['orderby']
			: 'appointment_date';

		$dir   = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		return [ " ORDER BY {$col} {$dir}" ];
	}

	/**
	 * Build LIMIT / OFFSET clause and its placeholder values.
	 *
	 * @param array<string, mixed> $args
	 * @return array{0: string, 1: array<int, int>}
	 */
	private function build_limit( array $args ): array {
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $args['page']     ?? 1  ) );
		$offset   = ( $page - 1 ) * $per_page;

		return [ ' LIMIT %d OFFSET %d', [ $per_page, $offset ] ];
	}

	// =========================================================================
	// Audit log
	// =========================================================================

	/**
	 * Write a status-change entry to the audit log.
	 *
	 * Failures are non-fatal — the primary update has already succeeded.
	 * Errors are written to the PHP error log for visibility.
	 */
	private function write_audit( int $booking_id, string $old_status, string $new_status, string $actor ): void {
		$result = $this->db->insert(
			$this->audit_table,
			[
				'booking_id' => $booking_id,
				'old_status' => $old_status,
				'new_status' => $new_status,
				'actor'      => $actor,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "WPAPPT audit log insert failed for booking #{$booking_id}: {$this->db->last_error}" );
		}
	}
}
