<?php
/**
 * Importer Permissions
 *
 * Add Visual interface to include and exclude fields when importing.
 *
 * @package ImportWP/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class IWP_Importer_Permissions
 */
class IWP_Importer_Permissions {

	/**
	 * IWP_Importer_Permissions constructor.
	 *
	 * Register hooks and filters
	 */
	public function __construct() {

		add_action( 'iwp/importer_permissions/create', array( $this, 'display_create_fields' ) );
		add_action( 'iwp/importer_permissions/update', array( $this, 'display_update_fields' ) );

		add_action( 'jci/save_template', array( $this, 'save_permissions' ), 10 );
		add_filter( 'iwp/import_mapper_permissions', array( $this, 'apply_permissions' ), 10, 2 );
	}

	/**
	 * Display Create Permission fields on Importer Edit Screen
	 */
	public function display_create_fields() {
		$this->display_fields( 'create' );
	}

	/**
	 * Display Update Permission fields on Importer Edit Screen
	 */
	public function display_update_fields() {
		$this->display_fields( 'update' );
	}

	/**
	 * Display permission fields
	 *
	 * @param string $type Permission type (create, update, delete)
	 */
	public function display_fields( $type ) {

		$id = JCI()->importer->get_ID();

		$permissions     = IWP_Importer_Settings::getImporterMetaArr( $id, 'field_permissions' );
		$permission_type = isset( $permissions["{$type}_type"] ) ? $permissions["{$type}_type"] : '';
		$fields          = isset( $permissions["{$type}_fields"] ) ? $permissions["{$type}_fields"] : array();

		echo '<div class="iwp-field-toggle-wrapper">';

		echo IWP_FormBuilder::select( "field_permissions_type[{$type}]", array(
			'label'   => 'Filter Fields',
			'class'   => 'iwp-field-toggle-trigger',
			'default' => $permission_type,
			'options' => array(
				''        => 'All Fields',
				'include' => 'Include Fields',
				'exclude' => 'Exclude Fields'
			)
		) );

		echo '<div class="iwp-field-toggle-show--include iwp-field-toggle-show--exclude">';
		echo IWP_FormBuilder::textarea( "field_permissions[{$type}]", array(
			'label'   => 'Fields',
			'default' => implode( "\n", $fields ),
			'tooltip' => 'Enter each field name on a new line, use * to match field names. E.g. "field_name", starts with "field_*", ends with "*_field", or match all "*"'
		) );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Save permission field for importer
	 *
	 * @param int $id Importer Id
	 */
	public function save_permissions( $id ) {

		if ( ! isset( $_POST['jc-importer_field_permissions_type'], $_POST['jc-importer_field_permissions'] ) ) {
			return;
		}

		$type   = $_POST['jc-importer_field_permissions_type'];
		$fields = $_POST['jc-importer_field_permissions'];

		$create_fields = isset( $fields['create'] ) && ! empty( $fields['create'] ) ? explode( "\n", $fields['create'] ) : array();
		$update_fields = isset( $fields['update'] ) && ! empty( $fields['update'] ) ? explode( "\n", $fields['update'] ) : array();

		$create_fields = array_map( 'trim', $create_fields );
		$update_fields = array_map( 'trim', $update_fields );

		$create_fields = array_filter( $create_fields );
		$update_fields = array_filter( $update_fields );

		$result = array(
			'create_type'   => $type['create'],
			'create_fields' => $create_fields,
			'update_type'   => $type['update'],
			'update_fields' => $update_fields,
		);

		IWP_Importer_Settings::setImporterMeta( $id, 'field_permissions', $result );

	}

	/**
	 * Filter permissions based on saved settings
	 *
	 * @param array $fields List of field_id => field_value
	 * @param string $method
	 *
	 * @return array
	 */
	public function apply_permissions( $fields, $method ) {

		$importer_id = JCI()->importer->get_ID();

		if ( 'insert' === $method ) {
			$method = 'create';
		}

		switch ( $method ) {
			case 'create':
			case 'update':

				$permissions       = IWP_Importer_Settings::getImporterMetaArr( $importer_id, 'field_permissions' );
				$permission_type   = isset( $permissions["{$method}_type"] ) ? $permissions["{$method}_type"] : '';
				$permission_fields = isset( $permissions["{$method}_fields"] ) ? $permissions["{$method}_fields"] : array();

				$matches = array();
				foreach ( $permission_fields as $field_search ) {
					$matches = array_merge( $matches, $this->match_permissions( $field_search, $fields ) );
				}

				if ( 'include' === $permission_type ) {

					return $matches;

				} elseif ( 'exclude' === $permission_type ) {

					$result = array();
					foreach ( $fields as $field_id => $field_value ) {
						if ( ! isset( $matches[ $field_id ] ) ) {
							$result[ $field_id ] = $field_value;
						}
					}

					return $result;
				}

				break;
		}

		return $fields;
	}

	/**
	 * Match search string against field list
	 *
	 * @param string $field_search
	 * @param array $fields List of field_id => field_value
	 *
	 * @return array
	 */
	public function match_permissions( $field_search, $fields ) {

		$result = array();

		// replaces * with the regex pattern
		$pattern = '[a-zA-Z\d_-]+';

		if ( '*' === $field_search ) {

			// *
			return $fields;

		} elseif ( 1 === preg_match( "/^\*{$pattern}/i", $field_search ) ) {

			$search = substr( $field_search, 1 );

			// *_src
			foreach ( $fields as $field_id => $field_value ) {
				if ( 1 === preg_match( "/^{$pattern}{$search}$/i", $field_id ) ) {
					$result[ $field_id ] = $fields[ $field_id ];
				}
			}

		} elseif ( 1 === preg_match( "/{$pattern}\*$/i", $field_search ) ) {

			$search = substr( $field_search, 0, - 1 );

			// attachment_*
			foreach ( $fields as $field_id => $field_value ) {
				if ( 1 === preg_match( "/^{$search}{$pattern}$/i", $field_id ) ) {
					$result[ $field_id ] = $fields[ $field_id ];
				}
			}

		} else {

			if ( ! isset( $fields[ $field_search ] ) ) {
				return $result;
			}

			$result[ $field_search ] = $fields[ $field_search ];
		}

		return $result;
	}

}

new IWP_Importer_Permissions();