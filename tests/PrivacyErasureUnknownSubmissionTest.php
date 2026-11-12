<?php
/**
 * Privacy erasure must preserve uncertain native submission identity when
 * the outbox event is absent, as well as under incomplete database migration.
 * Synthetic WPDB checks exact SQL intent, not real MySQL execution.
 */
declare(strict_types=1);

require_once dirname( __DIR__ ) . '/includes/core/class-privacy-integration.php';

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Privacy_Integration;
use Sabri\UniversalComposer\Core\Session_Store;
use Sabri\UniversalComposer\Core\Submission_Store;

if ( ! function_exists( 'get_user_by' ) ) {
    function get_user_by( string $field, string $value ): object|false {
        return 'email' === $field && 'privacy-subject@example.test' === $value
            ? (object) array( 'ID' => 17 ) : false;
    }
}

final class PrivacyErasureRecoveryDbProbe {
    public string $prefix = 'wp_';
    public bool $recovery_tables = true;
    /** @var list<array{sql:string,args:array}> */
    public array $queries = array();
    /** @var array<int,mixed> */
    private array $last_args = array();

    public function esc_like( string $table ): string {
        return $table;
    }

    public function prepare( string $sql, ...$args ): string {
        $this->last_args = $args;
        return $sql;
    }

    public function get_var( string $sql ): ?string {
        $table = (string) ( $this->last_args[0] ?? '' );
        if ( str_contains( $sql, 'SHOW TABLES LIKE' ) ) {
            if ( in_array( $table, array( Session_Store::table_name() ), true ) ) {
                return $table;
            }
            if ( $this->recovery_tables && in_array( $table, array(
                Submission_Store::submission_table_name(),
                Submission_Store::outbox_table_name()
            ), true ) ) {
                return $table;
            }
            return null; // Upload/audit stores absent in this isolated probe.
        }
        // One unresolved native attempt with no outbox event.
        if ( str_contains( $sql, 'COUNT(*)' ) ) {
            if ( $table === Submission_Store::submission_table_name()
                && str_contains( $sql, "state IN ('prepared','dispatched','reconcile','retryable','dead_letter')" )
                && ! str_contains( $sql, 'state NOT IN' ) ) {
                return '1';
            }
            if ( $table === Session_Store::table_name() && ! $this->recovery_tables ) {
                return '1'; // Unknown recovery schema: preserve one session.
            }
        }
        return '0';
    }

    public function query( string $sql ): int {
        $this->queries[] = array( 'sql' => $sql, 'args' => $this->last_args );
        return 0; // Protected rows are not deleted.
    }
}

final class PrivacyErasureUnknownSubmissionTest extends TestCase {
    private bool $had_db = false;
    private mixed $previous_db = null;

    protected function setUp(): void {
        $this->had_db = array_key_exists( 'wpdb', $GLOBALS );
        $this->previous_db = $GLOBALS['wpdb'] ?? null;
    }

    protected function tearDown(): void {
        if ( $this->had_db ) {
            $GLOBALS['wpdb'] = $this->previous_db;
        } else {
            unset( $GLOBALS['wpdb'] );
        }
    }

    public function test_erasure_preserves_unsettled_native_attempt_without_outbox(): void {
        $db = new PrivacyErasureRecoveryDbProbe();
        $GLOBALS['wpdb'] = $db;
        $result = Privacy_Integration::erase_personal_data( 'privacy-subject@example.test' );

        $sql = array_column( $db->queries, 'sql' );
        self::assertCount( 3, $sql, 'Completed-outbox, safe-submission, safe-session deletes only' );
        self::assertStringContainsString(
            "state NOT IN ('prepared','dispatched','reconcile','retryable','dead_letter')",
            $sql[1],
            'Erasure cannot remove uncertain native submission just because outbox enqueue failed.'
        );
        self::assertStringContainsString(
            "session_uuid NOT IN (SELECT session_uuid FROM %i WHERE user_id = %d AND state IN ('prepared','dispatched','reconcile','retryable','dead_letter'))",
            $sql[2],
            'Erasure must protect session independently of the outbox.'
        );
        self::assertTrue( $result['items_retained'] );
        self::assertTrue( $result['done'], 'Protected active items must not block every privacy batch forever.' );
        self::assertFalse( $result['items_removed'] );
        self::assertNotEmpty( $result['messages'] );
    }

    public function test_erasure_defers_session_deletion_when_recovery_tables_are_missing(): void {
        $db = new PrivacyErasureRecoveryDbProbe();
        $db->recovery_tables = false;
        $GLOBALS['wpdb'] = $db;

        $result = Privacy_Integration::erase_personal_data( 'privacy-subject@example.test' );
        self::assertSame( array(), $db->queries, 'No fallback blind delete on schema mismatch.' );
        self::assertTrue( $result['items_retained'] );
        self::assertFalse( $result['done'] );
        self::assertFalse( $result['items_removed'] );
        self::assertNotEmpty( $result['messages'] );
    }

    public function test_erasure_retention_count_independently_considers_submissions(): void {
        $source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/core/class-privacy-integration.php' );
        self::assertStringContainsString(
            "SELECT COUNT(*) FROM %i WHERE user_id = %d AND state IN ('prepared','dispatched','reconcile','retryable','dead_letter')",
            $source
        );
        self::assertStringContainsString(
            "state NOT IN ('prepared','dispatched','reconcile','retryable','dead_letter')",
            $source
        );
        self::assertStringNotContainsString(
            "'items_retained' => \$active_retained > 0",
            $source
        );
    }
}
