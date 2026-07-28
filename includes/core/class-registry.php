<?php
/**
 * Adapter registry.
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Contracts\Adapter;

if (! defined('ABSPATH')) {
    exit;
}

final class Registry
{
    /** @var array<string, Adapter> */
    private array $adapters = array();

    public function register(Adapter $adapter): bool
    {
        $key = sanitize_key($adapter->key());

        if ('' === $key || isset($this->adapters[$key])) {
            return false;
        }

        $this->adapters[$key] = $adapter;
        return true;
    }

    public function get(string $key): ?Adapter
    {
        $key = sanitize_key($key);
        return $this->adapters[$key] ?? null;
    }

    /**
     * @return array<string, Adapter>
     */
    public function all(): array
    {
        return $this->adapters;
    }

    /**
     * Return only healthy adapters the user can actually invoke.
     *
     * @return array<string, Adapter>
     */
    public function available_for_user(int $user_id): array
    {
        if ($user_id <= 0) {
            return array();
        }

        return array_filter(
            $this->adapters,
            static fn (Adapter $adapter): bool => $adapter->is_available() && $adapter->can_create($user_id)
        );
    }

    public function has_available_for_user(int $user_id): bool
    {
        return array() !== $this->available_for_user($user_id);
    }
}
