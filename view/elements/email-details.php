<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use DeliciousBrains\WP_Offload_SES\Utils;

/* @var array $email */
/* @var array|null $click_data */

$recipient = Utils::maybe_unserialize( $email['email_to'] );
$recipient = is_array( $recipient ) ? implode( ', ', $recipient ) : $recipient;
$sent      = Utils::get_date_and_time( $email['email_created'] );
$clicks    = ! empty( $click_data['email_click_count'] ) ? (int) $click_data['email_click_count'] : 0;
$opens     = ! empty( $email['email_open_count'] ) ? (int) $email['email_open_count'] : 0;
?>
<div class="wposes-email-details">
	<h2><?php echo esc_html( $email['email_subject'] ); ?></h2>

	<table class="wposes-email-details-meta widefat striped">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'To', 'wp-offload-ses' ); ?></th>
				<td><?php echo esc_html( $recipient ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Status', 'wp-offload-ses' ); ?></th>
				<td><?php echo esc_html( ucfirst( $email['email_status'] ) ); ?></td>
			</tr>
			<?php if ( $sent ) : ?>
				<tr>
					<th><?php esc_html_e( 'Sent', 'wp-offload-ses' ); ?></th>
					<td><?php echo esc_html( $sent['date'] . ' ' . $sent['time'] ); ?></td>
				</tr>
			<?php endif; ?>
			<tr>
				<th><?php esc_html_e( 'Opens', 'wp-offload-ses' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $opens ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Clicks', 'wp-offload-ses' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $clicks ) ); ?></td>
			</tr>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Message', 'wp-offload-ses' ); ?></h3>
	<div class="wposes-email-details-body">
		<?php echo wp_kses_post( wpautop( $email['email_message'] ) ); ?>
	</div>
</div>
