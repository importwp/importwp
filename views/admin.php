<div id="importwp-root">
    <h1>An error has occured.</h1>

    <?php if (!defined('IWP_PRO_VERSION') || version_compare(IWP_PRO_VERSION, '2.4.0', '<=')) : ?>
        <div class="notice notice-error">
            <p><strong>Import WP v<?= IWP_VERSION; ?></strong> is incompatable with <strong>Import WP PRO v2.4.0</strong> and below<?php if (defined('IWP_PRO_VERSION')) : ?>, you have <strong>Import WP PRO v<?= IWP_PRO_VERSION; ?><strong> installed<?php endif; ?>.</p>
        </div>
    <?php endif; ?>
</div>