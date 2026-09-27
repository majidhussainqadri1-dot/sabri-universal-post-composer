<?php
/**
 * File 24 Security/Privacy/Compliance assurance bridge.
 *
 * File 22 publishes only a public-safe, bounded module manifest. File 24 remains
 * the assurance owner and File 22 never treats manifest collection as an
 * authorization grant or a substitute for native controls.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class File24_Assurance_Bridge {
	public const CONTRACT_VERSION = '1.2.0';
	public const MODULE_KEY       = 'file-22-universal-composer';

	public function register(): void {
		add_filter( 'spcrc/module_manifests', array( $this, 'append_manifest' ), 20, 1 );
		add_filter( 'supc_system_check_report', array( $this, 'append_system_check' ), 70, 1 );
	}

	/**
	 * @param mixed $manifests Existing File 24 manifest collection.
	 * @return array<int,array<string,mixed>>
	 */
	public function append_manifest( mixed $manifests ): array {
		$manifests = is_array( $manifests ) ? array_values( $manifests ) : array();
		foreach ( $manifests as $manifest ) {
			if ( is_array( $manifest ) && self::MODULE_KEY === (string) ( $manifest['module_key'] ?? '' ) ) {
				return $manifests;
			}
		}
		$manifests[] = $this->manifest();
		return $manifests;
	}

	/** @return array<string,mixed> */
	public function manifest(): array {
		$last_security_test = apply_filters( 'supc_file24_last_security_test', '' );
		$evidence_source    = apply_filters( 'supc_file24_evidence_source', '' );
		$last_security_test = is_string( $last_security_test ) ? trim( $last_security_test ) : '';
		$evidence_source    = is_string( $evidence_source ) ? trim( $evidence_source ) : '';

		return array(
			'module_key'             => self::MODULE_KEY,
			'name'                   => 'Sabri Universal Post Composer',
			'version'                => defined( 'SUPC_VERSION' ) ? (string) SUPC_VERSION : '1.0.0-rc.3',
			'owner'                  => 'File 22',
			'posture'                => '' !== $last_security_test && '' !== $evidence_source ? 'foundation' : 'unassessed',
			'data_classes'           => array( 'C1 Internal', 'C2 Personal Metadata', 'C4 Sensitive Workflow Metadata' ),
			'public_routes'          => array(),
			'private_routes'         => array( '/create/', '/my-content/', '/wp-json/sabri-composer/v1' ),
			'tables'                 => array( 'supc_sessions', 'supc_submissions', 'supc_outbox', 'supc_upload_tokens', 'supc_audit_log' ),
			'files'                  => array( 'composer-public-safe-source' ),
			'capabilities'           => array( 'native-owner-authorized-create', 'native-owner-authorized-lifecycle' ),
			'external_vendors'       => array(),
			'secret_classes'         => array( 'browser-profile-recovery-key-metadata-only' ),
			'privacy_operations'     => array( 'export', 'erase' ),
			'exporters'              => array( 'wordpress-personal-data-exporter' ),
			'erasers'                => array( 'wordpress-personal-data-eraser' ),
			'emergency_callbacks'    => array( 'file22-safe-mode', 'file22-write-feature-gate' ),
			'last_security_test'     => $last_security_test,
			'verification_level'     => 'not-applicable',
			'contract_version'       => self::CONTRACT_VERSION,
			'canonical_data_owner'   => 'File 22 orchestration metadata only',
			'canonical_action_owner' => 'Native domain owners; File 22 orchestration only',
			'evidence_source'        => $evidence_source,
			'degraded_behavior'      => 'Unknown or unavailable assurance never grants authority; native owner controls remain authoritative.',
			'release_gate'           => 'Exact-head QA, staging acceptance, rollback proof, Founder approval and live parity verification.',
		);
	}

	/** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
	public function append_system_check( array $rows ): array {
		$available = defined( 'SPCRC_VERSION' ) || class_exists( '\\Sabri\\Platform\\Security\\Registry\\ModuleRegistry' );
		$manifest = $this->manifest();
		$evidence_ready = '' !== (string) ( $manifest['last_security_test'] ?? '' ) && '' !== (string) ( $manifest['evidence_source'] ?? '' );
		$status = $available && $evidence_ready ? 'pass' : 'warning';
		$code = ! $available ? 'file24_assurance_optional_unavailable' : 'file24_assurance_evidence_unverified';
		$rows[] = array(
			'key'    => 'file24_assurance_contract',
			'status' => $status,
			'count'  => 'pass' === $status ? 0 : 1,
			'codes'  => 'pass' === $status ? array() : array( $code ),
		);
		return $rows;
	}
}