<?php
/**
 * Preview xml selection from currently selected base node
 */
?>
<div id="treeView">
	<?php
	echo $xml->output();
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