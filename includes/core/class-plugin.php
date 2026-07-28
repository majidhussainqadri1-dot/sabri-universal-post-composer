<?php
/**
 * Main plugin runtime.
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Integration\Shell_Bridge;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static ?self $instance = null;

    private Registry $registry;

    private bool $booted = false;

    private function __construct()
    {
        $this->registry = new Registry();
    }

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        /**
         * Native modules register adapters here. File 22 does not create
         * duplicate permanent content models.
         */
        do_action('supc_register_adapters', $this->registry);

        add_shortcode('sabri_universal_composer', array($this, 'render_shortcode'));

        (new Shell_Bridge($this->registry))->register();

        do_action('supc_booted', $this->registry);
    }

    public function registry(): Registry
    {
        return $this->registry;
    }

    public function render_shortcode(): string
    {
        if (! is_user_logged_in()) {
            $login_url = wp_login_url((string) get_permalink());
            return sprintf(
                '<div class="supc-notice supc-notice--login"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a></p></div>',
                esc_html__('Sign in to create authorized platform content.', 'sabri-universal-post-composer'),
                esc_url($login_url),
                esc_html__('Sign In', 'sabri-universal-post-composer')
            );
        }

        $available = $this->registry->available_for_user(get_current_user_id());

        if (array() === $available) {
            return '<div class="supc-notice supc-notice--empty"><p>'
                . esc_html__('No authorized content type is currently available for this account.', 'sabri-universal-post-composer')
                . '</p></div>';
        }

        $items = '';
        foreach ($available as $key => $adapter) {
            $items .= sprintf(
                '<li data-supc-type="%1$s"><button type="button" class="supc-type-button" data-supc-type="%1$s">%2$s</button></li>',
                esc_attr($key),
                esc_html($adapter->label())
            );
        }

        return '<section class="supc-shell" aria-labelledby="supc-heading">'
            . '<h1 id="supc-heading">' . esc_html__('Create', 'sabri-universal-post-composer') . '</h1>'
            . '<p>' . esc_html__('Choose an authorized content type. The native module remains the permanent data owner.', 'sabri-universal-post-composer') . '</p>'
            . '<ul class="supc-type-list">' . $items . '</ul>'
            . '</section>';
    }
}
