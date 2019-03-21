<?php

/**
 * Get Importer Settings
 */
class IWP_Importer_Settings {

	/**
	 * Config Instance
	 * @var class
	 */
	static $config;

	/**
	 * Cached Settings
	 * @var boolean
	 */
	static $meta = false;

	static function init( &$config ) {
		self::$config = $config;
	}

	/**
	 * Get all importers
	 * @return WP_Query
	 */
	static function getImporters() {

		$query = new WP_Query( array(
			'post_type'      => 'jc-imports',
			'posts_per_page' => - 1
		) );

		return $query;
	}

	/**
	 * Get Importer by Id
	 *
	 * @param  integer $id
	 *
	 * @return WP_Query
	 */
	static function getImporter( $id = 0 ) {

		if ( $id > 0 ) {
			$query = new WP_Query( array(
				'post_type' => 'jc-imports',
				'p'         => $id,
			) );
		} else {
			$query = false;
		}

		return $query;

	}

	/**
	 * Set Import File
	 *
	 * @param int $id
	 * @param string $file
	 */
	static function setImportFile( $id, $file ) {

		if ( empty( $file ) ) {
			return false;
		}

		$old_value = get_post_meta( $id, '_import_settings', true );

		if ( empty( $file['type'] ) || $file['type'] != $old_value['template_type'] ) {
			return false;
		}

		$value                = $old_value;
		$value['import_file'] = $file['id'];

		if ( $value && '' == $old_value ) {
			add_post_meta( $id, '_import_settings', $value );
		} elseif ( $value && $value != $old_value ) {
			update_post_meta( $id, '_import_settings', $value );
		} elseif ( '' == $value && $old_value ) {
			delete_post_meta( $id, '_import_settings', $value );
		}
	}

	/**
	 * Set Importer Version
	 *
	 * @param int $id
	 * @param int $ver new version number
	 */
	static function setImportVersion( $id, $ver ) {

		$old_ver = get_post_meta( $id, '_import_version', true );
		update_post_meta( $id, '_import_version', $ver, $old_ver );
	}

	/**
	 * Add Importer
	 *
	 * @param  int $post_id
	 * @param  array $data
	 *
	 * @return int
	 */
	static function insertImporter( $post_id, $data = array() ) {

		$args = array(
			'post_title'     => $data['name'],
			'post_status'    => 'publish',
			'post_type'      => 'jc-imports',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		);
		$meta = array();

		if ( $post_id > 0 ) {
			$args['ID'] = $post_id;
		} else {
			$args['post_name'] = substr( md5( time() ), 0, rand( 5, 10 ) );
		}

		$meta['_import_settings'] = isset( $data['settings'] ) ? $data['settings'] : array();
		$meta['_mapped_fields']   = isset( $data['fields'] ) ? $data['fields'] : array();
		$meta['_setting_addons']  = isset( $data['setting_addons'] ) ? $data['setting_addons'] : array();
		$meta['_field_addons']    = isset( $data['field_addons'] ) ? $data['field_addons'] : array();
		$meta['_attachments']     = isset( $data['attachments'] ) ? $data['attachments'] : array();
		$meta['_taxonomies']      = isset( $data['taxonomies'] ) ? $data['taxonomies'] : array();

		$post_id = wp_insert_post( $args );

		foreach ( $meta as $key => $value ) {

			$old_value = get_post_meta( $post_id, $key, true );

			if ( $value && '' == $old_value ) {
				add_post_meta( $post_id, $key, $value );
			} elseif ( $value && $value != $old_value ) {
				update_post_meta( $post_id, $key, $value );
			} elseif ( '' == $value && $old_value ) {
				delete_post_meta( $post_id, $key, $value );
			}
		}

		return $post_id;
	}

	/**
	 * Clear cached importer
	 * @return void
	 */
	static function clearImportSettings() {
		self::$meta = false;
	}

	static function getImporterMetaArr( $post_id, $keys ) {

		if ( is_null( $keys ) || empty( $keys ) ) {
			return false;
		}

		if ( is_array( $keys ) ) {

			$key = array_shift( $keys );
			// todo: cache results
			$old_value = get_post_meta( $post_id, $key, true );

			$temp = $old_value;
			foreach ( $keys as $k ) {
				if ( isset( $temp[ $k ] ) ) {
					$temp = $temp[ $k ];
				} else {
					return '';
				}
			}

			return $temp;

		} elseif ( is_string( $keys ) ) {

			$key = $keys;

			// todo: cache results
			return get_post_meta( $post_id, $key, true );
		}

	}

