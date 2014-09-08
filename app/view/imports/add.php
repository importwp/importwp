<div id="icon-tools" class="icon32"><br></div>
<h2>Importer</h2>

<?php
echo JCI_FormHelper::create( 'CreateImporter', array( 'type' => 'file' ) );
?>

<div id="poststuff">
	<div id="post-body" class="metabox-holder columns-2">

		<div id="post-body-content">

			<div id="postbox-container-2" class="postbox-container">

				<div id="pageparentdiv" class="postbox " style="display: block;">
					<div class="handlediv" title="Click to toggle"><br></div>
					<h3 class="hndle"><span>Create Importer</span></h3>

					<div class="inside">
						<?php
						do_action( 'jci/before_import_settings' );

						echo '<h2 class="title">1. General Settings</h2>';

						// core fields
						echo JCI_FormHelper::text( 'name', array( 'label' => 'Name', 'default' => '' ) );
						echo JCI_FormHelper::select( 'template', array(
								'options' => get_template_list(false),
								'label'   => 'Template'
							) );

						echo '<h2 class="title">2. Choose Datasource</h2>';
						echo '<p>Select where you wish to import your data from.</p>';

						// upload file
						echo JCI_FormHelper::radio( 'import_type', array(
								'label' => 'Upload',
								'value' => 'upload',
								'class' => 'toggle-fields',
								'checked' => true
							) );


						// get file from url
						echo JCI_FormHelper::radio( 'import_type', array(
								'label' => 'Remote',
								'value' => 'remote',
								'class' => 'toggle-fields'
							) );


						do_action( 'jci/output_datasource_option' );

						echo '<h2 class="title">3. Setup Datasource</h2>';

						echo '<div class="hidden show-upload toggle-field">';
						echo '<p>Upload a file from your computer</p>';
						echo JCI_FormHelper::file( 'import_file', array( 'label' => 'Import File' ) );
						echo '</div>';

						echo '<div class="hidden show-remote toggle-field">';
						echo '<p>Download your file from a website or url</p>';
						echo JCI_FormHelper::text( 'remote_url', array( 'label' => 'URL' ) );
						echo '</div>';

						echo '<h2 class="title">4. Setup Permissions</h2>';
						echo '<p>Choose the permissions you wish the importer to have</p>';
						echo JCI_FormHelper::checkbox( 'permissions[create]', array(
							'label'   => 'Create',
							'default' => 1,
							'checked' => false
						) );
						echo JCI_FormHelper::checkbox( 'permissions[update]', array(
							'label'   => 'Update',
							'default' => 1,
							'checked' => false
						) );
						echo JCI_FormHelper::checkbox( 'permissions[delete]', array(
							'label'   => 'Delete',
							'default' => 1,
							'checked' => false
						) );

						do_action( 'jci/output_datasource_section' );

						do_action( 'jci/after_import_settings' );

						echo JCI_FormHelper::Submit( 'update', array(
								'class' => 'button button-primary button-large',
								'value' => 'Continue'
							) );
						?>
					</div>
				</div>

			</div>

			<div id="postbox-container-1" class="postbox-container">
				<?php include $this->config->plugin_dir . '/app/view/elements/about_block.php'; ?>
			</div>
			<!-- /postbox-container-1 -->
		</div>
	</div>
	<?php
	echo JCI_FormHelper::end();
	?>
	<script type="text/javascript">
		jQuery(function ($) {

			$('.toggle-fields > input').on('change', function () {

				var _this = $(this);
				var _selected = $('.toggle-fields > input:checked');
				$('.toggle-field').hide();
				$('.toggle-field.show-' + _selected.val()).show();

			}).trigger('change');
		});
	</script>