<?php

class JC_BaseMapper {

	protected $_template = array();

	protected $_group_process_order = array();
	protected $_key = array();
	protected $_relationship = array();
	protected $_field_types = array();
	protected $_unique = array();
	protected $_setup = false;

	protected $_insert = array();
	protected $_current_row = 0;
	protected $_mappers = array();

	protected $_data = false;

	private $attachment_class = false;

	function __construct(){

		add_action( 'jci/import_finished', array( $this, 'on_import_complete' ) , 10 , 2 );
		add_filter( 'jci/import_removal_check' , array( $this, 'get_objects_for_removal' ), 10 , 2 );
	}

	/**
	 * Remove objects which arn't being import
	 * 
	 * @param  int $importer_id 
	 * @return void
	 */
	public function on_import_complete( $importer_id , $is_ajax ) {

		if($is_ajax)
			return false;

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$permissions = $jcimporter->importer->get_permissions();

		if( $permissions['delete'] == 0 )
			return;

		$groups = $jcimporter->importer->get_template_groups();
		$version = $jcimporter->importer->get_version();
		$template = $jcimporter->importer->get_template();


		// setup if not already
		$this->setup($template);

		// loop through all groups
		foreach($groups as $group => $args) {

			// get importer
			$importer = $this->_mappers[ $args['import_type'] ];

			// if mapper has remove function
			if( method_exists( $importer, 'remove_all_objects' ) ){
				$importer->remove_all_objects( $importer_id, $version, $args['import_type_name'] );
			}
		}
	}

	function get_objects_for_removal( $objects = array() , $importer_id ){

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$permissions = $jcimporter->importer->get_permissions();

		if( $permissions['delete'] == 0 )
			return;

		$groups = $jcimporter->importer->get_template_groups();
		$version = $jcimporter->importer->get_version();
		$template = $jcimporter->importer->get_template();

		// setup if not already
		$this->setup($template);

		// loop through all groups
		foreach($groups as $group => $args) {

			// get importer
			$importer = $this->_mappers[ $args['import_type'] ];

			// if mapper has remove function
			if( method_exists( $importer, 'get_objects_for_removal' ) ){
				$result = $importer->get_objects_for_removal( $importer_id , $version , $args['import_type_name'] );
				if( is_array( $result ) && !empty( $result ) ){
					$objects = array_merge( $objects , $result );
				}
			}
		}

		$remove_complete = 1;
		if(count($objects) > 0){
			$remove_complete = 0;
		}

		$old_version = get_post_meta( $importer_id, '_jci_remove_complete_' . $version, true );
		if ( $old_version !== false ) {
			update_post_meta( $importer_id, '_jci_remove_complete_' . $version, $remove_complete, $old_version );
		} else {
			add_post_meta( $importer_id, '_jci_remove_complete_' . $version, $remove_complete);
		}

		return array_unique( $objects );
	}

	function remove_single_object( $importer_id ){

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$permissions = $jcimporter->importer->get_permissions();

		if( $permissions['delete'] == 0 )
			return;

		$groups = $jcimporter->importer->get_template_groups();
		$version = $jcimporter->importer->get_version();
		$template = $jcimporter->importer->get_template();
		$result = false;

		// setup if not already
		$this->setup($template);

		// loop through all groups
		foreach($groups as $group => $args) {

			// get importer
			$importer = $this->_mappers[ $args['import_type'] ];

			// if mapper has remove function
			if( method_exists( $importer, 'remove_single_object' ) ){
				$result[] = $importer->remove_single_object( $importer_id , $version , $args['import_type_name'] );
			}
		}

		$this->get_objects_for_removal( array(), $importer_id );

		return $result;
	}

