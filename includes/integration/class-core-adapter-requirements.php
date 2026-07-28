<?php
/**
 * Core adapter readiness requirements.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Integration;

use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;
use Sabri\UniversalComposer\Core\Registry;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports whether File 22's first release-critical native adapter is present.
 * This is diagnostic and fail-soft: optional adapters remain independent, and
 * a missing File 21 adapter does not fatal the public website.
 */
final class Core_Adapter_Requirements {
	public const SOCIAL_PUBLICATION_KEY        = 'social_publication';
	public const FILE21_NATIVE_MODULE          = 'sabri-complete-home-news-feed';
	public const MINIMUM_FILE21_VERSION        = '1.0.3';
	public const REQUIRED_CREATE_CAPABILITY    = 'sabri_feed_create_posts';
	public const REQUIRED_GROUP                = 'publishing';
	public const REQUIRED_PRIVACY_CLASS        = 'public';

	public function __construct( private Registry $registry ) {
	}

	public function register(): void {
		add_filter( 'supc_system_check_report', array( $this, 'append_report' ), 20 );
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Existing report rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function append_report( array $rows ): array {
		$rows[] = $this->social_publication_report();
		return $rows;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function social_publication_report(): array {
		$adapter = $this->registry->get( self::SOCIAL_PUBLICATION_KEY );
		if ( ! $adapter instanceof Adapter ) {
			return $this->failure( 'not_registered' );
		}

		try {
			$contract_errors = array();
			if ( self::SOCIAL_PUBLICATION_KEY !== $adapter->key() ) {
				$contract_errors[] = 'adapter_key';
			}
			if ( self::FILE21_NATIVE_MODULE !== $adapter->native_module() ) {
				$contract_errors[] = 'native_module';
			}
			if ( version_compare( $adapter->minimum_native_version(), self::MINIMUM_FILE21_VERSION, '<' ) ) {
				$contract_errors[] = 'minimum_native_version';
			}
			if ( self::REQUIRED_CREATE_CAPABILITY !== $adapter->required_capability() ) {
				$contract_errors[] = 'required_capability';
			}
			if ( self::REQUIRED_GROUP !== $adapter->group() ) {
				$contract_errors[] = 'group';
			}
			if ( self::REQUIRED_PRIVACY_CLASS !== $adapter->privacy_classification() ) {
				$contract_errors[] = 'privacy_classification';
			}

			if ( array() !== $contract_errors ) {
				return array(
					'key'             => 'social_publication_adapter',
					'status'          => 'fail',
					'adapter_key'     => self::SOCIAL_PUBLICATION_KEY,
					'native_module'   => self::FILE21_NATIVE_MODULE,
					'minimum_native'  => self::MINIMUM_FILE21_VERSION,
					'reason'          => 'contract_mismatch',
					'contract_errors' => $contract_errors,
				);
			}

			$health = $adapter instanceof Diagnostic_Adapter ? $adapter->health_report() : array();
			$actual = isset( $health['actual_native_version'] ) && is_string( $health['actual_native_version'] )
				? $health['actual_native_version']
				: '';
			if ( '' === $actual ) {
				return $this->failure( 'native_version_unreported' );
			}
			if ( version_compare( $actual, self::MINIMUM_FILE21_VERSION, '<' ) ) {
				return $this->failure( 'native_version_too_low', array( 'actual_native' => $actual ) );
			}

			$available = $adapter->is_available();
			return array(
				'key'             => 'social_publication_adapter',
				'status'          => $available ? 'pass' : 'warning',
				'adapter_key'     => $adapter->key(),
				'native_module'   => $adapter->native_module(),
				'actual_native'   => $actual,
				'minimum_native'  => $adapter->minimum_native_version(),
				'reason'          => $available ? 'available' : 'temporarily_unavailable',
			);
		} catch ( Throwable $error ) {
			return $this->failure( 'diagnostic_exception', array( 'exception' => get_class( $error ) ) );
		}
	}

	/**
	 * @param array<string,mixed> $extra Additional privacy-safe fields.
	 * @return array<string,mixed>
	 */
	private function failure( string $reason, array $extra = array() ): array {
		return array_merge(
			array(
				'key'            => 'social_publication_adapter',
				'status'         => 'fail',
				'adapter_key'    => self::SOCIAL_PUBLICATION_KEY,
				'native_module'  => self::FILE21_NATIVE_MODULE,
				'minimum_native' => self::MINIMUM_FILE21_VERSION,
				'reason'         => $reason,
			),
			$extra
		);
	}
}