	static function setImporterMeta( $post_id, $keys = null, $value = null ) {

		if ( is_null( $keys ) || is_null( $value ) ) {
			return false;
		}

		if ( is_array( $keys ) ) {

			//settings/test/test1 = test
			$key       = array_shift( $keys );
			$old_value = get_post_meta( $post_id, $key, true );

			$temp  = (array) $old_value;
			$value = self::get_key( $temp, $keys, $value );

		} elseif ( is_string( $keys ) ) {
			$key       = $keys;
			$old_value = get_post_meta( $post_id, $key, true );
		}

		if ( $value && '' == $old_value ) {
			add_post_meta( $post_id, $key, $value );
		} elseif ( $value && $value != $old_value ) {
			update_post_meta( $post_id, $key, $value );
		} elseif ( '' == $value && $old_value ) {
			delete_post_meta( $post_id, $key, $value );
		}
	}

	private static function get_key( &$arr, $keys = array(), $value = '', $counter = 0 ) {

		$key = $keys[ $counter ];
		$counter ++;
		if ( isset( $arr[ $key ] ) ) {

			// if keys exist
			if ( $counter == count( $keys ) ) {
				$arr[ $key ] = $value;
			} else {
				$arr[ $key ] = self::get_key( $arr[ $key ], $keys, $value, $counter );
			}
		} else {

			// create keys
			$arr[ $key ] = array();
			if ( $counter == count( $keys ) ) {
				$arr[ $key ] = $value;
			} else {
				$arr[ $key ] = self::get_key( $arr[ $key ], $keys, $value, $counter );
			}

		}

		return $arr;
	}

	static function update( $post_id, $data = array() ) {

		$meta['_mapped_fields']  = isset( $data['fields'] ) ? $data['fields'] : array();
		$meta['_attachments']    = isset( $data['attachments'] ) ? $data['attachments'] : array();
		$meta['_taxonomies']     = isset( $data['taxonomies'] ) ? $data['taxonomies'] : array();
		$meta['_field_addons']   = isset( $data['addon_fields'] ) ? $data['addon_fields'] : array();
		$meta['_setting_addons'] = isset( $data['addon_settings'] ) ? $data['addon_settings'] : array();

		$settings                           = get_post_meta( $post_id, '_import_settings', true );
		$settings['start_line']             = isset( $data['settings']['start_line'] ) ? $data['settings']['start_line'] : 1;
		$settings['row_count']              = isset( $data['settings']['row_count'] ) ? $data['settings']['row_count'] : 0;
		$settings['record_import_count']    = isset( $data['settings']['record_import_count'] ) ? $data['settings']['record_import_count'] : 10;
		$settings['template_unique_field']  = isset( $data['settings']['template_unique_field'] ) ? $data['settings']['template_unique_field'] : '';

		if ( isset( $data['settings']['template_type'] ) && in_array( $data['settings']['template_type'], array(
				'csv',
				'xml'
			) )
		) {
			$settings['template_type'] = $data['settings']['template_type'];
		}
		// $settings['template_type'] = isset($data['settings']['template_type']) ? $data['settings']['template_type'] : 0;

		if ( isset( $data['settings']['import_file'] ) && ! empty( $data['settings']['import_file'] ) ) {
			$settings['import_file'] = $data['settings']['import_file'];
		}

		$permissions = isset( $data['settings']['permissions'] ) ? $data['settings']['permissions'] : array();

		// permissions
		$permission_keys         = array( 'create', 'update', 'delete' );
		$settings['permissions'] = array();
		foreach ( $permission_keys as $key ) {
			if ( isset( $permissions[ $key ] ) ) {
				$settings['permissions'][ $key ] = $permissions[ $key ];
			} else {
				$settings['permissions'][ $key ] = 0;
			}
		}


		// validate row start/count
		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$template_type = IWP_Importer_Settings::getImportSettings( $post_id, 'template_type' );

		$importer_settings = IWP_Importer_Settings::getImportSettings( $post_id );

		// set specific import_type settings
		$settings = apply_filters( "jci/importer_save", $settings, $importer_settings['import_type'], $data );


		// TODO: remove tie to post datasource
		if ( $importer_settings['import_type'] != 'post' ) {

			// if file exists get total rows
			$file = self::getImporterFile( $settings['import_file'] );
			if ( $file ) {
				$wp_upload_dir = wp_upload_dir();
				$filepath      = $wp_upload_dir['basedir'] . $file->src;
			}

			$config_file = JCI()->get_tmp_config_path($post_id); // tempnam(sys_get_temp_dir(), 'config');
			$config = new \ImportWP\Importer\Config\Config($config_file);

			if($settings['template_type'] === 'csv'){

				$file = new \ImportWP\Importer\File\CSVFile($filepath, $config);
				$row_count = $file->getRecordCount();

			}else{
				$base = isset($_POST['jc-importer_parser_settings']) && isset($_POST['jc-importer_parser_settings']['import_base']) ? $_POST['jc-importer_parser_settings']['import_base'] : '';
				$file = new \ImportWP\Importer\File\XMLFile($filepath, $config);
				$file->setRecordPath($base);
				$row_count = !empty($base) ? $file->getRecordCount() : 0;
			}

			if ( $settings['start_line'] > $row_count ) {
				if ( $settings['row_count'] > $row_count ) {
					$settings['start_line'] = 1;
					$settings['row_count']  = 0;
				} else {
					if ( $settings['row_count'] > 0 ) {
						$settings['start_line'] = $row_count - ( $settings['row_count'] - 1 );
					} else {
						$settings['start_line'] = 1;
					}
				}
			} elseif ( $settings['start_line'] + $settings['row_count'] > ( $row_count + 1 ) ) {
				$settings['row_count'] = $row_count - ( $settings['start_line'] - 1 );
			}
			if ( $settings['start_line'] <= 0 ) {
				$settings['start_line'] = 1;
			}
			if ( $settings['row_count'] < 0 ) {
				$settings['row_count'] = 0;
			}
		}

		// Update Importer Post Record with new Title
		if ( isset( $data['name'] ) && $data['name'] ) {
			wp_update_post( array(
				'ID'         => $post_id,
				'post_title' => $data['name']
			) );
		}

		// save settings
		$meta['_import_settings'] = $settings;

		foreach ( $meta as $key => $value ) {

			$old_value = get_post_meta( $post_id, $key, true );

			if ( $value && '' == $old_value ) {
				add_post_meta( $post_id, $key, $value );
			} elseif ( $value && $value != $old_value ) {
				update_post_meta( $post_id, $key, $value );
			} elseif ( '' == $value && $old_value ) {
				delete_post_meta( $post_id, $key, $value );
			}
		}

		return $post_id;
	}

