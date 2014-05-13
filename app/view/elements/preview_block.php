<?php
/**
 * Display preview record box and display preview text under template fields.
 *
 * @todo ammend to work with multiple groups, currently only works with one
 */

$jci_template_type = $jcimporter->importer->template_type;
?>
<div id="postimagediv" class="postbox">
	<h3><span>Preview Settings</span></h3>

	<div class="inside">
		<p>Choose the record you wish to preview</p>
		<input type="text" id="preview-record" value="1" />
		<a href="#" id="prev-record">[&laquo;]</a><a href="#" id="next-record">[&raquo;]</a>
		<script type="text/javascript">
		jQuery(function($){
			
			var preview_record = $('#preview-record');
			var min_record = 1;
			var max_record = <?php echo $jcimporter->importer->get_total_rows(); ?>;
			
			$('#prev-record').click(function(){

				val = parseInt(preview_record.val());
				if(val-1 >= min_record){
					preview_record.val(val - 1);
					preview_record.trigger('change');
				}
				return false;
			});
			
			$('#next-record').click(function(){

				val = parseInt(preview_record.val());
				if(val+1 <= max_record){
					preview_record.val(val + 1);
					preview_record.trigger('change');
				}
				return false;
			});

			// new record preview
			$('.xml-drop input').on('change', function(){

				var val = $(this).val();
				var obj = $(this).parent();
				if(val != ''){
					$.ajax({
						url: ajax_object.ajax_url,
						data: {
							action: 'jc_preview_record',
							id: ajax_object.id,
							<?php if($jci_template_type == 'xml'): ?>map: val,
							row: $('#preview-record').val(),
							general_base: $('#jc-importer_parser_settings-import_base').val(),
							group_base: $('input[id^="jc-importer_parser_settings-group-"]').val()
							<?php else: ?>map: val,
							row: $('#preview-record').val()
							<?php endif; ?>
							
						},
						dataType: 'json',
						type: "POST",
						beforeSend: function(){
							obj.find('.preview-text').text('Loading...');
						},
						success: function(response){
							
							$.each(response, function(index, value){
								obj.find('.preview-text').text('Preview: '+value[1]);
							});

						}
					});
				}else{
					obj.find('.preview-text').text('Preview:');
				}

			});


			// change all inputs
			function refreshPreview(){
				var nodes = [];

				$('.xml-drop input').each(function(){

					input_val = $(this).val();
					if(input_val != '' && $.inArray(input_val, nodes) == -1){
						// add to array if unique and not empty
						nodes.push(input_val);
					}
				});

				$.ajax({
					url: ajax_object.ajax_url,
					data: {
						action: 'jc_preview_record',
						id: ajax_object.id,
						<?php if($jci_template_type == 'xml'): ?>map: nodes,
						row: $('#preview-record').val(),
						general_base: $('#jc-importer_parser_settings-import_base').val(),
						group_base: $('input[id^="jc-importer_parser_settings-group-"]').val()
						<?php else: ?>map: nodes,
						row: $('#preview-record').val()
						<?php endif; ?>
					},
					dataType: 'json',
					type: "POST",
					success: function(response){

						$.each(response, function(index, value){

							$('.xml-drop input').each(function(){
								if($(this).val() == value[0]){
									$(this).parent().find('.preview-text').text('Preview: '+value[1]);
								}
							})
						});
					}
				});
			}

			function getRecordCount(){
				$.ajax({
					url: ajax_object.ajax_url,
					data: {
						action: 'jc_record_total',
						id: ajax_object.id,
						<?php if($jci_template_type == 'xml'): ?>general_base: $('#jc-importer_parser_settings-import_base').val()
						<?php endif; ?>
					},
					dataType: 'json',
					type: "POST",
					success: function(response){

						max_record = response;
						if(max_record < $('#preview-record').val()){
							$('#preview-record').val(max_record);
						}
					}
				})
			}
			getRecordCount();

			$('#preview-record').change('change', function(){
				refreshPreview();
			});

			<?php if($jci_template_type == 'xml'): ?>$('#jc-importer_parser_settings-import_base, input[id^="jc-importer_parser_settings-group-"]').on('change', function(){
				refreshPreview();
				getRecordCount();
			});
			<?php endif; ?>

			$('#preview-record').val($('#jc-importer_start-line').val());
			$('#preview-record').trigger('change');
		});
		</script>
	</div>
</div>