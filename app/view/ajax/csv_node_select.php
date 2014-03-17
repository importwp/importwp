<h1>CSV</h1>
<table id="jci-csv-select" width="100%" border="1">
	<?php foreach($records as $row): ?>
		<tr>
		<?php foreach($row as $item): ?>
			<td>
				<?php echo $item; ?>
			</td>
		<?php endforeach; ?>
		</tr>
	<?php endforeach; ?>
</table>

<script>
	jQuery(function($){

		$('#jci-csv-select tr').each(function(){
			$(this).find('td').each(function(index){
				$(this).click(function(e){
					jci_element.val('{'+index+'}');
					tb_remove();
					e.preventDefault();
					return false;
				});
			});
		});
	});
</script>