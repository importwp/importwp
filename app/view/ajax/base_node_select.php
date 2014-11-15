<?php
/**
 * XML Base node selector view
 *
 * Display list of avaliable nodes from current xml file
 * @todo: add xml result count
 */
?>
<div class="jci-node-select">

<div>
	<select id="jci-node-selector">
		<option value="">Leave Empty</option>
	<?php if ( ! empty( $nodes ) ): ?>
	<?php foreach ( $nodes as $node ): ?>
		<option value="<?php echo $node; ?>"><?php echo $node; ?></option>
	<?php endforeach; ?>
	<?php endif; ?>
	</select>
	<span class="spinner preview-loading" style="display: none;"></span>
	</div>

	<a class="button-primary jci-select-node">Submit</a>

	<h1>Records <span id="jci-record-count"></span></h1>

	
	<div id="jci-node-select-preview"></div>

	<script type="text/javascript">

		// set base node once clicked
		jQuery(function ($) {

			var base_node  = '';
			var base_node_parent = '<?php echo $base_node; ?>';

			$('#jci-node-selector').on('change', function(){

				base_node = $(this).val();
				var output_base_node = base_node_parent + base_node;

				data = {
		            action: 'jc_preview_xml_base_bode',
		            id: ajax_object.id,
		            base: base_node_parent + base_node
		        };

		        $('.jci-node-select .preview-loading').show();
		        $('#jci-node-select-preview').html('');

		        $.post(ajax_object.ajax_url, data, function (xml) {

		        	$('#jci-node-select-preview').html(xml);
		        }).always(function(){
		        	$('.jci-node-select .preview-loading').hide();
		        });

		        // get max amount of records which chosen base node
		        if(output_base_node == ''){
		        	output_base_node = '/';
		        }

		        $.ajax({
					url: ajax_object.ajax_url,
					data: {
						action: 'jc_record_total',
						id: ajax_object.id,
						general_base: output_base_node
					},
					dataType: 'json',
					type: "POST",
					success: function(response){

						$('#jci-record-count').text(response);
					}
				})
			});

			$('a.jci-select-node').click(function(event){
				event.preventDefault();

				jci_element.val(base_node);
				jci_element.trigger("change");
				tb_remove();
				return false;
			});

			$('#jci-node-selector').trigger('change');
		});
	</script>
</div>