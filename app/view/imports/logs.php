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

<?php if(!file_exists($jcimporter->importer->file)): ?>
<div id="message" class="error_msg warn error below-h2"><p>File to import could not be found: <?php echo $jcimporter->importer->file; ?></p></div>
<?php endif; ?>


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
							<?php 
							if(isset($_GET['continue'])){

								$rows = ImportLog::get_importer_log( $importer_id, $jcimporter->importer->get_version() );

								if ( $rows ){
									foreach ( $rows as $r ){
										$row  = $r->row;
										$data = array( unserialize( $r->value ) );
										require $jcimporter->plugin_dir . 'app/view/imports/log/log_table_record.php';
									}
								}
							}
							?>
						</tbody>
					</table>
				</div>

				<?php if(file_exists($jcimporter->importer->file)): ?>

				<div class="form-actions">
					<br/>
					<?php if($import_status == 1): ?>
						<a href="#" class="jc-importer_update-run button-primary">Continue Import</a>
					<?php else: ?>
					<a href="#" class="jc-importer_update-run button-primary">Run Import</a>
					<?php endif; ?>
				</div>

				<?php endif; ?>
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
		var curr_del_record = 0;
		var del_count = 0;
		var records_per_row = 1;
		var record_diffs = new Array();

		// ajax import
		$('.jc-importer_update-run').click(function (event) {

			if (running == 3) {
				return;
			}

			if (running == 0) {
				$('#the-list').html("");
			}

			function getNextRecord() {
				
				var record_start = new Date();
				$.ajax({
					url: ajax_object.ajax_url,
					data: {
						action: 'jc_import_row',
						id: ajax_object.id,
						row: record,
						records: records_per_row
					},
					dataType: 'html',
					type: "POST",
					beforeSend: function () {
						$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Importing Record #' + (record - <?php echo $start_line -1; ?>) + ' out of #' + (record_total - <?php echo $start_line -1; ?>) + ' Estimated Finish time at ' + estimatedFinishDate + '</p></div>');
						document.title = 'Importing ('+ (record - <?php echo $start_line -1; ?>) +'/' + (record_total - <?php echo $start_line -1; ?>) +')';
					},
					success: function (response) {
						$('#ajaxResponse').html('');

						$('#the-list').prepend(response);

						if (record < record_total) {

							record += records_per_row;
							if (running == 2) {

								var currentDate = new Date();
								var diff = currentDate.getTime() - startDate.getTime();
								var time_in_seconds = Math.floor(diff / 1000);
								var current_record_count = (record - <?php echo $start_line; ?>);
								var total_records = <?php echo $record_count - $start_line; ?>;
								var total_records_left = ( total_records - current_record_count);
								var seconds = (time_in_seconds / current_record_count) * total_records_left;

								var record_diff = currentDate.getTime() - record_start.getTime();
								var record_time_in_seconds = Math.floor(record_diff / 1000);
								record_diffs.push(record_time_in_seconds);

								record_diffs_sum = 0;
								for(var l = 0; l < record_diffs.length; l++){
									record_diffs_sum += record_diffs[l];
								}

								var record_diffs_avg = record_diffs_sum / record_diffs.length;

								if(record_diffs_avg > 1){
									// reduce
									record_diffs = [];
									if(records_per_row > 1){
										records_per_row = Math.floor(records_per_row / 2);	
									}
								}else{
									records_per_row++;	
								}

								console.log(records_per_row);
								console.log('Diff: ' + record_time_in_seconds + ' Avg: '+ record_diffs_avg);

								estimatedFinishDate = new Date(new Date().getTime() + new Date(1970, 1, 1, 0, 0, parseInt(seconds), 0).getTime());

								getNextRecord();
							} else {
								$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Import Paused of (' + (record - <?php echo $start_line; ?>) + '/' + (record_total - <?php echo $start_line -1; ?>) + ') Records</p></div>');
								document.title = 'Import Paused ('+ (record - <?php echo $start_line -1; ?>) +'/' + (record_total - <?php echo $start_line -1; ?>) +')';
							}

						} else {
							$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Import of ' + (record_total - <?php echo $start_line -1; ?>) + ' Records</p></div>');
							document.title = 'Import Complete: '+ (record_total - <?php echo $start_line -1; ?>);
							running = 3;
							$('.form-actions').hide();

							// ajax process delete items
							deleteNextRecord();
						}
					}
				});
			}



			function deleteNextRecord(){

				var params = {
					id: ajax_object.id,
					action: 'jc_process_delete'
				};

				if(curr_del_record > 0){
					params.delete = 1;
				}

				$.ajax({
					url: ajax_object.ajax_url,
					data: params,
					dataType: 'json',
					type: "POST",
					success: function(response){

						if(del_count == 0){

							if(response.status == 'S'){
								del_count = response.response.total;
								if(del_count == 0){
									document.title = 'Import Complete';
									$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Import Complete, No Items to delete</p></div>');
									running = 3;
									$('.form-actions').hide();
								}else{
									document.title = 'Deleting Items 0/'+del_count;
									$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Deleting Items (0/'+del_count+')</p></div>');
								}
								
							}
						}

						if(del_count > curr_del_record){
							curr_del_record++;
							deleteNextRecord();
							document.title = 'Deleting Items '+curr_del_record+'/'+del_count;
							$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Deleting Items ('+curr_del_record+'/'+del_count+')</p></div>');
						}else{

							if(del_count > 0){
								document.title = 'Import Complete';
								$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Import of ' + (record_total - <?php echo $start_line -1; ?>) + ' Items, '+del_count+' Items Deleted</p></div>');
								running = 3;
								$('.form-actions').hide();
							}
							
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
				<?php if( $jcimporter->importer->get_object_delete() !== false && $jcimporter->importer->get_object_delete() == 0): ?>
				$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Continue Deleting Items</p></div>');
				deleteNextRecord();
				<?php else: ?>
				getNextRecord();
				<?php endif; ?>
				
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