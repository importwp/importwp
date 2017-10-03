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
							$links[] = '<span class="edit"><a href="' . admin_url( sprintf( 'admin.php?page=jci-importers&import=%d&action=logs', $item->ID ) ) . '" aria-label="Run">Run</a></span>';
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
							echo sprintf( '<td>%s</td>', 'N/A' );
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