<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Admin;

use WPCalibrate\WatermarkStudio\Support\Capabilities;
use WPCalibrate\WatermarkStudio\Support\Logger;
use WPCalibrate\WatermarkStudio\Watermark\Processor;
use WPCalibrate\WatermarkStudio\Media\RestoreService;
use WPCalibrate\WatermarkStudio\Media\AttachmentRepository;

/**
 * Class MediaActions
 *
 * Integrates single-item manual watermarking, reapplying, and restoring actions into WordPress
 * Media Library list view and attachment edit interfaces.
 */
final class MediaActions {
	public const ACTION_NONCE = 'wpcalibrate_ws_media_action_nonce';

	public function __construct(
		private readonly Processor $processor,
		private readonly RestoreService $restore_service,
		private readonly AttachmentRepository $attachment_repository,
		private readonly Logger $logger,
		private readonly ?\WPCalibrate\WatermarkStudio\Media\OriginalManager $original_manager = null
	) {}

	public function init(): void {
		// List View row actions
		add_filter( 'media_row_actions', array( $this, 'add_row_actions' ), 10, 2 );

		// Bulk actions in Media Library list table
		add_filter( 'bulk_actions-upload', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'display_bulk_notices' ) );

		// Attachment details side-panel
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_fields' ), 10, 2 );

