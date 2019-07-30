<?php
/**
 * @global JC_Importer $jcimporter
 */
global $jcimporter;

// load settings from gloabl
$importer_id   = $jcimporter->importer->get_ID();
$template_name = $jcimporter->importer->get_template_name();
$name          = $jcimporter->importer->get_name();

$columns = apply_filters( "jci/log_{$template_name}_columns", array() );
?>

<div id="icon-tools" class="icon32"><br></div>
<h2 class="nav-tab-wrapper">
    <a href="admin.php?page=jci-importers&import=<?php echo $id; ?>&action=edit"
       class="nav-tab tab"><?php echo $name; ?></a>
    <a href="admin.php?page=jci-importers&import=<?php echo $id; ?>&action=history" class="nav-tab nav-tab-active tab">History</a>
</h2>

<div id="ajaxResponse"></div>


<div id="poststuff">
    <div id="post-body" class="metabox-holder columns-2">


        <div id="post-body-content">

			<?php
			$log = isset( $_GET['log'] ) && intval( $_GET['log'] ) > 0 ? intval( $_GET['log'] ) : false;
			?>
			<?php if ( ! $log ): ?>

				<?php
				$rows = IWP_Importer_Log::get_importer_logs( $importer_id );
				?>

                <div id="postbox-container-2" class="postbox-container">

                    <h1>Import History</h1>

                    <p>Click on a record below to view</p>

                    <div id="jci-table-wrapper">
                        <table class="wp-list-table widefat fixed posts" cellspacing="0">
                            <thead>
                            <tr>
                                <th scope="col" id="author" class="manage-column column-author" style="width:30px;">ID
                                </th>
                                <th>Rows</th>
                                <th>Date</th>
                                <th>_</th>
                            </tr>
                            </thead>
                            <tbody>
							<?php if ( $rows ): ?>
								<?php foreach ( $rows as $row ): ?>
                                    <tr>
                                        <td><?php echo esc_html($row->version); ?></td>
                                        <td><?php echo esc_html($row->row_total); ?></td>
                                        <td><?php echo date( 'H:i:s d/m/y', strtotime( $row->created ) ); ?></td>
                                        <td>
                                            <a href="admin.php?page=jci-importers&import=<?php echo $importer_id; ?>&log=<?php echo $row->version; ?>&action=history">View</a>
                                        </td>
                                    </tr>
								<?php endforeach; ?>
							<?php else: ?>
                                <tr>
                                    <td colspan="4">No Records have been imported</td>
                                </tr>
							<?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>

			<?php else: ?>

                <div id="postbox-container-2" class="postbox-container">

                    <h1>Import #<?php echo $log; ?> History</h1>

                    <p><a href="admin.php?page=jci-importers&import=<?php echo $importer_id; ?>&action=history">&larr;
                            Back to importer history</a></p>

					<?php
					$page     = isset( $_GET['iwp_page'] ) && intval( $_GET['iwp_page'] ) > 0 ? intval( $_GET['iwp_page'] ) : 1;
					$total    = IWP_Importer_Log::get_importer_log_count( $importer_id, $log );
					$per_page = 100;
					$rows     = IWP_Importer_Log::get_importer_log( $importer_id, $log, 'ASC', $per_page, $page );
					?>

					<?php iwp_output_pagination( $page, $total, $per_page ); ?>

                    <div id="jci-table-wrapper">
                        <table class="wp-list-table widefat fixed posts" cellspacing="0">
                            <thead>
                            <tr>
                                <th scope="col" id="author" class="manage-column column-author" style="width:30px;">ID
                                </th>
								<?php foreach ( $columns as $key => $col ): ?>
                                    <th scope="col" id="<?php echo esc_attr($key); ?>"
                                        class="manage-column column-<?php echo esc_attr($key); ?>"
                                        style=""><?php echo esc_html($col); ?></th>
								<?php endforeach; ?>
                            </tr>
                            </thead>
                            <tbody>
							<?php if ( $rows ): ?>
								<?php foreach ( $rows as $r ): ?>
									<?php
									$row  = $r->row;
									$data = array( unserialize( $r->value ) );
									require $jcimporter->get_plugin_dir() . 'resources/views/imports/log/log_table_record.php';
									?>
								<?php endforeach; ?>
							<?php else: ?>
							<?php endif; ?>
                            </tbody>
                        </table>
                    </div>
					<?php iwp_output_pagination( $page, $total, $per_page ); ?>

                </div>

			<?php endif; ?>

        </div>

        <div id="postbox-container-1" class="postbox-container">

			<?php include $this->config->get_plugin_dir() . '/resources/views/elements/about_block.php'; ?>

        </div>
        <!-- /postbox-container-1 -->

    </div>
</div>