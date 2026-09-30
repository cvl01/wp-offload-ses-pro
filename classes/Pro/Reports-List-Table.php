<?php
/**
 * Displays the WP Offload SES engagement (open & click) reports list table.
 *
 * @package WP Offload SES
 * @author  Delicious Brains
 */

namespace DeliciousBrains\WP_Offload_SES\Pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DeliciousBrains\WP_Offload_SES\Utils;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/wp-list-table.php';
}

/**
 * Class Reports_List_Table
 *
 * @since 1.0.0
 */
class Reports_List_Table extends \WP_List_Table {

	/**
	 * The WordPress database object.
	 *
	 * @var \wpdb
	 */
	private $database;

	/**
	 * The emails table.
	 *
	 * @var string
	 */
	private $emails_table;

	/**
	 * The clicks table.
	 *
	 * @var string
	 */
	private $clicks_table;

	/**
	 * Constructs the Reports_List_Table class.
	 */
	public function __construct() {
		global $wpdb;

		$this->database     = $wpdb;
		$this->emails_table = $this->database->base_prefix . 'oses_emails';
		$this->clicks_table = $this->database->base_prefix . 'oses_clicks';

		parent::__construct(
			array(
				'singular' => 'report',
				'plural'   => 'reports',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get the column names for the table.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'date'      => __( 'Date', 'wp-offload-ses' ),
			'subject'   => __( 'Subject', 'wp-offload-ses' ),
			'recipient' => __( 'Recipient', 'wp-offload-ses' ),
			'opens'     => __( 'Opens', 'wp-offload-ses' ),
			'clicks'    => __( 'Clicks', 'wp-offload-ses' ),
		);
	}

	/**
	 * Get the sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'date'      => array( 'date', true ),
			'subject'   => array( 'subject', false ),
			'recipient' => array( 'recipient', false ),
			'opens'     => array( 'opens', false ),
			'clicks'    => array( 'clicks', false ),
		);
	}

	/**
	 * Return the individual data for each column.
	 *
	 * @param array  $item        The row data.
	 * @param string $column_name The column to display.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'opens':
			case 'clicks':
				return number_format_i18n( (int) $item[ $column_name ] );
			default:
				return esc_html( $item[ $column_name ] );
		}
	}

	/**
	 * Handles the date column output.
	 *
	 * @param array $item The row data.
	 *
	 * @return string
	 */
	public function column_date( $item ) {
		$formatted = Utils::get_date_and_time( $item['date'] );

		if ( ! $formatted ) {
			return '';
		}

		return esc_html( $formatted['date'] ) . '<br>' . esc_html( $formatted['time'] );
	}

	/**
	 * Handles the subject column output, linking through to the email details.
	 *
	 * @param array $item The row data.
	 *
	 * @return string
	 */
	public function column_subject( $item ) {
		return '<a href="#activity" class="wposes-view-email" data-email="' . esc_attr( $item['id'] ) . '">' . esc_html( $item['subject'] ) . '</a>';
	}

	/**
	 * Handles the recipient column output.
	 *
	 * @param array $item The row data.
	 *
	 * @return string
	 */
	public function column_recipient( $item ) {
		$recipient = Utils::maybe_unserialize( $item['recipient'] );

		if ( is_array( $recipient ) ) {
			return esc_html( implode( ', ', $recipient ) );
		}

		return esc_html( $recipient );
	}

	/**
	 * Get the WHERE used in SQL.
	 *
	 * @return string
	 */
	private function get_where() {
		$where = array( "email_status = 'sent'" );

		if ( is_multisite() && ! Utils::is_network_admin() ) {
			$where[] = $this->database->prepare( "$this->emails_table.subsite_id = %d", get_current_blog_id() );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only search parameters, sanitized
		$subject   = ! empty( $_REQUEST['subject'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['subject'] ) ) : false;
		$recipient = ! empty( $_REQUEST['recipient'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['recipient'] ) ) : false;
		// phpcs:enable

		if ( $subject ) {
			$where[] = $this->database->prepare( "$this->emails_table.email_subject LIKE %s", '%' . $this->database->esc_like( $subject ) . '%' );
		}

		if ( $recipient ) {
			$where[] = $this->database->prepare( "$this->emails_table.email_to LIKE %s", '%' . $this->database->esc_like( $recipient ) . '%' );
		}

		return 'WHERE ' . implode( ' AND ', $where );
	}

	/**
	 * Get the orderby used in SQL.
	 *
	 * @return string
	 */
	private function get_orderby() {
		$columns = array(
			'date'      => 'email_created',
			'subject'   => 'email_subject',
			'recipient' => 'email_to',
			'opens'     => 'opens',
			'clicks'    => 'clicks',
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only table sorting parameter, sanitized
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'date';

		return $columns[ $orderby ] ?? 'email_created';
	}

	/**
	 * Get the order used in SQL.
	 *
	 * @return string
	 */
	private function get_order() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only table sorting parameter, sanitized
		if ( isset( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) ) {
			return 'ASC';
		}

		return 'DESC';
	}

	/**
	 * Get the total number of sent emails matching the current filters.
	 *
	 * @return int
	 */
	private function get_total_items() {
		$where = $this->get_where();
		$total = $this->database->get_var( "SELECT COUNT(*) FROM {$this->emails_table} $where" );

		return $total ? (int) $total : 0;
	}

	/**
	 * Fetch the emails, along with their aggregate click counts, for the current page.
	 *
	 * @param int $current_page The current page of the list table.
	 * @param int $per_page     The number of items to return per page.
	 *
	 * @return array
	 */
	private function get_data( $current_page, $per_page ) {
		$offset  = ( $current_page - 1 ) * $per_page;
		$where   = $this->get_where();
		$orderby = $this->get_orderby();
		$order   = $this->get_order();

		$query = "SELECT
					e.email_id AS `id`,
					e.email_created AS `date`,
					e.email_subject AS `subject`,
					e.email_to AS `recipient`,
					e.email_open_count AS `opens`,
					COALESCE( c.total_clicks, 0 ) AS `clicks`
				FROM {$this->emails_table} e
				LEFT JOIN (
					SELECT email_id, SUM( email_click_count ) AS total_clicks
					FROM {$this->clicks_table}
					GROUP BY email_id
				) c ON c.email_id = e.email_id
				$where
				ORDER BY $orderby $order
				LIMIT $offset, $per_page";

		return stripslashes_deep( $this->database->get_results( $query, ARRAY_A ) );
	}

	/**
	 * Display the no items message.
	 */
	public function no_items() {
		esc_html_e( 'No sent emails to report on yet.', 'wp-offload-ses' );
	}

	/**
	 * Set up the table's items and pagination.
	 */
	public function prepare_items() {
		$per_page              = 20;
		$current_page          = $this->get_pagenum();
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$total_items           = $this->get_total_items();

		$this->items = $this->get_data( $current_page, $per_page );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}
}
