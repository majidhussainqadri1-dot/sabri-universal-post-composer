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
	public const VERSION = '1.0.0';

	/** @return array<string,array<int,string>> */
	public static function aliases(): array {
		$map = array(
			'standard_publication' => array( 'post', 'social_post', 'doctor_post', 'founder_post' ),
			'official_news'        => array( 'news', 'editorial_news', 'announcement' ),
			'patient_case'         => array( 'clinical_case', 'public_patient_case', 'case_report' ),
			'successful_case'      => array( 'success_case', 'successful_patient_case' ),
			'learning'             => array( 'lesson', 'course_lesson', 'learning_resource' ),
			'encyclopedia'         => array( 'knowledge_entry', 'encyclopedia_entry' ),
			'video'                => array( 'long_video', 'recorded_video', 'live_replay' ),
			'reel'                 => array( 'short_video', 'vertical_video' ),
			'pdf'                  => array( 'document', 'book', 'pdf_document' ),
			'marketplace'          => array( 'product', 'listing', 'marketplace_listing' ),
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
			foreach ( $provided as $alias ) {
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
