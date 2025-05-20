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

        $addons = AddonManager::instance()->listRequiredAddons();
        foreach ($addons as $addon) {

            $notice_name = 'iwp_available_addon_' . $addon['slug'];

            if (get_user_meta(get_current_user_id(), 'dismissed_' . $notice_name . '_notice', true)) {
                continue;
            }

            $class = 'notice notice-warning is-dismissible';
            printf('<div class="%1$s">
                    <a href="' . esc_url(wp_nonce_url(add_query_arg('iwp-hide-notice', $notice_name), 'importwp_hide_notices_nonce', '_iwp_notice_nonce')) . '" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></a>
                    <p><strong>Import WP:</strong> Your website appears to have <strong>' . $addon['name'] . '</strong> installed, We highly recommend you download the <strong>Import WP - ' . $addon['name'] . ' addon</strong>. <a href="' . $addon['url'] . '" target="_blank">Learn more here</a>.</p>
                </div>', esc_attr($class));
        }
    }
}
