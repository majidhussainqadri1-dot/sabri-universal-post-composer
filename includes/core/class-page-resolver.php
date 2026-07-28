<?php
/**
 * Safe Create page creation and routing.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Page_Resolver {
	private const SHORTCODE = 'sabri_universal_composer';

	public static function activate(): void {
		$page_id = self::resolve_page_id( true );
		if ( $page_id > 0 ) {
			update_option( 'supc_create_page_id', $page_id, false );
		}
	}

	public static function resolve_page_id( bool $create = false ): int {
		$configured = absint( get_option( 'supc_create_page_id', 0 ) );
		if ( self::is_valid_page( $configured ) ) {
			return $configured;
		}

		$existing = self::find_shortcode_page();
		if ( $existing > 0 ) {
			update_option( 'supc_create_page_id', $existing, false );
			return $existing;
		}

		if ( ! $create ) {
			return 0;
		}

		foreach ( array( 'create', 'create-content', 'platform-create', 'sabri-create' ) as $slug ) {
			if ( get_page_by_path( $slug, OBJECT, 'page' ) ) {
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'   => __( 'Create', 'sabri-universal-post-composer' ),
					'post_name'    => $slug,
					'post_content' => '[' . self::SHORTCODE . ']',
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'meta_input'   => array( '_supc_managed_page' => 1 ),
				),
				true
			);

			if ( ! is_wp_error( $page_id ) ) {
				return (int) $page_id;
			}
		}

		return 0;
	}

	public static function url(): string {
		$page_id = self::resolve_page_id( false );
		if ( $page_id <= 0 ) {
			return '';
		}

		$url = get_permalink( $page_id );
		return is_string( $url ) ? $url : '';
	}

	public static function is_ready(): bool {
		return self::resolve_page_id( false ) > 0;
	}

	public static function is_create_request(): bool {
		$page_id = self::resolve_page_id( false );
		return $page_id > 0 && is_page( $page_id );
	}

	private static function is_valid_page( int $page_id ): bool {
		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return false;
		}

		$content = (string) get_post_field( 'post_content', $page_id );
		return has_shortcode( $content, self::SHORTCODE );
	}

	private static function find_shortcode_page(): int {
		$pages = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $pages as $page_id ) {
			$content = (string) get_post_field( 'post_content', (int) $page_id );
			if ( has_shortcode( $content, self::SHORTCODE ) ) {
				return (int) $page_id;
			}
		}

		return 0;
	}
}
