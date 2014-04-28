<?php
global $jcimporter;

// load settings from gloabl
$importer_id   = $jcimporter->importer->get_ID();
$parser        = $jcimporter->importer->get_parser();
$template_name = $jcimporter->importer->get_template_name();
$template      = $jcimporter->importer->get_template();
$start_line    = $jcimporter->importer->get_start_line();
$row_count     = $jcimporter->importer->get_row_count();
$name          = $jcimporter->importer->get_name();
$import_status = 0;

if ( $row_count <= 0 ) {
	$record_count = $parser->get_total_rows();
} else {
	$record_count = ( $start_line - 1 ) + $row_count;
}

// check for continue
if(isset($_GET['continue'])){

	$last_import_row = $jcimporter->importer->get_last_import_row();
	if($last_import_row >= $start_line){
		$start_line = $last_import_row + 1;
	}

	$import_status = 1; // 1 = paused
}

$columns = apply_filters( "jci/log_{$template_name}_columns", array() );
?>

<div id="icon-tools" class="icon32"><br></div>
<h2 class="nav-tab-wrapper">
	<a href="admin.php?page=jci-importers&import=<?php echo $id; ?>&action=edit"
	   class="nav-tab tab"><?php echo $name; ?></a>
	<a href="admin.php?page=jci-importers&import=<?php echo $id; ?>&action=history" class="nav-tab tab">History</a>
	<a href="admin.php?page=jci-importers&import=<?php echo $id; ?>&action=logs" class="nav-tab nav-tab-active tab">Run
		Import</a>
</h2>

<div id="ajaxResponse"></div>


<div id="poststuff">
	<div id="post-body" class="metabox-holder columns-2">


		<div id="post-body-content">

			<div id="postbox-container-2" class="postbox-container">

				<div id="test-response"></div>

				<div id="jci-table-wrapper">
					<table class="wp-list-table widefat fixed posts" cellspacing="0">
						<thead>
						<tr>
							<th scope="col" id="author" class="manage-column column-author" style="width:30px;">ID</th>
							<?php foreach ( $columns as $key => $col ): ?>
								<th scope="col" id="<?php echo $key; ?>"
								    class="manage-column column-<?php echo $key; ?>" style=""><?php echo $col; ?></th>
							<?php endforeach; ?>
						</tr>
						</thead>
						<tbody id="the-list">
						</tbody>
					</table>
				</div>

				<div class="form-actions">
					<br/>
					<?php if($import_status == 1): ?>
						<a href="#" class="jc-importer_update-run button-primary">Continue Import</a>
					<?php else: ?>
					<a href="#" class="jc-importer_update-run button-primary">Run Import</a>
					<?php endif; ?>
				</div>
			</div>

		</div>

		<div id="postbox-container-1" class="postbox-container">

			<?php include $this->config->plugin_dir . '/app/view/elements/about_block.php'; ?>

		</div>
		<!-- /postbox-container-1 -->

	</div>
</div>

<script type="text/javascript">
	jQuery(document).ready(function ($) {

		var running = <?php echo $import_status; ?>; // 0 = stopped , 1 = paused, 2 = running, 3 = complete
		var record_total = <?php echo $record_count; ?>;
		var record = <?php echo $start_line; ?>;
		var columns = <?php echo json_encode($columns); ?>;
		var startDate = false;
		var avgTimes = new Array();
		var estimatedFinishDate = new Date();

		// ajax import
		$('.jc-importer_update-run').click(function (event) {

			if (running == 3) {
				return;
			}

			if (running == 0) {
				$('#the-list').html("");
			}

			function getNextRecord() {
				$.ajax({
					url: ajax_object.ajax_url,
					data: {
						action: 'jc_import_row',
						id: ajax_object.id,
						row: record,
					},
					dataType: 'html',
					type: "POST",
					beforeSend: function () {
						$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Importing Record #' + (record - <?php echo $start_line -1; ?>) + ' out of #' + (record_total - <?php echo $start_line -1; ?>) + ' Estimated Finish time at ' + estimatedFinishDate + '</p></div>');
					},
					success: function (response) {
						$('#ajaxResponse').html('');

						$('#the-list').prepend(response);

						if (record < record_total) {

							record++;
							if (running == 2) {

								var currentDate = new Date();
								var diff = currentDate.getTime() - startDate.getTime();
								var time_in_seconds = Math.floor(diff / 1000);
								var current_record_count = (record - <?php echo $start_line; ?>);
								var total_records = <?php echo $record_count - $start_line; ?>;
								var total_records_left = ( total_records - current_record_count);
								var seconds = (time_in_seconds / current_record_count) * total_records_left;

								estimatedFinishDate = new Date(new Date().getTime() + new Date(1970, 1, 1, 0, 0, parseInt(seconds), 0).getTime());

								getNextRecord();
							} else {
								$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Import Paused of (' + (record - <?php echo $start_line -1; ?>) + '/' + (record_total - <?php echo $start_line -1; ?>) + ') Records</p></div>');
							}

						} else {
							$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Import of ' + (record_total - <?php echo $start_line -1; ?>) + ' Records</p></div>');
							running = 3;
							$('.form-actions').hide();
						}
					}
				});
			}

			<?php if(isset($_GET['continue'])): ?>
			startDate = new Date();
			<?php endif; ?>

			if (running == 0) {
				startDate = new Date();
			}

			if (running == 0 || running == 1) {
				running = 2;
				getNextRecord();
			} else {
				running = 1;
			}

			if (running == 2) {
				$('.button-primary').text("Pause Import");
			} else if (running == 1) {
				$('.button-primary').text("Continue Import");
			}

			event.preventDefault();
			return false;
		});

	});
</script>