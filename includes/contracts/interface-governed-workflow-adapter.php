<?php
/**
 * Governing-plan workflow metadata contract.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the non-owning orchestration metadata required by the 2026 governing
 * plans without moving native content, moderation, media, search, or
 * notification ownership into File 22.
 */
interface Governed_Workflow_Adapter extends Workflow_Adapter {
	/**
	 * Exact File 22 governance contract version implemented by the adapter.
	 */
	public function governance_api_version(): string;

	/**
	 * Return a declarative, public-safe capability profile.
	 *
	 * Required keys:
	 * - authoring_features: list of supported governed feature keys.
	 * - media_rules: native-owner media policy key.
	 * - edit_capability: current native capability required for edit/revision.
	 * - cleanup_policy: native_owner, reversible_native, or none.
	 * - search_indexing_policy: native_canonical, noindex, or conditional_native.
	 * - notification_events: bounded list of native event keys File 19 may consume.
	 *
	 * @return array<string, mixed>
	 */
	public function governance_profile(): array;
}
