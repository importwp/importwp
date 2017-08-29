<?php
$importers = ImporterModel::getImporters();

global $wpdb;
$res = $wpdb->get_results("SELECT object_id as ID, MAX(created) as created FROM `" . $wpdb->prefix . "importer_log` GROUP BY object_id ORDER BY created DESC");
$last_ran = array();
foreach($res as $obj){
	$last_ran[$obj->ID] = $obj->created;
}

?>
<div id="icon-tools" class="icon32"><br></div>
<h2>Importers <a href="<?php echo admin_url('admin.php?page=jci-importers&action=add' ); ?>"
                class="add-new-h2">Add New</a></h2>

<?php jci_display_messages(); ?>

<div id="poststuff">
	<div id="post-body" class="metabox-holder columns-2">
		<div id="post-body-content">

			<table id="template_fields" class="wp-list-table widefat fixed">

				<thead class="template_heading">
				<th>Importer</th>
				<th style="width: 80px;">Template</th>
				<th style="width: 50px;">Type</th>
				<th style="width: 80px;">Last Ran</th>
				<!-- <th width="50px">Fields</th> -->
				<th style="width: 80px;">Created</th>
				</thead>


				<tbody class="fields">
				<?php if ( $importers->have_posts() ): ?>
					<?php while ( $importers->have_posts() ): $importers->the_post(); ?>

						<tr>
							<td class="post-title column-title">
								<a href="<?php echo admin_url('admin.php?page=jci-importers&import=' . get_the_ID() . '&action=edit' ); ?>"
								   class="row-title"><?php the_title(); ?></a>

								<div class="row-actions">
									<span class="edit"><a
											href="<?php echo admin_url('admin.php?page=jci-importers&import=' . get_the_ID() . '&action=edit' ); ?>"
											title="Edit this item">Edit</a> | </span>
									<span class="edit"><a
											href="<?php echo admin_url('admin.php?page=jci-importers&import=' . get_the_ID() . '&action=logs' ); ?>"
											title="Run this item">Run</a> | </span>
									<span class="edit"><a
											href="<?php echo admin_url('admin.php?page=jci-importers&import=' . get_the_ID() . '&action=history' ); ?>"
											title="View this item">History</a> | </span>
									<span class="trash"><a class="submitdelete" title="Move this item to the Trash"
									                       href="<?php echo admin_url('admin.php?page=jci-importers&import=' . get_the_ID() . '&action=trash' ); ?>">Trash</a></span>
								</div>
							</td>
							<td>
								<?php echo ImporterModel::getImportSettings( get_the_ID(), 'template' ); ?>
							</td>
							<td>
								<?php echo ImporterModel::getImportSettings(get_the_ID(), 'template_type'); ?>
							</td>
							<td><?php echo isset($last_ran[get_the_ID()]) ? date( 'H:i:s \<\b\r \/\> d/m/Y ', strtotime( $last_ran[get_the_ID()] ) ) : 'N/A'; ?></td>
							<!-- <td><?php
								$field_count = 0;
								if ( ! empty( $fields ) ) {
									foreach ( $fields as $field ) {
										$field_count += count( $field );
									}
								}
								echo $field_count;
								?></td> -->
							<td><?php echo get_the_date(); ?></td>
						</tr>
						<?php ImporterModel::clearImportSettings(); ?>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else: ?>
					<tr>
						<td colspan="4"><p>To start an import <a
									href="<?php echo admin_url('admin.php?page=jci-importers&action=add' ); ?>">click
									here</a> , click on add new at the top of the page.</p></td>
					</tr>
				<?php endif; ?>
				</tbody>

			</table>

		</div>

		<div id="postbox-container-1" class="postbox-container">

			<?php include $this->config->get_plugin_dir() . '/app/view/elements/about_block.php'; ?>

		</div>
		<!-- /postbox-container-1 -->
	</div>
</div>