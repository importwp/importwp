<?php
// load settings from gloabl
$importer_id   = JCI()->importer->get_ID();
$parser        = JCI()->importer->get_parser();
$template_name = JCI()->importer->get_template_name();
$template      = JCI()->importer->get_template();
$start_line    = JCI()->importer->get_start_line();
$row_count     = JCI()->importer->get_row_count();
$record_import_count  = JCI()->importer->get_record_import_count();
$name          = JCI()->importer->get_name();
$template_type = JCI()->importer->get_template_type();
$import_status = 0;

if ( $row_count <= 0 ) {
	$record_count = $parser->get_total_rows();
} else {
	$record_count = ( $start_line - 1 ) + $row_count;
}

// check for continue
if(isset($_GET['continue'])){

	$last_import_row = JCI()->importer->get_last_import_row();
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

<?php if(!file_exists(JCI()->importer->file)): ?>
<div id="message" class="error_msg warn error below-h2"><p>File to import could not be found: <?php echo JCI()->importer->file; ?></p></div>
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

								$rows = ImportLog::get_importer_log( $importer_id, JCI()->importer->get_version() );

								if ( $rows ){
									foreach ( $rows as $r ){
										$row  = $r->row;
										$data = array( unserialize( $r->value ) );
										require $jcimporter->get_plugin_dir() . 'app/view/imports/log/log_table_record.php';
									}
								}
							}
							?>
						</tbody>
					</table>
				</div>

				<?php if(file_exists(JCI()->importer->file)): ?>

                <div class="iwp__progress"><div class="spinner iwp__progress-spinner"></div><p class="iwp__progress-text"></p></div>

				<div class="form-actions">
					<br/>
					<?php if($import_status == 1): ?>
						<a href="#" class="jc-importer_update-run iwp-import-btn__<?php echo $template_type; ?> button-primary">Continue Import</a>
					<?php else: ?>
					<a href="#" class="jc-importer_update-run iwp-import-btn__<?php echo $template_type; ?> button-primary">Run Import</a>
					<?php endif; ?>
				</div>

				<?php endif; ?>
			</div>

		</div>

		<div id="postbox-container-1" class="postbox-container">

			<?php include $this->config->get_plugin_dir() . '/app/view/elements/about_block.php'; ?>

		</div>
		<!-- /postbox-container-1 -->

	</div>
</div>

