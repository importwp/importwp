<div class="jci-node-select jci-csv-selector">

	<div class="jci-heading">
		<div class="jci-left">
			<h1>CSV</h1>
			<p>Select a column from the data below.</p>
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
</div>

<script>
    jQuery(function ($) {

        $('#jci-csv-select tr').each(function () {
            $(this).find('td').each(function (index) {
                $(this).click(function (e) {
                    jci_element.val('{' + index + '}');
                    jci_element.trigger('change');
                    tb_remove();
                    e.preventDefault();
                    return false;
                });
            });
        });
    });
</script>