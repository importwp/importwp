<?php

namespace ImportWP\Common\AddonAPI;

class AddonManager
{
    /**
     * @var Addon[]
     */
    private $_addons = [];

    /**
     * @var AddonManager
     */
    private static $instance;

    /**
     * @return AddonManager Instance
     */
    public static function instance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof AddonManager)) {
            self::$instance = new AddonManager();
        }

        return self::$instance;
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * class via the `new` operator from outside of this class.
     */
    protected function __construct() {}

    /**
     * As this class is a singleton it should not be clone-able
     */
    protected function __clone() {}

    /**
     * As this class is a singleton it should not be able to be unserialized
     */
    public function __wakeup() {}

    /**
     * Register Addon
     * 
     * @param Addon $addon 
     * @return void 
     */
    public function register($addon)
    {
        $this->_addons[] = $addon;
    }

    public function getAddonList()
    {

        $addons = [];

        // WooCommerce
        if (class_exists('WooCommerce') && !function_exists('iwp_woocommerce_setup')) {
            $addons[] = [
                'name' => 'WooCommerce',
                'slug' => 'woocommerce',
                'url' => 'https://www.importwp.com/integrations/import-woocommerce-products/',
            ];
        }

        // Pods
        if (function_exists('pods_is_plugin_active') && !function_exists('iwp_pods_setup')) {
            $addons[] = [
                'name' => 'Pods',
                'slug' => 'pods',
                'url' => 'https://www.importwp.com/integrations/pods/',
            ];
        }

        // JetEngine
        if (class_exists('Jet_Engine') && !function_exists('iwp_jet_engine_setup')) {
            $addons[] = [
                'name' => 'JetEngine',
                'slug' => 'jetengine',
                'url' => 'https://www.importwp.com/integrations/jet-engine/',
            ];
        }

        // Yoast SEO
        if (function_exists('wpseo_init') && !function_exists('iwp_yoast_setup')) {
            $addons[] = [
                'name' => 'Yoast SEO',
                'slug' => 'yoast-seo',
                'url' => 'https://www.importwp.com/integrations/import-export-yoast-seo/',
            ];
        }

        // Rank Math SEO
        if (function_exists('rank_math') && !function_exists('iwp_rank_math_setup')) {
            $addons[] = [
                'name' => 'Rank Math SEO',
                'slug' => 'rank-math',
                'url' => 'https://www.importwp.com/integrations/rank-math-seo/',
            ];
        }

        // Polylang
        if (defined('POLYLANG') && !function_exists('iwp_polylang_setup')) {
            $addons[] = [
                'name' => 'Polylang',
                'slug' => 'polylang',
                'url' => 'https://www.importwp.com/integrations/polylang/',
            ];
        }

        // WPML
        if (class_exists('\SitePress') && !function_exists('iwp_wpml_setup')) {
            $addons[] = [
                'name' => 'WPML',
                'slug' => 'wpml',
                'url' => 'https://www.importwp.com/integrations/import-wpml-translations/',
            ];
        }

        return $addons;
    }
}
