<div id="postimagediv" class="postbox iwp-about">
	<h3 class="iwp-about__heading"><span>ImportWP</span></h3>
	<hr />
	<div class="inside iwp-about__desc">
		<p>Thank you for using ImportWP, for more information on how to use ImportWP and all its features checkout the following links.</p>
		<ul>
			<li><a href="https://www.importwp.com/documentation/" target="_blank">Documentation</a></li>
			<li><a href="https://www.importwp.com/add-ons/" target="_blank">Add-ons</a></li>
			<li><a href="https://www.importwp.com/" target="_blank">About</a></li>
			<?php if(!class_exists('ImportWP_Pro')): ?>
				<li class="iwp-about__upgrade"><i class="iwp-premium">*</i><a href="<?php echo admin_url('admin.php?page=jci-settings&tab=premium'); ?>"><strong>Go Premium</strong></a></li>
			<?php endif; ?>
		</ul>
	</div>
	<p class="iwp-about__version">
		Version: <strong><?php echo JCI()->get_version(); ?></strong>
	</p>
</div>