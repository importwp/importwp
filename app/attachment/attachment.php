<?php

/**
 * Wordpress Attachments
 *
 * Handle wordpress attachments
 *
 * @author James Collings <james@jclabs.co.uk>
 * @todo: improve error reporting, show if max upload size has been met...
 * @version 0.1
 */
class JC_Attachment {

	/**
	 * Class Errors
	 * @var array
	 */
	protected $_errors = array();

	public function __construct() {

		// load required libraries
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
	}

	/**
	 * Check if an error has occured
	 * @return boolean
	 */
	public function has_error() {
		if ( ! empty( $this->_errors ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Add Error Message
	 *
	 * @param string $msg
	 */
	public function set_error( $msg ) {
		$this->_errors[] = $msg;
	}

	/**
	 * Get Latest Error Message
	 * @return string
	 */
	public function get_error() {
		return array_pop( $this->_errors );
	}

	/**
	 * Set Post Featured Image
	 *
	 * @param  int $post_id
	 * @param  int $attach_id
	 *
	 * @return bool
	 */
	private function wp_attach_featured_image( $post_id, $attach_id ) {

		$value = $attach_id;
		$key   = '_thumbnail_id';

		$old_value = get_post_meta( $post_id, $key, true );

		if ( $value && '' == $old_value ) {
			return add_post_meta( $post_id, $key, $value );
		} elseif ( $value && $value != $old_value ) {
			return update_post_meta( $post_id, $key, $value );
		} elseif ( '' == $value && $old_value ) {
			return delete_post_meta( $post_id, $key, $value );
		}

	}

	/**
	 * Add Attachment to wordpress
	 *
	 * Add attachment and resize
	 *
	 * @param  int $post_id
	 * @param  string $file
	 * @param  array $args
	 *
	 * @return boolean
	 */
	public function wp_insert_attachment( $post_id, $file = '', $args = array() ) {

		$parent  = isset( $args['parent'] ) && intval( $args['parent'] ) >= 0 ? intval( $args['parent'] ) : 0;
		$feature = isset( $args['feature'] ) && is_bool( $args['feature'] ) ? $args['feature'] : true;
		$resize  = isset( $args['resize'] ) && is_bool( $args['resize'] ) ? $args['resize'] : true;

		$wp_filetype   = wp_check_filetype( $file, null );
		$wp_upload_dir = wp_upload_dir();		

		// $args['importer-file'] = true;

		if(isset($args['importer-file']) && $args['importer-file'] === true){

			$file_id = ImporterModel::insertImporterFile($post_id, $file);
			if( intval( $file_id ) > 0 ){

				// if file uploaded, increase verion number
				$version = get_post_meta( $post_id, '_import_version', true );
				$last_row = ImportLog::get_last_row( $post_id, $version );
				if($last_row > 0){
					ImporterModel::setImportVersion($post_id, $version + 1);
				}	

				return $file_id;
			}
			return false;

		}else{

			$attachment = array(
				'guid'           => $wp_upload_dir['url'] . '/' . basename( $file ),
				'post_mime_type' => $wp_filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'    => get_current_user_id(),
				'post_parent'    => $post_id
			);

			$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		}

		

		// generate wp sizes
		if ( $resize ) {
			$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
			wp_update_attachment_metadata( $attach_id, $attach_data );
		}

		// set featured image
		if ( $feature ) {
			$this->wp_attach_featured_image( $post_id, $attach_id );
		}

		if ( empty( $this->_errors ) ) {
			return $attach_id;
		}

		return false;
	}

	/**
	 * Get mime type
	 *
	 * @param  string $file
	 *
	 * @return string
	 */
	public function get_file_mime( $file ) {

		$mime = mime_content_type( $file );

		return $mime;
	}

	/**
	 * Get files mime type
	 *
	 * @param  string $file
	 *
	 * @return string/bool
	 */
	public function get_template_type( $file ) {

		// get template type
		$mime = $this->get_file_mime( $file );

		return $this->check_mime_header( $mime );
	}

	/**
	 * Get mime type
	 *
	 * @param  string $mime
	 *
	 * @return string/bool
	 */
	public function check_mime_header( $mime ) {
		switch ( $mime ) {
			case 'text/comma-separated-values':
			case 'text/csv':
			case 'application/csv':
			case 'application/excel':
			case 'application/vnd.ms-excel':
			case 'application/vnd.msexcel':
			case 'text/anytext':
				return 'csv';
				break;
			case 'text/xml':
			case 'application/xml':
			case 'application/x-xml':
				return 'xml';
				break;
		}

		return false;
	}

	/**
	 * Attach remote image to post
	 *
	 * @param  int $post_id
	 * @param  string $src
	 * @param  string $dest
	 * @param  array $args
	 */
	public function attach_remote_image( $post_id, $src, $dest, $args = array() ) {

		$unique        = isset( $args['unique'] ) && is_bool( $args['unique'] ) ? $args['unique'] : true;
		$wp_upload_dir = wp_upload_dir();
		$wp_dest       = $wp_upload_dir['path'] . '/' . $dest;

		if ( ! $unique && file_exists( $wp_dest ) ) {
			$this->_errors[] = 'File Already Exists';

			return false;
		}

		if ( $unique ) {
			$dest    = wp_unique_filename( $wp_upload_dir['path'], $dest );
			$wp_dest = $wp_upload_dir['path'] . '/' . $dest;
		}

		if ( ! $this->fetch_image( $src, $wp_dest ) ) {
			if ( empty( $this->_errors ) ) {
				$this->_errors[] = 'Unable to fetch remote image';
			}

			return false;
		}

		return $this->wp_insert_attachment( $post_id, $wp_dest, $args );

	}

	/**
	 * Scaffikd for child classes to fetch an image
	 *
	 * @param  string $src
	 * @param  string $dest
	 *
	 * @return bool
	 */
	public function fetch_image( $src, $dest ) {
		return false;
	}

	/**
	 * Attach local file to post
	 *
	 * @param  string $src
	 *
	 * @return string/bool
	 */
	public function attach_local_file( $src ) {

		$wp_upload_dir = wp_upload_dir();
		$new_file      = $wp_upload_dir['path'] . '/' . basename( $src );

		if ( copy( $src, $new_file ) ) {
			return $new_file;
		}

		return false;
	}

	/**
	 * Attach remote file to post
	 *
	 * @param  int $post_id
	 * @param  string $src
	 * @param  string $dest
	 * @param  array $args
	 */
	public function attach_remote_file( $post_id, $src, $dest, $args = array() ) {

		$unique        = isset( $args['unique'] ) && is_bool( $args['unique'] ) ? $args['unique'] : true;
		$wp_upload_dir = wp_upload_dir();
		$wp_dest       = $wp_upload_dir['path'] . '/' . $dest;

		if ( ! $unique && file_exists( $wp_dest ) ) {
			return false;
		}

		if ( $unique ) {
			$dest    = wp_unique_filename( $wp_upload_dir['path'], $dest );
			$wp_dest = $wp_upload_dir['path'] . '/' . $dest;
		}

		if ( ! $this->fetch_image( $src, $wp_dest ) ) {
			return false;
		}

		$template_type = $this->get_template_type( $wp_dest );

		return array(
			'dest' => $wp_dest,
			'type' => $template_type,
			'id'   => $this->wp_insert_attachment( $post_id, $wp_dest, $args )
		);
	}
}