	/**
	 * Setup template for processing import
	 * 
	 * @param  array  $template 
	 * @return void
	 */
	function setup($template = array()){

		// removed due to problem clearing mapper
		// check to see if already setup
		// if($this->_setup)
		// 	return;

		// set template
		$this->_template = $template;

		foreach ( $template->get_groups() as $template_data ) {

			// get keys
			$this->parse_keys( $template_data );

			// get relationships
			$this->parse_relationship( $template_data );

			// get field type
			$this->parse_field_type( $template_data );

			// get unique field
			$this->parse_unique_field( $template_data );

			// load importers
			$this->load_mapper( $template_data );
		}

		// $this->setGroupProcessOrder();
		$this->_group_process_order = $this->set_group_process_order( $template->get_groups() );
		$this->_setup = true;
	}

	final function process( $template = array(), $data = array(), $row = null ) {
		
		$this->setup($template);
		$is_ajax = true;

		if(is_null($row))
			$is_ajax = false;

		if ( $row ) {
			// @quickfix: set current row to selected row
			$this->_current_row = ( $row - 1 );
		}

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$importer_id = $jcimporter->importer->get_ID();

		foreach ( $data as $data_row ) {

			$this->set_import_version();
			$this->processRow( $data_row );
			ImportLog::insert( $importer_id, $this->_current_row, $this->_insert[ $this->_current_row ] );
		}

		// check to see if last row
		$this->complete_check( $this->_current_row, $is_ajax);

		return $this->_insert;
	}

	/**
	 * Check to see if the current row is the last
	 *
	 * @param  integer $row
	 * @return void
	 */
	function complete_check( $row = 0 , $is_ajax = true ) {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$importer_id = $jcimporter->importer->get_ID();
		$start_row   = $jcimporter->importer->get_start_line(); // row to start from
		$row_count   = $jcimporter->importer->get_row_count(); // how many rows to import
		$total_rows  = $jcimporter->importer->get_total_rows();

		// check to see if complete
		if ( $row_count == 0 ) {

			if ( $row == $total_rows ) {
				do_action( 'jci/import_finished', $importer_id, $is_ajax );
			}
		} else {

			if ( ( $start_row - 1 ) + $row_count == $row ) {
				do_action( 'jci/import_finished', $importer_id, $is_ajax );
			}
		}
	}

	/**
	 * Update import version if current row equals the starting row
	 */
	function set_import_version() {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;

		$import_id = $jcimporter->importer->get_ID();
		$start_row = $jcimporter->importer->get_start_line();
		$version   = $jcimporter->importer->get_version();

		if ( $start_row <= 0 ) {
			$start_row = 1;
		}

		if ( $this->_current_row == ( $start_row - 1 ) ) {

			// increate import version
			$version ++;

			// update import version in db
			$old_version = get_post_meta( $import_id, '_import_version', true );
			if ( $old_version ) {
				update_post_meta( $import_id, '_import_version', $version, $old_version );
			} else {
				add_post_meta( $import_id, '_import_version', $version, true );
			}

			// update importer class
			$jcimporter->importer->set_version( $version );
		}
	}

	/**
	 * Set group process order
	 *
	 * Loop through group keys, and foreign keys to find which order the groups can be processed
	 *
	 * @param array $field_groups
	 */
	public function set_group_process_order( $field_groups = array() ) {

		$processed_order   = array();
		$unprocessed_order = array();
		$order             = array(); // store final order

		// if one group, no need to order
		if ( count( $field_groups ) == 1 ) {
			$template = array_shift( $field_groups );

			return array( $template['group'] );
		}

		// get unrestrained groups
		foreach ( $field_groups as $template ) {
			if ( empty( $template['relationship'] ) ) {

				foreach ( $template['key'] as $key ) {
					$processed_order[ $template['group'] . '.' . $key ] = $template['group'];
				}

				if ( ! in_array( $template['group'], $order ) ) {
					$order[] = $template['group'];
				}
			} else {
				$unprocessed_order[] = $template;
			}
		}

		foreach ( $unprocessed_order as $template ) {
			foreach ( $template['relationship'] as $target ) {

				$target = str_replace( array( "{", "}" ), "", $target );
				if ( array_key_exists( $target, $processed_order ) ) {
					foreach ( $template['key'] as $key ) {
						$processed_order[ $template['group'] . '.' . $key ] = $template['group'];
					}

					if ( ! in_array( $template['group'], $order ) ) {
						$order[] = $template['group'];
					}
				}
			}
		}

		return $order;
	}

