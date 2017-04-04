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
$tabs = array('debug', 'tools', 'premium');

if(!in_array($tab, $tabs)){
	$tab = $tabs[0];
}

?>
<div class="wrap jci-page-wrapper <?php echo esc_attr(sprintf('jci-setting-%s-wrapper', $tab)); ?>">

	<h2>Settings</h2>

	<h3 class="nav-tab-wrapper">
		<a href="<?php echo add_query_arg('tab', 'tools'); ?>" class="nav-tab <?php if($tab == 'tools'): ?>nav-tab-active<?php endif; ?>">Tools</a>
		<a href="<?php echo add_query_arg('tab', 'debug'); ?>" class="nav-tab <?php if($tab == 'debug'): ?>nav-tab-active<?php endif; ?>">Debug</a>
		<?php if(!class_exists('ImportWP_Pro')): ?>
		<a href="<?php echo add_query_arg('tab', 'premium'); ?>" class="nav-tab <?php if($tab == 'premium'): ?>nav-tab-active<?php endif; ?>">Premium</a>
		<?php endif; ?>
	</h3>

	<div id="poststuff">

	<div id="post-body" class="metabox-holder columns-2">

			<div id="post-body-content">
				<?php include_once 'settings/' . $tab . '.php'; ?>
			</div>

			<div id="postbox-container-1" class="postbox-container">
				<?php include $this->config->get_plugin_dir() . '/app/view/elements/about_block.php'; ?>
			</div>
	</div>
	</div>



</div>
