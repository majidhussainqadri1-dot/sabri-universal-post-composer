<?php
/**
 * WP-030: regression for metadata retention across outbox enqueue failures.
 * The isolated fake only observes generated SQL; actual MySQL and live
 * migration behaviour remain independent staging acceptance gates.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Session_Store;

final class SessionCleanupDatabaseProbe {
    public string $prefix = 'wp_';
    public bool $tables_exist = true;
    /** @var array<int,string> */
    public array $queries = array();

    public function esc_like( string $name ): string {
        return $name;
    }

    public function prepare( string $sql, ...$params ): string {
        // Only the SHOW TABLES argument is interpolated; query() receives
        // its parameterized source template for structural inspection.
        if ( str_contains( $sql, 'SHOW TABLES LIKE' ) ) {
            return 'SHOW TABLES LIKE ' . (string) ( $params[0] ?? '' );
        }
        return $sql;
    }

    public function get_var( string $query ): ?string {
        if ( ! $this->tables_exist ) {
            return null;
        }
        if ( str_contains( $query, 'wp_supc_submissions' ) ) {
            return 'wp_supc_submissions';
        }
        if ( str_contains( $query, 'wp_supc_outbox' ) ) {
            return 'wp_supc_outbox';
        }
        return null;
    }

    public function query( string $sql ): int {
        $this->queries[] = $sql;
        return 1;
    }
}

final class UncertainSessionCleanupTest extends TestCase {
    private mixed $previous_db;
    private bool $had_previous_db;

    protected function setUp(): void {
        $this->had_previous_db = array_key_exists( 'wpdb', $GLOBALS );
        $this->previous_db = $GLOBALS['wpdb'] ?? null;
    }

    protected function tearDown(): void {
        if ( $this->had_previous_db ) {
            $GLOBALS['wpdb'] = $this->previous_db;
        } else {
            unset( $GLOBALS['wpdb'] );
        }
    }

    public function test_cleanup_preserves_unresolved_submissions_even_when_outbox_has_no_row(): void {
        $probe = new SessionCleanupDatabaseProbe();
        $GLOBALS['wpdb'] = $probe;

        self::assertSame( 1, Session_Store::cleanup_expired() );
        self::assertCount( 1, $probe->queries );
        $sql = $probe->queries[0];

        self::assertStringContainsString( 'DELETE FROM %i WHERE expires_at < %s', $sql );
        self::assertStringContainsString(
            "session_uuid NOT IN (SELECT session_uuid FROM %i WHERE status IN ('queued','retry','processing','dead_letter'))",
            $sql
        );
        self::assertStringContainsString(
            "session_uuid NOT IN (SELECT session_uuid FROM %i WHERE state IN ('prepared','dispatched','reconcile','retryable','dead_letter'))",
            $sql
        );
        self::assertStringContainsString( 'LIMIT 500', $sql );
        self::assertSame( 2, substr_count( $sql, 'session_uuid NOT IN' ), 'Both independent recovery tables must guard deletion.' );
    }

    public function test_cleanup_fails_closed_when_submission_or_outbox_schema_is_missing(): void {
        $probe = new SessionCleanupDatabaseProbe();
        $probe->tables_exist = false;
        $GLOBALS['wpdb'] = $probe;

        self::assertSame( 0, Session_Store::cleanup_expired() );
        self::assertSame( array(), $probe->queries, 'Do not purge sessions while durable recovery state is unverified.' );
    }

    public function test_no_delete_when_database_handle_cannot_be_queried(): void {
        $GLOBALS['wpdb'] = null;
        self::assertSame( 0, Session_Store::cleanup_expired() );
    }
}
