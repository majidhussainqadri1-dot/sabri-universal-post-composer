<?php
/**
 * File 22 V12.3 D13: native-owner media picker regressions.
 *
 * These are source-surface contract tests, not native provider, scan, or
 * production deployment acceptance.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Presentation\Workflow_Surface;

final class D13MediaPickerContractTest extends TestCase {
	private function upload_panel(): string {
		$surface = ( new ReflectionClass( Workflow_Surface::class ) )->newInstanceWithoutConstructor();
		return ( new ReflectionMethod( Workflow_Surface::class, 'upload_panel' ) )->invoke( $surface );
	}

	public function test_distinct_image_video_pdf_and_audio_picker_controls_exist(): void {
		$html = $this->upload_panel();

		self::assertSame( 2, substr_count( $html, 'data-supc-upload-input' ) );
		self::assertStringContainsString( 'Choose audio', $html );
		self::assertStringContainsString( 'image/jpeg,image/png,image/webp', $html );
		self::assertStringContainsString( 'audio/mpeg,audio/mp4,audio/ogg,audio/wav,audio/webm', $html );
		self::assertStringContainsString( 'video/mp4,video/webm,application/pdf', $html );
	}

	public function test_media_controls_do_not_claim_native_scanning_or_publication(): void {
		$html = $this->upload_panel();

		self::assertStringContainsString( 'native owner', $html );
		self::assertStringContainsString( 'Upload completion alone does not mean scanning, approval or publication is complete', $html );
		self::assertStringContainsString( 'opaque upload metadata', $html );
	}

	public function test_browser_wires_every_input_through_the_same_native_upload_flow(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/assets/js/workflow-composer.js' );

		self::assertIsString( $source );
		self::assertStringContainsString( "querySelectorAll('[data-supc-upload-input]')", $source );
		self::assertStringContainsString( 'uploadInputs.forEach((input) => {', $source );
		self::assertStringContainsString( 'Array.from(input.files || []).forEach(uploadFile);', $source );
		self::assertStringContainsString( "purpose: 'composer_media'", $source );
		self::assertStringContainsString( "checksum_sha256: hash", $source );
		self::assertStringNotContainsString( "const uploadInput = root.querySelector('[data-supc-upload-input]')", $source );
	}

	public function test_media_picker_is_only_rendered_for_native_upload_capability(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/presentation/class-workflow-surface.php' );

		self::assertIsString( $source );
		self::assertStringContainsString( 'if ( $uploads ) {', $source );
		self::assertStringContainsString( '$this->upload_panel()', $source );
		self::assertStringContainsString( 'Upload_Token_Adapter', $source );
	}
}
