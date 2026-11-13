<?php
/**
 * WP-041/044: uploading after metadata TTL is never authorized solely because
 * the cron cleanup event is delayed. Native bytes and provider state are not
 * materialized in these isolated tests.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Upload_Token_Store;

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

final class UploadExpiryDatabaseProbe {
    public string $prefix = 'wp_';
    /** @var array<string,mixed>|null */
    public ?array $row = null;
    public int $write_count = 0;
    public int $insert_count = 0;

    public function prepare( string $sql, ...$params ): string {
        unset( $params );
        return $sql;
    }

    /** @return array<string,mixed>|null */
    public function get_row( string $query, mixed $output = null ): ?array {
        unset( $query, $output );
        return $this->row;
    }

    public function query( string $sql ): int {
        unset( $sql );
        $this->write_count++;
        return 1;
    }

    public function insert( string $table, array $data, array $format ): int {
        unset( $table, $data, $format );
        $this->insert_count++;
        return 1;
    }
}

final class UploadTokenExpiryAtUseTest extends TestCase {
    private bool $had_db;
    private mixed $prior_db;
    private UploadExpiryDatabaseProbe $db;
    private Upload_Token_Store $store;

    protected function setUp(): void {
        $this->had_db = array_key_exists( 'wpdb', $GLOBALS );
        $this->prior_db = $GLOBALS['wpdb'] ?? null;
        $this->db = new UploadExpiryDatabaseProbe();
        $this->store = new Upload_Token_Store();
        $GLOBALS['wpdb'] = $this->db;
    }

    protected function tearDown(): void {
        if ( $this->had_db ) {
            $GLOBALS['wpdb'] = $this->prior_db;
        } else {
            unset( $GLOBALS['wpdb'] );
        }
    }

    /** @return array<string,mixed> */
    private function row( int $offset ): array {
        $uuid = '123e4567-e89b-42d3-a456-426614174000';
        return array(
            'upload_uuid' => '123e4567-e89b-42d3-a456-426614174001',
            'session_uuid' => $uuid,
            'user_id' => 1,
            'adapter_key' => 'social_publication',
            'native_upload_reference' => 'media:native:1',
            'purpose' => 'media_reference',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'checksum_sha256' => null,
            'status' => 'issued',
            'created_at' => gmdate( 'Y-m-d H:i:s', time() - 600 ),
            'updated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
            'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $offset ),
        );
    }

    public function test_expired_upload_cannot_be_retrieved_for_native_completion(): void {
        $this->db->row = $this->row( -3600 );
        $result = $this->store->get_owned( (string) $this->db->row['upload_uuid'], 1 );
        self::assertInstanceOf( WP_Error::class, $result );
        self::assertSame( 'supc_upload_expired', $result->get_error_code() );
    }

    public function test_expired_upload_cannot_transition_even_if_cron_did_not_clean_it(): void {
        $this->db->row = $this->row( -3600 );
        $result = $this->store->transition( (string) $this->db->row['upload_uuid'], 1, 'issued', 'completed' );
        self::assertInstanceOf( WP_Error::class, $result );
        self::assertSame( 'supc_upload_expired', $result->get_error_code() );
        self::assertSame( 0, $this->db->write_count );
    }

    public function test_retrying_register_cannot_reuse_expired_native_reference(): void {
        $this->db->row = $this->row( -3600 );
        $result = $this->store->register(
            (string) $this->db->row['session_uuid'],
            1,
            'social_publication',
            'media:native:1',
            'media_reference',
            array( 'mime_type' => 'image/png', 'size_bytes' => 1024 )
        );
        self::assertInstanceOf( WP_Error::class, $result );
        self::assertSame( 'supc_upload_expired', $result->get_error_code() );
        self::assertSame( 0, $this->db->insert_count );
    }

    public function test_valid_issued_and_valid_terminal_records_remain_readable(): void {
        $this->db->row = $this->row( 3600 );
        $valid = $this->store->get_owned( (string) $this->db->row['upload_uuid'], 1 );
        self::assertIsArray( $valid );
        self::assertSame( 'issued', $valid['status'] );
        $this->db->row['status'] = 'completed';
        $terminal = $this->store->get_owned( (string) $this->db->row['upload_uuid'], 1 );
        self::assertIsArray( $terminal );
        self::assertSame( 'completed', $terminal['status'] );
    }

    public function test_malformed_database_expiry_fails_closed(): void {
        $this->db->row = $this->row( 3600 );
        $this->db->row['expires_at'] = 'not-a-date';
        $result = $this->store->get_owned( (string) $this->db->row['upload_uuid'], 1 );
        self::assertInstanceOf( WP_Error::class, $result );
        self::assertSame( 'supc_upload_expired', $result->get_error_code() );
    }
}
