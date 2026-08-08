<?php
/**
 * Plan-complete, schema-driven, accessible File 22 workflow surface.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Presentation;

use Sabri\UniversalComposer\Contracts\Upload_Token_Adapter;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Contract_Boundary;
use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;
use Sabri\UniversalComposer\Core\Workflow_Validator;
use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Workflow_Surface {
	private const LABELS = array(
		'native_reference' => 'Draft reference',
		'title' => 'Title',
		'excerpt' => 'Short Introduction / Excerpt',
		'content' => 'Main Content',
		'feed_type' => 'Publication type',
		'topic' => 'Topic',
		'category' => 'Category',
		'keywords' => 'Keywords',
		'visibility' => 'Visibility',
		'language' => 'Language',
		'country_region' => 'Country or region',
		'comments_enabled' => 'Allow comments',
		'medical_disclaimer_confirmed' => 'Medical safety statement confirmed',
		'patient_privacy_confirmed' => 'Patient privacy and consent confirmed',
		'scheduled_date' => 'Scheduled date and time',
		'publication_action' => 'Publishing action',
		'action_submit' => 'Submit for review',
		'action_publish' => 'Publish now',
		'action_schedule' => 'Schedule',
	);

	private const STEP_LABELS = array(
		'compose' => 'Compose',
		'media' => 'Media and Relationships',
		'compliance' => 'References and Compliance',
		'validation' => 'Validation',
		'preview' => 'Preview',
		'publish' => 'Publish or Submit',
	);

	private const QUICK_FIELD_TOKENS = array(
		'title', 'excerpt', 'content', 'body', 'topic', 'category', 'keyword', 'language', 'image', 'media', 'link',
	);

	public function __construct( private Registry $registry, private Workflow_Coordinator $coordinator ) {
	}

	public function selected_adapter_key(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only route selection; authorization is re-evaluated server-side before any action.
		$value = isset( $_GET['type'] ) && is_scalar( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : '';
		return Contract_Boundary::adapter_key( $value ) ? $value : '';
	}

	public function can_render_selected( int $user_id ): bool {
		$key       = $this->selected_adapter_key();
		$adapter   = '' === $key ? null : $this->registry->get( $key );
		$contract  = '' === $key ? null : $this->registry->workflow_contract( $key );
		$available = $this->registry->available_for_user( $user_id );
		return $user_id > 0
			&& $adapter instanceof Workflow_Adapter
			&& null !== $contract
			&& ! empty( $contract['supports_native_drafts'] )
			&& isset( $available[ $key ] );
	}

	public function render( int $user_id ): string {
		$key = $this->selected_adapter_key();
		if ( '' === $key ) {
			return '';
		}
		$adapter = $this->registry->get( $key );
		if ( ! $adapter instanceof Workflow_Adapter || ! $this->can_render_selected( $user_id ) ) {
			return $this->notice( __( 'This creation workflow is unavailable.', 'sabri-universal-post-composer' ), __( 'The selected native adapter is missing, incompatible, or not authorized for this account.', 'sabri-universal-post-composer' ) );
		}
		$schema = $this->coordinator->schema( $user_id, $key );
		if ( $schema instanceof WP_Error ) {
			return $this->notice( __( 'The form could not be loaded.', 'sabri-universal-post-composer' ), __( 'The native field contract failed validation. No draft was created.', 'sabri-universal-post-composer' ) );
		}

		$fields       = isset( $schema['fields'] ) && is_array( $schema['fields'] ) ? $schema['fields'] : array();
		$grouped      = $this->group_fields( $fields );
		$quick        = $this->quick_mode_allowed( $fields );
		$actions      = $this->allowed_actions( $fields );
		$uploads      = $adapter instanceof Upload_Token_Adapter;
		$heading_id   = wp_unique_id( 'supc-workflow-heading-' );
		$status_id    = wp_unique_id( 'supc-workflow-status-' );
		$error_id     = wp_unique_id( 'supc-workflow-errors-' );
		$steps_id     = wp_unique_id( 'supc-workflow-steps-' );
		$native_url   = '';
		try {
			$native_url = ( new Workflow_Validator() )->internal_url( $adapter->start_url( $user_id ) );
		} catch ( Throwable $error ) {
			unset( $error );
			$native_url = '';
		}

		$html  = '<section class="supc-workflow" data-supc-workflow data-adapter="' . esc_attr( $key ) . '" data-mode="advanced" data-supports-uploads="' . ( $uploads ? '1' : '0' ) . '" data-native-url="' . esc_url( $native_url ) . '" aria-labelledby="' . esc_attr( $heading_id ) . '">';
		$html .= '<header class="supc-workflow__header"><div><p class="supc-create__eyebrow">' . esc_html__( 'Universal Post Composer', 'sabri-universal-post-composer' ) . '</p>';
		$html .= '<h2 id="' . esc_attr( $heading_id ) . '">' . esc_html( $adapter->label() ) . '</h2><p>' . esc_html( $adapter->description() ) . '</p></div>';
		$html .= '<div class="supc-workflow__header-actions"><a class="button" href="' . esc_url( Page_Resolver::url() ) . '">' . esc_html__( 'Choose another content type', 'sabri-universal-post-composer' ) . '</a>';
		if ( $quick ) {
			$html .= '<div class="supc-workflow__mode" role="group" aria-label="' . esc_attr__( 'Composer mode', 'sabri-universal-post-composer' ) . '"><button type="button" class="button" data-supc-mode="quick">' . esc_html__( 'Quick Composer', 'sabri-universal-post-composer' ) . '</button><button type="button" class="button button-primary" data-supc-mode="advanced" aria-pressed="true">' . esc_html__( 'Advanced Composer', 'sabri-universal-post-composer' ) . '</button></div>';
		}
		$html .= '</div></header>';
		$html .= '<div class="supc-workflow__connection" data-supc-connection role="status" aria-live="polite"><span data-supc-connection-dot aria-hidden="true"></span><span data-supc-connection-label>' . esc_html__( 'Checking connection…', 'sabri-universal-post-composer' ) . '</span></div>';
		$html .= '<div id="' . esc_attr( $error_id ) . '" class="supc-workflow__errors" role="alert" tabindex="-1" hidden></div>';
		$html .= '<form class="supc-workflow__form" data-supc-form novalidate autocomplete="off">';
		$html .= '<div class="supc-workflow__workspace">';
		$html .= $this->step_navigation( $steps_id );
		$html .= '<main class="supc-workflow__editor" data-supc-editor-column>';
		$html .= $this->fieldset( 'compose', $grouped['compose'], true );
		$html .= $this->fieldset( 'media', $grouped['media'], false );
		if ( $uploads ) {
			$html .= $this->upload_panel();
		}
		$html .= $this->fieldset( 'compliance', $grouped['compliance'], false );
		$html .= $this->validation_stage();
		$html .= $this->preview_stage();
		$html .= $this->fieldset( 'publish', $grouped['publish'], false );
		$html .= '</main>';
		$html .= $this->right_rail();
		$html .= '</div>';
		$html .= $this->action_bar( $actions, $status_id );
		$html .= '<noscript><p class="supc-workflow__noscript">' . esc_html__( 'JavaScript is required for the unified autosave workspace.', 'sabri-universal-post-composer' );
		if ( '' !== $native_url ) {
			$html .= ' <a href="' . esc_url( $native_url ) . '">' . esc_html__( 'Open the native creation form instead.', 'sabri-universal-post-composer' ) . '</a>';
		}
		$html .= '</p></noscript></form>';
		$html .= '<aside class="supc-create__boundary"><strong>' . esc_html__( 'Privacy boundary:', 'sabri-universal-post-composer' ) . '</strong> ' . esc_html__( 'File 22 stores only bounded orchestration metadata and opaque native references. Draft content, media bytes, consent evidence, moderation truth and permanent publication records remain with their native owners; plaintext draft content is never written to browser local storage.', 'sabri-universal-post-composer' ) . '</aside>';
		$html .= '</section>';
		return $html;
	}

	private function step_navigation( string $steps_id ): string {
		$html  = '<nav id="' . esc_attr( $steps_id ) . '" class="supc-workflow__steps" aria-label="' . esc_attr__( 'Composer steps', 'sabri-universal-post-composer' ) . '"><ol>';
		$html .= '<li class="is-complete"><a href="' . esc_url( Page_Resolver::url() ) . '"><span>1</span><strong>' . esc_html__( 'Select Content Type', 'sabri-universal-post-composer' ) . '</strong></a></li>';
		$number = 2;
		foreach ( self::STEP_LABELS as $key => $label ) {
			$html .= '<li' . ( 'compose' === $key ? ' class="is-current"' : '' ) . '><button type="button" data-supc-step-button="' . esc_attr( $key ) . '" aria-current="' . ( 'compose' === $key ? 'step' : 'false' ) . '"><span>' . esc_html( (string) $number ) . '</span><strong>' . esc_html__( $label, 'sabri-universal-post-composer' ) . '</strong></button></li>';
			++$number;
		}
		return $html . '</ol></nav>';
	}

	/** @param array<string,array<string,mixed>> $fields */
	private function fieldset( string $step, array $fields, bool $active ): string {
		$heading_id = wp_unique_id( 'supc-step-' . $step . '-' );
		$html       = '<section class="supc-workflow__stage' . ( $active ? ' is-active' : '' ) . '" data-supc-step="' . esc_attr( $step ) . '"' . ( $active ? '' : ' hidden' ) . ' aria-labelledby="' . esc_attr( $heading_id ) . '">';
		$html      .= '<header class="supc-workflow__stage-header"><p class="supc-workflow__stage-kicker">' . esc_html__( 'Step', 'sabri-universal-post-composer' ) . '</p><h3 id="' . esc_attr( $heading_id ) . '">' . esc_html__( self::STEP_LABELS[ $step ] ?? 'Compose', 'sabri-universal-post-composer' ) . '</h3></header>';
		if ( array() === $fields ) {
			$html .= '<p class="supc-workflow__empty-stage">' . esc_html__( 'This native workflow has no additional fields for this step.', 'sabri-universal-post-composer' ) . '</p>';
		} else {
			foreach ( $fields as $field_key => $definition ) {
				$html .= $this->field( $field_key, $definition );
			}
		}
		return $html . $this->stage_navigation_buttons( $step ) . '</section>';
	}

	private function validation_stage(): string {
		$html  = '<section class="supc-workflow__stage" data-supc-step="validation" hidden><header class="supc-workflow__stage-header"><p class="supc-workflow__stage-kicker">' . esc_html__( 'Step', 'sabri-universal-post-composer' ) . '</p><h3>' . esc_html__( 'Validation', 'sabri-universal-post-composer' ) . '</h3><p>' . esc_html__( 'Run client and native validation before preview or publication.', 'sabri-universal-post-composer' ) . '</p></header>';
		$html .= '<div class="supc-validation" data-supc-validation-summary><div data-validation-group="required"><h4>' . esc_html__( 'Required fields', 'sabri-universal-post-composer' ) . '</h4><p>' . esc_html__( 'Not checked yet.', 'sabri-universal-post-composer' ) . '</p></div><div data-validation-group="media"><h4>' . esc_html__( 'Media', 'sabri-universal-post-composer' ) . '</h4><p>' . esc_html__( 'Not checked yet.', 'sabri-universal-post-composer' ) . '</p></div><div data-validation-group="permission"><h4>' . esc_html__( 'Permissions', 'sabri-universal-post-composer' ) . '</h4><p>' . esc_html__( 'Not checked yet.', 'sabri-universal-post-composer' ) . '</p></div><div data-validation-group="medical"><h4>' . esc_html__( 'Medical safety', 'sabri-universal-post-composer' ) . '</h4><p>' . esc_html__( 'Not checked yet.', 'sabri-universal-post-composer' ) . '</p></div><div data-validation-group="privacy"><h4>' . esc_html__( 'Patient privacy', 'sabri-universal-post-composer' ) . '</h4><p>' . esc_html__( 'Not checked yet.', 'sabri-universal-post-composer' ) . '</p></div><div data-validation-group="copyright"><h4>' . esc_html__( 'Copyright and permissions', 'sabri-universal-post-composer' ) . '</h4><p>' . esc_html__( 'Not checked yet.', 'sabri-universal-post-composer' ) . '</p></div><div data-validation-group="references"><h4>' . esc_html__( 'References', 'sabri-universal-post-composer' ) . '</h4><p>' . esc_html__( 'Not checked yet.', 'sabri-universal-post-composer' ) . '</p></div><div data-validation-group="technical"><h4>' . esc_html__( 'Technical checks', 'sabri-universal-post-composer' ) . '</h4><p>' . esc_html__( 'Not checked yet.', 'sabri-universal-post-composer' ) . '</p></div></div>';
		$html .= '<p><button type="button" class="button button-primary" data-supc-action="validate">' . esc_html__( 'Run Validation', 'sabri-universal-post-composer' ) . '</button></p>';
		return $html . $this->stage_navigation_buttons( 'validation' ) . '</section>';
	}

	private function preview_stage(): string {
		$html  = '<section class="supc-workflow__stage" data-supc-step="preview" hidden><header class="supc-workflow__stage-header"><p class="supc-workflow__stage-kicker">' . esc_html__( 'Step', 'sabri-universal-post-composer' ) . '</p><h3>' . esc_html__( 'Preview', 'sabri-universal-post-composer' ) . '</h3><p>' . esc_html__( 'Use the drafting projection below, then open the authoritative native preview before publication.', 'sabri-universal-post-composer' ) . '</p></header>';
		$html .= '<div class="supc-preview-modes" role="group" aria-label="' . esc_attr__( 'Preview surface', 'sabri-universal-post-composer' ) . '"><button type="button" class="button is-active" data-supc-preview-surface="home">' . esc_html__( 'Home Card', 'sabri-universal-post-composer' ) . '</button><button type="button" class="button" data-supc-preview-surface="profile">' . esc_html__( 'Profile Timeline', 'sabri-universal-post-composer' ) . '</button><button type="button" class="button" data-supc-preview-surface="single">' . esc_html__( 'Single Page', 'sabri-universal-post-composer' ) . '</button><button type="button" class="button" data-supc-preview-surface="module">' . esc_html__( 'Module', 'sabri-universal-post-composer' ) . '</button></div>';
		$html .= '<div class="supc-preview-devices" role="group" aria-label="' . esc_attr__( 'Preview device', 'sabri-universal-post-composer' ) . '"><button type="button" class="button is-active" data-supc-preview-device="desktop">' . esc_html__( 'Desktop', 'sabri-universal-post-composer' ) . '</button><button type="button" class="button" data-supc-preview-device="tablet">' . esc_html__( 'Tablet', 'sabri-universal-post-composer' ) . '</button><button type="button" class="button" data-supc-preview-device="mobile">' . esc_html__( 'Mobile', 'sabri-universal-post-composer' ) . '</button></div>';
		$html .= '<div class="supc-preview-canvas" data-supc-preview-canvas data-surface="home" data-device="desktop"><article><p class="supc-preview-canvas__meta" data-supc-preview-meta>' . esc_html__( 'Draft · Native owner preview pending', 'sabri-universal-post-composer' ) . '</p><h4 data-supc-preview-title>' . esc_html__( 'Your title will appear here', 'sabri-universal-post-composer' ) . '</h4><div data-supc-preview-body>' . esc_html__( 'Your content preview will appear here as you write.', 'sabri-universal-post-composer' ) . '</div></article></div>';
		$html .= '<p><button type="button" class="button button-primary" data-supc-action="preview">' . esc_html__( 'Open Authoritative Preview', 'sabri-universal-post-composer' ) . '</button></p>';
		return $html . $this->stage_navigation_buttons( 'preview' ) . '</section>';
	}

	private function right_rail(): string {
		$html  = '<aside class="supc-workflow__rail" aria-label="' . esc_attr__( 'Composer status and publishing settings', 'sabri-universal-post-composer' ) . '">';
		$html .= '<section><h3>' . esc_html__( 'Draft Status', 'sabri-universal-post-composer' ) . '</h3><dl class="supc-workflow__status-grid"><div><dt>' . esc_html__( 'Autosave', 'sabri-universal-post-composer' ) . '</dt><dd data-supc-autosave-state>' . esc_html__( 'Waiting', 'sabri-universal-post-composer' ) . '</dd></div><div><dt>' . esc_html__( 'Words', 'sabri-universal-post-composer' ) . '</dt><dd data-supc-word-count>0</dd></div><div><dt>' . esc_html__( 'Reading time', 'sabri-universal-post-composer' ) . '</dt><dd data-supc-reading-time>0 min</dd></div></dl></section>';
		$html .= '<section><h3>' . esc_html__( 'Safety', 'sabri-universal-post-composer' ) . '</h3><p data-supc-safety-summary>' . esc_html__( 'Validation has not run yet.', 'sabri-universal-post-composer' ) . '</p></section>';
		$html .= '<section><h3>' . esc_html__( 'Publishing', 'sabri-universal-post-composer' ) . '</h3><p>' . esc_html__( 'Only actions authorized by the native workflow are shown. File 22 never infers permission from a role label.', 'sabri-universal-post-composer' ) . '</p></section>';
		return $html . '</aside>';
	}

	private function upload_panel(): string {
		$html  = '<section class="supc-upload-panel" data-supc-upload-zone><header><h4>' . esc_html__( 'Native-owner media upload', 'sabri-universal-post-composer' ) . '</h4><p>' . esc_html__( 'Files are sent directly to the native owner. File 22 retains only opaque upload metadata and never stores the media bytes.', 'sabri-universal-post-composer' ) . '</p></header>';
		$html .= '<label class="supc-upload-panel__picker"><span>' . esc_html__( 'Choose image, video or PDF', 'sabri-universal-post-composer' ) . '</span><input type="file" data-supc-upload-input multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf"></label>';
		$html .= '<ul class="supc-upload-panel__list" data-supc-upload-list aria-live="polite"></ul></section>';
		return $html;
	}

	/** @param array<string,string> $actions */
	private function action_bar( array $actions, string $status_id ): string {
		$html  = '<div class="supc-workflow__actionbar"><div class="supc-workflow__actions">';
		$html .= '<button type="button" class="button" data-supc-action="save">' . esc_html__( 'Save Draft', 'sabri-universal-post-composer' ) . '</button>';
		$html .= '<button type="button" class="button" data-supc-action="preview">' . esc_html__( 'Preview', 'sabri-universal-post-composer' ) . '</button>';
		foreach ( $actions as $value => $label ) {
			$html .= '<button type="submit" class="button button-primary" data-supc-final-action="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</button>';
		}
		$html .= '</div><p id="' . esc_attr( $status_id ) . '" class="supc-workflow__status" role="status" aria-live="polite" data-supc-status>' . esc_html__( 'No draft has been created yet.', 'sabri-universal-post-composer' ) . '</p></div>';
		return $html;
	}

	private function stage_navigation_buttons( string $step ): string {
		$keys    = array_keys( self::STEP_LABELS );
		$index   = array_search( $step, $keys, true );
		$html    = '<div class="supc-workflow__stage-actions">';
		if ( false !== $index && $index > 0 ) {
			$html .= '<button type="button" class="button" data-supc-step-go="' . esc_attr( $keys[ $index - 1 ] ) . '">' . esc_html__( 'Back', 'sabri-universal-post-composer' ) . '</button>';
		}
		if ( false !== $index && $index < count( $keys ) - 1 ) {
			$html .= '<button type="button" class="button button-primary" data-supc-step-go="' . esc_attr( $keys[ $index + 1 ] ) . '">' . esc_html__( 'Next', 'sabri-universal-post-composer' ) . '</button>';
		}
		return $html . '</div>';
	}

	/** @param array<string,mixed> $definition */
	private function field( string $key, array $definition ): string {
		if ( 'opaque_reference' === (string) ( $definition['type'] ?? '' ) && 'native_reference' === $key ) {
			return '';
		}
		$id             = wp_unique_id( 'supc-field-' );
		$type           = (string) $definition['type'];
		$required       = ! empty( $definition['required'] );
		$privacy        = (string) $definition['privacy_class'];
		$label          = $this->text( (string) $definition['label_code'] );
		$description    = isset( $definition['description_code'] ) ? $this->text( (string) $definition['description_code'] ) : '';
		$description_id = '' !== $description ? $id . '-description' : '';
		$privacy_id     = 'sensitive' === $privacy ? $id . '-privacy' : '';
		$described_by   = trim( $description_id . ' ' . $privacy_id );
		$is_rich        = 'textarea' === $type && $this->is_rich_text_field( $key );
		$is_action      = 'publication_action' === $key;
		$attrs          = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" data-supc-field data-field-type="' . esc_attr( $type ) . '" data-privacy="' . esc_attr( $privacy ) . '" data-required="' . ( $required ? '1' : '0' ) . '"' . ( $required && ! $is_rich && ! $is_action ? ' required aria-required="true"' : '' ) . ( '' !== $described_by ? ' aria-describedby="' . esc_attr( $described_by ) . '"' : '' );
		$html           = '<div class="supc-workflow__field' . ( $is_action ? ' supc-workflow__field--action' : '' ) . '" data-field-key="' . esc_attr( $key ) . '" data-privacy="' . esc_attr( $privacy ) . '">';
		if ( 'checkbox' !== $type && ! $is_action && 'opaque_reference' !== $type ) {
			$html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span aria-hidden="true">*</span>' : '' ) . '</label>';
		}
		if ( $is_rich ) {
			$html .= $this->rich_text_editor( $id, $attrs, $label, $required );
		} elseif ( in_array( $type, array( 'select', 'multiselect' ), true ) ) {
			$html .= '<select' . $attrs . ( 'multiselect' === $type ? ' multiple' : '' ) . ( $is_action ? ' hidden data-supc-publication-action' : '' ) . '>';
			if ( 'select' === $type && ! $is_action ) {
				$html .= '<option value="">' . esc_html__( 'Select an option', 'sabri-universal-post-composer' ) . '</option>';
			}
			foreach ( (array) ( $definition['choices'] ?? array() ) as $value => $code ) {
				$html .= '<option value="' . esc_attr( (string) $value ) . '">' . esc_html( $this->text( (string) $code ) ) . '</option>';
			}
			$html .= '</select>';
		} elseif ( 'checkbox' === $type ) {
			$html .= '<label class="supc-workflow__checkbox"><input type="checkbox"' . $attrs . '> <span>' . esc_html( $label ) . ( $required ? ' *' : '' ) . '</span></label>';
		} elseif ( 'opaque_reference' === $type ) {
			$html .= '<input type="hidden"' . $attrs . ' data-supc-opaque-reference>';
		} else {
			$input_type = match ( $type ) {
				'number' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', 'url' => 'url', 'email' => 'email', default => 'text',
			};
			$html .= '<input type="' . esc_attr( $input_type ) . '"' . $attrs;
			if ( isset( $definition['minimum'] ) ) {
				$html .= ' min="' . esc_attr( (string) $definition['minimum'] ) . '"';
			}
			if ( isset( $definition['maximum'] ) ) {
				$html .= ' max="' . esc_attr( (string) $definition['maximum'] ) . '"';
			}
			$html .= '>';
		}
		if ( '' !== $description && ! $is_action ) {
			$html .= '<p id="' . esc_attr( $description_id ) . '" class="description">' . esc_html( $description ) . '</p>';
		}
		if ( 'sensitive' === $privacy && ! $is_action ) {
			$html .= '<p id="' . esc_attr( $privacy_id ) . '" class="supc-workflow__privacy">' . esc_html__( 'Sensitive: this value is sent only to the authorized native owner and is not retained in File 22 session storage.', 'sabri-universal-post-composer' ) . '</p>';
		}
		return $html . '</div>';
	}

	private function rich_text_editor( string $id, string $attrs, string $label, bool $required ): string {
		$editor_id = $id . '-editor';
		$html      = '<div class="supc-rte" data-supc-rte-wrap><div class="supc-rte__toolbar" role="toolbar" aria-label="' . esc_attr__( 'Rich text formatting', 'sabri-universal-post-composer' ) . '">';
		$buttons   = array(
			'bold' => 'Bold', 'italic' => 'Italic', 'h2' => 'Heading', 'ul' => 'Bulleted list', 'ol' => 'Numbered list', 'quote' => 'Quotation', 'link' => 'Link', 'table' => 'Table', 'footnote' => 'Footnote', 'hr' => 'Divider', 'undo' => 'Undo', 'redo' => 'Redo',
		);
		foreach ( $buttons as $command => $button_label ) {
			$html .= '<button type="button" class="button" data-supc-rte-command="' . esc_attr( $command ) . '" aria-label="' . esc_attr__( $button_label, 'sabri-universal-post-composer' ) . '">' . esc_html__( $button_label, 'sabri-universal-post-composer' ) . '</button>';
		}
		$html .= '</div><div id="' . esc_attr( $editor_id ) . '" class="supc-rte__editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="' . esc_attr( $label ) . '"' . ( $required ? ' aria-required="true"' : '' ) . ' data-supc-rte></div>';
		$html .= '<textarea' . $attrs . ' class="supc-rte__source" data-supc-rte-source tabindex="-1" aria-hidden="true"></textarea><div class="supc-rte__metrics"><span data-supc-rte-words>0 words</span><span data-supc-rte-reading>0 min read</span></div></div>';
		return $html;
	}

	/** @param array<string,array<string,mixed>> $fields @return array<string,array<string,array<string,mixed>>> */
	private function group_fields( array $fields ): array {
		$groups = array( 'compose' => array(), 'media' => array(), 'compliance' => array(), 'publish' => array() );
		foreach ( $fields as $key => $definition ) {
			if ( ! is_string( $key ) || ! is_array( $definition ) ) {
				continue;
			}
			$groups[ $this->field_group( $key ) ][ $key ] = $definition;
		}
		return $groups;
	}

	private function field_group( string $key ): string {
		$value = strtolower( $key );
		if ( preg_match( '/(?:media|image|video|reel|pdf|attachment|thumbnail|caption|transcript|related|relationship|book|lesson|entry|embed|link)/', $value ) ) {
			return 'media';
		}
		if ( preg_match( '/(?:reference|citation|source|copyright|rights|license|patient|consent|anonym|medical|safety|emergency|seller|declaration|privacy)/', $value ) ) {
			return 'compliance';
		}
		if ( preg_match( '/(?:publication_action|scheduled|schedule|distribution|visibility|status|publish)/', $value ) ) {
			return 'publish';
		}
		return 'compose';
	}

	/** @param array<string,array<string,mixed>> $fields */
	private function quick_mode_allowed( array $fields ): bool {
		foreach ( $fields as $key => $definition ) {
			if ( ! is_string( $key ) || ! is_array( $definition ) || empty( $definition['required'] ) ) {
				continue;
			}
			$quick = false;
			foreach ( self::QUICK_FIELD_TOKENS as $token ) {
				if ( str_contains( strtolower( $key ), $token ) ) {
					$quick = true;
					break;
				}
			}
			if ( ! $quick && 'publication_action' !== $key ) {
				return false;
			}
		}
		return true;
	}

	/** @param array<string,array<string,mixed>> $fields @return array<string,string> */
	private function allowed_actions( array $fields ): array {
		$definition = $fields['publication_action'] ?? null;
		$choices    = is_array( $definition ) && isset( $definition['choices'] ) && is_array( $definition['choices'] ) ? $definition['choices'] : array();
		$actions    = array();
		foreach ( $choices as $value => $code ) {
			$key = sanitize_key( (string) $value );
			if ( ! preg_match( '/(?:publish|submit|schedule|update|revision)/', $key ) ) {
				continue;
			}
			$actions[ (string) $value ] = $this->text( is_string( $code ) ? $code : $key );
		}
		if ( array() === $actions ) {
			$actions['submit'] = __( 'Submit', 'sabri-universal-post-composer' );
		}
		return $actions;
	}

	private function is_rich_text_field( string $key ): bool {
		return in_array( sanitize_key( $key ), array( 'content', 'body', 'main_content', 'post_content', 'article_body' ), true );
	}

	private function text( string $code ): string {
		if ( isset( self::LABELS[ $code ] ) ) {
			return __( self::LABELS[ $code ], 'sabri-universal-post-composer' );
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', $code ) );
	}

	private function notice( string $title, string $message ): string {
		$id = wp_unique_id( 'supc-workflow-notice-' );
		return '<section class="supc-create-notice supc-create-notice--unavailable" role="alert" aria-labelledby="' . esc_attr( $id ) . '"><h2 id="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</h2><p>' . esc_html( $message ) . '</p><p><a href="' . esc_url( Page_Resolver::url() ) . '">' . esc_html__( 'Return to content types', 'sabri-universal-post-composer' ) . '</a></p></section>';
	}
}
