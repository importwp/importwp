<?php
/**
 * CURL_Attachments
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
 * Class JCI_CURL_Attachments
 */
class IWP_Attachment_CURL extends IWP_Attachment {

	/**
	 * Fetch Image from url
	 *
	 * If image exists on url, download via curl to specified destination
	 *
	 * @param  string $src remote destination.
	 * @param  string $dest local destination.
	 *
	 * @return string filename
	 */
	public function fetch_image( $src = '', $dest = '' ) {

		$src = trim($src);
		$response = wp_remote_get( $src, array( 'timeout' => 30, 'sslverify' => false ) );
		if ( is_wp_error( $response ) ) {

			$this->_errors[] = $response->get_error_message();

		} else {

			if ( is_array( $response ) ) {
				if ( 200 === $response['response']['code'] ) {
					file_put_contents( $dest, $response['body'] );
					return true;
				} else {
					$this->_errors[] = "Url returned response code: " . $response['response']['code'];
				}
			}
		}

		return false;
	}
}
