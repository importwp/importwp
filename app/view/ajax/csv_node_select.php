<div class="jci-node-select jci-csv-selector">

	<div class="jci-heading">
		<div class="jci-left">
			<h1>CSV Column Selector</h1>
			<p>Select columns below in the order you want, once happy click on the submit button to choose use the selection, you can manually edit the formatting in the <strong>Currently Selected</strong> field below.</p>
		</div>
	</div>

	<div class="jci-preview-block">
		<table id="jci-csv-select" width="100%" border="1">

			<thead>
				<?php
				$row = array_shift($records);
				if($row): ?>
					<tr>
					<?php foreach ( $row as $item ): ?>
						<th><?php echo $item; ?></th>
					<?php endforeach; ?>
					</tr>
				<?php endif; ?>
			</thead>

			<tbody>
			<?php if(count($records) > 0): ?>
				<?php foreach($records as $row): ?>
					<tr>
						<?php foreach ( $row as $item ): ?>
							<td><?php echo $item; ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>

		</table>
	</div>

	<div class="jci-footer">
		<div class="jci-left">
			<label for="">Currently Selected: <input type="text" id="jci-field-preview" /></label>
		</div>
		<div class="jci-left">
			<p><span id="jci-preview-selection"></span></p>
		</div>
		<div class="jci-right">
			<a class="button-primary jci-select-node" id="jci-submit-modal">Submit</a>
		</div>
	</div>
</div>

<script>
    jQuery(function ($) {

        var _preview_input = $('#jci-field-preview');

        // when loaded insert inital value into input
        $(document).ready(function () {
	        _preview_input.val(jci_element.val());
	        _preview_input.trigger('change');

	        // set heights of elements
	        var _modal = $('#TB_ajaxContent');
	        var _available_height = _modal.outerHeight();
	        var _header_height = _modal.find('.jci-heading').outerHeight();
	        var _footer_height = _modal.find('.jci-footer').outerHeight();

	        _modal.find('.jci-preview-block').height(_available_height - (_header_height + _footer_height));
        });

        // when a column is selected, insert that column into the input field
        $('#jci-csv-select tr').each(function () {
            $(this).find('td').each(function (index) {
                $(this).click(function (e) {
                    var _val = _preview_input.val();
                    _preview_input.val(_val + '{'+index+'}');
                    _preview_input.trigger('change');
                    e.preventDefault();
                    return false;
                });
            });
        });

        // When input changed, trigger preview
        _preview_input.on('change', function(){
            $.ajax({
                url: ajax_object.ajax_url,
                data: {
                    action: 'jc_preview_record',
                    id: ajax_object.id,
                    map: _preview_input.val(),
                    row: $('#preview-record').val()

                },
                dataType: 'json',
                type: "POST",
                beforeSend: function(){
                    $('#jci-preview-selection').text('Loading...');
                },
                success: function(response){
                    $.each(response, function(index, value){
                        $('#jci-preview-selection').text('Preview: '+value[1]);
                    });
                }
            });
        });

        // on submit modal button clicked
        $('#jci-submit-modal').on('click', function(event){
			jci_element.val(_preview_input.val());
            jci_element.trigger('change');
			tb_remove();
            event.preventDefault();
			return false;
        });
    });
</script>