	/**
	 * Set Keys
	 */
	final function parse_keys( $data ) {
		if ( ! isset( $data['key'] ) ) {
			return;
		}

		foreach ( $data['key'] as $key ) {

			if ( isset( $this->_key[ $data['group'] ] ) ) {
				$this->_key[ $data['group'] ][] = $key;
			}
		}
	}

	/**
	 * Set Relationships
	 */
	final function parse_relationship( $data ) {
		$this->_relationship[ $data['group'] ] = $data['relationship'];
	}

	/**
	 * Set Field Type
	 */
	final function parse_field_type( $data ) {
		$this->_field_types[ $data['group'] ] = $data['field_type'];
	}

	/**
	 * Set Unique Field
	 */
	final function parse_unique_field( $data ) {

		// set unique via template (new templates)
		if ( isset( $data['unique'] ) ) {
			$this->_unique[ $data['group'] ] = $data['unique'];
		}

		// get from indevidual field (old templates)
		if ( isset( $data['map'] ) ) {
			foreach ( $data['map'] as $field_data ) {

				if ( array_key_exists( 'unique', $field_data ) ) {
					$this->_unique[ $data['group'] ][] = $field_data['field'];
				}
			}
		}
		// todo: allow for unique keys not to be present
		$this->_unique[ $data['group'] ] = array_unique( $this->_unique[ $data['group'] ] );
	}

	/**
	 * Process Data
	 */
	final function processData( $matches ) {

		$match = $matches[1];

		$temp = explode( '.', $match );

		if ( count( $temp ) < 2 ) {
			return false;
		}

		$group = $temp[0];
		$field = $temp[1];

		if ( $group == 'this' ) {
			return $this->_current_group[ $field ];
		} else {
			return $this->_insert[ $this->_current_row ][ $group ][ $field ];
		}
	}

	/**
	 * Process Field
	 */
	final function processField( $field ) {

		$field = preg_replace_callback( '/{(.*?)}/', array( $this, 'processData' ), $field );

		return $field;
	}

	/**
	 * Process Group
	 */
	final function processGroup( $group_id, $data ) {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$permissions = $jcimporter->importer->permissions;

		if ( ! is_array( $data ) ) {
			return false;
		}

		try {

			// get import type: post | table | user
			$import_template_groups = $this->_template->get_groups();
			$import_type = $import_template_groups[ $group_id ]['import_type'];

			$mapper = $this->_mappers[ $import_type ];

			// merge relational fields
			if ( isset( $this->_relationship[ $group_id ] ) && is_array( $this->_relationship[ $group_id ] ) ) {
				$data = array_merge( $data, $this->_relationship[ $group_id ] );
			}

			$this->_current_group = $data;

			foreach ( $data as $id => $field ) {

				// process field
				$data[ $id ] = $this->processField( $field );
				$data[ $id ] = apply_filters( 'jci/' . $this->_template->get_name() . '/process_field', $data[ $id ], $id);
			}

			$data = apply_filters( 'jci/before_' . $this->_template->get_name() . '_group_save', $data, $group_id );

			if ( ! $post_id = $mapper->exists( $group_id, $data ) ) {

				// create if allowed
				if ( isset( $permissions['create'] ) && $permissions['create'] == 1 ) {
					$result = $mapper->insert( $group_id, $data );
					if ( ! is_wp_error( $result ) ) {
						$data['ID']        = $result;
						$data['_jci_type'] = 'I';
					} else {
						throw new JCI_Exception( $result->get_error_message(), JCI_ERR );
					}

					// $this->_insert[$this->_current_row]['type'] = 'I';
				} else {
					throw new JCI_Exception( "No Enough Permissions to Insert Record", JCI_ERR );
				}
			} else {

				// update if allowed
				if ( isset( $permissions['update'] ) && $permissions['update'] == 1 ) {
					$data['ID']        = $mapper->update( $post_id, $group_id, $data );
					$data['_jci_type'] = 'U';
					// $this->_insert[$this->_current_row]['type'] = 'U';
				} else {
					$data['ID'] = $post_id;
					throw new JCI_Exception( "No Enough Permissions to Update Record", JCI_ERR );
				}
			}

			$data['_jci_updated_fields'] = $mapper->changed_fields;
			$data['_jci_updated']        = $mapper->changed_field_count;

			$data = apply_filters( 'jci/after_' . $this->_template->get_name() . '_group_save', $data, $group_id );

			$data['_jci_status'] = 'S';
			$data['_jci_msg']    = '';
		} catch ( JCI_Exception $e ) {

			$data['_jci_status'] = 'E';
			$data['_jci_msg']    = jci_error_message($e);

			// throw errors not warnings to row
			if ( $e->getCode() == JCI_ERR ) {
				throw $e;
			}


		} catch ( Exception $e ) {

			// catch group errors
			$data['_jci_status'] = 'E';
			$data['_jci_msg']    = jci_error_message($e);

			throw $e;
		}

		return $data;
	}

