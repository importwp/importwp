<?php

$remote_fetch = "No Module available";
if ( function_exists( 'curl_init' ) ) {
	$remote_fetch = "Curl Request";
} elseif ( ini_get( 'allow_url_fopen' ) ) {
	$remote_fetch = "Non Curl Request";
}

$db_tables = '';

try{

    global $wpdb;
    $db_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}importer_%'");

}catch (Exception $e){

}

$debug_info = array(
	'General'     => array(
		'WordPress version'  => get_bloginfo( 'version' ),
		'Plugin version'     => JCI()->get_version(),
		'Php version'        => phpversion(),
		'Max execution time' => ini_get( 'max_execution_time' ),
		'Max input time'     => ini_get( 'max_input_time' ),
        'Memory Limit'       => ini_get('memory_limit'),
        'DISABLE_WP_CRON'   => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON === true ? 'Yes' : 'No',
	),
	'Database' => array(
		'Database Version' => get_site_option('iwp_db_version', 0),
		'Database Is Migrating' => get_site_option('iwp_is_migrating', 'no'),
        'Database Tables' => implode(', ', $db_tables)
    ),
	'File Upload' => array(
		'Post max size'       => ini_get( 'post_max_size' ),
		'Upload max filesize' => ini_get( 'upload_max_filesize' ),
		'Remote Fetch'        => $remote_fetch,

        'Temp directory' => JCI()->get_tmp_dir(),
        'Temp directory writable' => true === is_writable( JCI()->get_tmp_dir() ) ? 'Yes' : 'No',
	),
);

$debug_info = apply_filters('iwp/debug_info', $debug_info);
?>
<?php foreach ( $debug_info as $section => $section_data ) : ?>
    <div class="postbox ">
        <button type="button" class="handlediv button-link" aria-expanded="true">
            <span class="screen-reader-text">Toggle panel: <?php echo esc_html( $section ); ?></span><span
                    class="toggle-indicator" aria-hidden="true"></span>
        </button>
        <h2 class="hndle ui-sortable-handle">
            <span><?php echo esc_html( $section ); ?></span>
        </h2>
        <table class="importwp-debug-table" cellpadding="0" cellspacing="0">
			<?php
			$i = 0;
			foreach ( $section_data as $heading => $content ) : $i ++; ?>
                <tr class="<?php echo ( 0 === ( $i % 2 ) ) ? esc_attr( 'alt' ) : ''; ?>">
                    <th><?php echo esc_html( $heading ); ?>:</th>
                    <td><?php echo esc_html( $content ); ?></td>
                </tr>
			<?php endforeach; ?>
        </table>
    </div>
<?php endforeach; ?>