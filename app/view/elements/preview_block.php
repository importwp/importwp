<?php
/**
 * Display preview record box and display preview text under template fields.
 *
 * @todo pass xml base and group nodes with fields, so user doesnt have to save to see the updated version
 */
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
			var max_record = <?php echo $jcimporter->importer->get_total_rows() -1; ?>;
			
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
							map: val,
							row: $('#preview-record').val()
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
						map: nodes,
						row: $('#preview-record').val()
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

			$('#preview-record').change('change', function(){
				refreshPreview();
			});

			$('#preview-record').val($('#jc-importer_start-line').val());
			$('#preview-record').trigger('change');
		});
		</script>
	</div>
</div>