	/**
	 * Process Row
	 */
	final function processRow( $data ) {

		$this->_current_row ++;

		try {

			do_action( 'jci/before_' . $this->_template->get_name() . '_row_save', $data, $this->_current_row );

			foreach ( $this->_group_process_order as $group_id ) {

				$group = $data[ $group_id ];

				switch ( $this->_field_types[ $group_id ] ) {
					case 'single':
						$data[ $group_id ]                                 = $this->processGroup( $group_id, $group );
						$this->_insert[ $this->_current_row ][ $group_id ] = $data[ $group_id ];
						break;
					case 'repeater':
						// @todo: repeater field data
						$data[ $group_id ] = array();
						foreach ( $group as $group_data ) {
							$temp                                                = $this->processGroup( $group_id, $group_data );
							$this->_insert[ $this->_current_row ][ $group_id ][] = $temp; // $this->processGroup($group_id, $group_data);
							$data[ $group_id ][]                                 = $temp;
						}
						break;
				}
			}

			$this->processAttachments( $data );

			$this->processTaxonomies( $data );

			do_action( 'jci/after_' . $this->_template->get_name() . '_row_save', $data, $this->_current_row );

			$this->_insert[ $this->_current_row ]['_jci_status'] = 'S';
			$this->_insert[ $this->_current_row ]['_jci_msg']    = '';
		} catch ( JCI_Exception $e ) {

			// catch record errors
			$this->_insert[ $this->_current_row ]['_jci_status'] = 'E';
			$this->_insert[ $this->_current_row ]['_jci_msg']    = jci_error_message($e);


		} catch ( Exception $e ) {

			// catch record errors
			$this->_insert[ $this->_current_row ]['_jci_status'] = 'E';
			$this->_insert[ $this->_current_row ]['_jci_msg']    = jci_error_message($e);
		}
	}

