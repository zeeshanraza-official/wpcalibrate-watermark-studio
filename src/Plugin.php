<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio;

use WPCalibrate\WatermarkStudio\Admin\Menu;
use WPCalibrate\WatermarkStudio\Admin\SettingsPage;
use WPCalibrate\WatermarkStudio\Admin\MediaActions;
use WPCalibrate\WatermarkStudio\Admin\BulkPage;
use WPCalibrate\WatermarkStudio\Admin\DiagnosticsPage;
use WPCalibrate\WatermarkStudio\Settings\Settings;
use WPCalibrate\WatermarkStudio\Media\OriginalManager;
use WPCalibrate\WatermarkStudio\Media\AttachmentRepository;
use WPCalibrate\WatermarkStudio\Media\UploadHandler;
use WPCalibrate\WatermarkStudio\Media\RestoreService;
use WPCalibrate\WatermarkStudio\Watermark\Processor;
use WPCalibrate\WatermarkStudio\Queue\BatchProcessor;
use WPCalibrate\WatermarkStudio\WooCommerce\Integration as WooIntegration;
use WPCalibrate\WatermarkStudio\License\LicenseManager;
use WPCalibrate\WatermarkStudio\Support\Logger;
use WPCalibrate\WatermarkStudio\Support\Diagnostics;

/**
 * Class Plugin
 *
 * Core orchestrator and service container for WPCalibrate Watermark Studio.
 */
final class Plugin {
	/**
	 * Data schema / migration version.
	 *
	 * @var string
	 */
	public const DATA_VERSION = '1.0.0';

	/**
	 * Option name for tracking installed data version.
	 *
	 * @var string
	 */
	public const OPTION_DATA_VERSION = 'wpcalibrate_watermark_studio_data_version';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service instances.
	 */
	private Settings $settings;
	private Logger $logger;
	private OriginalManager $original_manager;
	private AttachmentRepository $attachment_repository;
	private Processor $processor;
	private RestoreService $restore_service;
	private BatchProcessor $batch_processor;
	private LicenseManager $license_manager;
	private Diagnostics $diagnostics;
	private WooIntegration $woo_integration;

	/**
	 * Admin controllers.
	 */
	private ?Menu $menu = null;
	private ?SettingsPage $settings_page = null;
	private ?MediaActions $media_actions = null;
	private ?BulkPage $bulk_page = null;
	private ?DiagnosticsPage $diagnostics_page = null;
	private ?UploadHandler $upload_handler = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		// Initialize core services.
		$this->settings              = new Settings();
		$this->logger                = new Logger( $this->settings );
		$this->original_manager      = new OriginalManager( $this->logger );
		$this->attachment_repository = new AttachmentRepository();
		$this->processor             = new Processor( $this->settings, $this->original_manager, $this->attachment_repository, $this->logger );
		$this->restore_service       = new RestoreService( $this->original_manager, $this->attachment_repository, $this->logger );
		$this->batch_processor       = new BatchProcessor( $this->processor, $this->restore_service, $this->attachment_repository, $this->logger );
		$this->license_manager       = new LicenseManager();
		$this->diagnostics           = new Diagnostics( $this->settings, $this->original_manager, $this->batch_processor );
		$this->woo_integration       = new WooIntegration( $this->settings );
	}

	/**
	 * Boot plugin components and hooks.
	 */
	public function boot(): void {
		// Load text domain for translations.
		$this->load_textdomain();

		// Check for database / data upgrades.
		Upgrade::check_upgrade();

		// Initialize WooCommerce integration early if present.
		$this->woo_integration->init();

		// Initialize Upload Handler for automatic watermarking on upload.
		$this->upload_handler = new UploadHandler(
			$this->settings,
			$this->processor,
			$this->woo_integration,
			$this->logger
		);
		$this->upload_handler->init();

		// Initialize batch background queue scheduler.
		$this->batch_processor->init();

		// Initialize Admin components if in admin or doing AJAX.
		if ( is_admin() ) {
			$this->menu             = new Menu();
			$this->settings_page    = new SettingsPage( $this->settings, $this->license_manager, $this->woo_integration );
			$this->bulk_page        = new BulkPage( $this->batch_processor, $this->settings, $this->attachment_repository );
			$this->diagnostics_page = new DiagnosticsPage( $this->diagnostics, $this->logger );
			$this->media_actions    = new MediaActions( $this->processor, $this->restore_service, $this->attachment_repository, $this->logger, $this->original_manager );

			$this->menu->init();
			$this->settings_page->init();
			$this->bulk_page->init();
			$this->diagnostics_page->init();
			$this->media_actions->init();

			// Register plugin action links on plugins.php.
			add_filter( 'plugin_action_links_' . WPCALIBRATE_WATERMARK_STUDIO_BASENAME, array( $this, 'add_action_links' ) );
		}
	}

	/**
	 * Load plugin textdomain for i18n.
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'wpcalibrate-watermark-studio',
			false,
			dirname( WPCALIBRATE_WATERMARK_STUDIO_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Add links to plugins page.
	 *
	 * @param array<string, string> $links Existing action links.
	 * @return array<string, string>
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wpcalibrate-watermark-studio' ) ),
			esc_html__( 'Settings', 'wpcalibrate-watermark-studio' )
		);
		$bulk_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wpcalibrate-watermark-studio-bulk' ) ),
			esc_html__( 'Bulk Processing', 'wpcalibrate-watermark-studio' )
		);

		return array_merge( array( 'settings' => $settings_link, 'bulk' => $bulk_link ), $links );
	}

	// Service Getters
	public function get_settings(): Settings {
		return $this->settings;
	}

	public function get_logger(): Logger {
		return $this->logger;
	}

	public function get_original_manager(): OriginalManager {
		return $this->original_manager;
	}

	public function get_attachment_repository(): AttachmentRepository {
		return $this->attachment_repository;
	}

	public function get_processor(): Processor {
		return $this->processor;
	}

	public function get_restore_service(): RestoreService {
		return $this->restore_service;
	}

	public function get_batch_processor(): BatchProcessor {
		return $this->batch_processor;
	}

	public function get_license_manager(): LicenseManager {
		return $this->license_manager;
	}

	public function get_diagnostics(): Diagnostics {
		return $this->diagnostics;
	}

	public function get_woo_integration(): WooIntegration {
		return $this->woo_integration;
	}
}
