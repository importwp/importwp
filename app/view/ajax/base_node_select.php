<?php
if ( ! empty( $nodes ) ) {
	foreach ( $nodes as $node ) {
		echo '<a href="/' . $node . '" class="set_node">' . $node . '</a><br />';
	}
}
?>
<a href="" class="set_node"> Leave Empty</a>
<script type="text/javascript">
	// set base node once clicked
	jQuery(function ($) {
		$('.set_node').on('click', function (e) {
			e.preventDefault();
			var url = $(this).attr('href');
			// $('#jc-importer_general_addons-import_base').val(url);
			// $('#jc-importer_general_addons-import_base').trigger('change');
			jci_element.val(url);
			tb_remove();
			return false;
		});
	})
</script>