	final function processTaxonomies( $data ) {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$jci_taxonomies             = $jcimporter->importer->taxonomies;
		$jci_taxonomies_permissions = $jcimporter->importer->taxonomies_permissions;
		$jci_template_type          = $jcimporter->importer->template_type;
		$jci_file                   = $jcimporter->importer->file;

		if ( ! isset( $jci_taxonomies ) || ! $jci_taxonomies || empty( $jci_taxonomies ) ) {
			return false;
		}

		if(!isset($this->_insert[ $this->_current_row ]))
			return;

		$row = $this->_insert[ $this->_current_row ];

		foreach ( $jci_taxonomies as $group_id => $taxonomies ) {

			$import_template_groups = $this->_template->get_groups();
			if ( isset( $import_template_groups[ $group_id ]['taxonomies'] ) && $import_template_groups[ $group_id ]['taxonomies'] <> 1 ) {
				continue;
			}

			if(count($jci_taxonomies[$group_id]) === 1 && empty($taxonomies)){
				continue;
			}

			$post_id = $row[ $group_id ]['ID'];

			foreach ( $taxonomies as $tax => $term_arr ) {

				// permission check (create, append, overwrite)
				$permission = $jci_taxonomies_permissions[ $group_id ][ $tax ];

				if ( $permission == 'create' ) {

					$existing_terms = wp_get_object_terms( $post_id, $tax );

					if ( ! empty( $existing_terms ) ) {
						continue;
					}
				}

				// clear categories
				if ( $permission == 'overwrite' ) {
					wp_set_object_terms( $post_id, null, $tax );
				}

				foreach ( $term_arr as $term_value ) {

					//@TODO: Add xml to filter
					if ( $jci_template_type == 'xml' ) {

						$field_map = apply_filters( 'jci/process_' . $jci_template_type . '_map_field', $group_id, $this->_current_row );

						$xml   = simplexml_load_file( $jci_file );
						$terms = apply_filters( 'jci/parse_' . $jci_template_type . '_field', $term_value, $field_map, $xml );
					} else {
						$field_map = apply_filters( 'jci/process_' . $jci_template_type . '_map_field', $group_id, $this->_current_row );
						$terms     = apply_filters( 'jci/parse_' . $jci_template_type . '_field', $term_value, $field_map, '' );
					}

					$terms = explode( ',', $terms );

					foreach ( $terms as $t ) {

						$t = trim( $t );

						if ( empty( $t ) ) {
							continue;
						}

						// skip if already has term
						if ( $permission == 'append' && has_term( $t, $tax, $post_id ) ) {
							continue;
						}

						$this->_insert[ $this->_current_row ]['taxonomies'][ $tax ][] = $t;

						if ( term_exists( $t, $tax ) ) {

							// attach term to post
							wp_set_object_terms( $post_id, $t, $tax, true );
						} else {

							// add term
							$term_id = wp_insert_term( $t, $tax );
							wp_set_object_terms( $post_id, $term_id, $tax, true );
						}
					}
				}
			}
		}
	}

