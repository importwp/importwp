<div class="wrap jci-addons-page">
	<div id="icon-tools" class="icon32"><br></div>
	<h2>ImportWP Tools </h2>
	<p>A colleciton on helpful tools to maintain ImportWP.</p>
	
	<hr>

	<h3>Clear Importer Logs older than 1 day</h3>
	<?php if(isset($_GET['action']) && $_GET['action'] == 'clear-logs'): ?>
		<?php if(isset($_GET['result']) && $_GET['result'] == 1): ?><div id="message" class="error_msg warn updated"><p>Importer Logs have been cleared</p><?php endif; ?>
	</div><?php endif; ?>
	<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'clear-logs' ), remove_query_arg( 'result' ) ) ); ?>" class="button button-primary">Clear Importer Logs</a>
	<br><br>
	<hr>

	<h3>Remove old settings history</h3>
	<?php if(isset($_GET['action']) && $_GET['action'] == 'clear-settings'): ?>
		<?php if(isset($_GET['result']) && $_GET['result'] == 1): ?><div id="message" class="error_msg warn updated"><p>Importer Logs have been cleared</p><?php endif; ?>
	</div><?php endif; ?>
	<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'clear-settings' ), remove_query_arg( 'result' ) ) ); ?>" class="button button-primary">Clear Settings History</a>
	<br><br>
	<hr>

	<h3>Run DB Upgrade</h3>
	<?php if(isset($_GET['action']) && $_GET['action'] == 'update-db'): ?>
		<?php if(isset($_GET['result']) && $_GET['result'] == 1): ?><div id="message" class="error_msg warn updated"><p>Importer DB has been updated</p><?php endif; ?>
	</div><?php endif; ?>
	<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'update-db' ), remove_query_arg( 'result' ) ) ); ?>" class="button button-primary">Run DB Update</a>
	<br><br>
	<hr>
</div>