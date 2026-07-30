<?php
/**
 * Reflection-backed ownership verification for cross-plugin runtime symbols.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Runtime_Trust {
	private const SHELL_DIRECTORY = 'sabri-unified-application-shell';
	private const SHELL_FILE      = 'sabri-unified-application-shell.php';
	private const SHELL_SLUG      = 'sabri-unified-application-shell';

	/**
	 * @param array<int,string> $functions Global function names.
	 */
	public static function functions_declared_by_file( array $functions, string $expected_file ): bool {
		$expected_file = realpath( $expected_file );
		if ( false === $expected_file || array() === $functions ) {
			return false;
		}

		try {
			foreach ( $functions as $function ) {
				if ( ! is_string( $function ) || '' === $function || ! function_exists( $function ) ) {
					return false;
				}

				$source = ( new \ReflectionFunction( $function ) )->getFileName();
				$source = is_string( $source ) ? realpath( $source ) : false;
				if ( false === $source || $source !== $expected_file ) {
					return false;
				}
			}
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}

		return true;
	}

	public static function shell_claimed(): bool {
		return defined( 'SABRI_SHELL_FILE' )
			|| defined( 'SABRI_SHELL_PATH' )
			|| defined( 'SABRI_SHELL_SLUG' )
			|| defined( 'SABRI_SHELL_VERSION' )
			|| defined( 'SABRI_SHELL_CREATE_CONTRACT_VERSION' )
			|| defined( 'SABRI_SHELL_CREATE_CONTRACT_OWNER' )
			|| defined( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED' )
			|| class_exists( '\Sabri\UnifiedShell\SafeMode', false );
	}

	/**
	 * @param array<int,string> $functions Global File 20 function names.
	 */
	public static function shell_symbols_owned( array $functions = array(), string $class_name = '' ): bool {
		$package = self::shell_package();
		if ( null === $package ) {
			return false;
		}

		try {
			foreach ( $functions as $function ) {
				if ( ! is_string( $function ) || '' === $function || ! function_exists( $function ) ) {
					return false;
				}

				$source = ( new \ReflectionFunction( $function ) )->getFileName();
				if ( ! self::source_is_inside( $source, $package['path'] ) ) {
					return false;
				}
			}

			if ( '' !== $class_name ) {
				if ( ! class_exists( $class_name ) ) {
					return false;
				}

				$source = ( new \ReflectionClass( $class_name ) )->getFileName();
				if ( ! self::source_is_inside( $source, $package['path'] ) ) {
					return false;
				}
			}
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}

		return true;
	}

	/**
	 * @return array{file:string,path:string}|null
	 */
	private static function shell_package(): ?array {
		if (
			! defined( 'SABRI_SHELL_FILE' ) ||
			! defined( 'SABRI_SHELL_PATH' ) ||
			! defined( 'SABRI_SHELL_SLUG' ) ||
			! defined( 'SABRI_SHELL_VERSION' ) ||
			self::SHELL_SLUG !== (string) SABRI_SHELL_SLUG ||
			! Version::valid( (string) SABRI_SHELL_VERSION )
		) {
			return null;
		}

		$file = realpath( (string) SABRI_SHELL_FILE );
		$path = realpath( (string) SABRI_SHELL_PATH );
		if (
			false === $file ||
			false === $path ||
			dirname( $file ) !== $path ||
			self::SHELL_FILE !== basename( $file ) ||
			self::SHELL_DIRECTORY !== basename( $path )
		) {
			return null;
		}

		return array( 'file' => $file, 'path' => $path );
	}

	private static function source_is_inside( string|false $source, string $path ): bool {
		$source = is_string( $source ) ? realpath( $source ) : false;
		if ( false === $source ) {
			return false;
		}

		$prefix = rtrim( $path, '/\\' ) . DIRECTORY_SEPARATOR;
		return $source === $path || str_starts_with( $source, $prefix );
	}
}