	final function processAttachments( $data ) {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$jci_attachments   = $jcimporter->importer->attachments;
		$jci_template_type = $jcimporter->importer->template_type;
		$jci_file          = $jcimporter->importer->file;

		if ( ! isset( $jci_attachments ) || ! $jci_attachments || empty( $jci_attachments ) ) {
			return false;
		}

		if(!isset($this->_insert[ $this->_current_row ]))
			return;

		$row = $this->_insert[ $this->_current_row ];

		foreach ( $jci_attachments as $group_id => $attachments ) {

			$import_template_groups = $this->_template->get_groups();
			if ( isset( $import_template_groups[ $group_id ]['attachments'] ) && $import_template_groups[ $group_id ]['attachments'] <> 1 ) {
				continue;
			}

			// escape if not attachment location has been entered
			if(empty($attachments['location']) || (count($attachments['location']) === 1 && empty($attachments['location'][0]))){
				continue;
			}

			// @todo get id from group not hard coded
			$post_id = $row[ $group_id ]['ID'];

			// escape from loop if only allowed one image and already exists
			// if($has_image && has_post_thumbnail( $post_id ))
			// 	continue;

			// loop through all attachment fields
			foreach ( $jci_attachments[ $group_id ]['location'] as $key => $src ) {

				// permission check (create, append, overwrite)
				$permission = $jci_attachments[ $group_id ]['permissions'][ $key ];

				//todo: make sure is always set in $jcimporter->importer
				$featured = isset( $jci_attachments[ $group_id ]['featured_image'][ $key ] ) ? $jci_attachments[ $group_id ]['featured_image'][ $key ] : 0;
				if ( $featured == 1 ) {
					$feature = true;
				} else {
					$feature = false;
				}

				// escape if already has attachment
				$has_image = has_post_thumbnail( $post_id );
				if ( $permission == 'create' && $has_image ) {
					continue;
				}

				set_error_handler( array( $this, 'handleError' ) );
				try {

					// unable to connect
					if ( ! $this->initAttachment( $group_id ) ) {
						throw new JCI_Exception( "Could Not Connect", JCI_ERR );

						return false;
					}

					// parse fields	
					//@TODO: Add xml to filter
					if ( $jci_template_type == 'xml' ) {

						// parse fields
						$field_map = apply_filters( 'jci/process_' . $jci_template_type . '_map_field', $group_id, $this->_current_row );

						$xml = simplexml_load_file( $jci_file );
						$src = apply_filters( 'jci/parse_' . $jci_template_type . '_field', $src, $field_map, $xml );
					} else {
						$field_map = apply_filters( 'jci/process_' . $jci_template_type . '_map_field', $group_id, $this->_current_row );
						$src       = apply_filters( 'jci/parse_' . $jci_template_type . '_field', $src, $field_map );
					}

					$dest = basename( $src );

					$dest = apply_filters( 'jci/attachment_name', $dest, $key );

					// download and install attachments
					$result = $this->attachment_class->attach_remote_image( $post_id, $src, $dest, array( 'unique'  => true,
					                                                                                      'parent'  => $post_id,
					                                                                                      'feature' => $feature
						) );
					if ( $result ) {

						$this->_insert[ $this->_current_row ]['attachments'][] = array(
							'status' => 'S',
							'msg'    => $dest
						);
					} else {
						$this->_insert[ $this->_current_row ]['attachments'][] = array(
							'status' => 'E',
							'msg'    => $this->attachment_class->get_error()
						);
					}
				} catch ( Exception $e ) {
					$this->_insert[ $this->_current_row ]['attachments'][] = array(
						'status' => 'E',
						'msg'    => jci_error_message($e)
					);
				}

				restore_error_handler();

			}
		}
	}

	function handleError( $errno, $errstr, $errfile, $errline, $errcontext = array() ) {
		// error was suppressed with the @-operator
		if ( 0 === error_reporting() ) {
			return false;
		}

		throw new ErrorException( $errstr, 0, $errno, $errfile, $errline );
	}

	final function initAttachment( $group_id ) {

		if ( $this->attachment_class ) {
			return true;
		}

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$jci_attachments = $jcimporter->importer->attachments;

		switch ( $jci_attachments[ $group_id ]['type'] ) {
			case 'url':

				// setup curl
				$this->attachment_class = new JCI_CURL_Attachments();

				return true;
				break;
			case 'ftp':

				// setup ftp connection
				$server = $jci_attachments[ $group_id ]['ftp']['server'];
				$user   = $jci_attachments[ $group_id ]['ftp']['user'];
				$pass   = $jci_attachments[ $group_id ]['ftp']['pass'];

				// exit if no sever
				if ( empty( $server ) ) {
					return false;
				}

				$this->attachment_class = new JCI_FTP_Attachments( $server, $user, $pass );
				if ( $this->attachment_class->is_connected() ) {
					return true;
				}

				return false;
				break;
			default:
				return false;
				break;
		}
	}

	/**
	 * Load Mapper
	 *
	 * Load group mapper (POST, TABLE, USER, VIRTUAL)
	 *
	 * @param  array $data
	 *
	 * @return void
	 */
	final function load_mapper( $data ) {

		$type      = $data['import_type'];
		$mappers   = array();

		// load mappers
		$mappers = apply_filters( 'jci/register_importer', $mappers );

		if ( ! array_key_exists( $type, $mappers ) ) {
			return false;
		}

		// load importer
		$mapper                  = $mappers[ $type ];
		$this->_mappers[ $type ] = new $mapper( $this->_template, $this->_unique );
	}
}