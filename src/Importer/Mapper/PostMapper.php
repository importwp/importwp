<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 12/04/2018
 * Time: 16:09
 */

class PostMapper extends AbstractMapper implements \ImportWP\Importer\MapperInterface {

	public $changed_field_count = 0;
	public $changed_fields = array();

	/**
	 * Reserved Field Names for post table
	 * @var array
	 */
	protected $_post_fields = array(
		'ID',
		'menu_order',
		'comment_status',
		'ping_status',
		'pinged',
		'post_author',
		'post_category',
		'post_content',
		'post_date',
		'post_date_gmt',
		'post_excerpt',
		'post_name',
		'post_parent',
		'post_password',
		'post_status',
		'post_title',
		'post_type',
		'tags_input',
		'to_ping',
		'tax_input'
	);

	protected $_query_vars = array(
		'post_name' => 'name',
		'ID'        => 'p'
	);

	private $attachment_class = false;

	public function exists( \ImportWP\Importer\ParsedData $data ) {

		$unique_fields = $this->template->_unique;
		$default_group = $data->getData('default');
		$template_group_id = $this->template->get_template_group_id();
		$post_type     = $this->template->_field_groups[$template_group_id]['import_type_name'];
		$post_status   = $this->template->_field_groups[$template_group_id]['post_status'];

		$meta_args  = array();
		$query_args = array(
			'post_type'   => $post_type,
			'post_status' => $post_status,
			'fields'      => 'ids',
			'cache_results' => false,
			'update_post_meta_cache' => false,
		);

		$has_unique_field = false;

		foreach ( $unique_fields as $field ) {

			if ( in_array( $field, $this->_post_fields ) ) {

				if ( array_key_exists( $field, $this->_query_vars ) ) {

					if ( ! empty( $default_group[ $field ] ) ) {
						$query_args[ $this->_query_vars[ $field ] ] = $default_group[ $field ];
						$has_unique_field                           = true;
					}

				} else {
					$query_args[ $field ] = $default_group[ $field ];
				}

			} else {
				$meta_args[] = array(
					'key'   => $field,
					'value' => $default_group[ $field ]
				);
			}
		}

		if ( ! $has_unique_field ) {
			return false;
		}

		if ( ! empty( $meta_args ) ) {
			$query_args['meta_query'] = $meta_args;
		}

		if(sizeof($query_args) === 3){
			// TODO: No unique reference field, throw error
			return false;
		}

		$query = new WP_Query( $query_args );

		if ( $query->post_count == 1 ) {
			$this->ID = $query->posts[0];
			return true;
		}

		return false;
	}

	public function insert( \ImportWP\Importer\ParsedData $data ) {

		// clear log
		$this->clearLog();

		// check permissions
		$this->checkPermissions('insert');

		$fields      = $data->getData('default');
		$template_group_id = $this->template->get_template_group_id();
		$post_type   = $this->template->_field_groups[$template_group_id]['import_type_name'];
		$post_status = $this->template->_field_groups[$template_group_id]['post_status'];

		$post = array();
		$meta = array();

		// if we are trying to insert a post with a specific id then used import_id instead.
		if(isset($fields['ID']) && !empty($fields['ID'])){
			$post['import_id'] = $fields['ID'];
			unset($fields['ID']);
		}

		$this->changed_field_count = count( $fields );
		$this->changed_fields      = array_keys( $fields );

		$this->sortFields( $fields, $post, $meta );

		// create post type
		$post['post_type'] = $post_type;

		// legacy to set post status in template
		if ( ! isset( $post['post_status'] ) ) {
			$post['post_status'] = $post_status;
		}

		$this->ID = wp_insert_post( $post, true );

		// check to see if is error
		if ( ! is_wp_error( $this->ID ) ) {

			// create post meta
			if ( $this->ID && ! empty( $meta ) ) {
				foreach ( $meta as $key => $value ) {

					if ( $value != '' ) {
						$this->update_custom_field( $this->ID, $key, $value );
					}
				}
			}

			$this->processTaxonomies($data);
			$this->processAttachments($data);

			$fields['ID'] = $this->ID;
			$this->logImport($fields, 'insert', 'post');
			$this->add_version_tag();
			$data->update( $fields );
		}else{
			throw new \ImportWP\Importer\Exception\MapperException( $this->ID->get_error_message() );
		}

		clean_post_cache($this->ID);

		return $this->ID;
	}

