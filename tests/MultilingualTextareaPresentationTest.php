<?php
/**
 * V12.1 WP-019/WP-021: the native schema's multiline text must remain a
 * textarea, and mixed Urdu/Arabic/English text must choose its own direction.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Presentation\Workflow_Surface;

final class MultilingualTextareaPresentationTest extends TestCase {
    private Workflow_Surface $surface;

    protected function setUp(): void {
        $this->surface = ( new ReflectionClass( Workflow_Surface::class ) )->newInstanceWithoutConstructor();
    }

    /** @param array<string,mixed> $details */
    private function field( string $key, array $details ): string {
        return ( new ReflectionMethod( Workflow_Surface::class, 'field' ) )->invoke( $this->surface, $key, $details );
    }

    /** @return array<string,mixed> */
    private function definition( string $type, string $label ): array {
        return array(
            'type' => $type,
            'label_code' => $label,
            'privacy_class' => 'public',
            'required' => true,
        );
    }

    public function test_plain_native_multiline_excerpt_is_a_textarea_not_single_line_input(): void {
        $html = $this->field( 'excerpt', $this->definition( 'textarea', 'excerpt' ) );
        self::assertStringContainsString( '<textarea', $html );
        self::assertStringContainsString( 'name="excerpt"', $html );
        self::assertStringContainsString( 'dir="auto"', $html );
        self::assertStringContainsString( 'rows="6"', $html );
        self::assertStringContainsString( 'required aria-required="true"', $html );
        self::assertStringNotContainsString( 'type="text"', $html );
    }

    public function test_regular_title_supports_automatic_urdu_english_direction(): void {
        $html = $this->field( 'title', $this->definition( 'text', 'title' ) );
        self::assertStringContainsString( 'type="text"', $html );
        self::assertStringContainsString( 'dir="auto"', $html );
        self::assertStringContainsString( 'name="title"', $html );
    }

    public function test_rich_content_editor_and_hidden_native_textarea_both_have_auto_direction(): void {
        $html = $this->field( 'content', $this->definition( 'textarea', 'content' ) );
        self::assertStringContainsString( 'contenteditable="true" dir="auto"', $html );
        self::assertStringContainsString( '<textarea', $html );
        self::assertStringContainsString( 'dir="auto" class="supc-rte__source"', $html );
        self::assertStringContainsString( 'data-supc-rte-source', $html );
    }

    public function test_text_preview_has_independent_auto_direction_beyond_english_status_meta(): void {
        $html = ( new ReflectionMethod( Workflow_Surface::class, 'preview_stage' ) )->invoke( $this->surface );
        self::assertStringContainsString( 'data-supc-preview-title dir="auto"', $html );
        self::assertStringContainsString( 'data-supc-preview-body dir="auto"', $html );
    }

    public function test_url_fields_keep_browser_semantic_url_input_type(): void {
        $html = $this->field( 'source_url', $this->definition( 'url', 'source' ) );
        self::assertStringContainsString( 'type="url"', $html );
        self::assertStringNotContainsString( 'dir="auto"', $html );
    }
}