	/**
	 * Load Importer Settings
	 *
	 * @param  int $post_id
	 * @param  string $section
	 *
	 * @return string
	 */
	static function getImportSettings( $post_id, $section = null ) {

		$settings = self::getImporterMeta( $post_id, 'settings' );

		switch ( $section ) {

			// get ftp settings
			// TODO: remove to ftp addon
			case 'ftp':
				$settings = array(
					'ftp_loc' => isset( $settings['general']['ftp_loc'] ) ? $settings['general']['ftp_loc'] : '',
				);
				break;

			// get remote settings
			case 'remote':
				$settings = array(
					'remote_url' => isset( $settings['general']['remote_url'] ) ? $settings['general']['remote_url'] : '',
				);
				break;

			// get local settings
			case 'local':
				$settings = array(
					'local_url' => isset( $settings['general']['local_url'] ) ? $settings['general']['local_url'] : '',
				);
				break;

			// individual settings
			case 'template':
				$settings = $settings['template'];
				$settings = (string) $settings;
				break;
			case 'template_type':
				$settings = isset( $settings['template_type'] ) && ! empty( $settings['template_type'] ) ? $settings['template_type'] : 'csv';
				$settings = (string) $settings;
				break;
			case 'import_type':
				$settings = $settings['import_type'];
				$settings = (string) $settings;
				break;
			case 'start_line':
				$settings = isset( $settings['start_line'] ) ? $settings['start_line'] : 1;
				break;
			case 'row_count':
				$settings = isset( $settings['row_count'] ) ? $settings['row_count'] : 0;
				break;
			case 'record_import_count':
				$settings = isset( $settings['record_import_count'] ) ? $settings['record_import_count'] : 10;
				break;
			case 'import_file':
				if ( intval( $settings['import_file'] ) > 0 ) {

					$file = self::getImporterFile( $settings['import_file'] );
					if ( $file ) {
						$wp_upload_dir = wp_upload_dir();
						$settings      = $wp_upload_dir['basedir'] . $file->src;
					}

				} else {
					$settings = isset( $settings['import_file'] ) ? $settings['import_file'] : '';
				}
				break;
			case 'permissions':
				$settings = isset( $settings['permissions'] ) ? $settings['permissions'] : array();
				break;
			case 'template_unique_field':
				$settings = isset( $settings['template_unique_field'] ) ? $settings['template_unique_field'] : '';
				break;
		}

		return $settings;
	}