		// AJAX and POST action endpoints
		add_action( 'wp_ajax_wpcalibrate_ws_media_action', array( $this, 'handle_ajax_action' ) );
		add_action( 'admin_post_wpcalibrate_ws_media_action', array( $this, 'handle_post_action' ) );
	}

	/**
	 * Register bulk actions in the Media Library list table dropdown.
	 *
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public function register_bulk_actions( array $actions ): array {
		if ( Capabilities::can_manage_settings() ) {
			$actions['wpcalibrate_restore_bulk']   = __( 'Restore Original Images (Remove Watermarks)', 'wpcalibrate-watermark-studio' );
			$actions['wpcalibrate_watermark_bulk'] = __( 'Apply / Reapply Watermarks', 'wpcalibrate-watermark-studio' );
		}
		return $actions;
	}

	/**
	 * Handle bulk table actions in Media Library list table.
	 *
	 * @param string          $redirect_to
	 * @param string          $doaction
	 * @param array<int, int> $post_ids
	 * @return string
	 */
	public function handle_bulk_actions( string $redirect_to, string $doaction, array $post_ids ): string {
		if ( 'wpcalibrate_restore_bulk' === $doaction ) {
			if ( ! Capabilities::can_manage_settings() ) {
				return $redirect_to;
			}
			$restored_count = 0;
			foreach ( $post_ids as $attachment_id ) {
				$res = $this->restore_service->restore_attachment( (int) $attachment_id );
				if ( true === $res ) {
					$restored_count++;
				}
			}
			return add_query_arg( 'wpcalibrate_bulk_restored', $restored_count, $redirect_to );
		}

		if ( 'wpcalibrate_watermark_bulk' === $doaction ) {
			if ( ! Capabilities::can_manage_settings() ) {
				return $redirect_to;
			}
			$processed_count = 0;
			foreach ( $post_ids as $attachment_id ) {
				$res = $this->processor->process_attachment( (int) $attachment_id, true );
				if ( true === $res ) {
					$processed_count++;
				}
			}
			return add_query_arg( 'wpcalibrate_bulk_watermarked', $processed_count, $redirect_to );
		}

		return $redirect_to;
	}

	/**
	 * Display admin notices after Media Library bulk actions.
	 */
	public function display_bulk_notices(): void {
		if ( isset( $_GET['wpcalibrate_bulk_restored'] ) ) {
			$count = (int) $_GET['wpcalibrate_bulk_restored'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				sprintf( esc_html( _n( 'Successfully restored %d original image without watermarks.', 'Successfully restored %d original images without watermarks.', $count, 'wpcalibrate-watermark-studio' ) ), $count )
			);
		}
		if ( isset( $_GET['wpcalibrate_bulk_watermarked'] ) ) {
			$count = (int) $_GET['wpcalibrate_bulk_watermarked'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				sprintf( esc_html( _n( 'Successfully watermarked %d image.', 'Successfully watermarked %d images.', $count, 'wpcalibrate-watermark-studio' ) ), $count )
			);
		}
	}

	/**
	 * Add custom action links to Media Library table rows.
	 *
	 * @param array<string, string> $actions
	 * @param \WP_Post              $post
	 * @return array<string, string>
	 */
	public function add_row_actions( array $actions, \WP_Post $post ): array {
		if ( ! str_starts_with( (string) $post->post_mime_type, 'image/' ) ) {
			return $actions;
		}

		if ( ! Capabilities::can_watermark_attachment( $post->ID ) ) {
			return $actions;
		}

		$state      = $this->attachment_repository->get_state( $post->ID );
		$has_backup = $state->has_backup || ( null !== $this->original_manager && $this->original_manager->has_backup( $post->ID ) );

		// Restore link: always visible when a backup exists, removing all watermark text and logo!
		if ( $has_backup ) {
			$restore_url = wp_nonce_url(
				admin_url( "admin-post.php?action=wpcalibrate_ws_media_action&sub_action=restore&attachment_id={$post->ID}" ),
				self::ACTION_NONCE . '_' . $post->ID,
				'_ws_nonce'
			);
			$actions['wpcalibrate_restore'] = sprintf(
				'<a href="%s" style="color:#b91c1c; font-weight:600;" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $restore_url ),
				esc_js( __( 'Are you sure you want to restore this image from the protected original backup? All watermarks will be removed.', 'wpcalibrate-watermark-studio' ) ),
				esc_html__( 'Restore Original Image', 'wpcalibrate-watermark-studio' )
			);
		}

		if ( $state->is_watermarked ) {
			$reapply_url = wp_nonce_url(
				admin_url( "admin-post.php?action=wpcalibrate_ws_media_action&sub_action=reapply&attachment_id={$post->ID}" ),
				self::ACTION_NONCE . '_' . $post->ID,
				'_ws_nonce'
			);
			$actions['wpcalibrate_reapply'] = sprintf(
				'<a href="%s" style="color:#0073aa;">%s</a>',
				esc_url( $reapply_url ),
				esc_html__( 'Reapply Watermark', 'wpcalibrate-watermark-studio' )
			);
		} else {
			$apply_url = wp_nonce_url(
				admin_url( "admin-post.php?action=wpcalibrate_ws_media_action&sub_action=apply&attachment_id={$post->ID}" ),
				self::ACTION_NONCE . '_' . $post->ID,
				'_ws_nonce'
			);
			$actions['wpcalibrate_apply'] = sprintf(
				'<a href="%s" style="color:#0073aa; font-weight:600;">%s</a>',
				esc_url( $apply_url ),
				esc_html__( 'Apply Watermark', 'wpcalibrate-watermark-studio' )
			);
		}

		return $actions;
	}

	/**
	 * Add watermark management details and action buttons to attachment edit screens & modals.
	 *
	 * @param array<string, mixed> $form_fields
	 * @param \WP_Post             $post
	 * @return array<string, mixed>
	 */
	public function add_attachment_fields( array $form_fields, \WP_Post $post ): array {
		if ( ! str_starts_with( (string) $post->post_mime_type, 'image/' ) ) {
			return $form_fields;
		}

		$state      = $this->attachment_repository->get_state( $post->ID );
		$has_backup = $state->has_backup || ( null !== $this->original_manager && $this->original_manager->has_backup( $post->ID ) );
		$status     = $state->is_watermarked
			? __( 'Watermarked', 'wpcalibrate-watermark-studio' )
			: __( 'Unwatermarked', 'wpcalibrate-watermark-studio' );

		$details_html = sprintf(
			'<div class="wpcalibrate-media-info"><p><strong>%s:</strong> %s</p>',
			esc_html__( 'Watermark Status', 'wpcalibrate-watermark-studio' ),
			esc_html( $status )
		);

		if ( $has_backup ) {
			$details_html .= sprintf(
				'<p><strong>%s:</strong> %s</p>',
				esc_html__( 'Protected Original Backup', 'wpcalibrate-watermark-studio' ),
				esc_html__( 'Available & Secured', 'wpcalibrate-watermark-studio' )
			);
		}

		if ( Capabilities::can_watermark_attachment( $post->ID ) ) {
			$details_html .= '<div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">';
			if ( $has_backup ) {
				$restore_url = wp_nonce_url(
					admin_url( "admin-post.php?action=wpcalibrate_ws_media_action&sub_action=restore&attachment_id={$post->ID}" ),
					self::ACTION_NONCE . '_' . $post->ID,
					'_ws_nonce'
				);
				$details_html .= sprintf(
					'<a href="%s" class="button button-secondary" style="color:#b91c1c; border-color:#fca5a5;" onclick="return confirm(\'%s\');">%s</a>',
					esc_url( $restore_url ),
					esc_js( __( 'Are you sure you want to restore this image from the protected original backup? All watermarks will be removed.', 'wpcalibrate-watermark-studio' ) ),
					esc_html__( 'Restore Original Image', 'wpcalibrate-watermark-studio' )
				);
			}

			$action_type  = $state->is_watermarked ? 'reapply' : 'apply';
			$action_label = $state->is_watermarked ? __( 'Reapply Watermark', 'wpcalibrate-watermark-studio' ) : __( 'Apply Watermark', 'wpcalibrate-watermark-studio' );
			$action_url   = wp_nonce_url(
				admin_url( "admin-post.php?action=wpcalibrate_ws_media_action&sub_action={$action_type}&attachment_id={$post->ID}" ),
				self::ACTION_NONCE . '_' . $post->ID,
				'_ws_nonce'
			);
			$details_html .= sprintf(
				'<a href="%s" class="button button-primary">%s</a>',
				esc_url( $action_url ),
				esc_html( $action_label )
			);
			$details_html .= '</div>';
		}

		$details_html .= '</div>';

		$form_fields['wpcalibrate_status'] = array(
			'label' => __( 'Watermark Studio', 'wpcalibrate-watermark-studio' ),
			'input' => 'html',
			'html'  => $details_html,
		);

		return $form_fields;
	}

	/**
	 * Handle admin post action triggers.
	 */
	public function handle_post_action(): void {
		$sub_action    = isset( $_GET['sub_action'] ) ? sanitize_key( (string) $_GET['sub_action'] ) : '';
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
		$nonce         = isset( $_GET['_ws_nonce'] ) ? sanitize_text_field( (string) $_GET['_ws_nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, self::ACTION_NONCE . '_' . $attachment_id ) ) {
			wp_die( esc_html__( 'Security check failed. Invalid nonce.', 'wpcalibrate-watermark-studio' ), 403 );
		}

		if ( ! Capabilities::can_watermark_attachment( $attachment_id ) ) {
			wp_die( esc_html__( 'Unauthorized access. You do not have permission to edit this media item.', 'wpcalibrate-watermark-studio' ), 403 );
		}

		$redirect_url = wp_get_referer();
		if ( ! $redirect_url ) {
			$redirect_url = admin_url( 'upload.php' );
		}

		if ( 'apply' === $sub_action || 'reapply' === $sub_action ) {
			$result = $this->processor->process_attachment( $attachment_id, 'reapply' === $sub_action );
			if ( is_wp_error( $result ) ) {
				$redirect_url = add_query_arg( 'wpcalibrate_err', rawurlencode( $result->get_error_message() ), $redirect_url );
			} else {
				$redirect_url = add_query_arg( 'wpcalibrate_msg', 'watermarked', $redirect_url );
			}
		} elseif ( 'restore' === $sub_action ) {
			$result = $this->restore_service->restore_attachment( $attachment_id );
			if ( is_wp_error( $result ) ) {
				$redirect_url = add_query_arg( 'wpcalibrate_err', rawurlencode( $result->get_error_message() ), $redirect_url );
			} else {
				$redirect_url = add_query_arg( 'wpcalibrate_msg', 'restored', $redirect_url );
			}
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle AJAX actions for modal dialogs.
	 */
	public function handle_ajax_action(): void {
		check_ajax_referer( 'wpcalibrate_ws_ajax_nonce', 'nonce' );

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$sub_action    = isset( $_POST['sub_action'] ) ? sanitize_key( (string) $_POST['sub_action'] ) : '';

		if ( ! Capabilities::can_watermark_attachment( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wpcalibrate-watermark-studio' ) ), 403 );
		}

		if ( 'apply' === $sub_action || 'reapply' === $sub_action ) {
			$res = $this->processor->process_attachment( $attachment_id, 'reapply' === $sub_action );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			wp_send_json_success( array( 'message' => __( 'Image watermarked successfully.', 'wpcalibrate-watermark-studio' ) ) );
		} elseif ( 'restore' === $sub_action ) {
			$res = $this->restore_service->restore_attachment( $attachment_id );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			wp_send_json_success( array( 'message' => __( 'Image restored successfully.', 'wpcalibrate-watermark-studio' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Invalid action.', 'wpcalibrate-watermark-studio' ) ) );
	}
}
