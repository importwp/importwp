<?php

function import_wp()
{
    global $iwp;

    if (!is_null($iwp)) {
        return $iwp;
    }

    $iwp = new ImportWP\Free\ImportWPFree();
    $iwp->register();
    return $iwp;
}

function iwp_is_pro_disabled()
{
    return defined('IWP_PRO_VERSION') && version_compare(IWP_PRO_VERSION, IWP_CORE_MIN_PRO_VERSION, '<');
}

/**
 * When updating ImportWP errors can arrise if the pro version has a different codebase
 * 
 * Disable PRO functionality and alert user to reason.
 *
 * @return void
 */
function iwp_has_required_version_of_pro()
{
    if (iwp_is_pro_disabled()) {
        remove_action('plugins_loaded', 'iwp_pro_loaded');

        // Display compatability message
        $message = '<strong>Import WP v' . IWP_VERSION . '</strong> requires <strong>Import WP PRO v' . IWP_CORE_MIN_PRO_VERSION . ' or greater</strong>, Download the lastest version of Import WP Pro or Rollback Import WP to a compatable version.';
        add_action('admin_notices', function () use ($message) {

            global $pagenow;

            if (!in_array($pagenow, ['plugins.php', 'update-core.php', 'index.php'])) {
                return;
            }

            echo '<div class="notice notice-error is-dismissible">
             <p>' . $message . '</p>
         </div>';
        });

        // display error message after plugin row
        $upgrade_mesasge = function () use ($message) {
?>
            <tr class="iwp-upgrade-row-error iwp-invalid">
                <td colspan="4"><?= $message; ?></td>
            </tr>
            <style>
                .iwp-upgrade-row-error td {
                    -webkit-box-shadow: 0px -1px 0 rgba(255, 255, 255, 0.1),
                        inset 0 -1px 0 rgba(0, 0, 0, 0.1);
                    box-shadow: 0px -1px 0 rgba(255, 255, 255, 0.1),
                        inset 0 -1px 0 rgba(0, 0, 0, 0.1);
                    border-left: 4px solid #ffb900;
                    background-color: #fff8e5;
                }

                .iwp-upgrade-row-error.iwp-invalid td {
                    border-left: 4px solid #dc3232;
                    background-color: #fef1f1;
                }

                .iwp-upgrade-row-error.iwp-valid td {
                    border-left: 4px solid #00a0d2;
                    background-color: #f7fcfe;
                }
            </style>
<?php
        };
        add_action('after_plugin_row_' . plugin_basename(dirname(__FILE__) . '/importwp-pro.php'), $upgrade_mesasge);
        add_action('after_plugin_row_' . plugin_basename(dirname(__FILE__) . '/jc-importer.php'), $upgrade_mesasge);

        add_filter('iwp/frontent/notices', function ($notices) use ($message) {
            $notices[] = ['type' => 'error', 'message' => strip_tags($message)];
            return $notices;
        });
    }
}
add_action('plugins_loaded', 'iwp_has_required_version_of_pro', 0);

function iwp_loaded()
{
    if (function_exists('import_wp_pro') && !iwp_is_pro_disabled()) {
        return;
    }

    if (function_exists('import_wp')) {
        import_wp();
    }
}
add_action('plugins_loaded', 'iwp_loaded');

/**
 * Register IWP Addon
 *
 * @param string $name
 * @param string $id
 * @param callable(AddonInterface) $callback
 * 
 * @return ImportWP\Common\Addon\AddonInterface
 */
function iwp_register_importer_addon($name, $id, $callback)
{
    return new ImportWP\Common\Addon\AddonBase($name, $id, $callback);
}
