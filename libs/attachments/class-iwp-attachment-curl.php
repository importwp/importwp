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

		if ( function_exists( 'curl_init' ) ) {
			return $this->fetch_curl_image( $src, $dest );
		} elseif ( ini_get( 'allow_url_fopen' ) ) {
			return $this->fetch_noncurl_image( $src, $dest );
		}

		return false;
	}

	/**
	 * Fetch file with curl
	 *
	 * @param  string $src Attachment source.
	 * @param  string $dest Attachment destination.
	 *
	 * @return bool
	 */
	private function fetch_curl_image( $src = '', $dest = '' ) {

		$ch = curl_init( $src );
		$fp = fopen( $dest, 'wb' );

		curl_setopt( $ch, CURLOPT_FILE, $fp );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		$result = curl_exec( $ch );
		curl_close( $ch );
		fclose( $fp );

		return $result;
	}

	/**
	 * Fetch file without curl
	 *
	 * @param  string $src Attachment source.
	 * @param  string $dest Attachment destination.
	 *
	 * @return bool
	 */
	private function fetch_noncurl_image( $src = '', $dest = '' ) {

		return file_put_contents( $dest, file_get_contents( $src ) );
	}
}
