<?php
/**
 * Campaign Query Service
 *
 * Read-model helpers for reporting campaign status and available lists. Kept separate
 * from the write-side Campaign_Service so REST and admin read paths share one source of
 * truth without pulling persistence logic into presentation code.
 *
 * @package MSKD\Application
 * @since   1.9.0
 */

namespace MSKD\Application;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Campaign_Query_Service
 */
class Campaign_Query_Service {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Queue table name.
	 *
	 * @var string
	 */
	private $queue_table;

	/**
	 * Campaigns table name.
	 *
	 * @var string
	 */
	private $campaigns_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb            = $wpdb;
		$this->queue_table     = $wpdb->prefix . 'mskd_queue';
		$this->campaigns_table = $wpdb->prefix . 'mskd_campaigns';
	}

	/**
	 * Get a campaign's status together with per-status recipient counts.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array|null Normalized status array, or null when the campaign does not exist.
	 */
	public function get_campaign_status( int $campaign_id ): ?array {
		$campaign = $this->wpdb->get_row(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT id, subject, type, status, total_recipients, scheduled_at, completed_at, created_at
				FROM {$this->campaigns_table} WHERE id = %d",
				$campaign_id
			)
		);

		if ( ! $campaign ) {
			return null;
		}

		return array(
			'id'               => (int) $campaign->id,
			'subject'          => $campaign->subject,
			'type'             => $campaign->type,
			'status'           => $campaign->status,
			'total_recipients' => (int) $campaign->total_recipients,
			'scheduled_at'     => $campaign->scheduled_at,
			'completed_at'     => $campaign->completed_at,
			'created_at'       => $campaign->created_at,
			'counts'           => $this->get_queue_counts( $campaign_id ),
		);
	}

	/**
	 * Get per-status queue counts for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array<string,int>
	 */
	public function get_queue_counts( int $campaign_id ): array {
		$counts = array(
			'pending'    => 0,
			'processing' => 0,
			'sent'       => 0,
			'failed'     => 0,
			'cancelled'  => 0,
		);

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT status, COUNT(*) AS count FROM {$this->queue_table} WHERE campaign_id = %d GROUP BY status",
				$campaign_id
			)
		);

		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row->status ] ) ) {
				$counts[ $row->status ] = (int) $row->count;
			}
		}

		return $counts;
	}

	/**
	 * Get available recipient lists in a normalized, API-friendly shape.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_available_lists(): array {
		$lists  = \MSKD_List_Provider::get_all_lists();
		$result = array();

		foreach ( (array) $lists as $list ) {
			$result[] = array(
				'id'               => (string) $list->id,
				'name'             => $list->name,
				'type'             => 'external' === $list->source ? 'external' : 'database',
				'provider'         => $list->provider,
				'subscriber_count' => (int) \MSKD_List_Provider::get_list_active_subscriber_count( $list ),
			);
		}

		return $result;
	}
}
