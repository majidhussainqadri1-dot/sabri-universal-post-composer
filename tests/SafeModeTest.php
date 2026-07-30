<?php

declare(strict_types=1);

namespace Sabri\UnifiedShell {
	final class SafeMode {
		public static bool $throw = false;

		public static function disabled(): bool {
			if ( self::$throw ) {
				throw new \RuntimeException( 'Private shell failure.' );
			}
			return false;
		}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use Sabri\UniversalComposer\Core\Safe_Mode;

	final class SafeModeTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['supc_test_options'] = array();
			\Sabri\UnifiedShell\SafeMode::$throw = false;
		}

		protected function tearDown(): void {
			\Sabri\UnifiedShell\SafeMode::$throw = false;
		}

		public function test_external_shell_exception_fails_closed(): void {
			\Sabri\UnifiedShell\SafeMode::$throw = true;

			$this->assertTrue( Safe_Mode::disabled() );
		}
	}
}
