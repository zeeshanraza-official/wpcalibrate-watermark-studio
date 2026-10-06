<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Support;

use WPCalibrate\WatermarkStudio\Settings\Settings;

/**
 * Class Logger
 *
 * Privacy-aware, sanitized diagnostic logger for WPCalibrate Watermark Studio.
 * Strictly redacts license keys, sensitive paths, and credentials.
 */
final class Logger {
	/**
	 * Log option key for storing recent sanitized log entries.
	 */
	public const OPTION_LOGS = 'wpcalibrate_watermark_studio_recent_logs';

	/**
	 * Maximum in-memory/option log entries to retain.
	 */
	private const MAX_LOG_ENTRIES = 100;

	public function __construct( private readonly Settings $settings ) {}

	/**
	 * Log a message with context.
	 *
	 * @param string               $level Log level: 'info', 'warning', 'error', 'debug'.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional structured data.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		$logging_enabled = (bool) $this->settings->get( 'logging_enabled', false );
		if ( ! $logging_enabled && 'error' !== $level ) {
			return; // Always record errors; ignore debug/info unless logging is enabled
		}

		$sanitized_context = $this->sanitize_context( $context );

		$entry = array(
			'timestamp' => time(),
			'date'      => gmdate( 'Y-m-d H:i:s' ),
			'level'     => strtoupper( $level ),
			'message'   => sanitize_text_field( $message ),
			'context'   => $sanitized_context,
		);

		// Record to recent logs option
		$recent = get_option( self::OPTION_LOGS, array() );
		if ( ! is_array( $recent ) ) {
			$recent = array();
		}

		array_unshift( $recent, $entry );
		if ( count( $recent ) > self::MAX_LOG_ENTRIES ) {
			$recent = array_slice( $recent, 0, self::MAX_LOG_ENTRIES );
		}

		update_option( self::OPTION_LOGS, $recent, false );

		// If WooCommerce WC_Logger is active, optionally forward
		if ( function_exists( 'wc_get_logger' ) ) {
			$wc_logger = wc_get_logger();
			$wc_logger->log(
				$level,
				$message . ' ' . wp_json_encode( $sanitized_context ),
				array( 'source' => 'wpcalibrate-watermark-studio' )
			);
		}
	}

	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Retrieve recent log entries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_logs(): array {
		$logs = get_option( self::OPTION_LOGS, array() );
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Clear recorded logs.
	 */
	public function clear_logs(): void {
		delete_option( self::OPTION_LOGS );
	}

	/**
	 * Sanitize context to redact sensitive information.
	 *
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	private function sanitize_context( array $context ): array {
		$clean = array();
		foreach ( $context as $key => $val ) {
			// Redact any key related to keys, tokens, passwords
			if ( preg_match( '/(key|token|secret|pass|auth)/i', (string) $key ) ) {
				$clean[ $key ] = '***REDACTED***';
				continue;
			}

			// Clean paths
			if ( is_string( $val ) ) {
				$clean[ $key ] = $this->sanitize_path_string( $val );
			} elseif ( is_scalar( $val ) ) {
				$clean[ $key ] = $val;
			} elseif ( is_array( $val ) ) {
				$clean[ $key ] = $this->sanitize_context( $val );
			}
		}
		return $clean;
	}

	/**
	 * Truncate or relative-format filesystem paths to avoid exposing full server directory structures.
	 */
	private function sanitize_path_string( string $val ): string {
		if ( defined( 'ABSPATH' ) && str_contains( $val, ABSPATH ) ) {
			return str_replace( ABSPATH, '[ABSPATH]/', $val );
		}
		return $val;
	}
}
