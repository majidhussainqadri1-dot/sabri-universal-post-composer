<?php
/**
 * Core adapter readiness requirements.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Integration;

use Sabri\UniversalComposer\Contracts\Adapter;
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
	public const SOCIAL_PUBLICATION_KEY = 'social_publication';
	public const MINIMUM_FILE21_VERSION = '1.0.3';

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
			return array(
				'key'            => 'social_publication_adapter',
				'status'         => 'fail',
				'adapter_key'    => self::SOCIAL_PUBLICATION_KEY,
				'minimum_native' => self::MINIMUM_FILE21_VERSION,
				'reason'         => 'not_registered',
			);
		}

		try {
			$minimum = $adapter->minimum_native_version();
			$valid_minimum = version_compare( $minimum, self::MINIMUM_FILE21_VERSION, '>=' );
			$available = $adapter->is_available();

			return array(
				'key'            => 'social_publication_adapter',
				'status'         => $valid_minimum && $available ? 'pass' : 'warning',
				'adapter_key'    => $adapter->key(),
				'native_module'  => $adapter->native_module(),
				'minimum_native' => $minimum,
				'reason'         => $valid_minimum ? ( $available ? 'available' : 'temporarily_unavailable' ) : 'native_version_contract_too_low',
			);
		} catch ( Throwable $error ) {
			return array(
				'key'         => 'social_publication_adapter',
				'status'      => 'fail',
				'adapter_key' => self::SOCIAL_PUBLICATION_KEY,
				'reason'      => 'diagnostic_exception',
				'exception'   => get_class( $error ),
			);
		}
	}
}
