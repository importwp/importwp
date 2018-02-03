<?php
/**
 * Created by PhpStorm.
 * User: jamescollings
 * Date: 03/10/2017
 * Time: 08:01
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class IWP_Imports_List_Table extends WP_List_Table {

	protected $_last_ran;

	public function __construct() {
		parent::__construct( array(
			'singular' => 'iwp_import_list',
			'plural'   => 'iwp_import_lists',
			'ajax'     => false,
			'screen'   => 'iwp_imports',
		) );
	}

	/**
	 * Prepares the list of items for displaying.
	 */
	public function prepare_items() {

		global $_wp_column_headers;

		$screen    = get_current_screen();
		$importers = ImporterModel::getImporters();

		$this->set_pagination_args( array(
			'total_items' => $importers->found_posts,
			'total_pages' => $importers->max_num_pages,
			'per_page'    => $importers->found_posts,
		) );

		$columns                           = $this->get_columns();
		$_wp_column_headers[ $screen->id ] = $columns;
		$this->items                       = $importers->posts;

		// TODO: Fetch last ran times in model not here!
		global $wpdb;
		$res             = $wpdb->get_results( "SELECT object_id as ID, MAX(created) as created FROM `" . $wpdb->prefix . "importer_log` GROUP BY object_id ORDER BY created DESC" );
		$this->_last_ran = array();
		foreach ( $res as $obj ) {
			$this->_last_ran[ $obj->ID ] = $obj->created;
		}
	}

	/**
	 * Get a list of columns. The format is:
	 * 'internal-name' => 'Title'
	 *
	 * @return array
	 */
	public function get_columns() {

		return $columns = array(
			'col_import_name'     => __( 'Name', 'iwp' ),
			'col_import_template' => __( 'Template', 'iwp' ),
			'col_import_type'     => __( 'Type', 'iwp' ),
			'col_import_last_ran' => __( 'Last Ran', 'iwp' ),
			'col_import_created'  => __( 'Created', 'iwp' ),
		);

	}

	/**
	 * Generate the table rows
	 */
	public function display_rows() {

		list( $columns, $hidden ) = $this->get_column_info();

		if ( empty( $this->items ) ) {
			echo '<tr>';
			echo '<td colspan="' . count( $columns ) . '"><p>To start an import <a
                                        href="' . admin_url( 'admin.php?page=jci-importers&action=add' ) . '">click
                                    here</a> , click on add new at the top of the page.</p></td>';
			echo '</tr>';
		} else {

			foreach ( $this->items as $item ) :

				echo '<tr>';

				foreach ( $columns as $column_name => $column_display_name ) :

					switch ( $column_name ) :
						case 'col_import_name':

							$link = admin_url( sprintf( 'admin.php?page=jci-importers&import=%d&action=edit', $item->ID ) );

							$links   = array();
							$links[] = '<span class="edit"><a href="' . $link . '" aria-label="View">Edit</a></span>';

							$run_url = admin_url(sprintf('admin.php?page=jci-importers&import=%d&action=logs', $item->ID ));
							if( in_array( ImporterModel::getImportSettings($item->ID, 'import_type'), array('local', 'remote') ) ){
								$run_url = admin_url(sprintf('admin.php?page=jci-importers&import=%d&action=fetch', $item->ID) );
							}else{
								$importer = new JC_Importer_Core($item->ID);
								$status = IWP_Status::read_file($importer->get_ID(), $importer->get_version());
								if($status['status'] === 'complete'){
									$run_url = admin_url(sprintf('admin.php?page=jci-importers&import=%d&action=start', $item->ID ));
								}
							}

							$links[] = '<span class="edit"><a href="' . $run_url . '" aria-label="Run">Run</a></span>';
							$links[] = '<span class="edit"><a href="' . admin_url( sprintf( 'admin.php?page=jci-importers&import=%s&action=history', $item->ID ) ) . '" aria-label="History">History</a></span>';
							$links[] = '<span class="delete"><a href="' . admin_url( sprintf( 'admin.php?page=jci-importers&import=%s&action=trash', $item->ID ) ) . '" aria-label="Delete">Delete</a></span>';

							echo '<td>';
							echo '<strong><a href="' . $link . '">' . get_the_title( $item->ID ) . '</a></strong>';
							if ( ! empty( $links ) ) {
								echo '<div class="row-actions">' . implode( ' | ', $links ) . '</div>';
							}
							echo '</td>';
							break;
						case 'col_import_template':
							echo sprintf( '<td>%s</td>', ImporterModel::getImportSettings( $item->ID, 'template' ) );
							break;
						case 'col_import_type':
							echo sprintf( '<td>%s</td>', ImporterModel::getImportSettings( $item->ID, 'template_type' ) );
							break;
						case 'col_import_last_ran':
							echo sprintf( '<td>%s</td>', isset( $this->_last_ran[ $item->ID ] ) ? date( 'H:i:s \<\b\r \/\> ' . get_option( 'date_format' ), strtotime( $this->_last_ran[ $item->ID ] ) ) : 'N/A' );
							break;
						case 'col_import_created':
							echo sprintf( '<td>%s</td>', get_the_date( '', $item->ID ) );
							break;
						default:
							echo '<td>&nbsp;</td>';
							break;
					endswitch;

				endforeach;

				echo '</tr>';

				// Reset importer data so next record is not loaded from cache
				ImporterModel::clearImportSettings();

			endforeach;
		}
	}

	protected function display_tablenav( $which ) {
		if ( 'top' === $which ) {
			return;
		}

		parent::display_tablenav( $which );
	}

}