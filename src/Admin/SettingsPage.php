<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Admin;

use WPCalibrate\WatermarkStudio\Settings\Settings;
use WPCalibrate\WatermarkStudio\Settings\Defaults;
use WPCalibrate\WatermarkStudio\Settings\Sanitizer;
use WPCalibrate\WatermarkStudio\License\LicenseManager;
use WPCalibrate\WatermarkStudio\WooCommerce\Integration as WooIntegration;
use WPCalibrate\WatermarkStudio\Media\ImageSizeRegistry;
use WPCalibrate\WatermarkStudio\Support\Capabilities;

/**
 * Class SettingsPage
 *
 * Renders the multi-tabbed administration settings console, enqueues scoped admin assets,
 * and handles license activation/deactivation triggers.
 */
final class SettingsPage {
	public function __construct(
		private readonly Settings $settings,
		private readonly LicenseManager $license_manager,
		private readonly WooIntegration $woo_integration
	) {}

	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wpcalibrate_watermark_studio_render_settings_page', array( $this, 'render' ) );

		// License action hooks
		add_action( 'admin_post_wpcalibrate_ws_activate_license', array( $this, 'handle_activate_license' ) );
		add_action( 'admin_post_wpcalibrate_ws_deactivate_license', array( $this, 'handle_deactivate_license' ) );
	}

	/**
	 * Register plugin settings with WordPress Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			Defaults::SETTINGS_GROUP,
			Defaults::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Sanitizer::class, 'sanitize' ),
				'default'           => Defaults::get_all(),
			)
		);
	}

	/**
	 * Enqueue admin stylesheets and scripts strictly on plugin screens.
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only load assets on our specific menu pages
		if ( ! str_contains( $hook_suffix, 'wpcalibrate' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'wpcalibrate-ws-admin',
			WPCALIBRATE_WATERMARK_STUDIO_URL . 'assets/css/admin.css',
			array(),
			WPCALIBRATE_WATERMARK_STUDIO_VERSION
		);

		wp_enqueue_script(
			'wpcalibrate-ws-preview',
			WPCALIBRATE_WATERMARK_STUDIO_URL . 'assets/js/preview.js',
			array( 'jquery' ),
			WPCALIBRATE_WATERMARK_STUDIO_VERSION,
			true
		);

		wp_enqueue_script(
			'wpcalibrate-ws-admin',
			WPCALIBRATE_WATERMARK_STUDIO_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker', 'wpcalibrate-ws-preview' ),
			WPCALIBRATE_WATERMARK_STUDIO_VERSION,
			true
		);

		wp_localize_script(
			'wpcalibrate-ws-admin',
			'wpcalibrateWsL10n',
			array(
				'selectWatermark' => __( 'Select Watermark Image', 'wpcalibrate-watermark-studio' ),
				'useAsWatermark'  => __( 'Use as Watermark', 'wpcalibrate-watermark-studio' ),
				'startQueue'      => __( 'Start Bulk Queue', 'wpcalibrate-watermark-studio' ),
				'starting'        => __( 'Starting...', 'wpcalibrate-watermark-studio' ),
				'cancelQueue'     => __( 'Cancel Queue', 'wpcalibrate-watermark-studio' ),
				'cancelling'      => __( 'Cancelling...', 'wpcalibrate-watermark-studio' ),
				'confirmCancel'   => __( 'Are you sure you want to stop processing?', 'wpcalibrate-watermark-studio' ),
			)
		);
	}

	/**
	 * Render settings console.
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage_settings() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-watermark-studio' ), 403 );
		}

		$options        = $this->settings->all();
		$positions      = Defaults::POSITIONS;
		$registered_sizes = ImageSizeRegistry::get_registered_sizes();
		$license_status = $this->license_manager->get_status();
		$masked_key     = $this->license_manager->get_masked_key();
		?>
		<div class="wrap wpcalibrate-admin-wrap">
			<!-- Header -->
			<div class="wpcalibrate-header">
				<div class="wpcalibrate-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_WATERMARK_STUDIO_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-logo" />
					<h1><?php esc_html_e( 'WPCalibrate Watermark Studio', 'wpcalibrate-watermark-studio' ); ?></h1>
				</div>
				<div class="wpcalibrate-header-meta">
					<span class="badge badge-info">v<?php echo esc_html( WPCALIBRATE_WATERMARK_STUDIO_VERSION ); ?></span>
				</div>
			</div>

			<?php settings_errors( Defaults::SETTINGS_GROUP ); ?>

			<!-- Tab Navigation -->
			<nav class="wpcalibrate-nav-tabs" aria-label="<?php esc_attr_e( 'Settings Navigation', 'wpcalibrate-watermark-studio' ); ?>">
				<a href="#general" class="wpcalibrate-tab-link active" data-tab="general"><?php esc_html_e( 'General', 'wpcalibrate-watermark-studio' ); ?></a>
				<a href="#image" class="wpcalibrate-tab-link" data-tab="image"><?php esc_html_e( 'Image Watermark', 'wpcalibrate-watermark-studio' ); ?></a>
				<a href="#text" class="wpcalibrate-tab-link" data-tab="text"><?php esc_html_e( 'Text Watermark', 'wpcalibrate-watermark-studio' ); ?></a>
				<a href="#preview" class="wpcalibrate-tab-link" data-tab="preview"><?php esc_html_e( 'Live Preview', 'wpcalibrate-watermark-studio' ); ?></a>
				<a href="#sizes" class="wpcalibrate-tab-link" data-tab="sizes"><?php esc_html_e( 'Image Sizes', 'wpcalibrate-watermark-studio' ); ?></a>
				<a href="#automation" class="wpcalibrate-tab-link" data-tab="automation"><?php esc_html_e( 'Automation', 'wpcalibrate-watermark-studio' ); ?></a>
				<a href="#woocommerce" class="wpcalibrate-tab-link" data-tab="woocommerce"><?php esc_html_e( 'WooCommerce', 'wpcalibrate-watermark-studio' ); ?></a>
				<a href="#license" class="wpcalibrate-tab-link" data-tab="license"><?php esc_html_e( 'License', 'wpcalibrate-watermark-studio' ); ?></a>
				<a href="#support" class="wpcalibrate-tab-link" data-tab="support"><?php esc_html_e( 'Support', 'wpcalibrate-watermark-studio' ); ?></a>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( Defaults::SETTINGS_GROUP ); ?>

				<!-- TAB: GENERAL -->
				<div id="tab-general" class="wpcalibrate-tab-panel active">
					<div class="wpcalibrate-card">
						<h2><?php esc_html_e( 'General Configuration', 'wpcalibrate-watermark-studio' ); ?></h2>
						<table class="wpcalibrate-form-table">
							<tr>
								<th scope="row"><label for="enable_automatic"><?php esc_html_e( 'Automatic Watermarking', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<label>
										<input type="checkbox" id="enable_automatic" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[enable_automatic]" value="1" <?php checked( $options['enable_automatic'] ); ?> />
										<?php esc_html_e( 'Automatically apply watermarks to new image uploads', 'wpcalibrate-watermark-studio' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Watermark Mode', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<?php foreach ( Defaults::MODES as $mode_key => $mode_label ) : ?>
										<label style="margin-right:20px; display:inline-block;">
											<input type="radio" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[watermark_mode]" value="<?php echo esc_attr( $mode_key ); ?>" <?php checked( $options['watermark_mode'], $mode_key ); ?> />
											<?php echo esc_html( $mode_label ); ?>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Minimum Source Dimensions', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<label>
										<?php esc_html_e( 'Width:', 'wpcalibrate-watermark-studio' ); ?>
										<input type="number" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[min_source_width]" value="<?php echo esc_attr( (string) $options['min_source_width'] ); ?>" min="0" /> px
									</label>
									&nbsp;&nbsp;
									<label>
										<?php esc_html_e( 'Height:', 'wpcalibrate-watermark-studio' ); ?>
										<input type="number" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[min_source_height]" value="<?php echo esc_attr( (string) $options['min_source_height'] ); ?>" min="0" /> px
									</label>
									<p class="description"><?php esc_html_e( 'Images smaller than these dimensions will be safely skipped to avoid distortion.', 'wpcalibrate-watermark-studio' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Supported File Types', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<?php
									$mimes = array(
										'image/jpeg' => 'JPEG (.jpg, .jpeg)',
										'image/png'  => 'PNG (.png)',
										'image/webp' => 'WebP (.webp)',
										'image/avif' => 'AVIF (.avif - if supported by editor)',
									);
									foreach ( $mimes as $mime_val => $mime_label ) :
										?>
										<label style="display:block; margin-bottom:6px;">
											<input type="checkbox" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[allowed_mime_types][]" value="<?php echo esc_attr( $mime_val ); ?>" <?php checked( in_array( $mime_val, (array) $options['allowed_mime_types'], true ) ); ?> />
											<?php echo esc_html( $mime_label ); ?>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Original File Protection', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<span class="badge badge-success"><?php esc_html_e( 'Always Active (Protected)', 'wpcalibrate-watermark-studio' ); ?></span>
									<p class="description">
										<?php esc_html_e( 'Untouched originals are automatically backed up in a secure, non-browsable directory prior to modification, ensuring zero data loss and clean restorations.', 'wpcalibrate-watermark-studio' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Diagnostic Logging', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[logging_enabled]" value="1" <?php checked( $options['logging_enabled'] ); ?> />
										<?php esc_html_e( 'Enable detailed diagnostic logs in Diagnostics tab', 'wpcalibrate-watermark-studio' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Data Retention on Uninstall', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<label style="color:#b91c1c;">
										<input type="checkbox" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[uninstall_cleanup]" value="1" <?php checked( $options['uninstall_cleanup'] ); ?> />
										<strong><?php esc_html_e( 'Delete all plugin settings and backup originals upon plugin deletion (Warning: Irreversible)', 'wpcalibrate-watermark-studio' ); ?></strong>
									</label>
									<p class="description"><?php esc_html_e( 'Defaults to unchecked so clean originals and configuration remain safe even if uninstalled.', 'wpcalibrate-watermark-studio' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- TAB: IMAGE WATERMARK -->
				<div id="tab-image" class="wpcalibrate-tab-panel">
					<div class="wpcalibrate-card">
						<h2><?php esc_html_e( 'Image / Logo Watermark Settings', 'wpcalibrate-watermark-studio' ); ?></h2>
						<table class="wpcalibrate-form-table">
							<tr>
								<th scope="row"><label><?php esc_html_e( 'Watermark Image', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<input type="hidden" id="input-image-id" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[image_attachment_id]" value="<?php echo esc_attr( (string) $options['image_attachment_id'] ); ?>" />
									<input type="hidden" id="input-image-url" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[image_url]" value="<?php echo esc_attr( (string) $options['image_url'] ); ?>" />

									<div style="margin-bottom:12px;">
										<img id="watermark-image-preview-thumb" src="<?php echo esc_url( $options['image_url'] ); ?>" style="max-height:80px; max-width:200px; <?php echo empty( $options['image_url'] ) ? 'display:none;' : ''; ?> background:#334155; padding:6px; border-radius:4px;" alt="Logo" />
									</div>

									<button type="button" class="button button-secondary" id="btn-select-watermark-image">
										<?php esc_html_e( 'Select Image from Library', 'wpcalibrate-watermark-studio' ); ?>
									</button>
									<button type="button" class="button button-link-delete" id="btn-remove-watermark-image" style="<?php echo empty( $options['image_url'] ) ? 'display:none;' : ''; ?>">
										<?php esc_html_e( 'Remove', 'wpcalibrate-watermark-studio' ); ?>
									</button>
									<p class="description"><?php esc_html_e( 'Recommended: Transparent PNG or WebP logo.', 'wpcalibrate-watermark-studio' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="input-image-relative-width"><?php esc_html_e( 'Relative Width (%)', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<input type="number" id="input-image-relative-width" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[image_relative_width]" value="<?php echo esc_attr( (string) $options['image_relative_width'] ); ?>" min="1" max="100" /> %
									<p class="description"><?php esc_html_e( 'Percentage of the destination image width.', 'wpcalibrate-watermark-studio' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="input-image-max-width"><?php esc_html_e( 'Max Pixel Width (px)', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<input type="number" id="input-image-max-width" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[image_max_width_px]" value="<?php echo esc_attr( (string) $options['image_max_width_px'] ); ?>" min="0" max="4000" /> px
									<p class="description"><?php esc_html_e( 'Maximum watermark width in pixels (0 for unlimited; constrained only by relative width).', 'wpcalibrate-watermark-studio' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="input-image-opacity"><?php esc_html_e( 'Opacity (%)', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<input type="number" id="input-image-opacity" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[image_opacity]" value="<?php echo esc_attr( (string) $options['image_opacity'] ); ?>" min="0" max="100" /> %
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="input-image-position"><?php esc_html_e( 'Position', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<select id="input-image-position" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[image_position]">
										<?php foreach ( $positions as $pos_key => $pos_label ) : ?>
											<option value="<?php echo esc_attr( $pos_key ); ?>" <?php selected( $options['image_position'], $pos_key ); ?>>
												<?php echo esc_html( $pos_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Offsets (X / Y)', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<label>
										X: <input type="number" id="input-image-offset-x" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[image_offset_x]" value="<?php echo esc_attr( (string) $options['image_offset_x'] ); ?>" /> px
									</label>
									&nbsp;&nbsp;
									<label>
										Y: <input type="number" id="input-image-offset-y" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[image_offset_y]" value="<?php echo esc_attr( (string) $options['image_offset_y'] ); ?>" /> px
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Scaling Constraints', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<label style="display:block; margin-bottom:6px;">
										<input type="checkbox" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[image_maintain_aspect_ratio]" value="1" <?php checked( $options['image_maintain_aspect_ratio'] ); ?> />
										<?php esc_html_e( 'Maintain original aspect ratio', 'wpcalibrate-watermark-studio' ); ?>
									</label>
									<label style="display:block;">
										<input type="checkbox" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[image_prevent_upscale]" value="1" <?php checked( $options['image_prevent_upscale'] ); ?> />
										<?php esc_html_e( 'Do not upscale watermark beyond natural dimensions', 'wpcalibrate-watermark-studio' ); ?>
									</label>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- TAB: TEXT WATERMARK -->
				<div id="tab-text" class="wpcalibrate-tab-panel">
					<div class="wpcalibrate-card">
						<h2><?php esc_html_e( 'Text Watermark Settings', 'wpcalibrate-watermark-studio' ); ?></h2>
						<table class="wpcalibrate-form-table">
							<tr>
								<th scope="row"><label for="input-text-content"><?php esc_html_e( 'Watermark Text', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<input type="text" id="input-text-content" class="regular-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[text_content]" value="<?php echo esc_attr( (string) $options['text_content'] ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="input-text-strategy"><?php esc_html_e( 'Font Size Strategy', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<select id="input-text-strategy" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[text_font_size_strategy]">
										<option value="proportional" <?php selected( $options['text_font_size_strategy'], 'proportional' ); ?>><?php esc_html_e( 'Proportional (% of Image Width)', 'wpcalibrate-watermark-studio' ); ?></option>
										<option value="fixed" <?php selected( $options['text_font_size_strategy'], 'fixed' ); ?>><?php esc_html_e( 'Fixed Pixel Size', 'wpcalibrate-watermark-studio' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Scale / Size', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<span id="wrapper-text-scale" style="margin-right:20px;">
										<label>
											<?php esc_html_e( 'Proportional Scale:', 'wpcalibrate-watermark-studio' ); ?>
											<input type="number" id="input-text-scale" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[text_proportional_scale]" value="<?php echo esc_attr( (string) $options['text_proportional_scale'] ); ?>" min="1" max="50" /> %
										</label>
									</span>
									<span id="wrapper-text-size">
										<label>
											<?php esc_html_e( 'Fixed Size:', 'wpcalibrate-watermark-studio' ); ?>
											<input type="number" id="input-text-size" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[text_font_size]" value="<?php echo esc_attr( (string) $options['text_font_size'] ); ?>" min="6" max="250" /> pt
										</label>
									</span>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="input-text-color"><?php esc_html_e( 'Text Color', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<input type="text" id="input-text-color" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[text_color]" value="<?php echo esc_attr( (string) $options['text_color'] ); ?>" class="color-picker-input" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="input-text-opacity"><?php esc_html_e( 'Opacity (%)', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<input type="number" id="input-text-opacity" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[text_opacity]" value="<?php echo esc_attr( (string) $options['text_opacity'] ); ?>" min="0" max="100" /> %
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="input-text-position"><?php esc_html_e( 'Position', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<select id="input-text-position" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[text_position]">
										<?php foreach ( $positions as $pos_key => $pos_label ) : ?>
											<option value="<?php echo esc_attr( $pos_key ); ?>" <?php selected( $options['text_position'], $pos_key ); ?>>
												<?php echo esc_html( $pos_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Offsets (X / Y)', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<label>
										X: <input type="number" id="input-text-offset-x" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[text_offset_x]" value="<?php echo esc_attr( (string) $options['text_offset_x'] ); ?>" /> px
									</label>
									&nbsp;&nbsp;
									<label>
										Y: <input type="number" id="input-text-offset-y" class="small-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[text_offset_y]" value="<?php echo esc_attr( (string) $options['text_offset_y'] ); ?>" /> px
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Text Shadow', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<label style="margin-right:20px;">
										<input type="checkbox" id="input-text-shadow" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[text_shadow_enabled]" value="1" <?php checked( $options['text_shadow_enabled'] ); ?> />
										<?php esc_html_e( 'Enable contrasting drop shadow', 'wpcalibrate-watermark-studio' ); ?>
									</label>
									<span id="wrapper-text-shadow-color" style="display:inline-block; vertical-align:middle;">
										<label>
											<?php esc_html_e( 'Shadow Color:', 'wpcalibrate-watermark-studio' ); ?>
											<input type="text" id="input-text-shadow-color" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[text_shadow_color]" value="<?php echo esc_attr( (string) $options['text_shadow_color'] ); ?>" class="color-picker-input" />
										</label>
									</span>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Typeface Engine', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<code>Outfit (Bundled SIL Open Font License)</code>
									<p class="description"><?php esc_html_e( 'Uses the bundled redistributable typeface to ensure identical, crisp server-side typography regardless of server OS.', 'wpcalibrate-watermark-studio' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- TAB: LIVE PREVIEW -->
				<div id="tab-preview" class="wpcalibrate-tab-panel">
					<div class="wpcalibrate-card">
						<h2><?php esc_html_e( 'Live Watermark Preview', 'wpcalibrate-watermark-studio' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Simulates watermark positioning, scaling, opacity, and combined image + text rendering in real time.', 'wpcalibrate-watermark-studio' ); ?></p>

						<div class="wpcalibrate-preview-wrapper">
							<div class="wpcalibrate-preview-header">
								<span><?php esc_html_e( 'Interactive Canvas Approximation (800 × 533)', 'wpcalibrate-watermark-studio' ); ?></span>
								<span id="preview-mode-indicator"><?php echo esc_html( strtoupper( $options['watermark_mode'] ) ); ?></span>
							</div>

							<div class="wpcalibrate-preview-stage" id="wpcalibrate-preview-stage" style="background-image:url('<?php echo esc_url( WPCALIBRATE_WATERMARK_STUDIO_URL . 'assets/images/sample.jpg' ); ?>');">
								<!-- Image Layer -->
								<div class="preview-layer-image" id="preview-layer-image">
									<img id="preview-layer-img-tag" src="<?php echo esc_url( $options['image_url'] ); ?>" alt="Watermark" />
								</div>
								<!-- Text Layer -->
								<div class="preview-layer-text" id="preview-layer-text">
									<?php echo esc_html( $options['text_content'] ); ?>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- TAB: IMAGE SIZES -->
				<div id="tab-sizes" class="wpcalibrate-tab-panel">
					<div class="wpcalibrate-card">
						<h2><?php esc_html_e( 'Target Intermediate Image Sizes', 'wpcalibrate-watermark-studio' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'Select which generated image sizes should receive watermarks. Full-size master can optionally be included or excluded.', 'wpcalibrate-watermark-studio' ); ?>
						</p>

						<table class="wpcalibrate-form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Full Size Image', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[include_full_size]" value="1" <?php checked( $options['include_full_size'] ); ?> />
										<strong><?php esc_html_e( 'Include WordPress Full-Size (Master) Image', 'wpcalibrate-watermark-studio' ); ?></strong>
									</label>
									<p class="description"><?php esc_html_e( 'Note: An untouched copy is always backed up first. If disabled, full-size remains clean and only sub-sizes are watermarked.', 'wpcalibrate-watermark-studio' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Registered Sub-Sizes', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<?php foreach ( $registered_sizes as $size_key => $size_meta ) :
										if ( 'full' === $size_key ) continue;
										$dim = ( $size_meta['width'] > 0 || $size_meta['height'] > 0 )
											? "({$size_meta['width']} × {$size_meta['height']}" . ( $size_meta['crop'] ? ', cropped' : '' ) . ')'
											: '(dynamic)';
										?>
										<label style="display:block; margin-bottom:8px;">
											<input type="checkbox" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[target_sizes][]" value="<?php echo esc_attr( $size_key ); ?>" <?php checked( in_array( $size_key, (array) $options['target_sizes'], true ) ); ?> />
											<strong><?php echo esc_html( $size_meta['name'] ); ?></strong> <code><?php echo esc_html( $size_key ); ?></code> <span style="color:#64748b;"><?php echo esc_html( $dim ); ?></span>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- TAB: AUTOMATION -->
				<div id="tab-automation" class="wpcalibrate-tab-panel">
					<div class="wpcalibrate-card">
						<h2><?php esc_html_e( 'Automation & Context Filtering', 'wpcalibrate-watermark-studio' ); ?></h2>
						<table class="wpcalibrate-form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Context Scope', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<label style="display:block; margin-bottom:8px;">
										<input type="radio" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[auto_target_context]" value="all" <?php checked( $options['auto_target_context'], 'all' ); ?> />
										<?php esc_html_e( 'All eligible Media Library images', 'wpcalibrate-watermark-studio' ); ?>
									</label>
									<label style="display:block; margin-bottom:8px;">
										<input type="radio" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[auto_target_context]" value="post_types" <?php checked( $options['auto_target_context'], 'post_types' ); ?> />
										<?php esc_html_e( 'Only images attached to specific post types', 'wpcalibrate-watermark-studio' ); ?>
									</label>
									<?php if ( class_exists( 'WooCommerce' ) ) : ?>
										<label style="display:block;">
											<input type="radio" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[auto_target_context]" value="woocommerce_products" <?php checked( $options['auto_target_context'], 'woocommerce_products' ); ?> />
											<?php esc_html_e( 'Only WooCommerce product images', 'wpcalibrate-watermark-studio' ); ?>
										</label>
									<?php endif; ?>
								</td>
							</tr>
							<tr id="row-auto-post-types">
								<th scope="row"><?php esc_html_e( 'Restricted Post Types', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<?php
									$post_types = get_post_types( array( 'public' => true ), 'objects' );
									foreach ( $post_types as $pt ) :
										if ( 'attachment' === $pt->name ) continue;
										?>
										<label style="margin-right:16px; display:inline-block;">
											<input type="checkbox" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[auto_post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $options['auto_post_types'], true ) ); ?> />
											<?php echo esc_html( $pt->label ); ?>
										</label>
									<?php endforeach; ?>
									<p class="description"><?php esc_html_e( 'Applied when "attached to specific post types" is selected.', 'wpcalibrate-watermark-studio' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- TAB: WOOCOMMERCE -->
				<div id="tab-woocommerce" class="wpcalibrate-tab-panel">
					<div class="wpcalibrate-card">
						<h2><?php esc_html_e( 'WooCommerce Integration', 'wpcalibrate-watermark-studio' ); ?></h2>
						<?php if ( $this->woo_integration->is_active() ) : ?>
							<p><span class="badge badge-success"><?php echo sprintf( esc_html__( 'WooCommerce %s Active & HPOS Declared', 'wpcalibrate-watermark-studio' ), esc_html( $this->woo_integration->get_version() ) ); ?></span></p>
							<table class="wpcalibrate-form-table">
								<tr>
									<th scope="row"><?php esc_html_e( 'Product Image Targets', 'wpcalibrate-watermark-studio' ); ?></th>
									<td>
										<label style="display:block; margin-bottom:8px;">
											<input type="checkbox" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[woo_target_featured]" value="1" <?php checked( $options['woo_target_featured'] ); ?> />
											<?php esc_html_e( 'Target Product Featured (Main) Images', 'wpcalibrate-watermark-studio' ); ?>
										</label>
										<label style="display:block; margin-bottom:8px;">
											<input type="checkbox" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[woo_target_gallery]" value="1" <?php checked( $options['woo_target_gallery'] ); ?> />
											<?php esc_html_e( 'Target Product Gallery Images', 'wpcalibrate-watermark-studio' ); ?>
										</label>
										<label style="display:block;">
											<input type="checkbox" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[woo_target_variations]" value="1" <?php checked( $options['woo_target_variations'] ); ?> />
											<?php esc_html_e( 'Target Product Variation Images', 'wpcalibrate-watermark-studio' ); ?>
										</label>
									</td>
								</tr>
							</table>
						<?php else : ?>
							<div class="notice notice-info inline" style="margin:10px 0 20px 0;">
								<p><?php esc_html_e( 'WooCommerce is not active on this site. Watermark Studio operates completely normally for standard Media Library images without WooCommerce.', 'wpcalibrate-watermark-studio' ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- TAB: LICENSE -->
				<div id="tab-license" class="wpcalibrate-tab-panel">
					<div class="wpcalibrate-card">
						<h2><?php esc_html_e( 'Plugin License Management', 'wpcalibrate-watermark-studio' ); ?></h2>
						<div class="notice notice-warning inline" style="margin-bottom:20px;">
							<p><strong><?php esc_html_e( 'Status: License service not configured.', 'wpcalibrate-watermark-studio' ); ?></strong> <?php esc_html_e( 'No remote licensing provider API has been registered yet for this installation.', 'wpcalibrate-watermark-studio' ); ?></p>
						</div>

						<table class="wpcalibrate-form-table">
							<tr>
								<th scope="row"><label for="license_key"><?php esc_html_e( 'License Key', 'wpcalibrate-watermark-studio' ); ?></label></th>
								<td>
									<input type="password" id="license_key" class="regular-text" name="<?php echo esc_attr( Defaults::OPTION_NAME ); ?>[license_key]" value="<?php echo esc_attr( $masked_key ); ?>" placeholder="<?php esc_attr_e( 'Enter license key', 'wpcalibrate-watermark-studio' ); ?>" autocomplete="off" />
									<p class="description"><?php esc_html_e( 'Stored keys are automatically masked for privacy.', 'wpcalibrate-watermark-studio' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Provider Status', 'wpcalibrate-watermark-studio' ); ?></th>
								<td>
									<span class="badge badge-warning"><?php echo esc_html( strtoupper( $license_status ) ); ?></span>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- TAB: SUPPORT -->
				<div id="tab-support" class="wpcalibrate-tab-panel">
					<div class="wpcalibrate-card">
						<h2><?php esc_html_e( 'WPCalibrate Support & Inquiries', 'wpcalibrate-watermark-studio' ); ?></h2>
						<p><?php esc_html_e( 'Need help, custom enhancements, or support for WPCalibrate Watermark Studio? Connect with our development team directly:', 'wpcalibrate-watermark-studio' ); ?></p>

						<ul class="support-contact-list">
							<li>
								<strong><?php esc_html_e( 'Website:', 'wpcalibrate-watermark-studio' ); ?></strong>
								<a href="<?php echo esc_url( 'https://wpcalibrate.com' ); ?>" target="_blank" rel="noopener noreferrer">https://wpcalibrate.com</a>
							</li>
							<li>
								<strong><?php esc_html_e( 'Marketplace:', 'wpcalibrate-watermark-studio' ); ?></strong>
								<a href="<?php echo esc_url( 'https://marketplace.wpcalibrate.com/' ); ?>" target="_blank" rel="noopener noreferrer">https://marketplace.wpcalibrate.com/</a>
							</li>
							<li>
								<strong><?php esc_html_e( 'Email Support:', 'wpcalibrate-watermark-studio' ); ?></strong>
								<a href="<?php echo esc_url( 'mailto:support@wpcalibrate.com' ); ?>">support@wpcalibrate.com</a>
							</li>
							<li>
								<strong><?php esc_html_e( 'Phone / WhatsApp:', 'wpcalibrate-watermark-studio' ); ?></strong>
								<a href="<?php echo esc_url( 'https://wa.me/447474795976' ); ?>" target="_blank" rel="noopener noreferrer">+447474795976</a>
							</li>
						</ul>
					</div>
				</div>

				<!-- Submit Button -->
				<p class="submit" style="margin-top:20px;">
					<?php submit_button( __( 'Save Watermark Settings', 'wpcalibrate-watermark-studio' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	public function handle_activate_license(): void {
		if ( ! Capabilities::can_manage_settings() ) {
			wp_die( esc_html__( 'Unauthorized.', 'wpcalibrate-watermark-studio' ), 403 );
		}
		check_admin_referer( 'wpcalibrate_ws_license_action', '_ws_lic_nonce' );

		$res = $this->license_manager->activate();
		wp_safe_redirect( add_query_arg( 'wpcalibrate_msg', rawurlencode( $res['message'] ), admin_url( 'admin.php?page=' . Menu::SETTINGS_SLUG . '#license' ) ) );
		exit;
	}

	public function handle_deactivate_license(): void {
		if ( ! Capabilities::can_manage_settings() ) {
			wp_die( esc_html__( 'Unauthorized.', 'wpcalibrate-watermark-studio' ), 403 );
		}
		check_admin_referer( 'wpcalibrate_ws_license_action', '_ws_lic_nonce' );

		$res = $this->license_manager->deactivate();
		wp_safe_redirect( add_query_arg( 'wpcalibrate_msg', rawurlencode( $res['message'] ), admin_url( 'admin.php?page=' . Menu::SETTINGS_SLUG . '#license' ) ) );
		exit;
	}
}
