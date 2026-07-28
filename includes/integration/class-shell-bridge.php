<?php
/**
 * Unified Application Shell bridge.
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Integration;

use Sabri\UniversalComposer\Core\Registry;

if (! defined('ABSPATH')) {
    exit;
}

final class Shell_Bridge
{
    public function __construct(private Registry $registry)
    {
    }

    public function register(): void
    {
        add_filter('sabri_shell_create_url', array($this, 'filter_create_url'));
        add_filter('sabri_shell_can_show_create', array($this, 'filter_create_visibility'), 10, 2);
    }

    public function filter_create_url(string $url): string
    {
        $page_id = absint(get_option('supc_create_page_id', 0));

        if ($page_id > 0 && 'publish' === get_post_status($page_id)) {
            $permalink = get_permalink($page_id);
            if (is_string($permalink) && '' !== $permalink) {
                return $permalink;
            }
        }

        return $url;
    }

    /**
     * Visibility is capability/adapter driven, not hard-coded to roles.
     *
     * @param bool $allowed Existing shell decision.
     * @param int  $user_id Current user ID when supplied by File 20.
     */
    public function filter_create_visibility(bool $allowed, int $user_id = 0): bool
    {
        $user_id = $user_id > 0 ? $user_id : get_current_user_id();

        if ($user_id <= 0) {
            return false;
        }

        return $this->registry->has_available_for_user($user_id);
    }
}
