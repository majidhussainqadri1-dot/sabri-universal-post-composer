<?php
/**
 * Safe mapping for the private File 22 My Content workspace.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Workspace_Page_Resolver {
	private const OPTION         = 'supc_my_content_page_id';
	private const META           = '_supc_managed_my_content';
	private const SHORTCODE      = 'sabri_composer_my_content';
	private const LOCK_OPTION    = 'supc_my_content_repair_lock';
	private const LOCK_TTL       = 60;
	private const MAX_CANDIDATES = 100;
	private const SLUGS          = array( 'my-content', 'composer-content', 'my-publications' );

	/** @return array{status:string,configured_page_id:int,candidate_page_ids:array<int,int>} */
	public static function inspect(): array {
		$configured = absint( get_option( self::OPTION, 0 ) );
		if ( $configured > 0 && self::valid_page( $configured ) ) {
			return array( 'status' => 'ready', 'configured_page_id' => $configured, 'candidate_page_ids' => array( $configured ) );
		}
		$candidates = self::candidates();
		return array(
			'status'             => 1 === count( $candidates ) ? 'repairable' : ( count( $candidates ) > 1 ? 'ambiguous' : 'missing' ),
			'configured_page_id' => $configured,
			'candidate_page_ids' => $candidates,
		);
	}

	/** @return array{result:string,page_id:int,created:bool} */
	public static function repair( int $selected_page_id = 0 ): array {
		$token = self::acquire_lock();
		if ( '' === $token ) {
			return array( 'result' => 'repair_locked', 'page_id' => 0, 'created' => false );
		}
		try {
			$inspection = self::inspect();
			if ( 'ready' === $inspection['status'] ) {
				return array( 'result' => 'already_ready', 'page_id' => $inspection['configured_page_id'], 'created' => false );
			}

			$page_id = 0;
			$created = false;
			if ( $selected_page_id > 0 && in_array( $selected_page_id, $inspection['candidate_page_ids'], true ) ) {
				$page_id = $selected_page_id;
			} elseif ( 'repairable' === $inspection['status'] ) {
				$page_id = $inspection['candidate_page_ids'][0];
			} elseif ( 'missing' === $inspection['status'] ) {
				$created_result = self::create_managed_page();
				if ( 'created' !== $created_result['result'] ) {
					return array( 'result' => $created_result['result'], 'page_id' => 0, 'created' => false );
				}
				$page_id = $created_result['page_id'];
				$created = true;
			} else {
				return array( 'result' => 'selection_required', 'page_id' => 0, 'created' => false );
			}

			if ( ! self::valid_page( $page_id ) || ! self::persist_mapping( $page_id ) ) {
				if ( $created ) {
					self::rollback_created_page( $page_id );
				}
				return array( 'result' => 'mapping_failed', 'page_id' => 0, 'created' => $created );
			}
			return array( 'result' => $created ? 'created' : 'mapped', 'page_id' => $page_id, 'created' => $created );
		} finally {
			self::release_lock( $token );
		}
	}

	public static function url(): string {
		$inspection = self::inspect();
		return 'ready' === $inspection['status'] ? self::validated_permalink( $inspection['configured_page_id'] ) : '';
	}

	public static function is_request(): bool {
		$inspection = self::inspect();
		return 'ready' === $inspection['status'] && function_exists( 'is_page' ) && is_page( $inspection['configured_page_id'] );
	}

	/** @return array{result:string,page_id:int} */
	private static function create_managed_page(): array {
		$slug = '';
		foreach ( self::SLUGS as $candidate ) {
			if ( ! get_page_by_path( $candidate, OBJECT, 'page' ) ) {
				$slug = $candidate;
				break;
			}
		}
		if ( '' === $slug ) {
			return array( 'result' => 'managed_slug_unavailable', 'page_id' => 0 );
		}
		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'My Content', 'sabri-universal-post-composer' ),
				'post_name'    => $slug,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[' . self::SHORTCODE . ']',
				'meta_input'   => array( self::META => '1' ),
			),
			true
		);
		if ( is_wp_error( $page_id ) || ! is_int( $page_id ) || $page_id <= 0 ) {
			return array( 'result' => 'create_failed', 'page_id' => 0 );
		}
		$post = get_post( $page_id );
		if ( ! $post instanceof \WP_Post || $slug !== (string) $post->post_name || '1' !== (string) get_post_meta( $page_id, self::META, true ) || ! self::valid_page( $page_id ) ) {
			self::rollback_created_page( $page_id );
			return array( 'result' => 'created_page_invalid', 'page_id' => 0 );
		}
		return array( 'result' => 'created', 'page_id' => $page_id );
	}

	private static function persist_mapping( int $page_id ): bool {
		$old = get_option( self::OPTION, null );
		if ( update_option( self::OPTION, $page_id, false ) || $page_id === (int) get_option( self::OPTION, 0 ) ) {
			return true;
		}
		if ( null === $old ) {
			delete_option( self::OPTION );
		} else {
			update_option( self::OPTION, $old, false );
		}
		return false;
	}

	private static function rollback_created_page( int $page_id ): bool {
		if ( $page_id <= 0 || '1' !== (string) get_post_meta( $page_id, self::META, true ) ) {
			return false;
		}
		$deleted = function_exists( 'wp_delete_post' ) ? wp_delete_post( $page_id, true ) : false;
		if ( false !== $deleted && null !== $deleted && null === get_post( $page_id ) ) {
			return true;
		}
		$quarantined = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_status'  => 'draft',
				'post_content' => '',
			),
			true
		);
		if ( ! is_wp_error( $quarantined ) && $page_id === (int) $quarantined ) {
			return true;
		}
		update_option( 'supc_emergency_disabled', true, false );
		return false;
	}

	private static function valid_page( int $page_id ): bool {
		$post = get_post( $page_id );
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
			return false;
		}
		return has_shortcode( (string) $post->post_content, self::SHORTCODE ) && '' !== self::validated_permalink( $page_id );
	}

	private static function validated_permalink( int $page_id ): string {
		$url = get_permalink( $page_id );
		if ( ! is_string( $url ) || '' === trim( $url ) || 1 === preg_match( '/[\x00-\x1F\x7F]/', $url ) || str_contains( $url, '\\' ) ) {
			return '';
		}
		$url = wp_validate_redirect( trim( $url ), '' );
		if ( '' === $url ) {
			return '';
		}
		if ( str_starts_with( $url, '/' ) ) {
			return str_starts_with( $url, '//' ) ? '' : $url;
		}
		$target = wp_parse_url( $url );
		$home   = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $target ) || ! is_array( $home ) || isset( $target['user'] ) || isset( $target['pass'] ) ) {
			return '';
		}
		$target_scheme = strtolower( (string) ( $target['scheme'] ?? '' ) );
		$home_scheme   = strtolower( (string) ( $home['scheme'] ?? '' ) );
		$target_host   = strtolower( (string) ( $target['host'] ?? '' ) );
		$home_host     = strtolower( (string) ( $home['host'] ?? '' ) );
		$target_port   = isset( $target['port'] ) ? (int) $target['port'] : 443;
		$home_port     = isset( $home['port'] ) ? (int) $home['port'] : 443;
		return 'https' === $target_scheme && 'https' === $home_scheme && '' !== $target_host && $target_host === $home_host && $target_port === $home_port ? $url : '';
	}

	/** @return array<int,int> */
	private static function candidates(): array {
		$pages = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_CANDIDATES + 1,
				's'                      => self::SHORTCODE,
				'sentence'               => true,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$ids = array();
		foreach ( $pages as $candidate ) {
			$id = absint( $candidate );
			if ( $id > 0 && self::valid_page( $id ) ) {
				$ids[] = $id;
				if ( count( $ids ) >= self::MAX_CANDIDATES ) {
					break;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	private static function acquire_lock(): string {
		if ( ! function_exists( 'wp_generate_uuid4' ) ) {
			return '';
		}
		$token = wp_generate_uuid4();
		$value = array( 'token' => $token, 'expires_at' => time() + self::LOCK_TTL );
		if ( add_option( self::LOCK_OPTION, $value, '', false ) ) {
			return $token;
		}
		$current = get_option( self::LOCK_OPTION, null );
		if ( is_array( $current ) && (int) ( $current['expires_at'] ?? 0 ) > time() ) {
			return '';
		}
		delete_option( self::LOCK_OPTION );
		return add_option( self::LOCK_OPTION, $value, '', false ) ? $token : '';
	}

	private static function release_lock( string $token ): void {
		$current = get_option( self::LOCK_OPTION, null );
		if ( is_array( $current ) && is_string( $current['token'] ?? null ) && hash_equals( $current['token'], $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}
}
