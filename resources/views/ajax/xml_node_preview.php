<?php
/**
 * Preview xml selection from currently selected base node
 */
$output = $xml->output();
?>
<div id="treeView">
	<?php
	echo $output;
	?>
</div>

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