	public function update( \ImportWP\Importer\ParsedData $data ) {

		// clear log
		$this->clearLog();

		// check permissions
		$this->checkPermissions('update');
		$template_group_id = $this->template->get_template_group_id();

		$fields    = $data->getData('default');
		$post_type = $this->template->_field_groups[$template_group_id]['import_type_name'];

		$post                      = array();
		$meta                      = array();
		$this->changed_field_count = 0;
		$this->changed_fields      = array();

		$this->sortFields( $fields, $post, $meta );

		if ( ! $this->ID ) {
			return false;
		}

		// update post type
		if ( ! empty( $post ) ) {

			// check to see if fields need updating
			$query = new WP_Query( array(
				'post_type'      => $post_type,
				'p'              => $this->ID,
				'posts_per_page' => 1,
				'cache_results' => false,
				'update_post_meta_cache' => false,
			) );
			if ( $query->found_posts == 1 ) {
				$old_post = $query->post;

				foreach ( $post as $k => $p ) {
					if ( $p == $old_post->$k ) {
						unset( $post[ $k ] );
					} else {
						$this->changed_field_count ++;
						$this->changed_fields[] = $k;
					}
				}
			}

			if ( ! empty( $post ) ) {
				// update remaining
				$post['ID'] = $this->ID;
				$res = wp_update_post( $post, true );
				if(is_wp_error($res)){
						throw new \ImportWP\Importer\Exception\MapperException( $res->get_error_message() );
				}
			}
		}

		// TODO: switch single get_post_meta to get_metadata

		// update post meta
		if ( ! empty( $meta ) ) {

			foreach ( $meta as $key => $value ) {
				$this->update_custom_field( $this->ID, $key, $value );
			}
		}

		$this->processTaxonomies($data);
		$this->processAttachments($data);

		$fields['ID'] = $this->ID;
		$this->logImport($fields, 'update', 'post');
		$this->update_version_tag();
		$data->update( $fields );

		clean_post_cache($this->ID);

		return $this->ID;

	}

	public function delete( \ImportWP\Importer\ParsedData $data ) {
		// TODO: Implement delete() method.
	}

	/**
	 * Sort fields into post and meta array
	 *
	 * @param  array $fields list of fields
	 * @param  array $post post_data pointer array
	 * @param  array $meta post_meta pointer array
	 *
	 * @return void
	 */
	function sortFields( $fields = array(), &$post = array(), &$meta = array() ) {

		foreach ( $fields as $id => $value ) {

			if ( in_array( $id, $this->_post_fields ) ) {

				// post field
				$post[ $id ] = $value;
			} else {

				// meta field
				$meta[ $id ] = $value;
			}
		}
	}

	public function update_custom_field( $post_id, $key, $value, $unique = false ) {

		$old_value = get_post_meta( $post_id, $key, true );

		// Stop double serialization
		if(is_serialized($value)){
			$value = unserialize($value);
		}

		// check if new value
		if ( $old_value === $value ) {
			return;
		}

		$this->changed_field_count ++;
		$this->changed_fields[] = $key;

		if ( $value !== '' && '' == $old_value ) {
			add_post_meta( $post_id, $key, $value, $unique );
		} elseif ( $value !== '' && $value !== $old_value ) {
			update_post_meta( $post_id, $key, $value );
		} elseif ( '' === $value && $old_value ) {
			delete_post_meta( $post_id, $key, $value );
		}

	}

	function add_version_tag() {

		if ( ! isset( JCI()->importer ) ) {
			return;
		}

		$importer_id = JCI()->importer->get_ID();
		$version     = JCI()->importer->get_version();

		add_post_meta( $this->ID, '_jci_version_' . $importer_id, $version, true );
	}

	/**
	 * Update Import Tracking Tag
	 */
	function update_version_tag() {

		if ( ! isset( JCI()->importer ) ) {
			return;
		}

		$importer_id = JCI()->importer->get_ID();
		$version     = JCI()->importer->get_version();

		$old_version = get_post_meta( $this->ID, '_jci_version_' . $importer_id, true );
		if ( $old_version ) {
			update_post_meta( $this->ID, '_jci_version_' . $importer_id, $version, $old_version );
		} else {
			add_post_meta( $this->ID, '_jci_version_' . $importer_id, $version, true );
		}
	}

