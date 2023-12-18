<div id="importwp-root">
    <h1><?php _e('An error has occured.', 'jc-importer'); ?></h1>

    <?php if (!defined('IWP_PRO_VERSION') || version_compare(IWP_PRO_VERSION, '2.4.0', '<=')) : ?>
        <div class="notice notice-error">
            <p><?php
                echo sprintf(__('Import WP v%s is incompatable with Import WP PRO v%s and below.', 'jc-importer'), IWP_VERSION, '2.4.0');
                ?></p>
        </div>
    <?php endif; ?>
</div>