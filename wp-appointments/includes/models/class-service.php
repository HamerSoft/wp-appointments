<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD wrapper for {prefix}appointments_services.
 *
 * Accepts an optional $db parameter so tests can inject a mock wpdb.
 */
class WPAPPT_Model_Service {

	private $db;
	private string $table;

	public function __construct( $db = null ) {
		global $wpdb;
		$this->db    = $db ?? $wpdb;
		$this->table = $this->db->prefix . 'appointments_services';
	}

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	/**
	 * Return all active services ordered by sort_order, then name.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_all_active(): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is hardcoded.
		$rows = $this->db->get_results(
			"SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY sort_order ASC, name ASC",
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Return all services (active and inactive) for the admin list.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_all(): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is hardcoded.
		$rows = $this->db->get_results(
			"SELECT * FROM {$this->table} ORDER BY sort_order ASC, name ASC",
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Return a single service by ID, or null if not found.
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

	// -------------------------------------------------------------------------
	// Write
	// -------------------------------------------------------------------------

	/**
	 * Insert a new service. Returns the new row ID, or false on failure.
	 *
	 * @param array<string, mixed> $data
	 * @return int|false
	 */
	public function create( array $data ): int|false {
		$result = $this->db->insert(
			$this->table,
			[
				'name'          => $data['name'],
				'duration_mins' => (int) $data['duration_mins'],
				'price'         => (float) $data['price'],
				'is_active'     => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
				'sort_order'    => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			],
			[ '%s', '%d', '%f', '%d', '%d' ]
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $this->db->insert_id;
	}

	/**
	 * Update an existing service. Returns true on success.
	 *
	 * @param array<string, mixed> $data
	 */
	public function update( int $id, array $data ): bool {
		$fields  = [];
		$formats = [];

		$allowed = [
			'name'          => '%s',
			'duration_mins' => '%d',
			'price'         => '%f',
			'is_active'     => '%d',
			'sort_order'    => '%d',
		];

		foreach ( $allowed as $key => $format ) {
			if ( array_key_exists( $key, $data ) ) {
				$fields[ $key ] = $data[ $key ];
				$formats[]      = $format;
			}
		}

		if ( empty( $fields ) ) {
			return false;
		}

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
	 * Soft-delete a service by setting is_active = 0.
	 *
	 * Hard delete is avoided because existing bookings reference service_id.
	 */
	public function delete( int $id ): bool {
		return $this->update( $id, [ 'is_active' => 0 ] );
	}
}
