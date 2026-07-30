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
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;
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
	public const SUBJECT_SCHEMA_API_VERSION    = '1.0.0';

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
			if ( ! $this->valid_version( $minimum ) ) {
				$codes[] = 'invalid_minimum_native_version';
			} elseif ( version_compare( $minimum, self::MINIMUM_FILE21_VERSION, '<' ) ) {
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
			}
			if ( self::SUBJECT_SCHEMA_API_VERSION !== $this->runtime_constant( 'SUPC_SUBJECT_SCHEMA_API_VERSION' ) ) {
				$codes[] = 'subject_schema_api_mismatch';
			}
			if ( ! is_callable( array( $adapter, 'schema_for_user' ) ) ) {
				$codes[] = 'subject_schema_contract_missing';
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
			}

			if ( $adapter instanceof Workflow_Adapter ) {
				$workflow = ( new Workflow_Coordinator( $this->registry, new Permission_Resolver() ) )->contract_health( self::SOCIAL_PUBLICATION_KEY );
				if ( 'pass' !== $workflow['status'] ) {
					$codes = array_merge( $codes, $workflow['codes'] );
				}
				if ( 'yes' !== $workflow['subject_schema_extension'] ) {
					$codes[] = 'subject_schema_contract_missing';
				}
			}

			if ( array() !== $codes ) {
				return $this->failure( 'contract_mismatch', $codes );
			}

			$health = $adapter instanceof Diagnostic_Adapter ? $adapter->health_report() : array();
			$actual = isset( $health['actual_native_version'] ) && is_string( $health['actual_native_version'] )
				? trim( $health['actual_native_version'] )
				: '';
			if ( '' === $actual ) {
				return $this->failure( 'native_version_unreported' );
			}
			if ( ! $this->valid_version( $actual ) ) {
				return $this->failure( 'native_version_invalid' );
			}
			if ( version_compare( $actual, self::MINIMUM_FILE21_VERSION, '<' ) ) {
				return $this->failure( 'native_version_too_low' );
			}
			if ( version_compare( $actual, $minimum, '<' ) ) {
				return $this->failure( 'native_version_below_declared_minimum' );
			}

			$available = $adapter->is_available();
			return array(
				'key'            => 'social_publication_adapter',
				'status'         => $available ? 'pass' : 'warning',
				'count'          => $available ? 0 : 1,
				'codes'          => $available ? array() : array( 'social_publication_temporarily_unavailable' ),
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
	 * @param string            $reason Controlled reason code.
	 * @param array<int,string> $extra_codes Additional controlled codes.
	 * @return array<string,mixed>
	 */
	private function failure( string $reason, array $extra_codes = array() ): array {
		$codes = array_merge( array( 'social_publication_' . sanitize_key( $reason ) ), array_map( 'sanitize_key', $extra_codes ) );
		return array(
			'key'            => 'social_publication_adapter',
			'status'         => 'fail',
			'count'          => count( array_unique( $codes ) ),
			'codes'          => array_values( array_unique( $codes ) ),
			'adapter_key'    => self::SOCIAL_PUBLICATION_KEY,
			'native_module'  => self::FILE21_NATIVE_MODULE,
			'minimum_native' => self::MINIMUM_FILE21_VERSION,
		);
	}

	private function runtime_constant( string $name ): mixed {
		return defined( $name ) ? constant( $name ) : null;
	}

	private function valid_version( string $version ): bool {
		return 1 === preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/', $version );
	}
}
