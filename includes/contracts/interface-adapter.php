<?php
/**
 * Base adapter contract.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines metadata, availability, authorization, and the safe entry route.
 */
interface Adapter {
	/**
	 * Exact File 22 adapter API version supported by this adapter.
	 */
	public function api_version(): string;

	/**
	 * Stable key matching ^[a-z][a-z0-9_]{2,63}$.
	 */
	public function key(): string;

	/**
	 * Human-readable American English label.
	 */
	public function label(): string;

	/**
	 * Short user-facing explanation of the content type.
	 */
	public function description(): string;

	/**
	 * Deterministic group key such as publishing, knowledge, media, or commerce.
	 */
	public function group(): string;

	/**
	 * Dashicon name or a safe registered platform icon key.
	 */
	public function icon(): string;

	/**
	 * Lower values render first.
	 */
	public function priority(): int;

	/**
	 * Native plugin slug or canonical module identifier.
	 */
	public function native_module(): string;

	/**
	 * Minimum supported native module version.
	 */
	public function minimum_native_version(): string;

	/**
	 * Central WordPress capability required before adapter-specific checks.
	 */
	public function required_capability(): string;

	/**
	 * One of public, private, or sensitive.
	 */
	public function privacy_classification(): string;

	/**
	 * Whether the native module and supported version are available.
	 */
	public function is_available(): bool;

	/**
	 * Additional adapter-specific restriction. It may restrict, never expand,
	 * the central Membership Core permission decision.
	 */
	public function can_create( int $user_id ): bool;

	/**
	 * Safe native or File 22 route that starts this creation workflow.
	 */
	public function start_url( int $user_id ): string;
}
