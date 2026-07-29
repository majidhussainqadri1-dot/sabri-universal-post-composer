<?php
/**
 * PHPUnit bootstrap extension for direct workflow contracts.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'SUPC_WORKFLOW_API_VERSION' ) ) {
	define( 'SUPC_WORKFLOW_API_VERSION', '1.0.0' );
}

require_once dirname( __DIR__ ) . '/includes/contracts/interface-workflow-adapter.php';
require_once dirname( __DIR__ ) . '/includes/core/class-workflow-coordinator.php';
require_once dirname( __DIR__ ) . '/includes/core/class-plugin.php';
require_once dirname( __DIR__ ) . '/includes/core/functions.php';
