<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AccessibilityContrastTest extends TestCase {
	public function test_sign_in_action_contrast_meets_wcag_normal_text_threshold(): void {
		$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/create-surface.css' );
		$this->assertIsString( $css );

		$pattern = '/--supc-orange-dark:\s*(?:var\(--sabri-color-primary-strong,\s*)?(#[0-9a-fA-F]{6})\)?\s*;/';
		$this->assertMatchesRegularExpression( $pattern, $css );
		preg_match( $pattern, $css, $matches );

		$this->assertGreaterThanOrEqual(
			4.5,
			$this->contrast_ratio( $matches[1], '#ffffff' ),
			'The Sign In action fallback must provide at least 4.5:1 contrast for normal text.'
		);
		$this->assertStringContainsString( 'var(--sabri-color-primary-strong, #05623e)', strtolower( $css ) );
		$this->assertStringContainsString( 'background: var(--supc-orange-dark);', $css );
		$this->assertStringContainsString( '.supc-create-notice__action:visited', $css );
	}

	private function contrast_ratio( string $foreground, string $background ): float {
		$foreground_luminance = $this->relative_luminance( $foreground );
		$background_luminance = $this->relative_luminance( $background );
		$lighter              = max( $foreground_luminance, $background_luminance );
		$darker               = min( $foreground_luminance, $background_luminance );
		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	private function relative_luminance( string $hex ): float {
		$hex      = ltrim( $hex, '#' );
		$channels = array(
			hexdec( substr( $hex, 0, 2 ) ) / 255,
			hexdec( substr( $hex, 2, 2 ) ) / 255,
			hexdec( substr( $hex, 4, 2 ) ) / 255,
		);

		$channels = array_map(
			static fn ( float $channel ): float => $channel <= 0.03928
				? $channel / 12.92
				: ( ( $channel + 0.055 ) / 1.055 ) ** 2.4,
			$channels
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}
}
