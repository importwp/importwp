<?php

/**
 * String_Attachments
 *
 * Import Attachment from string
 *
 * @author James Collings <james@jclabs.co.uk>
 * @version 0.1
 */
class JC_String_Attachments extends JC_Attachment {

	/**
	 * Save string into file
	 *
	 * @param  int $post_id
	 * @param  string $string Content to save to file
	 * @param  string $a_name Attachment Name
	 *
	 * @return array/bool
	 */
	public function attach_string( $post_id, $string = '', $a_name = '' ) {

		if ( ! empty( $string ) ) {

			// create temp name if no attachment name chosen
			if ( empty( $a_name ) ) {
				$a_name = 'jci_' . time() . '.txt';
			}

			$string = stripslashes( $string );

			$wp_upload_dir = wp_upload_dir();
			$wp_dest       = $wp_upload_dir['path'] . '/' . $a_name;

			$dest    = wp_unique_filename( $wp_upload_dir['path'], $a_name );
			$wp_dest = $wp_upload_dir['path'] . '/' . $dest;

			// check to  see if file was created
			if ( file_put_contents( $wp_dest, $string ) ) {

				// return result array
				return array(
					'dest' => $wp_dest,
					'type' => 'application/text',
					'id'   => $this->wp_insert_attachment( $post_id, $wp_dest, array() )
				);
			}
		}

		return false;
	}
}

require_once 'attachment.php';