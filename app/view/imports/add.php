<div id="icon-tools" class="icon32"><br></div>
<h2>ImportWP</h2>

<?php
echo IWP_FormBuilder::create( 'CreateImporter', array( 'type' => 'file' ) );
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
						do_action( 'jci/before_import_settings' ); ?>
                        <div class="jci-add-section">
							<?php
							echo '<h4 class="title">1. What are you importing?</h4>';

							// core fields
							echo IWP_FormBuilder::select( 'template', array(
								'options' => get_template_list( false ),
								'label'   => 'Import Template',
								'empty'   => 'Choose a template',
								'class'   => 'jci-template-selector'
							) );

							do_action( 'jci/output_template_option' );
							?>
                        </div>
                        <div class="jci-add-section">
							<?php
							echo '<h4 class="title">2. Where is the data being imported from?</h4>';

							// upload file
							echo IWP_FormBuilder::radio( 'import_type', array(
								'label'   => '<strong>Uploaded File</strong> - Upload a file from your computer',
								'value'   => 'upload',
								'class'   => 'toggle-fields',
								'checked' => true
							) );


							// get file from url
							echo IWP_FormBuilder::radio( 'import_type', array(
								'label' => '<strong>Remote File</strong> - Download your file from a website or url',
								'value' => 'remote',
								'class' => 'toggle-fields'
							) );

							// local file
							echo IWP_FormBuilder::radio( 'import_type', array(
								'label' => '<strong>Local File</strong> - Get file from within a local folder',
								'value' => 'local',
								'class' => 'toggle-fields'
							) );

							do_action( 'jci/output_datasource_option' );

							?>
                        </div>
                        <div class="jci-add-section">
							<?php

							echo '<h4 class="title">3. Setup Datasource</h4>';

							echo '<div class="hidden show-upload toggle-field">';
							echo '<p>Choose the file below that you would like to import</p>';
							echo IWP_FormBuilder::file( 'import_file', array( 'label' => 'Import File' ) );
							echo '</div>';

							echo '<div class="hidden show-remote toggle-field">';
							echo '<p>Enter the url of the remote file you would like to import</p>';
							echo IWP_FormBuilder::text( 'remote_url', array( 'label' => 'URL' ) );
							echo '</div>';

							echo '<div class="hidden show-local toggle-field">';
							echo '<p>Enter the local path the file you would like to import</p>';
							echo IWP_FormBuilder::text( 'local_url', array( 'label' => 'Local Path' ) );
							echo '</div>';

							do_action( 'jci/output_datasource_section' );

							?>
                        </div>
						<?php

						/*echo '<h4 class="title">4. Setup Permissions</h4>';
						echo '<p>Choose from the list below what access you would like the importer to be granted.</p>';
						echo JCI_FormHelper::checkbox( 'permissions[create]', array(
							'label'   => '<strong>Create</strong> - Ability to insert new records',
							'default' => 1,
							'checked' => false
						) );
						echo JCI_FormHelper::checkbox( 'permissions[update]', array(
							'label'   => '<strong>Update</strong> - Ability to modify existing records',
							'default' => 1,
							'checked' => false
						) );
						echo JCI_FormHelper::checkbox( 'permissions[delete]', array(
							'label'   => '<strong>Delete</strong> - Ability to only delete imported records that no longer exist.',
							'default' => 1,
							'checked' => false
						) );*/

						do_action( 'jci/after_import_settings' );

						echo IWP_FormBuilder::Submit( 'update', array(
							'class' => 'button button-primary button-large',
							'value' => 'Continue'
						) );
						?>
                    </div>
                </div>

            </div>

            <div id="postbox-container-1" class="postbox-container">
				<?php include $this->config->get_plugin_dir() . '/app/view/elements/about_block.php'; ?>
            </div>
            <!-- /postbox-container-1 -->
        </div>
    </div>
	<?php
	echo IWP_FormBuilder::end();
	?>
    <script type="text/javascript">
        jQuery(function ($) {

            $('.toggle-fields > input').on('change', function () {

                var _this = $(this);
                var _selected = $('.toggle-fields > input:checked');
                $('.toggle-field').hide();
                $('.toggle-field.show-' + _selected.val()).show();

            }).trigger('change');

            // on template field select
            $('.jci-template-selector > select').on('change', function () {

                var _this = $(this);
                var _selected = _this.val();
                $('.jci-template-toggle-field').hide();
                $('.jci-template-toggle-field.show-' + _selected).show();

            }).trigger('change');

        });

        /**
         * Limit file upload to server limit
         */
        jQuery(function ($) {
            var upload_limit = parseInt('<?php echo iwp_return_bytes( ini_get('upload_max_filesize') ); ?>');
            var human_upload_limit = '<?php echo ini_get('upload_max_filesize'); ?>';
            var $file_uploads = $('.jci-page-wrapper input[type="file"]');
            if($file_uploads.length > 0){

                $file_uploads.each(function(){

                    var $file_upload = $(this);
                    $file_upload.on('change', function () {

                        var $parent = $file_upload.closest('.input');
                        if (this.files[0].size > upload_limit) {

                            $parent.addClass('form-error');
                            if($parent.find('.validation_msg').length === 0){
                                $file_upload.after('<div class="validation_msg"><p></p></div>')
                            }
                            $parent.find('.validation_msg p').text('↑ Uploaded file is larger than your server allows: '+human_upload_limit);
                            this.value = "";
                        }else{

                            $parent.removeClass('form-error');
                            if($parent.find('.validation_msg').length > 0){
                                $parent.find('.validation_msg').remove();
                            }
                        }
                    });
                });
            }
        });

    </script>