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
	private const PUBLIC_API_VERSION = '1.0.0';
	private const PUBLIC_API_OWNER   = 'sabri-universal-post-composer';
	private const PUBLIC_API_MARKERS = array(
		'SUPC_PUBLIC_API_VERSION',
		'SUPC_PUBLIC_API_OWNER',
		'SUPC_PUBLIC_API_FUNCTIONS_OWNED',
		'SUPC_PUBLIC_API_COLLISIONS',
	);
	private const PUBLIC_API_FUNCTIONS = array(
		'supc_register_adapter',
		'supc_unregister_adapter',
		'supc_adapter_available',
		'supc_adapter_matches',
		'supc_workflow_schema',
		'supc_workflow_create_draft',
		'supc_workflow_validate',
		'supc_workflow_preview',
		'supc_workflow_submit',
		'supc_workflow_status',
		'supc_workflow_canonical_url',
		'supc_generate_idempotency_key',
	);
	private const SHELL_DIRECTORY = 'sabri-unified-application-shell';
	private const SHELL_FILE      = 'sabri-unified-application-shell.php';
	private const SHELL_SLUG      = 'sabri-unified-application-shell';

	/**
	 * @return array<int,string>
	 */
	public static function public_api_functions(): array {
		return self::PUBLIC_API_FUNCTIONS;
	}

	public static function public_api_claimed(): bool {
		foreach ( self::PUBLIC_API_MARKERS as $marker ) {
			if ( defined( $marker ) ) {
				return true;
			}
		}

		foreach ( self::PUBLIC_API_FUNCTIONS as $function ) {
			if ( function_exists( $function ) ) {
				return true;
			}
		}

		return false;
	}

	public static function public_api_owned( string $expected_file ): bool {
		if (
			! defined( 'SUPC_PUBLIC_API_VERSION' ) ||
			self::PUBLIC_API_VERSION !== (string) SUPC_PUBLIC_API_VERSION ||
			! defined( 'SUPC_PUBLIC_API_OWNER' ) ||
			self::PUBLIC_API_OWNER !== (string) SUPC_PUBLIC_API_OWNER ||
			! defined( 'SUPC_PUBLIC_API_FUNCTIONS_OWNED' ) ||
			true !== SUPC_PUBLIC_API_FUNCTIONS_OWNED ||
			! defined( 'SUPC_PUBLIC_API_COLLISIONS' ) ||
			! is_string( SUPC_PUBLIC_API_COLLISIONS ) ||
			'' !== SUPC_PUBLIC_API_COLLISIONS
		) {
			return false;
		}

		return self::functions_declared_by_file( self::PUBLIC_API_FUNCTIONS, $expected_file );
	}

	/**
	 * @param array<int,string> $functions Global function names.
	 */
	public static function functions_available( array $functions ): bool {
		if ( array() === $functions ) {
			return false;
		}

		foreach ( $functions as $function ) {
			if ( ! is_string( $function ) || '' === $function || ! function_exists( $function ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<int,string> $functions Global function names.
	 */
	public static function functions_declared_by_file( array $functions, string $expected_file ): bool {
		$expected_file = realpath( $expected_file );
		if ( false === $expected_file || ! self::functions_available( $functions ) ) {
			return false;
		}

		try {
			foreach ( $functions as $function ) {
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

	public static function owned_shell_function( string $function ): ?\Closure {
		if ( '' === $function || ! self::shell_symbols_owned( array( $function ) ) || ! is_callable( $function ) ) {
			return null;
		}

		try {
			return \Closure::fromCallable( $function );
		} catch ( \Throwable $error ) {
			unset( $error );
			return null;
		}
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