<script type="text/javascript">

    (function($){

        /**
         * Start time of import
         *
         * @type {Date}
         */
        var startDate;

        /**
         * Current import time
         *
         * @type {Date}
         */
        var currentDate;

        /**
         * Last ajax request sent
         *
         * @type {Date}
         */
        var lastAjaxRequestSent;

        /**
         * Keep track of how many ajax requests are currently running, limit to 2
         *
         * @type {number}
         */
        var requests = 0;

        /**
         * Minimum Time between ajax requests
         */
        var requestIntervalTimer = 2000;

        /**
         * Import completion state
         *
         * @type {boolean}
         */
        var complete = false;

        /**
         * @type {boolean}
         */
        var error = false;

        var requestCounter = 0;

        var run = true;

        /**
         * Interval id
         */
        var interval;

        var $jqajax = [];

        var on_error = function(response){

            // cancel other ajax requests
            while($jqajax.length > 0){
                var element = $jqajax.pop();
                element.abort();
            }

            if(false === error) {

                error = true;

                $progress.removeClass('iwp__progress--running');
                $progress.addClass('iwp__progress--error');

                $progress.find('.iwp__progress-text').text('Error: ' + response.data.message).show();
            }
        };

        var on_button_pressed = function($btn){

            // Escape due to error
            if(error === true){
                return;
            }

            var cTimer = new Date();
            var timer = cTimer.getTime() - lastAjaxRequestSent.getTime();

            if(requests >= 2 || (run !== true && timer < requestIntervalTimer ) ){
                return;
            }

            var data_arr = {
                action: 'jc_import_all',
                id: ajax_object.id
            };

            if(run === true){
                data_arr.request = 'run';
            }else{
                data_arr.request = 'check';
            }

            // reset run to false
            run = false;

//            if((requestCounter % 2) === 0){
//                data_arr.request = 'run';
//            }else{
//                data_arr.request = 'check';
//            }

            $jqajax.push($.ajax({
                url: ajax_object.ajax_url,
                data: data_arr,
                dataType: 'json',
                type: "POST",
                beforeSend: function(){
                    requests++;
                    lastAjaxRequestSent = new Date();
                },
                success: function (response) {

                    lastAjaxRequestSent = new Date();

                    if(response !== null && typeof response === 'object') {

                        if('error' === response.data.status){

                            on_error(response);
                            return;
                        }

                        var diff = 0;
                        var time_in_seconds = 0;
                        var status_text = '';

                        var response_text = '';
                        if (response !== null && typeof response === 'object' && response.hasOwnProperty('data') && response.data.hasOwnProperty('last_record') && response.data.hasOwnProperty('end')) {
                            response_text = response.data.last_record + "/" + response.data.end;
                        }else{
                            response_text = "initialising";
                        }

                        if (response.data.status === "timeout") {
                            // we have got a timeout response
                            // so the next ajax request will issue a fetch
                            run = true;
                        }

                        if (response.data.status === "complete") {

                            diff = currentDate.getTime() - startDate.getTime();
                            time_in_seconds = Math.floor(diff / 1000);

                            clearInterval(interval);
                            complete = true;
                            status_text = 'Complete, Imported '+response.data.counter+' Records, Elapsed time ' + time_in_seconds + 's';
                            $progress.removeClass('iwp__progress--running');
                            $btn.text('Complete');
                            document.title = "Complete";
                        } else {

                            currentDate = new Date();
                            diff = currentDate.getTime() - startDate.getTime();
                            time_in_seconds = Math.floor(diff / 1000);

                            if (response.data.status === "deleting") {
                                status_text = 'Deleting, Elapsed time ' + time_in_seconds + 's';
                            } else {
                                status_text = 'Importing: ' + response_text + ", Elapsed time " + time_in_seconds + 's';
                            }
                        }

                        $progress.find('.iwp__progress-text').text(status_text).show();
                        document.title = status_text;
                    }

                },
                error: function(res){

                    on_error( $.parseJSON(res.responseText) );
                },
                complete: function () {
                    requests--;
                }
            }));

            requestCounter++;

        };

        var $progress = $('.iwp__progress');

        /**
         * On Start Import button pressed
         */
        $(document).on('click', '.jc-importer_update-run', function(){

            var $btn = $(this);

            if(!$btn.hasClass('iwp-import-btn__csv')){
                return;
            }

            if($btn.hasClass('button-disabled')){
                return;
            }

            $progress.find('.iwp__progress-text').text('Initialising');
            $progress.addClass('iwp__progress--running iwp__progress--visible');
            $btn.addClass('button-disabled');
            $btn.text('Running');
            startDate = currentDate = lastAjaxRequestSent = new Date();

            on_button_pressed($btn);

            interval = setInterval(function(){

                on_button_pressed($btn);

            }, requestIntervalTimer/2 );
        });

    })(jQuery);

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
		var records_per_row = <?php echo $record_import_count; ?>;
		var record_diffs = new Array();

		// ajax import
		$('.jc-importer_update-run').click(function (event) {

		    var $btn = $(this);
		    if(!$btn.hasClass('iwp-import-btn__xml')){
		        return;
            }

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
						$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p><span id="preview-loading" class="spinner" style="display: block; float:left; margin: 0 5px 0 0;"></span> Importing Record #' + (record - <?php echo $start_line -1; ?>) + ' out of #' + (record_total - <?php echo $start_line -1; ?>) + ' Estimated Finish time at ' + estimatedFinishDate + '</p></div>');
						document.title = 'Importing ('+ (record - <?php echo $start_line -1; ?>) +'/' + (record_total - <?php echo $start_line -1; ?>) +')';
					},
					success: function (response) {
						$('#ajaxResponse').html('');

						$('#the-list').prepend(response);

						record += records_per_row;

						// current record is (record - 1)
						if ((record - 1) < record_total) {

							if (running == 2) {

								// =========================================
								// Estimate how long on the current rate
								// would it take to complete
								// =========================================

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
							curr_del_record += records_per_row;

							var curr_del_record_output = curr_del_record;
							if(curr_del_record_output > del_count){
								curr_del_record_output = del_count;
							}

							deleteNextRecord();
							document.title = 'Deleting Items '+curr_del_record_output+'/'+del_count;
							$('#ajaxResponse').html('<div id="message" class="updated below-h2"><p>Deleting Items ('+curr_del_record_output+'/'+del_count+')</p></div>');
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
				<?php if( JCI()->importer->get_object_delete() !== false && JCI()->importer->get_object_delete() == 0): ?>
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