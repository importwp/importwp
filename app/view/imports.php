<?php 
$importers = ImporterModel::getImporters();
?>
<div id="icon-tools" class="icon32"><br></div>
<h2>Importer <a href="<?php echo site_url('/wp-admin/admin.php?page=jci-importers&action=add');?>" class="add-new-h2">Add New</a></h2>

<div id="poststuff">
	<div id="post-body" class="metabox-holder columns-2">
		<div id="post-body-content">

			<table id="template_fields" class="wp-list-table widefat fixed">

				<thead class="template_heading">
					<th class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="cb-select-all-1">Select All</label>
						<input id="cb-select-all-1" type="checkbox">
					</th>
					<th>Title</th>
					<th width="75px">Groups</th>
					<th width="75px">Fields</th>
					<th width="150px">Modified</th>
				</thead>

				
				<tbody class="fields">
					<?php if($importers->have_posts()): ?>
					<?php while($importers->have_posts()): $importers->the_post(); ?>
					<tr>
						<th scope="row" class="check-column">
							<label class="screen-reader-text" for="cb-select-<?php the_ID(); ?>">Select <?php the_title(); ?></label>
							<input id="cb-select-<?php the_ID(); ?>" type="checkbox" name="post[]" value="1">
							<div class="locked-indicator"></div>
						</th>
						<td class="post-title column-title">
							<a href="<?php echo site_url('/wp-admin/admin.php?page=jci-importers&import='.get_the_ID().'&action=edit'); ?>" class="row-title"><?php the_title(); ?></a>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo site_url('/wp-admin/admin.php?page=jci-importers&import='.get_the_ID().'&action=edit'); ?>" title="Edit this item">Edit</a> | </span>
								<span class="edit"><a href="<?php echo site_url('/wp-admin/admin.php?page=jci-importers&import='.get_the_ID().'&action=logs'); ?>" title="Run this item">Run</a> | </span>
								<span class="edit"><a href="<?php echo site_url('/wp-admin/admin.php?page=jci-importers&import='.get_the_ID().'&action=history'); ?>" title="View this item">History</a> | </span>
								<span class="trash"><a class="submitdelete" title="Move this item to the Trash" href="<?php echo site_url('/wp-admin/admin.php?page=jci-importers&import='.get_the_ID().'&action=trash'); ?>">Trash</a></span>
							</div>
						</td>
						<td><?php
						$fields =  get_post_meta( get_the_ID(), '_mapped_fields', true); 
						echo count($fields);
						?></td>
						<td><?php
						$field_count = 0;
						if(!empty($fields)){
							foreach($fields as $field){
								$field_count += count($field);
							}
						}
						echo $field_count;
						?></td>
						<td><?php echo get_the_date(); ?></td>
					</tr>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
					<?php else: ?>
					<tr>
						<td colspan="5"><p>To start an import <a href="<?php echo site_url('/wp-admin/admin.php?page=jci-importers&action=add');?>">click here</a> , click on add new at the top of the page.</p></td>
					</tr>
					<?php endif; ?>
				</tbody>

			</table>

		</div>

		<div id="postbox-container-1" class="postbox-container">

			<?php include $this->config->plugin_dir . '/app/view/elements/about_block.php'; ?>

		</div><!-- /postbox-container-1 -->
	</div>
</div>