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
			$codes = array();
			if ( self::SOCIAL_PUBLICATION_KEY !== $adapter->key() ) {
				$codes[] = 'adapter_key_mismatch';
			}
			if ( self::FILE21_NATIVE_MODULE !== $adapter->native_module() ) {
				$codes[] = 'native_module_mismatch';
			}
			if ( version_compare( $adapter->minimum_native_version(), self::MINIMUM_FILE21_VERSION, '<' ) ) {
				$codes[] = 'minimum_native_version_too_low';
			}
			if ( self::REQUIRED_CREATE_CAPABILITY !== $adapter->required_capability() ) {
				$codes[] = 'required_capability_mismatch';
			}
			if ( self::REQUIRED_GROUP !== $adapter->group() ) {
				$codes[] = 'group_mismatch';
			}
			if ( self::REQUIRED_PRIVACY_CLASS !== $adapter->privacy_classification() ) {
				$codes[] = 'privacy_classification_mismatch';
			}
			if ( ! $adapter instanceof Diagnostic_Adapter ) {
				$codes[] = 'diagnostic_contract_missing';
			}
			if ( ! $adapter instanceof Workflow_Adapter ) {
				$codes[] = 'workflow_contract_missing';
			}

			$contract = $this->registry->workflow_contract( self::SOCIAL_PUBLICATION_KEY );
			if ( null === $contract ) {
				$codes[] = 'workflow_registration_metadata_missing';
			} else {
				if ( SUPC_WORKFLOW_API_VERSION !== $contract['workflow_api_version'] ) {
					$codes[] = 'workflow_api_mismatch';
				}
				if ( self::REQUIRED_CREATE_CAPABILITY !== $contract['required_capability'] ) {
					$codes[] = 'workflow_capability_mismatch';
				}
				if ( ! $contract['supports_native_drafts'] ) {
					$codes[] = 'native_draft_contract_missing';
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

			$health = $adapter instanceof Diagnostic_Adapter ? $adapter->health_report() : array();
			$actual = isset( $health['actual_native_version'] ) && is_string( $health['actual_native_version'] )
				? $health['actual_native_version']
				: '';
			if ( '' === $actual ) {
				return $this->failure( 'native_version_unreported' );
			}
			if ( version_compare( $actual, self::MINIMUM_FILE21_VERSION, '<' ) ) {
				return $this->failure( 'native_version_too_low' );
			}

			$available = $adapter->is_available();
			return array(
				'key'            => 'social_publication_adapter',
				'status'         => $available ? 'pass' : 'warning',
				'count'          => $available ? 0 : 1,
				'codes'          => $available ? array() : array( 'social_publication_temporarily_unavailable' ),
				'adapter_key'    => $adapter->key(),
				'native_module'  => $adapter->native_module(),
				'actual_native'  => $actual,
				'minimum_native' => $adapter->minimum_native_version(),
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
}
