<?php
// load settings from gloabl
$importer_id         = JCI()->importer->get_ID();
$template_name       = JCI()->importer->get_template_name();
$template            = JCI()->importer->get_template();
$start_line          = JCI()->importer->get_start_line();
$row_count           = JCI()->importer->get_row_count();
$record_import_count = JCI()->importer->get_record_import_count();
$name                = JCI()->importer->get_name();
$template_type       = JCI()->importer->get_template_type();
$import_status       = 0;

if ( $row_count <= 0 ) {
	$record_count = 0; //$parser->get_total_rows();
} else {
	$record_count = ( $start_line - 1 ) + $row_count;
}

// check for continue
if ( isset( $_GET['continue'] ) ) {

	$last_import_row = JCI()->importer->get_last_import_row();
	if ( $last_import_row >= $start_line ) {
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

<?php if ( ! file_exists( JCI()->importer->file ) ): ?>
    <div id="message" class="error_msg warn error below-h2"><p>File to import could not be
            found: <?php echo JCI()->importer->file; ?></p></div>
<?php endif; ?>


<div id="poststuff">
    <div id="post-body" class="metabox-holder columns-2">


        <div id="post-body-content">

            <div id="postbox-container-2" class="postbox-container">

                <div id="test-response"></div>

				<?php

				// Hide results table for csv importer
				if (  isset( $_GET['continue'] ) ): ?>

                    <div id="jci-table-wrapper">
                        <table class="wp-list-table widefat fixed posts" cellspacing="0">
                            <thead>
                            <tr>
                                <th scope="col" id="author" class="manage-column column-author" style="width:30px;">ID
                                </th>
								<?php foreach ( $columns as $key => $col ): ?>
                                    <th scope="col" id="<?php echo $key; ?>"
                                        class="manage-column column-<?php echo $key; ?>"
                                        style=""><?php echo $col; ?></th>
								<?php endforeach; ?>
                            </tr>
                            </thead>
                            <tbody id="the-list">
							<?php
							if ( isset( $_GET['continue'] ) ):

								// TODO: add pagination to continue logs
								$rows = ImportLog::get_importer_log( $importer_id, JCI()->importer->get_version() );

								if ( $rows ) {
									foreach ( $rows as $r ) {
										$row  = $r->row;
										$data = array( unserialize( $r->value ) );
										require $jcimporter->get_plugin_dir() . 'app/view/imports/log/log_table_record.php';
									}
								}

							endif;
							?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <td colspan="<?php echo count( $columns ) + 1; ?>">&nbsp;</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

				<?php endif; ?>

				<?php if ( file_exists( JCI()->importer->file ) ): ?>

                    <div class="iwp__progress">
                        <div class="spinner iwp__progress-spinner"></div>
                        <p class="iwp__progress-text"></p></div>

                    <div class="form-actions">
                        <br/>
						<?php if ( $import_status == 1 ): ?>
                            <a href="#"
                               class="jc-importer_update-run iwp-import-btn__csv <?php echo $template_type; ?> button-primary">Continue
                                Import</a>
						<?php else: ?>
                            <a href="#"
                               class="jc-importer_update-run iwp-import-btn__csv <?php echo $template_type; ?> button-primary">Run
                                Import</a>
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

    (function ($) {

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

        var on_error = function (response) {

            if (false === error) {

                error = true;

                // cancel other ajax requests
                while ($jqajax.length > 0) {
                    var element = $jqajax.pop();
                    element.abort();
                }

                $progress.removeClass('iwp__progress--running');
                $progress.addClass('iwp__progress--error');

                $progress.find('.iwp__progress-text').text('Error: ' + response.data.message).show();
            }
        };

        var on_button_pressed = function ($btn) {

            // Escape due to error
            if (error === true) {
                return;
            }

            var cTimer = new Date();
            var timer = cTimer.getTime() - lastAjaxRequestSent.getTime();

            if (requests >= 2 || (run !== true && timer < requestIntervalTimer )) {
                return;
            }

            var data_arr = {
                action: 'jc_import_all',
                id: ajax_object.id
            };

            if (run === true) {
                data_arr.request = 'run';
            } else {
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
                beforeSend: function () {
                    requests++;
                    lastAjaxRequestSent = new Date();
                },
                success: function (response) {

                    lastAjaxRequestSent = new Date();

                    if (response !== null && typeof response === 'object') {

                        if ('error' === response.data.status) {

                            on_error(response);
                            return;
                        }

                        var diff = 0;
                        var time_in_seconds = 0;
                        var status_text = '';

                        var response_text = '';
                        if (response !== null && typeof response === 'object' && response.hasOwnProperty('data') && response.data.hasOwnProperty('last_record') && response.data.hasOwnProperty('end')) {
                            response_text = (parseInt(response.data.start) + parseInt(response.data.counter)) + "/" + response.data.end + ' , '+response.data.error+' Errors';
                        } else {
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

                            var deleteCount = response.data.hasOwnProperty('delete') ? response.data.delete : 0;
                            var deleteString = '';
                            if(deleteCount > 0){
                              deleteString = ', Deleted ' + deleteCount + ' Records';
                            }

                            clearInterval(interval);
                            complete = true;
                            status_text = 'Complete, Imported ' + response.data.counter + ' Records'+ deleteString +', '+response.data.error+' Errors, Elapsed time ' + time_in_seconds + 's';
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
                error: function (res) {
                    // TODO: Display this error but keep the import going, as this could just be a timeout message from ther server.
                    //on_error( $.parseJSON(res.responseText) );
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
        $(document).on('click', '.jc-importer_update-run', function () {

            var $btn = $(this);

            if (!$btn.hasClass('iwp-import-btn__csv')) {
                return;
            }

            if ($btn.hasClass('button-disabled')) {
                return;
            }

            $progress.find('.iwp__progress-text').text('Initialising');
            $progress.addClass('iwp__progress--running iwp__progress--visible');
            $btn.addClass('button-disabled');
            $btn.text('Running');
            startDate = currentDate = lastAjaxRequestSent = new Date();

            on_button_pressed($btn);

            interval = setInterval(function () {

                on_button_pressed($btn);

            }, requestIntervalTimer / 2);
        });

    })(jQuery);
</script>