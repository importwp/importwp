<script type="text/javascript">
	jQuery(document).ready(function ($) {
		$.fn.nodeSelect('<?php echo $base_node; ?>');
	});
</script>

<ul id="treeView">
	<?php
	$xml->output();
	?>
</ul>

<style type="text/css">
	#treeView li {
		list-style: none;
	}

	#treeView ul {
		padding-left: 1em;
	}

	#treeView b {
		padding-right: 1em;
	}
</style>