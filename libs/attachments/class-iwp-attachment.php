<?php
/**
 * Importer Attachments
 *
 * @todo: improve error reporting, show if max upload size has been met...
 * @package ImportWP
 * @author James Collings <james@jclabs.co.uk>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class JC_Attachment
 *
 * Handle WordPress attachments
 */
class IWP_Attachment {

	/**
	 * Class Errors
	 *
	 * @var array
	 */
	protected $_errors = array();

	/**
	 * JC_Attachment constructor.
	 */
	public function __construct() {

		// load required libraries.
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
	}

	/**
	 * Check if an error has occured
	 *
	 * @return boolean
	 */
	public function has_error() {
		if ( ! empty( $this->_errors ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get Latest Error Message
	 *
	 * @return string
	 */
	public function get_error() {
		return array_pop( $this->_errors );
	}

	/**
	 * Attach remote image to post
	 *
	 * @param  int $post_id Post id.
	 * @param  string $src Remote image url.
	 * @param  string $dest Local attachment destination.
	 * @param  array $args Arguments.
	 *
	 * @return bool
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

		$result = $this->wp_insert_attachment( $post_id, $wp_dest, $args );
		if(intval($result) > 0){
			$this->store_attachment_hash($result, $src);
		}
		return $result;
	}

	/**
	 * Scaffold for child classes to fetch an image
	 *
	 * @param  string $src Attachment Source.
	 * @param  string $dest Local attachment destination.
	 *
	 * @return bool
	 */
	public function fetch_image( $src, $dest ) {
		return false;
	}

	/**
	 * Add Attachment to wordpress
	 *
	 * Add attachment and resize
	 *
	 * @param  int $post_id Post Id.
	 * @param  string $file File name or path.
	 * @param  array $args Arguments.
	 *
	 * @return boolean
	 */
	public function wp_insert_attachment( $post_id, $file = '', $args = array() ) {

		$parent  = isset( $args['parent'] ) && intval( $args['parent'] ) >= 0 ? intval( $args['parent'] ) : 0;
		$feature = isset( $args['feature'] ) && is_bool( $args['feature'] ) ? $args['feature'] : true;
		$resize  = isset( $args['resize'] ) && is_bool( $args['resize'] ) ? $args['resize'] : true;

		$wp_filetype   = wp_check_filetype( $file, null );
		$wp_upload_dir = wp_upload_dir();

		if ( isset( $args['importer-file'] ) && true === $args['importer-file'] ) {

			$file_id = IWP_Importer_Settings::insertImporterFile( $post_id, $file );
			if ( intval( $file_id ) > 0 ) {

				// if file uploaded, increase version number.
				$version  = get_post_meta( $post_id, '_import_version', true );
				$last_row = IWP_Importer_Log::get_last_row( $post_id, $version );
				if ( $last_row > 0 ) {
					IWP_Importer_Settings::setImportVersion( $post_id, $version + 1 );
				}

				return $file_id;
			}

			return false;

		} else {

			$attachment = array(
				'guid'           => $wp_upload_dir['url'] . '/' . basename( $file ),
				'post_mime_type' => $wp_filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'    => get_current_user_id(),
				'post_parent'    => $post_id,
			);

			$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		}


		// generate wp sizes.
		if ( $resize ) {
			$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
			wp_update_attachment_metadata( $attach_id, $attach_data );
		}

		// set featured image.
		if ( $feature ) {
			$this->wp_attach_featured_image( $post_id, $attach_id );
		}

		if ( empty( $this->_errors ) ) {
			return $attach_id;
		}

		return false;
	}

	public function store_attachment_hash($attachment_id, $src){

		// Store data to relate it to the importer
		update_post_meta( $attachment_id, '_iwp_attachment_src' , md5($src));
		update_post_meta( $attachment_id, '_iwp_attachment_importer' , JCI()->importer->get_ID());
	}

	/**
	 * Set Post Featured Image
	 *
	 * @param  int $post_id Post Id.
	 * @param  int $attach_id Attachment Id.
	 *
	 * @return bool
	 */
	private function wp_attach_featured_image( $post_id, $attach_id ) {

		$value = $attach_id;
		$key   = '_thumbnail_id';

		$old_value = get_post_meta( $post_id, $key, true );

		if ( $value && '' === $old_value ) {
			return add_post_meta( $post_id, $key, $value );
		} elseif ( $value && $value !== $old_value ) {
			return update_post_meta( $post_id, $key, $value );
		} elseif ( '' === $value && $old_value ) {
			return delete_post_meta( $post_id, $key, $value );
		}

	}

	/**
	 * Attach local file to post
	 *
	 * @param  string $src Attachment Source.
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
	 * @param  int $post_id Post Id.
	 * @param  string $src Remote image url.
	 * @param  string $dest Local attachment destination.
	 * @param  array $args Arguments.
	 *
	 * @return array|bool
	 */
	public function attach_remote_file( $post_id, $src, $dest, $args = array() ) {

		$unique            = isset( $args['unique'] ) && is_bool( $args['unique'] ) ? $args['unique'] : true;
		$wp_upload_dir     = wp_upload_dir();
		$wp_dest           = $wp_upload_dir['path'] . '/' . $dest;
        $restrict_filetype = isset($args['restrict_filetype']) ? $args['restrict_filetype'] : true;

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

        if ($restrict_filetype) {
            $template_type = $this->get_template_type($wp_dest);
        } else {
            $template_type = $this->get_file_mime($wp_dest);
        }

        $result = $this->wp_insert_attachment( $post_id, $wp_dest, $args );
		if(intval($result) > 0){
			$this->store_attachment_hash($result, $src);
		}

		return array(
			'dest' => $wp_dest,
			'type' => $template_type,
			'id'   => $result,
		);
	}

	/**
	 * Get files mime type
	 *
	 * @param  string $file File name or path.
	 *
	 * @return string/bool
	 */
	public function get_template_type( $file ) {

		// get template type.
		$mime = $this->get_file_mime( $file );

		$result = $this->check_mime_header( $mime );

		// fallback to check for filetype by extension.
		if ( ! $result ) {

			if ( strpos( $file, '.xml' ) > 0 ) {
				$result = 'xml';
			} elseif ( strpos( $file, '.csv' ) > 0 ) {
				$result = 'csv';
			}
		}

		if ( ! $result ) {
			$this->set_error( 'Could not determine filetype' );
		}

		return $result;
	}

	/**
	 * Get mime type
	 *
	 * @param  string $file File name or path.
	 *
	 * @return string
	 */
	public function get_file_mime( $file ) {

		$mime = mime_content_type( $file );

		return $mime;
	}

	/**
	 * Get mime type
	 *
	 * @param  string $mime Mimetype.
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
			case 'text/plain':
				return 'csv';
			case 'text/xml':
			case 'application/xml':
			case 'application/x-xml':
				return 'xml';
		}

		return false;
	}

	/**
	 * Add Error Message
	 *
	 * @param string $msg Error message.
	 */
	public function set_error( $msg ) {
		$this->_errors[] = $msg;
	}
}