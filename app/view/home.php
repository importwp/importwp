<?php
/**
 * @global JC_Importer $jcimporter
 */
global $jcimporter;
$tab    = isset( $_GET['tab'] ) && ! empty( $_GET['tab'] ) ? $_GET['tab'] : 'home';
$action = isset( $_GET['action'] ) && ! empty( $_GET['action'] ) ? $_GET['action'] : 'index';
?>
<div class="wrap">
	<?php
	switch ( $tab ) {
		case 'settings':
			require 'settings.php';
			break;
		default:
			$id = isset( $_GET['import'] ) && ! empty( $_GET['import'] ) ? $_GET['import'] : 0;
			switch ( $action ) {
				case 'logs':
					require 'imports/logs.php';
					break;
				case 'history':
					require 'imports/history.php';
					break;
				case 'edit':
					require 'imports/edit.php';
					break;
				case 'add':
					require 'imports/add.php';
					break;
				default:
					require 'imports.php';
					break;
			}

			break;
	}
	?>
</div>