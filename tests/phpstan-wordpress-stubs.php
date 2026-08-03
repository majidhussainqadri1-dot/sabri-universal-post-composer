<?php
/**
 * Minimal WordPress and plugin signatures used only by PHPStan.
 */

declare(strict_types=1);

if ( ! defined( 'SUPC_WORKFLOW_API_VERSION' ) ) {
	define( 'SUPC_WORKFLOW_API_VERSION', '1.0.0' );
}

if ( ! defined( 'SUPC_FILE' ) ) {
	define( 'SUPC_FILE', __FILE__ );
}

if ( ! defined( 'SUPC_PATH' ) ) {
	define( 'SUPC_PATH', dirname( __DIR__ ) . '/' );
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	final class WP_REST_Server {
		public const READABLE  = 'GET';
		public const CREATABLE = 'POST';
		public const EDITABLE  = 'POST, PUT, PATCH';
		public const DELETABLE = 'DELETE';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/** @implements ArrayAccess<string,mixed> */
	class WP_REST_Request implements ArrayAccess {
		/** @var array<string,mixed> */
		private array $params = array();

		public function get_method(): string {
			return 'GET';
		}

		public function get_header( string $key ): string {
			unset( $key );
			return '';
		}

		public function get_route(): string {
			return '';
		}

		public function get_body(): string {
			return '';
		}

		/** @return array<string,mixed>|null */
		public function get_json_params(): ?array {
			return array();
		}

		public function offsetExists( mixed $offset ): bool {
			return is_string( $offset ) && array_key_exists( $offset, $this->params );
		}

		public function offsetGet( mixed $offset ): mixed {
			return is_string( $offset ) ? ( $this->params[ $offset ] ?? null ) : null;
		}

		public function offsetSet( mixed $offset, mixed $value ): void {
			if ( is_string( $offset ) ) {
				$this->params[ $offset ] = $value;
			}
		}

		public function offsetUnset( mixed $offset ): void {
			if ( is_string( $offset ) ) {
				unset( $this->params[ $offset ] );
			}
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @param array<string,mixed> $data */
		public function __construct( public array $data = array(), public int $status = 200 ) {
		}

		public function header( string $key, string $value, bool $replace = true ): void {
			unset( $key, $value, $replace );
		}
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $value, $flags, $depth );
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( string $domain, bool $deprecated = false, string $plugin_rel_path = '' ): bool {
		unset( $domain, $deprecated, $plugin_rel_path );
		return true;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return basename( $file );
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( string $tag, callable $callback ): void {
		unset( $tag, $callback );
	}
}

if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers(): void {
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( string $email ): string|false {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}
