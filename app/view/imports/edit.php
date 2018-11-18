<?php
/**
 * @global JC_Importer $jcimporter
 */
global $jcimporter;
$name                 = $jcimporter->importer->get_name();
$template = $jcimporter->importer->get_template();
$template_type        = $jcimporter->importer->get_template_type();
$template_groups      = $jcimporter->importer->get_template_groups();
$start_line           = $jcimporter->importer->get_start_line();
$row_count            = $jcimporter->importer->get_row_count();
$record_import_count  = $jcimporter->importer->get_record_import_count();
$permissions_general  = $jcimporter->importer->get_permissions();
$taxonomies           = $jcimporter->importer->get_taxonomies();
$taxonomy_permissions = $jcimporter->importer->get_taxonomies_permissions();
$attachments          = $jcimporter->importer->get_attachments();
$template_unique_field = $jcimporter->importer->get_template_unique_field();
$total_rows = 0;//$jcimporter->importer->get_total_rows();
$last_import_row = $jcimporter->importer->get_last_import_row();
?>

<div id="icon-tools" class="icon32"><br></div>
<h2 class="nav-tab-wrapper">
    <a href="admin.php?page=jci-importers&import=<?php echo $id; ?>&action=edit"
       class="nav-tab nav-tab-active tab"><?php echo $name; ?></a>
    <a href="admin.php?page=jci-importers&import=<?php echo $id; ?>&action=history" class="nav-tab tab">History</a>
</h2>

<div id="ajaxResponse"></div>

<div id="processing" style="display: none;" class="error_msg warn error below-h2"><span class="spinner preview-loading" style="display: block; visibility: visible;"></span><p>Processing File</p></div>

<?php
jci_display_messages();

if ( ! $jcimporter->importer->get_template() ) {
	echo '<div id="message" class="error_msg warn error below-h2"><p>The import template could not be located</p></div>';
}

// check for incomplete import and show message to resume
$import_complete = true;
$status = IWP_Status::read_file();
if($status !== false){

	switch($status['status']){
        case 'error':
	        echo '<div id="message" class="error_msg error updated below-h2"><p><strong>Last Import ran threw the following error: </strong><br />'.$status['message'].'.</p></div>';
	        break;
		case 'timeout':
		case 'running':
		case 'started':
		case 'deleting':

		$cron_enabled_meta = get_post_meta( $id, '_jci_cron_enabled', true);
		if($cron_enabled_meta !== 'yes'){
			echo '<div id="message" class="error_msg warn updated below-h2"><p>Do you want to continue your last import? <a href="admin.php?page=jci-importers&import=' . $id . '&action=logs&continue=1">Click here</a>.</p></div>';
			$import_complete = false;
        }
			break;
		case 'complete':
			break;
	}
}
?>

<?php
echo JCI_FormHelper::create( 'EditImporter', array( 'type' => 'file' ) );

// hidden fields
echo JCI_FormHelper::hidden( 'import_id', array( 'value' => $id ) );
?>

