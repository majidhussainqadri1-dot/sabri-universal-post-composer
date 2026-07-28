<?php
/**
 * Optional diagnostic adapter contract.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies privacy-safe health information to System Check.
 */
interface Diagnostic_Adapter extends Adapter {
	/**
	 * @return array<string, mixed>
	 */
	public function health_report(): array;
}
