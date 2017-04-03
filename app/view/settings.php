<?php
/**
 * Plugin Settings Page
 *
 * @package ImportWP/Admin
 * @author James Collings
 * @created 30/11/2016
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : false;
$tabs = array('debug', 'tools');

if(!in_array($tab, $tabs)){
	$tab = $tabs[0];
}

?>
<div class="wrap jci-page-wrapper">

	<h2>Settings</h2>

	<h3 class="nav-tab-wrapper">
		<a href="<?php echo add_query_arg('tab', 'tools'); ?>" class="nav-tab <?php if($tab == 'tools'): ?>nav-tab-active<?php endif; ?>">Tools</a>
		<a href="<?php echo add_query_arg('tab', 'debug'); ?>" class="nav-tab <?php if($tab == 'debug'): ?>nav-tab-active<?php endif; ?>">Debug</a>
	</h3>

	<div id="poststuff">

	<div id="post-body" class="metabox-holder columns-2">

			<div id="post-body-content">
				<?php include_once 'settings/' . $tab . '.php'; ?>
			</div>

			<div id="postbox-container-1" class="postbox-container">
				<div id="postimagediv" class="postbox iwp-about">
					<h3 class="iwp-about__heading"><span>ImportWP</span></h3>
					<hr>
					<div class="inside iwp-about__desc">
						<p>Thank you for using ImportWP, for more information on how to use ImportWP and all its features checkout the following links.</p>
						<ul>
							<li><a href="https://www.importwp.com/documentation/" target="_blank">Documentation</a></li>
							<li><a href="https://www.importwp.com/add-ons/" target="_blank">Add-ons</a></li>
							<li><a href="https://www.importwp.com/" target="_blank">About</a></li>
						</ul>
					</div>
					<p class="iwp-about__version">
						Version: <strong>0.3</strong>
					</p>
				</div>
			</div>
	</div>
	</div>



</div>
