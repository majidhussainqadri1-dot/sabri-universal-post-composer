<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Safe_Mode;
use Sabri\UnifiedShell\SafeMode as Shell_Safe_Mode;

final class SafeModeTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_options'] = array();
		Shell_Safe_Mode::$throw       = false;
		Shell_Safe_Mode::$disabled    = false;
	}

	protected function tearDown(): void {
		Shell_Safe_Mode::$throw    = false;
		Shell_Safe_Mode::$disabled = false;
	}

	public function test_owned_shell_can_report_normal_mode(): void {
		$this->assertFalse( Safe_Mode::disabled() );
	}

	public function test_owned_shell_safe_mode_is_honored(): void {
		Shell_Safe_Mode::$disabled = true;

		$this->assertTrue( Safe_Mode::disabled() );
	}

	public function test_external_shell_exception_fails_closed(): void {
		Shell_Safe_Mode::$throw = true;

		$this->assertTrue( Safe_Mode::disabled() );
	}
}
