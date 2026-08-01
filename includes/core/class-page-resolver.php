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
	private const OPTION_KEY = 'supc_create_page_id';
	private const MANAGED_META = '_supc_managed_page';
	private const REPAIR_LOCK_OPTION = 'supc_create_page_repair_lock';
	private const REPAIR_LOCK_TTL = 60;
	private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
	private const APPROVED_SLUGS = array( 'create', 'create-content', 'platform-create', 'sabri-create' );
	private const MISSING_MAPPING = '__supc_mapping_missing__';

	private static ?int $resolved_page_id = null;

	/** @var array{status:string,configured_page_id:int,discovered_page_id:int,candidate_page_ids:array<int,int>}|null */
	private static ?array $inspection_cache = null;

	public static function activate(): void {
		self::repair_mapping( true );
	}

	public static function resolve_page_id( bool $create = false ): int {
		if ( null !== self::$resolved_page_id && ( self::$resolved_page_id > 0 || ! $create ) ) {
			return self::$resolved_page_id;
		}

		$inspection = self::inspect();
		if ( 'ready' === $inspection['status'] ) {
			self::$resolved_page_id = $inspection['configured_page_id'];
			return self::$resolved_page_id;
		}

		if ( 'repairable' === $inspection['status'] && ! $create ) {
			self::$resolved_page_id = $inspection['discovered_page_id'];
			return self::$resolved_page_id;
		}

		if ( ! $create ) {
			self::$resolved_page_id = 0;
			return 0;
		}

		$result = self::repair_mapping( true );
		if ( in_array( $result['result'], array( 'no_change', 'mapped_existing', 'created_managed_page' ), true ) ) {
			self::$resolved_page_id = $result['page_id'];
			return self::$resolved_page_id;
		}

		self::$resolved_page_id = 0;
		return 0;
	}

	/**
	 * Inspect the current mapping without writing options or posts.
	 *
	 * @return array{status:string,configured_page_id:int,discovered_page_id:int,candidate_page_ids:array<int,int>}
	 */
	public static function inspect(): array {
		if ( null !== self::$inspection_cache ) {
			return self::$inspection_cache;
		}

		$configured = absint( get_option( self::OPTION_KEY, 0 ) );
		if ( self::is_valid_page( $configured ) ) {
			self::$inspection_cache = array(
				'status'             => 'ready',
				'configured_page_id' => $configured,
				'discovered_page_id' => $configured,
				'candidate_page_ids' => array( $configured ),
			);
			return self::$inspection_cache;
		}

		$candidates = self::find_shortcode_pages();
		$count      = count( $candidates );
		$status     = 0 === $count ? 'missing' : ( 1 === $count ? 'repairable' : 'ambiguous' );

		self::$inspection_cache = array(
			'status'             => $status,
			'configured_page_id' => $configured,
			'discovered_page_id' => 1 === $count ? $candidates[0] : 0,
			'candidate_page_ids' => $candidates,
		);
		return self::$inspection_cache;
	}

	/**
	 * Repair only File 22's Create-page mapping. Existing unrelated pages are
	 * never edited, overwritten, trashed, or deleted.
	 *
	 * @return array{result:string,page_id:int}
	 */
	public static function repair_mapping( bool $create = true, int $selected_page_id = 0 ): array {
		$inspection = self::inspect();

		if ( ! $create ) {
			if ( 'ready' === $inspection['status'] ) {
				return array( 'result' => 'no_change', 'page_id' => $inspection['configured_page_id'] );
			}
			if ( 'repairable' === $inspection['status'] ) {
				return array( 'result' => 'would_map_existing', 'page_id' => $inspection['discovered_page_id'] );
			}
			if ( 'ambiguous' === $inspection['status'] ) {
				return array( 'result' => 'ambiguous_selection_required', 'page_id' => 0 );
			}
			return array( 'result' => 'would_create_managed_page', 'page_id' => 0 );
		}

		$lock_token = self::acquire_repair_lock();
		if ( '' === $lock_token ) {
			return array( 'result' => 'repair_locked', 'page_id' => 0 );
		}

		try {
			self::reset_cache();
			$inspection = self::inspect();

			if ( 'ready' === $inspection['status'] ) {
				self::$resolved_page_id = $inspection['configured_page_id'];
				return array( 'result' => 'no_change', 'page_id' => self::$resolved_page_id );
			}

			if ( 'repairable' === $inspection['status'] || 'ambiguous' === $inspection['status'] ) {
				$candidates = $inspection['candidate_page_ids'];
				$page_id    = 'repairable' === $inspection['status'] ? $inspection['discovered_page_id'] : absint( $selected_page_id );

				if ( 'ambiguous' === $inspection['status'] && 0 === $page_id ) {
					return array( 'result' => 'ambiguous_selection_required', 'page_id' => 0 );
				}

				if ( ! in_array( $page_id, $candidates, true ) || ! self::is_valid_page( $page_id ) ) {
					return array( 'result' => 'invalid_candidate', 'page_id' => 0 );
				}

				$persistence = self::persist_mapping( $page_id );
				if ( ! $persistence['persisted'] ) {
					return array( 'result' => 'mapping_persistence_failed', 'page_id' => $page_id );
				}

				return array( 'result' => 'mapped_existing', 'page_id' => $page_id );
			}

			$created = self::create_managed_page();
			if ( 'managed_page_created' !== $created['result'] ) {
				self::$resolved_page_id = 0;
				return $created;
			}

			$persistence = self::persist_mapping( $created['page_id'] );
			if ( ! $persistence['persisted'] ) {
				$page_rolled_back = self::rollback_created_page( $created['page_id'] );
				do_action( 'supc_created_page_mapping_rollback', $created['page_id'], $persistence['restored'], $page_rolled_back );
				return array( 'result' => 'mapping_persistence_failed', 'page_id' => $created['page_id'] );
			}

			return array( 'result' => 'created_managed_page', 'page_id' => $created['page_id'] );
		} finally {
			self::release_repair_lock( $lock_token );
		}
	}

	public static function reset_cache(): void {
		self::$resolved_page_id = null;
		self::$inspection_cache = null;
	}

	public static function url(): string {
		$page_id = self::resolve_page_id( false );
		return $page_id > 0 ? self::validated_permalink( $page_id ) : '';
	}

	public static function is_ready(): bool {
		return self::resolve_page_id( false ) > 0;
	}

	public static function is_create_request(): bool {
		$page_id = self::resolve_page_id( false );
		return $page_id > 0 && is_page( $page_id );
	}

	private static function is_valid_page( int $page_id ): bool {
		if ( $page_id <= 0 || 'page' !== get_post_type( $page_id ) || 'publish' !== get_post_status( $page_id ) ) {
			return false;
		}

		$content = (string) get_post_field( 'post_content', $page_id );
		return has_shortcode( $content, self::SHORTCODE ) && '' !== self::validated_permalink( $page_id );
	}

	private static function validated_permalink( int $page_id ): string {
		$url = get_permalink( $page_id );
		if ( ! is_string( $url ) ) {
			return '';
		}

		$url = trim( $url );
		if ( '' === $url || 1 === preg_match( '/[\x00-\x1F\x7F]/', $url ) || str_contains( $url, '\\' ) ) {
			return '';
		}

		$validated = wp_validate_redirect( $url, '' );
		if ( '' === $validated ) {
			return '';
		}
		if ( str_starts_with( $validated, '/' ) ) {
			return str_starts_with( $validated, '//' ) ? '' : $validated;
		}

		$target = wp_parse_url( $validated );
		$home   = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $target ) || ! is_array( $home ) ) {
			return '';
		}

		$target_scheme = strtolower( (string) ( $target['scheme'] ?? '' ) );
		$home_scheme   = strtolower( (string) ( $home['scheme'] ?? '' ) );
		$target_host   = strtolower( (string) ( $target['host'] ?? '' ) );
		$home_host     = strtolower( (string) ( $home['host'] ?? '' ) );
		if (
			'https' !== $target_scheme ||
			'https' !== $home_scheme ||
			'' === $target_host ||
			$target_host !== $home_host ||
			isset( $target['user'] ) ||
			isset( $target['pass'] )
		) {
			return '';
		}

		$target_port = isset( $target['port'] ) ? (int) $target['port'] : 443;
		$home_port   = isset( $home['port'] ) ? (int) $home['port'] : 443;
		return $target_port === $home_port ? $validated : '';
	}

	/**
	 * Search only likely shortcode-bearing pages instead of hydrating every
	 * published page ID when the canonical mapping is missing or damaged.
	 *
	 * @return array<int, int>
	 */
	private static function find_shortcode_pages(): array {
		$pages = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
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

		$matches = array();
		foreach ( $pages as $page_id ) {
			$page_id = (int) $page_id;
			if ( self::is_valid_page( $page_id ) ) {
				$matches[] = $page_id;
			}
		}

		return $matches;
	}

	/**
	 * @return array{result:string,page_id:int}
	 */
	private static function create_managed_page(): array {
		$slug = '';
		foreach ( self::APPROVED_SLUGS as $candidate ) {
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
				'post_title'   => __( 'Create', 'sabri-universal-post-composer' ),
				'post_name'    => $slug,
				'post_content' => '[' . self::SHORTCODE . ']',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'meta_input'   => array( self::MANAGED_META => 1 ),
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return array( 'result' => 'managed_page_insert_failed', 'page_id' => 0 );
		}

		$page_id = (int) $page_id;
		if ( ! self::is_valid_managed_page( $page_id, $slug ) ) {
			$rolled_back = self::rollback_created_page( $page_id );
			do_action( 'supc_invalid_managed_page_rollback', $page_id, $rolled_back );
			return array( 'result' => 'managed_page_validation_failed', 'page_id' => $page_id );
		}

		return array( 'result' => 'managed_page_created', 'page_id' => $page_id );
	}

	private static function is_valid_managed_page( int $page_id, string $expected_slug ): bool {
		$managed = get_post_meta( $page_id, self::MANAGED_META, true );
		return self::is_valid_page( $page_id )
			&& $expected_slug === (string) get_post_field( 'post_name', $page_id )
			&& in_array( $managed, array( 1, '1' ), true );
	}

	/**
	 * @return array{persisted:bool,restored:bool}
	 */
	private static function persist_mapping( int $page_id ): array {
		$previous = get_option( self::OPTION_KEY, self::MISSING_MAPPING );
		update_option( self::OPTION_KEY, $page_id, false );
		if ( $page_id === absint( get_option( self::OPTION_KEY, 0 ) ) ) {
			self::reset_cache();
			self::$resolved_page_id = $page_id;
			return array( 'persisted' => true, 'restored' => true );
		}

		$restored = self::restore_mapping( $previous );
		self::reset_cache();
		do_action( 'supc_mapping_persistence_rollback', $page_id, $restored );
		return array( 'persisted' => false, 'restored' => $restored );
	}

	private static function restore_mapping( mixed $previous ): bool {
		if ( self::MISSING_MAPPING === $previous ) {
			delete_option( self::OPTION_KEY );
			return self::MISSING_MAPPING === get_option( self::OPTION_KEY, self::MISSING_MAPPING );
		}

		update_option( self::OPTION_KEY, $previous, false );
		return $previous === get_option( self::OPTION_KEY, self::MISSING_MAPPING );
	}

	private static function rollback_created_page( int $page_id ): bool {
		if ( ! function_exists( 'wp_delete_post' ) ) {
			return false;
		}

		$deleted = wp_delete_post( $page_id, true );
		return false !== $deleted && null !== $deleted;
	}

	private static function acquire_repair_lock(): string {
		$existing = get_option( self::REPAIR_LOCK_OPTION, false );
		$now      = time();
		if ( false !== $existing && ! self::repair_lock_is_active( $existing, $now ) ) {
			delete_option( self::REPAIR_LOCK_OPTION );
			$existing = false;
		}

		if ( false !== $existing ) {
			return '';
		}

		$token = wp_generate_uuid4();
		$added = add_option(
			self::REPAIR_LOCK_OPTION,
			array( 'token' => $token, 'created' => $now ),
			'',
			false
		);
		return $added ? $token : '';
	}

	private static function repair_lock_is_active( mixed $lock, int $now ): bool {
		if ( ! is_array( $lock ) ) {
			return false;
		}

		$token   = (string) ( $lock['token'] ?? '' );
		$created = (int) ( $lock['created'] ?? 0 );
		return 1 === preg_match( self::UUID_V4_PATTERN, $token )
			&& $created > $now - self::REPAIR_LOCK_TTL
			&& $created <= $now + self::REPAIR_LOCK_TTL;
	}

	private static function release_repair_lock( string $token ): void {
		$lock = get_option( self::REPAIR_LOCK_OPTION, false );
		if ( is_array( $lock ) && $token === (string) ( $lock['token'] ?? '' ) ) {
			delete_option( self::REPAIR_LOCK_OPTION );
		}
	}
}
