<?php

namespace ImportWP\Common\UI;

use ImportWP\Common\AddonAPI\AddonManager;

class AdminNotices
{

    public function __construct()
    {
        add_action('admin_init', [__CLASS__, 'dismiss_notices'], 20);
        add_action('admin_notices', [__CLASS__, 'display_notices']);
    }

    public static function dismiss_notices()
    {

        if (isset($_GET['iwp-hide-notice']) && isset($_GET['_iwp_notice_nonce'])) {

            if (! wp_verify_nonce(sanitize_key(wp_unslash($_GET['_iwp_notice_nonce'])), 'importwp_hide_notices_nonce')) {
                wp_die(esc_html__('Action failed. Please refresh the page and retry.', 'importwp'));
            }

            $notice_name = sanitize_text_field(wp_unslash($_GET['iwp-hide-notice']));

            update_user_meta(get_current_user_id(), 'dismissed_' . $notice_name . '_notice', true);

            wp_safe_redirect(remove_query_arg(['iwp-hide-notice', '_iwp_notice_nonce']));
            exit;
        }
    }

    // dismiss notices
    public static function display_notices()
    {
        global $pagenow;
        $screen = get_current_screen();

        if ($screen->id != 'tools_page_importwp' && !in_array($pagenow, ['index.php', 'plugins.php'])) {
            return;
        }

        $addons = AddonManager::instance()->getAddonList();
        foreach ($addons as $addon) {

            $notice_name = 'iwp_available_addon_' . $addon['slug'];

            if (get_user_meta(get_current_user_id(), 'dismissed_' . $notice_name . '_notice', true)) {
                continue;
            }

            $class = 'notice notice-warning is-dismissible';
            $text = sprintf('We have noticed that you are using Import WP with %1$s. To ensure compatibility, we recommend you use', $addon['name']);
            $plugin_name = sprintf('Import WP %1$s Addon', $addon['name']);

            printf('<div class="%1$s">
                    <a href="%5$s" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></a>
                    <p>%2$s <a href="%4$s" target="_blank">%3$s</a>.</p>
                </div>', esc_attr($class), esc_html($text), esc_html($plugin_name), esc_url($addon['url']), esc_url(wp_nonce_url(add_query_arg('iwp-hide-notice', $notice_name), 'importwp_hide_notices_nonce', '_iwp_notice_nonce')));
        }
    }
}
