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
	private const SHELL_SAFE_MODE_CLASS = '\\Sabri\\UnifiedShell\\SafeMode';
	private const SHELL_CREATE_MARKERS = array(
		'SABRI_SHELL_CREATE_CONTRACT_VERSION',
		'SABRI_SHELL_CREATE_CONTRACT_OWNER',
		'SABRI_SHELL_CREATE_FUNCTIONS_OWNED',
	);
	private const SHELL_CREATE_FUNCTIONS = array(
		'sabri_shell_create_contract_available',
		'sabri_shell_create_visible_for_current_user',
	);

	/**
	 * @return array<int,string>
	 */
	public static function public_api_functions(): array {
		return self::PUBLIC_API_FUNCTIONS;
	}

	/**
	 * @return array<int,string>
	 */
	public static function shell_create_functions(): array {
		return self::SHELL_CREATE_FUNCTIONS;
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
			! defined( 'SUPC_PUBLIC_API_OWNER' ) ||
			! defined( 'SUPC_PUBLIC_API_FUNCTIONS_OWNED' ) ||
			! defined( 'SUPC_PUBLIC_API_COLLISIONS' )
		) {
			return false;
		}

		$version    = constant( 'SUPC_PUBLIC_API_VERSION' );
		$owner      = constant( 'SUPC_PUBLIC_API_OWNER' );
		$owned      = constant( 'SUPC_PUBLIC_API_FUNCTIONS_OWNED' );
		$collisions = constant( 'SUPC_PUBLIC_API_COLLISIONS' );
		if (
			self::PUBLIC_API_VERSION !== $version ||
			self::PUBLIC_API_OWNER !== $owner ||
			true !== $owned ||
			! is_string( $collisions ) ||
			'' !== $collisions
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

	/**
	 * A base File 20 package claim is not the same thing as the later optional
	 * File 20 Create contract. The shipped File 20 version 1.0.0 exposes the base
	 * package constants and URL filter but no Create-contract markers/functions.
	 */
	public static function shell_package_claimed(): bool {
		return defined( 'SABRI_SHELL_FILE' )
			|| defined( 'SABRI_SHELL_PATH' )
			|| defined( 'SABRI_SHELL_SLUG' )
			|| defined( 'SABRI_SHELL_VERSION' )
			|| class_exists( self::SHELL_SAFE_MODE_CLASS, false );
	}

	public static function shell_create_contract_claimed(): bool {
		foreach ( self::SHELL_CREATE_MARKERS as $marker ) {
			if ( defined( $marker ) ) {
				return true;
			}
		}

		foreach ( self::SHELL_CREATE_FUNCTIONS as $function ) {
			if ( function_exists( $function ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Backward-compatible aggregate claim query used by existing diagnostics.
	 */
	public static function shell_claimed(): bool {
		return self::shell_package_claimed() || self::shell_create_contract_claimed();
	}

	public static function shell_package_owned(): bool {
		return null !== self::shell_package();
	}

	/**
	 * The optional Create contract is trusted only as one atomic package-owned
	 * family. A partially claimed family must never become an authorization or
	 * emergency-state authority.
	 */
	public static function shell_create_contract_owned(): bool {
		if (
			! defined( 'SABRI_SHELL_CREATE_CONTRACT_VERSION' ) ||
			! defined( 'SABRI_SHELL_CREATE_CONTRACT_OWNER' ) ||
			! defined( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED' )
		) {
			return false;
		}

		$version = constant( 'SABRI_SHELL_CREATE_CONTRACT_VERSION' );
		$owner   = constant( 'SABRI_SHELL_CREATE_CONTRACT_OWNER' );
		$owned   = constant( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED' );
		return '1.0.1' === $version
			&& self::SHELL_SLUG === $owner
			&& true === $owned
			&& self::shell_symbols_owned( self::SHELL_CREATE_FUNCTIONS, self::SHELL_SAFE_MODE_CLASS )
			&& null !== self::owned_shell_static_method( self::SHELL_SAFE_MODE_CLASS, 'disabled' );
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
				if ( ! class_exists( $class_name, false ) ) {
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

	public static function owned_shell_static_method( string $class_name, string $method ): ?\Closure {
		$package = self::shell_package();
		if ( null === $package || '' === $class_name || '' === $method || ! class_exists( $class_name, false ) ) {
			return null;
		}

		try {
			$class = new \ReflectionClass( $class_name );
			if ( ! self::source_is_inside( $class->getFileName(), $package['path'] ) || ! $class->hasMethod( $method ) ) {
				return null;
			}

			$reflection_method = $class->getMethod( $method );
			if (
				! $reflection_method->isPublic() ||
				! $reflection_method->isStatic() ||
				! self::source_is_inside( $reflection_method->getFileName(), $package['path'] )
			) {
				return null;
			}

			return \Closure::fromCallable( array( $class_name, $method ) );
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
			! defined( 'SABRI_SHELL_VERSION' )
		) {
			return null;
		}

		$slug    = constant( 'SABRI_SHELL_SLUG' );
		$version = constant( 'SABRI_SHELL_VERSION' );
		$file    = realpath( (string) constant( 'SABRI_SHELL_FILE' ) );
		$path    = realpath( (string) constant( 'SABRI_SHELL_PATH' ) );
		if (
			self::SHELL_SLUG !== $slug ||
			! is_string( $version ) ||
			! Version::valid( $version ) ||
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
