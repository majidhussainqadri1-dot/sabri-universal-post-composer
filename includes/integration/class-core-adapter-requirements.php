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
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Contract_Boundary;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Version;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Core_Adapter_Requirements {
	public const SOCIAL_PUBLICATION_KEY     = 'social_publication';
	public const FILE21_NATIVE_MODULE       = 'sabri-complete-home-news-feed';
	public const MINIMUM_FILE21_VERSION     = '1.0.3';
	public const REQUIRED_CREATE_CAPABILITY = 'sabri_feed_create_posts';
	public const REQUIRED_GROUP             = 'publishing';
	public const REQUIRED_PRIVACY_CLASS     = 'public';
	public const SUBJECT_SCHEMA_API_VERSION = '1.0.0';

	public function __construct( private Registry $registry ) {
	}

	public function register(): void {
		add_filter( 'supc_system_check_report', array( $this, 'append_report' ), 20 );
	}

	/** @return array<int,array<string,mixed>> */
	public function append_report( mixed $rows ): array {
		$rows   = is_array( $rows ) ? array_values( $rows ) : array();
		$rows[] = $this->social_publication_report();
		return $rows;
	}

	/** @return array<string,mixed> */
	public function social_publication_report(): array {
		$adapter       = $this->registry->get( self::SOCIAL_PUBLICATION_KEY );
		$base_contract = $this->registry->adapter_contract( self::SOCIAL_PUBLICATION_KEY );
		if ( ! $adapter instanceof Adapter ) {
			return $this->failure( 'not_registered' );
		}
		if ( null === $base_contract ) {
			return $this->failure( 'registration_metadata_missing' );
		}

		try {
			$codes   = array();
			$minimum = $base_contract['minimum_native_version'];
			if ( self::FILE21_NATIVE_MODULE !== $base_contract['native_module'] ) {
				$codes[] = 'native_module_mismatch';
			}
			if ( ! Version::valid( $minimum ) ) {
				$codes[] = 'invalid_minimum_native_version';
			} elseif ( ! Version::at_least( $minimum, self::MINIMUM_FILE21_VERSION ) ) {
				$codes[] = 'minimum_native_version_too_low';
			}
			if ( self::REQUIRED_CREATE_CAPABILITY !== $base_contract['required_capability'] ) {
				$codes[] = 'required_capability_mismatch';
			}
			if ( self::REQUIRED_GROUP !== $base_contract['group'] ) {
				$codes[] = 'group_mismatch';
			}
			if ( self::REQUIRED_PRIVACY_CLASS !== $base_contract['privacy_classification'] ) {
				$codes[] = 'privacy_classification_mismatch';
			}
			if ( ! $adapter instanceof Diagnostic_Adapter ) {
				$codes[] = 'diagnostic_contract_missing';
			}
			if ( ! $adapter instanceof Workflow_Adapter ) {
				$codes[] = 'workflow_contract_missing';
				$codes[] = 'subject_schema_contract_missing';
			}
			if ( self::SUBJECT_SCHEMA_API_VERSION !== $this->runtime_constant( 'SUPC_SUBJECT_SCHEMA_API_VERSION' ) ) {
				$codes[] = 'subject_schema_api_mismatch';
			}

			$workflow_contract = $this->registry->workflow_contract( self::SOCIAL_PUBLICATION_KEY );
			if ( null === $workflow_contract ) {
				$codes[] = 'workflow_registration_metadata_missing';
			} else {
				if ( SUPC_WORKFLOW_API_VERSION !== $workflow_contract['workflow_api_version'] ) {
					$codes[] = 'workflow_api_mismatch';
				}
				if ( self::REQUIRED_CREATE_CAPABILITY !== $workflow_contract['required_capability'] ) {
					$codes[] = 'workflow_capability_mismatch';
				}
				if ( ! $workflow_contract['supports_native_drafts'] ) {
					$codes[] = 'native_draft_contract_missing';
				}
				if ( ! $workflow_contract['subject_schema_extension'] ) {
					$codes[] = 'subject_schema_contract_missing';
				}
			}

			if ( $adapter instanceof Workflow_Adapter ) {
				$workflow = ( new Workflow_Coordinator( $this->registry, new Permission_Resolver() ) )->contract_health( self::SOCIAL_PUBLICATION_KEY );
				if ( 'pass' !== $workflow['status'] ) {
					$codes = array_merge( $codes, $workflow['codes'] );
				}
			}
			if ( array() !== $codes ) {
				return $this->failure( 'contract_mismatch', $codes );
			}

			$health        = $adapter instanceof Diagnostic_Adapter ? $adapter->health_report() : array();
			$health_status = $this->health_status( $health['status'] ?? 'warning' );
			$health_codes  = $this->health_codes( $health['codes'] ?? array() );
			$actual        = isset( $health['actual_native_version'] ) && is_string( $health['actual_native_version'] )
				? $health['actual_native_version']
				: '';
			if ( '' === $actual ) {
				return $this->failure( 'native_version_unreported', $health_codes );
			}
			if ( ! Version::valid( $actual ) ) {
				return $this->failure( 'native_version_invalid', $health_codes );
			}
			if ( ! Version::at_least( $actual, self::MINIMUM_FILE21_VERSION ) ) {
				return $this->failure( 'native_version_too_low', $health_codes );
			}
			if ( ! Version::at_least( $actual, $minimum ) ) {
				return $this->failure( 'native_version_below_declared_minimum', $health_codes );
			}
			if ( 'fail' === $health_status ) {
				return $this->failure( 'diagnostic_failure', array() !== $health_codes ? $health_codes : array( 'diagnostic_reason_missing' ) );
			}

			$available = $adapter->is_available();
			if ( ! $available ) {
				$health_codes[] = 'social_publication_temporarily_unavailable';
			}
			$health_codes = array_values( array_unique( $health_codes ) );
			$status       = ! $available || 'warning' === $health_status || array() !== $health_codes ? 'warning' : 'pass';
			if ( 'warning' === $status && array() === $health_codes ) {
				$health_codes[] = 'diagnostic_reason_missing';
			}
			return array(
				'key'            => 'social_publication_adapter',
				'status'         => $status,
				'count'          => count( $health_codes ),
				'codes'          => $health_codes,
				'adapter_key'    => self::SOCIAL_PUBLICATION_KEY,
				'native_module'  => $base_contract['native_module'],
				'actual_native'  => $actual,
				'minimum_native' => $minimum,
			);
		} catch ( Throwable $error ) {
			unset( $error );
			return $this->failure( 'diagnostic_exception' );
		}
	}

	/**
	 * @param array<int,string> $extra_codes Privacy-safe controlled codes.
	 * @return array<string,mixed>
	 */
	private function failure( string $reason, array $extra_codes = array() ): array {
		$codes = array_merge( array( 'social_publication_' . sanitize_key( $reason ) ), array_slice( array_map( 'sanitize_key', $extra_codes ), 0, 20 ) );
		$codes = array_values( array_unique( array_filter( $codes ) ) );
		return array(
			'key'            => 'social_publication_adapter',
			'status'         => 'fail',
			'count'          => count( $codes ),
			'codes'          => $codes,
			'adapter_key'    => self::SOCIAL_PUBLICATION_KEY,
			'native_module'  => self::FILE21_NATIVE_MODULE,
			'minimum_native' => self::MINIMUM_FILE21_VERSION,
		);
	}

	private function health_status( mixed $status ): string {
		return is_string( $status ) && in_array( $status, array( 'pass', 'warning', 'fail' ), true ) ? $status : 'warning';
	}

	/** @return array<int,string> */
	private function health_codes( mixed $codes ): array {
		if ( ! is_array( $codes ) || array_values( $codes ) !== $codes ) {
			return array( 'diagnostic_contract_invalid' );
		}
		$normalized = array();
		foreach ( array_slice( $codes, 0, 20 ) as $code ) {
			if ( is_string( $code ) && Contract_Boundary::code( $code ) ) {
				$normalized[] = $code;
			} else {
				$normalized[] = 'diagnostic_contract_invalid';
			}
		}
		return array_values( array_unique( $normalized ) );
	}

	private function runtime_constant( string $name ): mixed {
		return defined( $name ) ? constant( $name ) : null;
	}
}
