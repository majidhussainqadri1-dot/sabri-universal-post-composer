<?php
/**
 * Schema-driven accessible File 22 workflow surface.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Presentation;

use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Contract_Boundary;
use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Workflow_Surface {
	private const LABELS = array(
		'native_reference' => 'Draft reference',
		'title' => 'Title',
		'content' => 'Content',
		'feed_type' => 'Publication type',
		'topic' => 'Topic',
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
		$heading_id = wp_unique_id( 'supc-workflow-heading-' );
		$status_id  = wp_unique_id( 'supc-workflow-status-' );
		$error_id   = wp_unique_id( 'supc-workflow-errors-' );
		$native_url = $adapter->start_url( $user_id );
		$html       = '<section class="supc-workflow" data-supc-workflow data-adapter="' . esc_attr( $key ) . '" aria-labelledby="' . esc_attr( $heading_id ) . '">';
		$html      .= '<header class="supc-workflow__header"><p class="supc-create__eyebrow">' . esc_html__( 'Universal Post Composer', 'sabri-universal-post-composer' ) . '</p>';
		$html      .= '<h2 id="' . esc_attr( $heading_id ) . '">' . esc_html( $adapter->label() ) . '</h2><p>' . esc_html( $adapter->description() ) . '</p>';
		$html      .= '<p><a href="' . esc_url( Page_Resolver::url() ) . '">' . esc_html__( 'Choose another content type', 'sabri-universal-post-composer' ) . '</a></p></header>';
		$html      .= '<div id="' . esc_attr( $error_id ) . '" class="supc-workflow__errors" role="alert" tabindex="-1" hidden></div>';
		$html      .= '<form class="supc-workflow__form" data-supc-form novalidate autocomplete="off">';
		foreach ( $schema['fields'] as $field_key => $definition ) {
			if ( 'opaque_reference' === $definition['type'] ) {
				continue;
			}
			$html .= $this->field( $field_key, $definition );
		}
		$html .= '<div class="supc-workflow__actions">';
		$html .= '<button type="button" class="button" data-supc-action="save">' . esc_html__( 'Save Draft', 'sabri-universal-post-composer' ) . '</button>';
		$html .= '<button type="button" class="button" data-supc-action="preview">' . esc_html__( 'Preview', 'sabri-universal-post-composer' ) . '</button>';
		$html .= '<button type="submit" class="button button-primary" data-supc-action="submit">' . esc_html__( 'Submit', 'sabri-universal-post-composer' ) . '</button>';
		$html .= '</div><p id="' . esc_attr( $status_id ) . '" class="supc-workflow__status" role="status" aria-live="polite" data-supc-status>' . esc_html__( 'No draft has been created yet.', 'sabri-universal-post-composer' ) . '</p>';
		$html .= '<noscript><p class="supc-workflow__noscript">' . esc_html__( 'JavaScript is required for the unified autosave workspace.', 'sabri-universal-post-composer' );
		if ( '' !== $native_url ) {
			$html .= ' <a href="' . esc_url( $native_url ) . '">' . esc_html__( 'Open the native creation form instead.', 'sabri-universal-post-composer' ) . '</a>';
		}
		$html .= '</p></noscript></form>';
		$html .= '<aside class="supc-create__boundary"><strong>' . esc_html__( 'Privacy boundary:', 'sabri-universal-post-composer' ) . '</strong> ' . esc_html__( 'File 22 stores only session metadata and an opaque native draft reference. Draft content remains with the native owner and is never written to browser local storage.', 'sabri-universal-post-composer' ) . '</aside>';
		$html .= '</section>';
		return $html;
	}

	/** @param array<string,mixed> $definition */
	private function field( string $key, array $definition ): string {
		$id          = wp_unique_id( 'supc-field-' );
		$type        = (string) $definition['type'];
		$required    = ! empty( $definition['required'] );
		$privacy     = (string) $definition['privacy_class'];
		$label       = $this->text( (string) $definition['label_code'] );
		$description = isset( $definition['description_code'] ) ? $this->text( (string) $definition['description_code'] ) : '';
		$attrs       = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" data-supc-field data-field-type="' . esc_attr( $type ) . '" data-privacy="' . esc_attr( $privacy ) . '"' . ( $required ? ' required aria-required="true"' : '' );
		$html        = '<div class="supc-workflow__field" data-privacy="' . esc_attr( $privacy ) . '">';
		if ( 'checkbox' !== $type ) {
			$html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span aria-hidden="true">*</span>' : '' ) . '</label>';
		}
		if ( 'textarea' === $type ) {
			$html .= '<textarea' . $attrs . ' rows="12"></textarea>';
		} elseif ( in_array( $type, array( 'select', 'multiselect' ), true ) ) {
			$html .= '<select' . $attrs . ( 'multiselect' === $type ? ' multiple' : '' ) . '>';
			if ( 'select' === $type ) {
				$html .= '<option value="">' . esc_html__( 'Select an option', 'sabri-universal-post-composer' ) . '</option>';
			}
			foreach ( (array) ( $definition['choices'] ?? array() ) as $value => $code ) {
				$html .= '<option value="' . esc_attr( (string) $value ) . '">' . esc_html( $this->text( (string) $code ) ) . '</option>';
			}
			$html .= '</select>';
		} elseif ( 'checkbox' === $type ) {
			$html .= '<label class="supc-workflow__checkbox"><input type="checkbox"' . $attrs . '> <span>' . esc_html( $label ) . ( $required ? ' *' : '' ) . '</span></label>';
		} else {
			$input_type = match ( $type ) {
				'number' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', 'url' => 'url', 'email' => 'email', default => 'text',
			};
			$html .= '<input type="' . esc_attr( $input_type ) . '"' . $attrs;
			if ( isset( $definition['minimum'] ) ) { $html .= ' min="' . esc_attr( (string) $definition['minimum'] ) . '"'; }
			if ( isset( $definition['maximum'] ) ) { $html .= ' max="' . esc_attr( (string) $definition['maximum'] ) . '"'; }
			$html .= '>';
		}
		if ( '' !== $description ) {
			$html .= '<p class="description">' . esc_html( $description ) . '</p>';
		}
		if ( 'sensitive' === $privacy ) {
			$html .= '<p class="supc-workflow__privacy">' . esc_html__( 'Sensitive: this value is sent only to the authorized native owner and is not retained in File 22 session storage.', 'sabri-universal-post-composer' ) . '</p>';
		}
		return $html . '</div>';
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
