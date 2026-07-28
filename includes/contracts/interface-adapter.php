<?php
/**
 * Adapter contract.
 *
 * Native modules retain ownership of permanent records, moderation, secure
 * storage, and canonical URLs. The Universal Composer only orchestrates.
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Contracts;

if (! defined('ABSPATH')) {
    exit;
}

interface Adapter
{
    /**
     * Stable machine key, for example publication, learning_lesson, or pdf.
     */
    public function key(): string;

    /**
     * Human-readable American English label.
     */
    public function label(): string;

    /**
     * Native module and version are present and healthy enough to create.
     */
    public function is_available(): bool;

    /**
     * Current user is authorized to create this content type.
     */
    public function can_create(int $user_id): bool;

    /**
     * Versioned field schema and validation metadata.
     *
     * @return array<string, mixed>
     */
    public function schema(): array;

    /**
     * Create or resume a native draft and return an opaque native reference.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|\WP_Error
     */
    public function create_draft(int $user_id, array $payload);

    /**
     * Validate without publishing or mutating public state.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|\WP_Error
     */
    public function validate(int $user_id, array $payload);

    /**
     * Idempotently submit to the native owner.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|\WP_Error
     */
    public function submit(int $user_id, string $idempotency_key, array $payload);

    /**
     * Return the canonical native destination for an existing object.
     */
    public function canonical_url(string $native_reference): string;
}