	function processTaxonomies(\ImportWP\Importer\ParsedData $data){

		$taxonomies = $data->getData('taxonomies');

		if(empty($taxonomies)){
			return;
		}

		$taxonomies_permissions = JCI()->importer->taxonomies_permissions[$this->template->get_template_group_id()];
		$log = array();

		foreach($taxonomies as $tax  => $term_arr ){

			$permission = $taxonomies_permissions[$tax];

			// clear existing taxonomies
			if ( $permission == 'overwrite' ) {
				wp_set_object_terms( $this->ID, null, $tax );
			}


			foreach($term_arr  as $term_value ){

				$term_delimiter = apply_filters( 'jci/value_delimiter', ',' );
				$term_delimiter = apply_filters( 'jci/taxonomy/value_delimiter', $term_delimiter );
				$terms          = explode( $term_delimiter, $term_value );

				foreach ( $terms as $t ) {
					$t = trim( $t );
					if ( empty( $t ) ) {
						continue;
					}
					// skip if already has term
					if ( $permission == 'append' && has_term( $t, $tax, $this->ID ) ) {
						continue;
					}

					$log[ $tax ][] = $t;

					if ( term_exists( $t, $tax ) ) {
						// attach term to post
						wp_set_object_terms( $this->ID, $t, $tax, true );
					} else {
						// add term
						$term_id = wp_insert_term( $t, $tax );
						wp_set_object_terms( $this->ID, $term_id, $tax, true );
					}
				}

			}
		}

		$this->appendLog($log, 'taxonomies');

	}

	function processAttachments(\ImportWP\Importer\ParsedData $data){

		$attachments = $data->getData('attachments');

		if(empty($attachments)){
			return;
		}

		foreach($attachments['location'] as $key => $src){

			if(empty($src)){
				continue;
			}

			$permission = $attachments['permissions'][ $key ];

			$featured = isset( $attachments['featured_image'][ $key ] ) ? $attachments['featured_image'][ $key ] : 0;
			if ( $featured == 1 ) {
				$feature = true;
			} else {
				$feature = false;
			}
			// escape if already has attachment
			$has_image = has_post_thumbnail( $this->ID );
			if ( $permission == 'create' && $has_image ) {
				continue;
			}

			set_error_handler( array( $this, 'handleError' ) );
			try {
				// unable to connect
				if ( ! $this->initAttachment( $attachments ) ) {
					throw new JCI_Exception( "Could Not Connect", JCI_ERR );
					return false;
				}

				$dest = basename( $src );
				$dest = apply_filters( 'jci/attachment_name', $dest, $key );
				// download and install attachments
				$result = $this->attachment_class->attach_remote_image( $this->ID, $src, $dest, array(
					'unique'  => true,
					'parent'  => $this->ID,
					'feature' => $feature
				) );
				if ( $result ) {
					$this->appendLog( array(array('status' => 'S', 'msg' => $dest)), 'attachments');
				} else {
					$this->appendLog( array(array('status' => 'E', 'msg' => $this->attachment_class->get_error())), 'attachments');
				}
			} catch ( Exception $e ) {
				$this->appendLog( array(array('status' => 'E', 'msg' => jci_error_message( $e ))), 'attachments');
			}
			restore_error_handler();
		}
	}

	final function initAttachment( $attachments ) {
		if ( $this->attachment_class ) {
			return true;
		}

		switch ( $attachments['type'] ) {
			case 'local':
				$this->attachment_class = new JCI_Local_Attachments();
				$base_path              = $attachments['local']['base_path'];
				if ( ! $this->attachment_class->set_local_dir( $base_path ) ) {
					return false;
				}
				return true;
				break;
			case 'url':
				// setup curl
				$this->attachment_class = new JCI_CURL_Attachments();
				return true;
				break;
			case 'ftp':
				// setup ftp connection
				$server = $attachments['ftp']['server'];
				$user   = $attachments['ftp']['user'];
				$pass   = $attachments['ftp']['pass'];
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

	function handleError( $errno, $errstr, $errfile, $errline, $errcontext = array() ) {
		// error was suppressed with the @-operator
		if ( 0 === error_reporting() ) {
			return false;
		}
		throw new ErrorException( $errstr, 0, $errno, $errfile, $errline );
	}

	/**
	 * Remove all posts from the current tracked import
	 *
	 * @param  int $importer_id
	 * @param  int $version
	 * @param  string $post_type
	 *
	 * @return void
	 */
	function remove_all_objects( $importer_id, $version ) {

		$post_type = $this->template->_field_groups[$this->template->get_template_group_id()]['import_type_name'];

		// get a list of all objects which were not in current update
		$q = new WP_Query( array(
			'post_type'      => $post_type,
			'meta_query'     => array(
				array(
					'key'     => '_jci_version_' . $importer_id,
					'value'   => $version,
					'compare' => '!='
				)
			),
			'fields'         => 'ids',
			'posts_per_page' => - 1,
			'cache_results' => false,
			'update_post_meta_cache' => false,
		) );

		// delete list of objects
		if ( $q->have_posts() ) {
			$ids = $q->posts;
			foreach ( $ids as $id ) {
				wp_delete_post( $id, true );
			}
		}

	}
}