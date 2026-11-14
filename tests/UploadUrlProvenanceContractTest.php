<?php
/**
 * Source-only guards for a native provider's browser-upload destination.
 *
 * A native provider remains responsible for endpoint authority, signed URLs,
 * MIME/rights/scan and data retention. These tests do not certify a provider.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;
use Sabri\UniversalComposer\Core\Workflow_Validator;

final class UploadUrlProvenanceContractTest extends TestCase {
	/** @return array<string,mixed>|WP_Error */
	private function validate( string $url ): array|WP_Error {
		$coordinator = ( new ReflectionClass( Workflow_Coordinator::class ) )->newInstanceWithoutConstructor();
		$property    = new ReflectionProperty( Workflow_Coordinator::class, 'validator' );
		$property->setValue( $coordinator, new Workflow_Validator() );
		$method      = new ReflectionMethod( Workflow_Coordinator::class, 'validate_upload_result' );

		return $method->invoke(
			$coordinator,
			array( 'upload_reference' => 'media:native:1', 'status' => 'issued', 'upload_url' => $url ),
			'social_publication'
		);
	}

	/** @return iterable<string,array{string}> */
	public static function unsafeUrls(): iterable {
		yield 'hostless https URI' => array( 'https:upload-asset' );
		yield 'hostless slash' => array( 'https:/upload-asset' );
		yield 'protocol relative' => array( '//assets.example.test/upload' );
		yield 'http downgrade' => array( 'http://assets.example.test/upload' );
		yield 'userinfo' => array( 'https://admin:pass@assets.example.test/upload' );
		yield 'URL fragment' => array( 'https://assets.example.test/upload#private-secret' );
		yield 'javascript' => array( 'javascript:alert(1)' );
	}

	#[DataProvider( 'unsafeUrls' )]
	public function test_untrusted_native_url_is_rejected_before_browser_upload( string $url ): void {
		$result = $this->validate( $url );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'supc_invalid_native_upload_url', $result->get_error_code() );
		self::assertStringNotContainsString( $url, (string) wp_json_encode( $result->get_error_data() ) );
	}

	public function test_valid_https_provider_destination_remains_available(): void {
		$result = $this->validate( 'https://assets.example.test/upload?signature=abc%2F123&expires=1234' );
		self::assertIsArray( $result );
		self::assertSame( 'media:native:1', $result['upload_reference'] );
		self::assertSame( 'issued', $result['status'] );
		self::assertStringStartsWith( 'https://assets.example.test/upload?', $result['upload_url'] );
	}

	public function test_native_upload_response_does_not_inherit_unknown_private_fields(): void {
		$coordinator = ( new ReflectionClass( Workflow_Coordinator::class ) )->newInstanceWithoutConstructor();
		$property = new ReflectionProperty( Workflow_Coordinator::class, 'validator' );
		$property->setValue( $coordinator, new Workflow_Validator() );
		$method = new ReflectionMethod( Workflow_Coordinator::class, 'validate_upload_result' );
		$result = $method->invoke( $coordinator, array(
			'upload_reference' => 'media:native:1',
			'status' => 'issued',
			'upload_url' => 'https://assets.example.test/upload',
			'patient_name' => 'must_not_escape',
			'secret_token' => 'private',
		), 'social_publication' );
		self::assertIsArray( $result );
		self::assertArrayNotHasKey( 'patient_name', $result );
		self::assertArrayNotHasKey( 'secret_token', $result );
	}
}
