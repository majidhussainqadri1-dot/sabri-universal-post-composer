<?php
/**
 * Strict bounded input and runtime-contract validation helpers.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contract_Boundary {
	private const UNSAFE_TEXT_CONTROLS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';
	private const UNSAFE_TOKEN_CONTROLS = '/[\x00-\x1F\x7F-\x9F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

	public static function valid_utf8( string $value ): bool {
		return 1 === preg_match( '//u', $value );
	}

	public static function bounded_text( string $value, int $minimum_bytes, int $maximum_bytes, bool $allow_line_breaks = false ): bool {
		$length = strlen( $value );
		if ( $minimum_bytes < 0 || $maximum_bytes < $minimum_bytes || $length < $minimum_bytes || $length > $maximum_bytes || ! self::valid_utf8( $value ) ) {
			return false;
		}

		$pattern = $allow_line_breaks ? self::UNSAFE_TEXT_CONTROLS : self::UNSAFE_TOKEN_CONTROLS;
		return 0 === preg_match( $pattern, $value );
	}

	public static function canonical( string $value, string $pattern, int $maximum_bytes ): bool {
		return self::bounded_text( $value, 1, $maximum_bytes )
			&& $value === trim( $value )
			&& 1 === preg_match( $pattern, $value );
	}

	public static function adapter_key( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9_]{2,63}$/D', 64 );
	}

	public static function capability( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9_-]{0,63}$/D', 64 );
	}

	public static function native_module( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9-]{2,127}$/D', 128 );
	}

	public static function group( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9_]{0,63}$/D', 64 );
	}

	public static function code( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9_.:-]{0,63}$/D', 64 );
	}

	public static function field_key( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9_]{0,63}$/D', 64 );
	}

	public static function version( string $value ): bool {
		return self::bounded_text( $value, 1, 255 ) && $value === trim( $value ) && Version::valid( $value );
	}

	public static function diagnostic_storage_key( string $candidate, string $prefix = 'invalid' ): string {
		if ( self::adapter_key( $candidate ) ) {
			return $candidate;
		}

		$prefix = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $prefix ) ) ?? 'invalid';
		$prefix = '' !== $prefix ? substr( $prefix, 0, 24 ) : 'invalid';
		return '[' . $prefix . ']:' . substr( hash( 'sha256', $candidate ), 0, 20 );
	}

	public static function public_identifier( string $candidate ): string {
		return self::adapter_key( $candidate ) ? $candidate : 'invalid_adapter';
	}

	public static function declared_public_instance_method( object $object, string $method, int $required_parameters ): bool {
		if ( '' === $method || $required_parameters < 0 || ! method_exists( $object, $method ) ) {
			return false;
		}

		try {
			$reflection = new \ReflectionMethod( $object, $method );
			if ( ! $reflection->isPublic() || $reflection->isStatic() || $reflection->isAbstract() ) {
				return false;
			}
			if ( $reflection->getNumberOfRequiredParameters() !== $required_parameters || $reflection->getNumberOfParameters() !== $required_parameters ) {
				return false;
			}

			$parameters = $reflection->getParameters();
			if ( 1 === $required_parameters ) {
				$type = $parameters[0]->getType();
				if ( $type instanceof \ReflectionNamedType && ( $type->allowsNull() || 'int' !== $type->getName() ) ) {
					return false;
				}
			}

			$return_type = $reflection->getReturnType();
			return ! $return_type instanceof \ReflectionNamedType || ( ! $return_type->allowsNull() && 'array' === $return_type->getName() );
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}
}
