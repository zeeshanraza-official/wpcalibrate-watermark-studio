<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\WooCommerce;

use WPCalibrate\WatermarkStudio\Settings\Settings;

/**
 * Class Integration
 *
 * Handles optional WooCommerce features, HPOS compatibility declarations,
 * and product media querying without requiring WooCommerce to be active.
 */
final class Integration {
	public function __construct( private readonly Settings $settings ) {}

	/**
	 * Initialize WooCommerce compatibility declarations and filters.
	 */
	public function init(): void {
		// Declare HPOS compatibility cleanly
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
	}

	/**
	 * Check if WooCommerce is installed and active on the current site.
	 */
	public function is_active(): bool {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}

	/**
	 * Declare High-Performance Order Storage (HPOS) compatibility.
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WPCALIBRATE_WATERMARK_STUDIO_FILE,
				true
			);
		}
	}

	/**
	 * Get WooCommerce version string if available.
	 */
	public function get_version(): string {
		if ( defined( 'WC_VERSION' ) ) {
			return (string) WC_VERSION;
		}
		return 'N/A';
	}

	/**
	 * Query attachment IDs linked to WooCommerce products (featured, gallery, variations).
	 *
	 * @param int $limit
	 * @param int $offset
	 * @return array<int, int> List of attachment IDs.
	 */
	public function get_product_attachment_ids( int $limit = 100, int $offset = 0 ): array {
		if ( ! $this->is_active() ) {
			return array();
		}

		global $wpdb;

		$featured   = (bool) $this->settings->get( 'woo_target_featured', true );
		$gallery    = (bool) $this->settings->get( 'woo_target_gallery', true );
		$variations = (bool) $this->settings->get( 'woo_target_variations', true );

		$attachment_ids = array();

		// 1. Featured product images (_thumbnail_id)
		if ( $featured ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$thumb_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE p.post_type = 'product' AND pm.meta_key = '_thumbnail_id' AND pm.meta_value > 0
					LIMIT %d OFFSET %d",
					$limit,
					$offset
				)
			);
			if ( is_array( $thumb_ids ) ) {
				$attachment_ids = array_merge( $attachment_ids, array_map( 'intval', $thumb_ids ) );
			}
		}

		// 2. Product gallery images (_product_image_gallery)
		if ( $gallery ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$galleries = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE p.post_type = 'product' AND pm.meta_key = '_product_image_gallery' AND pm.meta_value != ''
					LIMIT %d OFFSET %d",
					$limit,
					$offset
				)
			);
			if ( is_array( $galleries ) ) {
				foreach ( $galleries as $gallery_csv ) {
					$split = explode( ',', (string) $gallery_csv );
					foreach ( $split as $id_str ) {
						$id = (int) trim( $id_str );
						if ( $id > 0 ) {
							$attachment_ids[] = $id;
						}
					}
				}
			}
		}

		// 3. Product variation images
		if ( $variations ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$var_thumbs = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE p.post_type = 'product_variation' AND pm.meta_key = '_thumbnail_id' AND pm.meta_value > 0
					LIMIT %d OFFSET %d",
					$limit,
					$offset
				)
			);
			if ( is_array( $var_thumbs ) ) {
				$attachment_ids = array_merge( $attachment_ids, array_map( 'intval', $var_thumbs ) );
			}
		}

		return array_values( array_unique( array_filter( $attachment_ids ) ) );
	}
}
