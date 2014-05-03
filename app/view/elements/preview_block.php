<?php
/**
 * Display preview record box and display preview text under template fields.
 *
 * @todo pass array of all fields to save each field being indevidually processed.
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
						dataType: 'html',
						type: "POST",
						success: function(response){
							obj.find('.preview-text').text(response);
						}
					});
				}

			});

			$('#preview-record').change('change', function(){
				$('.xml-drop .preview-text').each(function(){
					$(this).text('Preview:');
				});
				$('.xml-drop input').trigger('change');
			});

			$('#preview-record').val($('#jc-importer_start-line').val());
			$('#preview-record').trigger('change');
		});
		</script>
	</div>
</div>