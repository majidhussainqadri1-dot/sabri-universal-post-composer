<?php
/**
 * Central-plan + File 22 source-completion regressions for the authoring UX.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlanCompleteExperienceTest extends TestCase {
	private function source( string $path ): string {
		$content = file_get_contents( dirname( __DIR__ ) . '/' . $path );
		$this->assertIsString( $content );
		return $content;
	}

	public function test_seven_stage_composer_and_two_desktop_modes_are_source_complete(): void {
		$surface = $this->source( 'includes/presentation/class-workflow-surface.php' );
		$this->assertStringContainsString( 'Select Content Type', $surface );
		foreach ( array( 'Compose', 'Media and Relationships', 'References and Compliance', 'Validation', 'Preview', 'Publish or Submit' ) as $label ) {
			$this->assertStringContainsString( $label, $surface );
		}
		$this->assertStringContainsString( 'Quick Composer', $surface );
		$this->assertStringContainsString( 'Advanced Composer', $surface );
		$this->assertStringContainsString( 'data-supc-step', $surface );
	}

	public function test_rich_editor_capabilities_and_paste_cleaning_are_present(): void {
		$surface = $this->source( 'includes/presentation/class-workflow-surface.php' );
		$browser = $this->source( 'assets/js/workflow-composer.js' );
		foreach ( array( 'bold', 'italic', 'h2', 'ul', 'ol', 'quote', 'link', 'table', 'footnote', 'hr', 'undo', 'redo' ) as $command ) {
			$this->assertStringContainsString( "'{$command}'", $surface );
		}
		$this->assertStringContainsString( 'sanitizeRichHtml', $browser );
		$this->assertStringContainsString( "clipboard.getData('text/html')", $browser );
		$this->assertStringContainsString( 'data-supc-rte-words', $surface );
		$this->assertStringContainsString( 'data-supc-rte-reading', $surface );
	}

	public function test_native_owner_upload_ui_and_opaque_reference_binding_are_present(): void {
		$surface = $this->source( 'includes/presentation/class-workflow-surface.php' );
		$browser = $this->source( 'assets/js/workflow-composer.js' );
		$this->assertStringContainsString( 'Upload_Token_Adapter', $surface );
		$this->assertStringContainsString( 'data-supc-upload-input', $surface );
		$this->assertStringContainsString( "'/uploads'", $browser );
		$this->assertStringContainsString( "'/complete'", $browser );
		$this->assertStringContainsString( 'data-supc-opaque-reference', $surface );
		$this->assertStringNotContainsString( 'FileReader.readAsDataURL', $browser );
	}

	public function test_preview_surfaces_devices_and_role_authorized_actions_are_dynamic(): void {
		$surface = $this->source( 'includes/presentation/class-workflow-surface.php' );
		foreach ( array( 'Home Card', 'Profile Timeline', 'Single Page', 'Module', 'Desktop', 'Tablet', 'Mobile' ) as $label ) {
			$this->assertStringContainsString( $label, $surface );
		}
		$this->assertStringContainsString( "\$fields['publication_action']", $surface );
		$this->assertStringContainsString( 'data-supc-final-action', $surface );
		$this->assertStringNotContainsString( "current_user_can( 'administrator'", $surface );
	}

	public function test_offline_behavior_is_truthful_and_never_persists_plaintext_drafts_in_browser_storage(): void {
		$browser = $this->source( 'assets/js/workflow-composer.js' );
		$this->assertStringContainsString( 'navigator.onLine', $browser );
		$this->assertStringContainsString( "setConnection('offline'", $browser );
		$this->assertStringContainsString( "setConnection('conflict'", $browser );
		$this->assertDoesNotMatchRegularExpression( '/localStorage|sessionStorage|indexedDB/i', $browser );
	}

	public function test_server_side_policy_rejects_unsafe_html_and_protocols(): void {
		$policy = $this->source( 'includes/core/class-policy-engine.php' );
		$this->assertStringContainsString( 'unsafe_rich_text_markup_prohibited', $policy );
		$this->assertStringContainsString( 'unsafe_link_or_embed_protocol', $policy );
		$this->assertStringContainsString( 'contains_unsafe_rich_content', $policy );
		$this->assertStringContainsString( 'contains_unsafe_link_protocol', $policy );
		$this->assertStringContainsString( 'security_hold', $policy );
	}

	public function test_responsive_source_contains_tablet_mobile_zoom_safe_geometry_and_forced_colors(): void {
		$css = $this->source( 'assets/css/workflow-composer.css' );
		$this->assertStringContainsString( '@media (max-width:1100px)', $css );
		$this->assertStringContainsString( '@media (max-width:760px)', $css );
		$this->assertStringContainsString( '@media (max-width:430px)', $css );
		$this->assertStringContainsString( '@media (forced-colors:active)', $css );
		$this->assertStringContainsString( '@media (prefers-reduced-motion:reduce)', $css );
		$this->assertStringContainsString( 'overflow-wrap:anywhere', $css );
	}
}
