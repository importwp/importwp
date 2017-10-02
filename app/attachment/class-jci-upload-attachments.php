<?php
/**
 * Upload_Attachments
 *
 * Fetch and insert Attachments from a remote url
 *
 * @author James Collings <james@jclabs.co.uk>
 * @version 0.1
 * @package ImportWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class JCI_Upload_Attachments
 */
class JCI_Upload_Attachments extends JCI_Attachment {

	/**
	 * Attach uploaded file to post
	 *
	 * @param  int $post_id Post id.
	 * @param  array $attachment $_FILES array.
	 * @param  array $args Arguments.
	 *
	 * @return array|bool
	 */
	public function attach_upload( $post_id, $attachment, $args = array() ) {

		// check for upload status.
		switch ( $attachment['error'] ) {
			case UPLOAD_ERR_OK:
				break;
			case UPLOAD_ERR_NO_FILE:
				$this->set_error( 'No file sent.' );
				break;
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				$this->set_error( 'Exceeded filesize limit.' );
				break;
			default:
				$this->set_error( 'Unknown errors.' );
				break;
		}

		if ( isset( $attachment['error'] ) && UPLOAD_ERR_OK === $attachment['error'] ) {

			// uploaded without errors.
			$a_name     = $attachment['name'];
			$a_tmp_name = $attachment['tmp_name'];

			// determine file type from mimetype.
			$template_type = $this->check_mime_header( $attachment['type'] );

			// if header doesnt match check for file extension.
			if ( ! $template_type ) {
				if ( stripos( $attachment['name'], '.csv' ) ) {
					$template_type = 'csv';
				} elseif ( stripos( $attachment['name'], '.xml' ) ) {
					$template_type = 'xml';
				}
			}

			$wp_upload_dir = wp_upload_dir();

			$dest    = wp_unique_filename( $wp_upload_dir['path'], $a_name );
			$wp_dest = $wp_upload_dir['path'] . '/' . $dest;

			// check to see if file was created.
			if ( move_uploaded_file( $a_tmp_name, $wp_dest ) ) {

				// return result array.
				return array(
					'dest' => $wp_dest,
					'type' => $template_type,
					'mime' => $attachment['type'],
					'id'   => $this->wp_insert_attachment( $post_id, $wp_dest, $args ),
				);
			}
		}

		return false;
	}
}

require_once 'class-jci-attachment.php';
