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

	private static ?int $resolved_page_id = null;

	public static function activate(): void {
		$page_id = self::resolve_page_id( true );
		if ( $page_id > 0 ) {
			update_option( 'supc_create_page_id', $page_id, false );
		}
	}

	public static function resolve_page_id( bool $create = false ): int {
		if ( null !== self::$resolved_page_id && ( self::$resolved_page_id > 0 || ! $create ) ) {
			return self::$resolved_page_id;
		}

		$configured = absint( get_option( 'supc_create_page_id', 0 ) );
		if ( self::is_valid_page( $configured ) ) {
			self::$resolved_page_id = $configured;
			return $configured;
		}

		$existing = self::find_shortcode_page();
		if ( $existing > 0 ) {
			update_option( 'supc_create_page_id', $existing, false );
			self::$resolved_page_id = $existing;
			return $existing;
		}

		if ( ! $create ) {
			self::$resolved_page_id = 0;
			return 0;
		}

		$page_id = self::create_managed_page();
		self::$resolved_page_id = $page_id;
		return $page_id;
	}

	/**
	 * Inspect the current mapping without writing options or posts.
	 *
	 * @return array{status:string,configured_page_id:int,discovered_page_id:int}
	 */
	public static function inspect(): array {
		$configured = absint( get_option( 'supc_create_page_id', 0 ) );
		if ( self::is_valid_page( $configured ) ) {
			return array(
				'status'               => 'ready',
				'configured_page_id'   => $configured,
				'discovered_page_id'   => $configured,
			);
		}

		$existing = self::find_shortcode_page();
		return array(
			'status'               => $existing > 0 ? 'repairable' : 'missing',
			'configured_page_id'   => $configured,
			'discovered_page_id'   => $existing,
		);
	}

	/**
	 * Repair only File 22's Create-page mapping. Existing unrelated pages are
	 * never edited, overwritten, trashed, or deleted.
	 *
	 * @return array{result:string,page_id:int}
	 */
	public static function repair_mapping( bool $create = true ): array {
		$inspection = self::inspect();
		if ( 'ready' === $inspection['status'] ) {
			self::$resolved_page_id = (int) $inspection['configured_page_id'];
			return array(
				'result'  => 'no_change',
				'page_id' => self::$resolved_page_id,
			);
		}

		if ( 'repairable' === $inspection['status'] ) {
			$page_id = (int) $inspection['discovered_page_id'];
			update_option( 'supc_create_page_id', $page_id, false );
			self::$resolved_page_id = $page_id;
			return array(
				'result'  => 'mapped_existing',
				'page_id' => $page_id,
			);
		}

		if ( ! $create ) {
			return array(
				'result'  => 'would_create_managed_page',
				'page_id' => 0,
			);
		}

		$page_id = self::create_managed_page();
		if ( $page_id <= 0 ) {
			self::$resolved_page_id = 0;
			return array(
				'result'  => 'repair_failed',
				'page_id' => 0,
			);
		}

		update_option( 'supc_create_page_id', $page_id, false );
		self::$resolved_page_id = $page_id;
		return array(
			'result'  => 'created_managed_page',
			'page_id' => $page_id,
		);
	}

	public static function reset_cache(): void {
		self::$resolved_page_id = null;
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
				'posts_per_page'         => -1,
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

	private static function create_managed_page(): int {
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
}