	/**
	 * Get All Importer Metadata
	 *
	 * @param  integer $post_id
	 * @param  string $section
	 *
	 * @return array
	 */
	static function getImporterMeta( $post_id, $section = null ) {

		if ( ! self::$meta ) {
			$importer_meta = get_metadata( 'post', $post_id, '', true );

			// get settings
			$settings       = isset( $importer_meta['_import_settings'] ) ? $importer_meta['_import_settings'][0] : array();
			$fields         = isset( $importer_meta['_mapped_fields'] ) ? $importer_meta['_mapped_fields'][0] : array();
			$attachments    = isset( $importer_meta['_attachments'] ) ? $importer_meta['_attachments'][0] : array();
			$taxonomies     = isset( $importer_meta['_taxonomies'] ) ? $importer_meta['_taxonomies'][0] : array();
			$addon_settings = isset( $importer_meta['_setting_addons'] ) ? $importer_meta['_setting_addons'][0] : array();
			$addon_fields   = isset( $importer_meta['_field_addons'] ) ? $importer_meta['_field_addons'][0] : array();

			// only unserialize if string
			$settings       = is_string( $settings ) ? unserialize( $settings ) : $settings;
			$fields         = is_string( $fields ) ? unserialize( $fields ) : $fields;
			$attachments    = is_string( $attachments ) ? unserialize( $attachments ) : $attachments;
			$taxonomies     = is_string( $taxonomies ) ? unserialize( $taxonomies ) : $taxonomies;
			$addon_settings = is_string( $addon_settings ) ? unserialize( $addon_settings ) : $addon_settings;
			$addon_fields   = is_string( $addon_fields ) ? unserialize( $addon_fields ) : $addon_fields;

			self::$meta = array(
				'settings'       => $settings,
				'fields'         => $fields,
				'attachments'    => $attachments,
				'taxonomies'     => $taxonomies,
				'addon_settings' => $addon_settings,
				'addon_fields'   => $addon_fields,
			);
		}

		$meta = self::$meta;

		switch ( $section ) {
			case 'settings':
				$meta = $meta['settings'];
				break;
			case 'fields':
				$meta = $meta['fields'];
				break;
			case 'attachments':
				$meta = $meta['attachments'];
				break;
			case 'taxonomies':
				$meta = $meta['taxonomies'];
				break;
			case 'addon_settings':
				$meta = $meta['addon_settings'];
				break;
			case 'addon_fields':
				$meta = $meta['addon_fields'];
				break;
		}

		return $meta;
	}

	static function getImporterFile( $file_id ) {

		global $wpdb;

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `" . $wpdb->prefix . "importer_files` WHERE id=%d", $file_id ) );
		if ( $result ) {
			return $result;
		}

		return false;
	}

	/**
	 * Add importer file into directory
	 *
	 * @todo : Re-structure this function
	 * @since 0.2
	 *
	 * @param  int $importer_id
	 * @param  string $file
	 *
	 * @return int
	 */
	static function insertImporterFile( $importer_id, $file ) {

		$wp_filetype   = wp_check_filetype( $file, null );
		$wp_upload_dir = wp_upload_dir();

		$mime           = $wp_filetype['type'];
		$name           = preg_replace( '/\.[^.]+$/', '', basename( $file ) );
		$attachment_src = $wp_upload_dir['subdir'] . '/' . basename( $file );
		$author_id      = get_current_user_id();

		global $wpdb;

		$wpdb->query( $wpdb->prepare( "INSERT INTO `" . $wpdb->prefix . "importer_files`(importer_id, author_id, mime_type, name, src, created) VALUES(%d, %d, %s, %s, %s, NOW())", $importer_id, $author_id, $mime, $name, $attachment_src ) );

		return $wpdb->insert_id;
	}

	static function getImporterFiles( $importer_id, $order = 'ASC' ) {

		global $wpdb;

		if('ASC' === $order){
			$order = 'ASC';
		}else{
			$order = 'DESC';
		}

		$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `" . $wpdb->prefix . "importer_files` WHERE importer_id=%d ORDER BY created $order", $importer_id ) );
		if ( $result ) {
			return $result;
		}

		return false;
	}
}

?>