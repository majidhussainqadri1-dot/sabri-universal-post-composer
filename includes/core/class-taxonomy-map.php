<?php
/**
 * Versioned canonical content-type aliases used only for orchestration.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Taxonomy_Map {
	public const VERSION = '1.1.0';

	/** @return array<string,array<int,string>> */
	public static function aliases(): array {
		$map = array(
			'standard_publication' => array( 'post', 'social_post', 'doctor_post', 'founder_post' ),
			'official_news'        => array( 'news', 'platform_news', 'platform-news', 'editorial_news', 'editorial-news', 'announcement' ),
			'patient_case'         => array( 'patient_cases', 'patient-cases', 'clinical_case', 'clinical_cases', 'clinical-cases', 'public_patient_case', 'case_report' ),
			'successful_case'      => array( 'success_case', 'successful_patient_case' ),
			'learning'             => array( 'lesson', 'course_lesson', 'learning_resource' ),
			'encyclopedia'         => array( 'knowledge_entry', 'encyclopedia_entry' ),
			'video'                => array( 'long_video', 'recorded_video', 'live_replay' ),
			'reel'                 => array( 'short_video', 'vertical_video' ),
			'pdf'                  => array( 'document', 'book', 'pdf_document' ),
			'marketplace'          => array( 'product', 'listing', 'marketplace_listing' ),
			'principles_hygiene'    => array( 'principles-hygiene', 'principles_of_hygiene', 'principles-of-hygiene', 'hygiene_principles' ),
		);
		$filtered = apply_filters( 'supc_taxonomy_alias_map', $map, self::VERSION );
		if ( ! is_array( $filtered ) ) {
			return $map;
		}

		// The built-in canonical keys may be extended but never removed or
		// renamed by a mutable filter. Every added alias is normalized and
		// bounded before it can influence orchestration.
		foreach ( $map as $canonical => $aliases ) {
			$provided = isset( $filtered[ $canonical ] ) && is_array( $filtered[ $canonical ] ) ? $filtered[ $canonical ] : array();
			foreach ( array_slice( $provided, 0, 128 ) as $alias ) {
				if ( ! is_string( $alias ) ) {
					continue;
				}
				$alias = sanitize_key( $alias );
				if ( '' !== $alias && strlen( $alias ) <= 64 ) {
					$aliases[] = $alias;
				}
			}
			$map[ $canonical ] = array_values( array_unique( $aliases ) );
		}
		return $map;
	}

	/**
	 * Verify the mandatory cross-file aliases and reject ambiguous alias
	 * collisions introduced by extensions. This is a System Check gate, not a
	 * second taxonomy database: native modules still own their real term IDs.
	 *
	 * @return array<int,string>
	 */
	public static function integrity_codes(): array {
		$map   = self::aliases();
		$codes = array();
		$required = array(
			'official_news'     => array( 'platform_news', 'platform-news', 'editorial_news', 'editorial-news' ),
			'patient_case'      => array( 'patient_cases', 'patient-cases', 'clinical_case', 'clinical_cases', 'clinical-cases' ),
			'principles_hygiene' => array( 'principles-hygiene', 'principles_of_hygiene', 'principles-of-hygiene' ),
		);

		foreach ( $required as $canonical => $aliases ) {
			if ( ! isset( $map[ $canonical ] ) || ! is_array( $map[ $canonical ] ) ) {
				$codes[] = 'taxonomy_missing_' . $canonical;
				continue;
			}
			foreach ( $aliases as $alias ) {
				if ( ! in_array( $alias, $map[ $canonical ], true ) ) {
					$codes[] = 'taxonomy_alias_missing_' . sanitize_key( str_replace( '-', '_', $alias ) );
				}
			}
		}

		$owners = array();
		foreach ( $map as $canonical => $aliases ) {
			$all = array_merge( array( $canonical ), is_array( $aliases ) ? $aliases : array() );
			foreach ( $all as $alias ) {
				if ( ! is_string( $alias ) ) {
					$codes[] = 'taxonomy_alias_invalid';
					continue;
				}
				$normalized = sanitize_key( $alias );
				if ( '' === $normalized || strlen( $normalized ) > 64 ) {
					$codes[] = 'taxonomy_alias_invalid';
					continue;
				}
				if ( isset( $owners[ $normalized ] ) && $owners[ $normalized ] !== $canonical ) {
					$codes[] = 'taxonomy_alias_collision_' . sanitize_key( str_replace( '-', '_', $normalized ) );
					continue;
				}
				$owners[ $normalized ] = $canonical;
			}
		}

		return array_values( array_unique( array_slice( $codes, 0, 100 ) ) );
	}

	public static function canonical( string $value ): string {
		$value = sanitize_key( $value );
		foreach ( self::aliases() as $canonical => $aliases ) {
			if ( $value === $canonical || in_array( $value, $aliases, true ) ) {
				return $canonical;
			}
		}
		return $value;
	}
}
