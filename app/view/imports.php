<?php
$importers = ImporterModel::getImporters();

global $wpdb;
$res      = $wpdb->get_results( "SELECT object_id as ID, MAX(created) as created FROM `" . $wpdb->prefix . "importer_log` GROUP BY object_id ORDER BY created DESC" );
$last_ran = array();
foreach ( $res as $obj ) {
	$last_ran[ $obj->ID ] = $obj->created;
}

?>
<div id="icon-tools" class="icon32"><br></div>
<h2>Importers <a href="<?php echo admin_url( 'admin.php?page=jci-importers&action=add' ); ?>"
                 class="add-new-h2">Add New</a></h2>

<?php jci_display_messages(); ?>

<div id="poststuff">
    <div id="post-body" class="metabox-holder columns-2">
        <div id="post-body-content">

	        <?php
	        $iwp_imports_table = new IWP_Imports_List_Table();
	        $iwp_imports_table->prepare_items();
	        $iwp_imports_table->display();
	        ?>

        </div>

        <div id="postbox-container-1" class="postbox-container">

			<?php include $this->config->get_plugin_dir() . '/app/view/elements/about_block.php'; ?>

        </div>
        <!-- /postbox-container-1 -->
    </div>
</div>