<div id="poststuff" class="<?php echo $template_type; ?>-import jci-edit-screen">
    <div id="post-body" class="metabox-holder columns-2">

        <div id="post-body-content">

            <div id="jci-about-block" class="postbox-container jci-sidebar">

				<?php include $this->config->get_plugin_dir() . '/app/view/elements/about_block.php'; ?>

            </div><!-- /#jci-about-block -->

            <div id="jci-settings-block" class="postbox-container">

                <div id="pageparentdiv" class="postbox " style="display: block;">
                    <div class="handlediv" title="Click to toggle"><br></div>
                    <h3 class="hndle"><span>Import Settings</span></h3>

                    <div class="inside jci-node-group">

                        <ul class="jci-node-group-tabs subsubsub"></ul>

                        <div style="clear:both;"></div>

                        <div class="jci-group-general jci-group-section" data-section-id="general">

							<?php

							do_action( 'jci/before_import_settings' );

							echo JCI_FormHelper::text( 'name', array(
								'label'   => 'Importer Name',
								'default' => $name,
								'tooltip' => JCI()->text()->get( 'import.settings.name' )
							) );

							echo JCI_FormHelper::text( 'start-line', array(
								'label'   => 'Start Row',
								'default' => $start_line,
								'tooltip' => JCI()->text()->get( 'import.settings.start_line' )
							) );
							echo JCI_FormHelper::text( 'row-count', array(
								'label'   => 'Max Rows',
								'default' => $row_count,
								'tooltip' => JCI()->text()->get( 'import.settings.row_count' )
							) );
							echo JCI_FormHelper::text( 'record-import-count', array(
								'label'   => 'Records per Import',
								'default' => $record_import_count,
								'tooltip' => JCI()->text()->get( 'import.settings.record_import_count' )
							) );
							echo JCI_FormHelper::select( 'template_type', array(
								'label'   => 'Template Type',
								'options' => array( 'csv' => 'CSV', 'xml' => 'XML' ),
								'default' => $template_type,
								'tooltip' => JCI()->text()->get( 'import.settings.template_type' )
							) );
							echo JCI_FormHelper::text( 'template-unique-field', array(
								'label'   => 'Unique Field',
								'default' => $template_unique_field,
								'tooltip' => JCI()->text()->get( 'template.default.template_unique+field' )
							) );

							// core fields
							do_action( "jci/output_{$template_type}_general_settings", $id );

							do_action( 'jci/after_import_settings' );
							?>
                        </div>

						<?php if ( $jcimporter->importer->get_import_type() == 'remote' ): ?>
                            <div class="jci-group-remote jci-group-section" data-section-id="Remote">
                                <div class="remote">
									<?php
									$remote_settings = ImporterModel::getImportSettings( $id, 'remote' );
									$url             = $remote_settings['remote_url'];
									echo JCI_FormHelper::text( 'remote_url', array(
										'label'   => 'Remote Url',
										'default' => $url,
										'tooltip' => JCI()->text()->get( 'import.remote.remote_url' )
									) );
									/*echo JCI_FormHelper::select( 'remote_frequency', array(
											'label'   => 'Frequency',
											'options' => array(
												'None',
												'Hourly',
												'Daily',
												'Weekly',
												'Monthly'
											)
										) );*/
									?>
                                </div>
                            </div>
						<?php endif; ?>

						<?php if ( $jcimporter->importer->get_import_type() == 'local' ): ?>
                            <div class="jci-group-local jci-group-section" data-section-id="Local Path">
                                <div class="local">
									<?php
									$remote_settings = ImporterModel::getImportSettings( $id, 'local' );
									$url             = $remote_settings['local_url'];
									echo JCI_FormHelper::text( 'local_url', array(
										'label'   => 'Local Path',
										'default' => $url,
										'tooltip' => JCI()->text()->get( 'import.local.local_url' )
									) );
									?>
                                </div>
                            </div>
						<?php endif; ?>

						<?php
						/**
						 * Output Importer Settings Sections
						 */
						do_action( 'jci/importer_setting_section' ); ?>

                        <div class="jci-group-permissions jci-group-section" data-section-id="permissions">
                            <div class="permissions">
                                <h4>Permissions</h4>
								<?php
								$perm_create = isset( $permissions_general['create'] ) && $permissions_general['create'] == 1 ? 1 : 0;
								$perm_update = isset( $permissions_general['update'] ) && $permissions_general['update'] == 1 ? 1 : 0;
								$perm_delete = isset( $permissions_general['delete'] ) && $permissions_general['delete'] == 1 ? 1 : 0;

								echo JCI_FormHelper::checkbox( 'permissions[create]', array(
									'label'   => 'Create',
									'default' => 1,
									'checked' => $perm_create
								) );
								echo JCI_FormHelper::checkbox( 'permissions[update]', array(
									'label'   => 'Update',
									'default' => 1,
									'checked' => $perm_update
								) );
								echo JCI_FormHelper::checkbox( 'permissions[delete]', array(
									'label'   => 'Delete',
									'default' => 1,
									'checked' => $perm_delete
								) );
								?>
                            </div>
                        </div>

                        <div class="jci-group-files jci-group-section" data-section-id="files">
                            <div class="file_hostory">
                                <h4>Files:</h4>
								<?php

								$current_import_file = basename( $jcimporter->importer->get_file() );
								$files               = ImporterModel::getImporterFiles( $id );

								echo '<ul>';

								if ( $files ) {

									foreach ( $files as $file ) {
										$import_file = basename( $file->src );
										echo '<li>' . JCI_FormHelper::radio( 'file_select', array(
												'value'   => $file->id,
												'label'   => $import_file . ' (' . date( get_site_option( 'date_format' ), strtotime( $file->created ) ) . ' at ' . date( get_site_option( 'time_format' ), strtotime( $file->created ) ) . ')',
												'checked' => $import_file == $current_import_file ? true : false
											) ) . '</li>';
									}
								}

								echo '</ul>';

								// file upload
								echo JCI_FormHelper::file( 'import_file', array( 'label' => 'Import File' ) );
								echo JCI_FormHelper::Submit( 'upload_file', array(
									'class' => 'button',
									'value' => 'Upload File'
								) );
								?>
                            </div>
                            <!-- /.file_history -->
                        </div>

                    </div>

                    <div class="form-actions">

						<?php
						echo JCI_FormHelper::Submit( 'btn-save', array(
							'class' => 'button-primary button',
							'value' => 'Save All'
						) );
						echo JCI_FormHelper::Submit( 'btn-continue', array(
							'class' => 'button-secondary button',
							'value' => 'Save & Run'
						) );
						?>
                    </div>

                </div>

            </div>


            <!-- /postbox-container-1 -->


            <!-- end of top section -->


            <div style="clear:both;"></div>

            <div id="jci-preview-block" class="postbox-container jci-sidebar">

				<?php
                include $this->config->get_plugin_dir() . '/app/view/elements/preview_block.php';
				?>

            </div><!-- /#jci-preview-block -->

            <div class="postbox-container">


				<?php if ( $id > 0 ): ?>

					<?php do_action( 'jci/before_import_fields' ); ?>

					<?php foreach ( $template_groups

					as $group_id => $group ): ?>
                    <!--Container-->
                    <div id="pageparentdiv" class="postbox " style="display: block;">
                        <div class="handlediv" title="Click to toggle"><br></div>
                        <h3 class="hndle"><span>Template Fields: <?php echo $group_id; ?></span></h3>

                        <div class="inside jci-node-group">

                            <ul class="jci-node-group-tabs subsubsub">
                            </ul>

                            <div style="clear:both;"></div>

                            <div class="jci-group-fields jci-group-section" data-section-id="fields">

								<?php
								$group_type = $group['import_type'];

								// output addon group fields
								do_action( "jci/output_{$template_type}_group_settings", $id, $group_id );

								if(method_exists($template, 'get_template_version')){

									/**
									 * @var IWP_Base_Template $template
									 */
									$template->display_fields();

                                }else{
									foreach ( $group['fields'] as $key => $value ) {
										$title   = $group['titles'][ $key ];
										$tooltip = $group['tooltips'][ $key ];
										echo JCI_FormHelper::text( 'field[' . $group_id . '][' . $key . ']', array(
											'label'   => $title,
											'tooltip' => $tooltip,
											'default' => esc_attr($value),
											'class'   => 'xml-drop jci-group',
											'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
											'data'    => array(
												'jci-field' => $key,
											)
										) );
									}
                                }



								?>
                            </div>

							<?php
							/**
							 * Display template settings
							 */
							do_action( 'jci/after_template_fields', $id, $group_id, $group ); ?>

							<?php
							/**
							 * Do post specific options
							 */
							if ( $group_type == 'post' ): ?>

								<?php
								// if taxonomies are allowed , get_taxonomies doesnt work 100%
								$temp_taxonomies = get_object_taxonomies( $group['import_type_name'], 'objects' );

								if ( isset( $group['taxonomies'] ) && $group['taxonomies'] == 1 && ! empty( $temp_taxonomies ) ): ?>
                                    <div class="jci-group-taxonomy jci-group-section" data-section-id="taxonomy">

										<?php

										$post_taxonomies = array();
										foreach ( $temp_taxonomies as $tax_id => $tax ) {
											$post_taxonomies[ $tax_id ] = $tax->label;
										}
										?>

                                        <div id="<?php echo $group_id; ?>-taxonomies" class="taxonomies multi-rows">

                                            <table class="iwp-table" cellspacing="0" cellpadding="0">
                                                <thead class="iwp-table__header">
                                                <tr>
                                                    <th>Taxonomies</th>
                                                    <th>_</th>
                                                </tr>
                                                </thead>
                                                <tbody class="iwp-table__body">
												<?php if ( isset( $taxonomies[ $group_id ] ) && ! empty( $taxonomies[ $group_id ] ) ): ?>

													<?php foreach ( $taxonomies[ $group_id ] as $tax => $term_arr ): $term = isset( $term_arr[0] ) ? $term_arr[0] : ''; ?>

                                                        <tr class="taxonomy multi-row">
                                                            <td>
																<?php echo JCI_FormHelper::select( 'taxonomies[' . $group_id . '][tax][]', array(
																	'label'   => 'Tax',
																	'default' => $tax,
																	'options' => $post_taxonomies,
																	'tooltip' => JCI()->text()->get( 'template.default.taxonomy_tax' )
																) ); ?>
																<?php echo JCI_FormHelper::text( 'taxonomies[' . $group_id . '][term][]', array(
																	'label'   => 'Term',
																	'default' => $term,
																	'class'   => 'xml-drop jci-group',
																	'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
																	'tooltip' => JCI()->text()->get( 'template.default.taxonomy_term' )
																) ); ?>
																<?php
																// $permissions = isset($taxonomies[$group_id]['permissions'][$key]) && !empty($taxonomies[$group_id]['permissions'][$key]) ? $taxonomies[$group_id]['permissions'][$key] : 'overwrite';
																echo JCI_FormHelper::select( 'taxonomies[' . $group_id . '][permissions][]', array(
																	'label'   => 'Permissions',
																	'default' => $taxonomy_permissions[ $group_id ][ $tax ],
																	'options' => array(
																		'create'    => 'Add if no existing terms',
																		'overwrite' => 'Overwrite Existing terms',
																		'append'    => 'Append New terms'
																	),
																	'tooltip' => JCI()->text()->get( 'template.default.taxonomy_permission' )
																) );
																?>
                                                            </td>
                                                            <td>
                                                                <a href="#" class="add-row button"
                                                                   title="Add New Taxonomy">+</a>
                                                                <a href="#" class="del-row button"
                                                                   title="Delete Taxonomy">-</a>
                                                            </td>
                                                        </tr>
													<?php endforeach; ?>

												<?php else: ?>

                                                    <tr class="taxonomy multi-row">
                                                        <td>
															<?php echo JCI_FormHelper::select( 'taxonomies[' . $group_id . '][tax][]', array(
																'label'   => 'Tax',
																'default' => '',
																'options' => $post_taxonomies,
																'tooltip' => JCI()->text()->get( 'template.default.taxonomy_tax' )
															) ); ?>
															<?php echo JCI_FormHelper::text( 'taxonomies[' . $group_id . '][term][]', array(
																'label'   => 'Term',
																'default' => '',
																'class'   => 'xml-drop jci-group',
																'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
																'tooltip' => JCI()->text()->get( 'template.default.taxonomy_term' )
															) ); ?>
															<?php
															echo JCI_FormHelper::select( 'taxonomies[' . $group_id . '][permissions][]', array(
																'label'   => 'Permissions',
																'default' => '',
																'options' => array(
																	'create'    => 'Add if no existing terms',
																	'overwrite' => 'Overwrite Existing terms',
																	'append'    => 'Append New terms'
																),
																'tooltip' => JCI()->text()->get( 'template.default.taxonomy_permission' )
															) );
															?>
                                                        </td>
                                                        <td>
                                                            <a href="#" class="add-row button" title="Add New Taxonomy">+</a>
                                                            <a href="#" class="del-row button"
                                                               title="Delete Taxonomy">-</a>
                                                        </td>
                                                    </tr>

												<?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>


                                        <!-- /taxonomy section -->
                                    </div>
								<?php endif; ?>


								<?php
								// if attachments are allowed
								if ( isset( $group['attachments'] ) && $group['attachments'] == 1 ): ?>
                                    <div class="jci-group-attachment jci-group-section" data-section-id="attachment">

                                        <div id="attachments" class="attachments multi-rows">

                                            <table class="iwp-table" cellspacing="0" cellpadding="0">
                                                <thead class="iwp-table__header">
                                                <tr>
                                                    <th>Attachments</th>
                                                    <th>_</th>
                                                </tr>
                                                </thead>
                                                <tbody class="iwp-table__body">

												<?php if ( isset( $attachments[ $group_id ]['location'] ) && ! empty( $attachments[ $group_id ]['location'] ) ): ?>

													<?php foreach ( $attachments[ $group_id ]['location'] as $key => $val ): ?>
                                                        <tr class="attachment multi-row">
                                                            <td>
																<?php echo JCI_FormHelper::text( 'attachment[' . $group_id . '][location][]', array(
																	'label'   => 'Location',
																	'default' => $val,
																	'class'   => 'xml-drop jci-group',
																	'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
																	'tooltip' => JCI()->text()->get( 'template.default.attachment_location' ),
																) ); ?>
																<?php
																$permissions = isset( $attachments[ $group_id ]['permissions'][ $key ] ) && ! empty( $attachments[ $group_id ]['permissions'][ $key ] ) ? $attachments[ $group_id ]['permissions'][ $key ] : 'overwrite';
																echo JCI_FormHelper::select( 'attachment[' . $group_id . '][permissions][]', array(
																	'label'   => 'Permissions',
																	'default' => $permissions,
																	'options' => array(
																		'create' => 'Add if no existing attachments',
																		// 'overwrite' => 'Overwrite Existing Attachments',
																		'append' => 'Append New Attachments'
																	),
																	'tooltip' => JCI()->text()->get( 'template.default.attachment_permissions' ),
																) );

																$featured_image = isset( $attachments[ $group_id ]['featured_image'][ $key ] ) && ! empty( $attachments[ $group_id ]['featured_image'][ $key ] ) ? $attachments[ $group_id ]['featured_image'][ $key ] : 0;
																echo JCI_FormHelper::checkbox( "attachment[$group_id][featured_image][]", array(
																	'label'   => 'Set as Featured Image',
																	'checked' => $featured_image
																) );
																?>
                                                            </td>
                                                            <td>
                                                                <a href="#" class="add-row button"
                                                                   title="Add New Attachment">+</a>
                                                                <a href="#" class="del-row button"
                                                                   title="Delete Attachment">-</a>
                                                            </td>
                                                        </tr>
													<?php endforeach; ?>

												<?php else: ?>
                                                    <tr class="attachment multi-row">
                                                        <td>
															<?php echo JCI_FormHelper::text( 'attachment[' . $group_id . '][location][]', array(
																'label'   => 'Location',
																'default' => '',
																'class'   => 'xml-drop jci-group',
																'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
																'tooltip' => JCI()->text()->get( 'template.default.attachment_location' ),
															) ); ?>
															<?php
															echo JCI_FormHelper::select( 'attachment[' . $group_id . '][permissions][]', array(
																'label'   => 'Permissions',
																'default' => '',
																'options' => array(
																	'create' => 'Add if no existing attachments',
																	// 'overwrite' => 'Overwrite Existing Attachments',
																	'append' => 'Append New Attachments'
																),
																'tooltip' => JCI()->text()->get( 'template.default.attachment_permissions' ),
															) );
															echo JCI_FormHelper::checkbox( "attachment[$group_id][featured_image][]", array(
																'label'   => 'Set as Featured Image',
																'checked' => 0
															) );
															?>
                                                        </td>
                                                        <td>
                                                            <a href="#" class="add-row button"
                                                               title="Add New Attachment">+</a>
                                                            <a href="#" class="del-row button"
                                                               title="Delete Attachment">-</a>
                                                        </td>
                                                    </tr>
												<?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <table class="iwp-table" cellspacing="0" cellpadding="0">
                                            <thead class="iwp-table__header">
                                            <tr>
                                                <th>Connection Details</th>
                                            </tr>
                                            </thead>
                                            <tbody class="iwp-table__body">
                                            <tr>
                                                <td>
													<?php
													$attachment_type = isset( $attachments[ $group_id ]['type'] ) && ! empty( $attachments[ $group_id ]['type'] ) ? $attachments[ $group_id ]['type'] : '';
													echo JCI_FormHelper::select( 'attachment[' . $group_id . '][type]', array(
														'label'   => 'Download',
														'options' => array(
															'ftp'   => 'Ftp',
															'url'   => 'Remote Url',
															'local' => 'Local Filesystem'
														),
														'class'   => 'download-toggle',
														'default' => $attachment_type,
														'tooltip' => JCI()->text()->get( 'template.default.attachment_download' ),
													) );
													?>

													<?php
													$ftp_server = isset( $attachments[ $group_id ]['ftp']['server'] ) && ! empty( $attachments[ $group_id ]['ftp']['server'] ) ? $attachments[ $group_id ]['ftp']['server'] : '';
													echo JCI_FormHelper::text( 'attachment[' . $group_id . '][ftp][server]', array(
														'label'   => 'FTP Server',
														'default' => $ftp_server,
														'class'   => 'ftp-field input-toggle',
														'tooltip' => JCI()->text()->get( 'template.default.attachment_ftp_server' ),
													) );
													?>
													<?php
													$ftp_user = isset( $attachments[ $group_id ]['ftp']['user'] ) && ! empty( $attachments[ $group_id ]['ftp']['user'] ) ? $attachments[ $group_id ]['ftp']['user'] : '';
													echo JCI_FormHelper::text( 'attachment[' . $group_id . '][ftp][user]', array(
														'label'   => 'Username',
														'default' => $ftp_user,
														'class'   => 'ftp-field input-toggle',
														'tooltip' => JCI()->text()->get( 'template.default.attachment_ftp_username' ),
													) );
													?>
													<?php
													$ftp_pass = isset( $attachments[ $group_id ]['ftp']['pass'] ) && ! empty( $attachments[ $group_id ]['ftp']['pass'] ) ? $attachments[ $group_id ]['ftp']['pass'] : '';
													echo JCI_FormHelper::password( 'attachment[' . $group_id . '][ftp][pass]', array(
														'label'   => 'Password',
														'default' => $ftp_pass,
														'class'   => 'ftp-field input-toggle',
														'tooltip' => JCI()->text()->get( 'template.default.attachment_ftp_password' ),
													) );
													?>
													<?php
													$local_base_path = isset( $attachments[ $group_id ]['local']['base_path'] ) && ! empty( $attachments[ $group_id ]['local']['base_path'] ) ? $attachments[ $group_id ]['local']['base_path'] : '';
													echo JCI_FormHelper::text( 'attachment[' . $group_id . '][local][base_path]', array(
														'label'   => 'Local Base Path',
														'default' => $local_base_path,
														'class'   => 'local-field input-toggle',
														'tooltip' => JCI()->text()->get( 'template.default.attachment_local_path' ),
													) );
													?>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
								<?php endif; ?>

							<?php endif; ?>

                        </div>

                        <div class="form-actions">
							<?php
							echo JCI_FormHelper::Submit( 'btn-save', array(
								'class' => 'button-primary button',
								'value' => 'Save All'
							) );
							echo JCI_FormHelper::Submit( 'btn-continue', array(
								'class' => 'button-secondary button',
								'value' => 'Save & Run'
							) );
							?>
                        </div>
                    </div>
                    <!--/Container-->
				<?php endforeach; ?>

                    <script type="text/javascript">

                        // turn sections into tabs
                        jQuery(function ($) {

                            $('.jci-node-group').each(function () {

                                // create tabs
                                var _section = $(this);
                                var _sections = $(this).find('.jci-group-section');

                                if (_sections.length > 1) {

                                    _sections.each(function (index) {

                                        group_id = $(this).data('section-id');

                                        if (_sections.length == (index + 1)) {
                                            _section.find('.jci-node-group-tabs').append('<li class="' + group_id + '"><a href="#">' + group_id + ' </a></li>');
                                        } else {
                                            _section.find('.jci-node-group-tabs').append('<li class="' + group_id + '"><a href="#">' + group_id + ' </a> |</li>');
                                        }

                                    });

                                    _section.find('.jci-node-group-tabs a').each(function (index) {

                                        if (index > 0) {
                                            _section.find('.jci-group-section:eq(' + index + ')').hide();
                                        } else {
                                            $(this).addClass('current');
                                        }

                                        var _handle = $(this);

                                        _handle.click(function (e) {
                                            _section.find('.jci-group-section').hide();
                                            _section.find('.jci-group-section:eq(' + index + ')').show();

                                            _section.find('.jci-node-group-tabs a').removeClass('current');
                                            $(this).addClass('current');
                                            e.preventDefault();
                                        })

                                    });
                                }

                            });
                        });
                    </script>

				<?php do_action( 'jci/after_import_fields' ); ?>

                    <script type="text/javascript">

                        jQuery(function ($) {

                            $('.input-toggle').hide();

                            $('.download-toggle select').on('change', function () {
                                $('.input-toggle').hide();
                                $('.' + $(this).val() + '-field').show();
                            });

                            $('.download-toggle select').trigger('change');
                        });

                        /**
                         * jQuery Multi row function
                         *
                         * @return void
                         */
                        jQuery(function ($) {

                            $('.multi-rows').each(function (index) {

                                var _parent = $(this);

                                // add new row
                                $(this).on('click', '.add-row', function () {

                                    var repeating = _parent.find('.multi-row').last();
                                    var clone = repeating.clone();
                                    $('input[type=text]', clone).val('');
                                    clone.insertAfter(repeating);

                                    // Re initialize tooltips
                                    clone.find('.iwp-field__tooltip').each(function () {
                                        var title = $(this).data('title');
                                        if (title.length > 0) {
                                            $(this).attr('title', title);
                                            $(this).removeClass('iwp-field__tooltip--initialized');
                                        }
                                    });
                                    $(document).trigger('iwp_importer_updated');
                                    return false;
                                });

                                // del new row
                                $(this).on('click', '.del-row', function () {

                                    if (_parent.find('.multi-row').length <= 1) {
                                        return false;
                                    }
                                    $(this).closest('.multi-row').remove();
                                    return false;
                                });
                            });
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
				<?php endif; ?>

            </div>

        </div>
    </div>
	<?php
	echo JCI_FormHelper::end();
	?>
    <div class="field-option" style="display:none;">
		<?php
		// Output template field options
		foreach ( $template_groups as $group_id => $group ) {

			$group_type = $group['import_type'];

			// output addon group fields
			do_action( "jci/output_{$template_type}_group_settings", $id, $group_id );

			foreach ( $group['field_options'] as $key => $value ) {

				$title = $group['titles'][ $key ];

				$default = isset( $group[ $key ]['field_options_default'] ) ? $group[ $key ]['field_options_default'] : '';

				echo JCI_FormHelper::select( 'field[' . $group_id . '][' . $key . ']', array(
					'label'   => false,
					'id'      => "{$group_id}-{$key}-options",
					'default' => $group['field_options_default'][ $key ],
					'options' => $value,
					'class'   => 'xml-drop jci-group jci-default-dropdown'
				) );
			}

		}
		?>
		<?php
		// allow plugins to output default field selects
		do_action( 'jci/edit/custom_field_options', $id ); ?>
    </div>