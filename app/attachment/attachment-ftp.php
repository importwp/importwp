<?php

/**
 * FTP_Attachments
 *
 * Fetch and insert Attachments from an FTP Server to any wordpress posts
 *
 * @author James Collings <james@jclabs.co.uk>
 * @version 0.1
 */
class JC_FTP_Attachments extends JC_Attachment {

	/**
	 * FTP Connection
	 * @var FTP Stream
	 */
	private $_conn = null;

	public function __construct( $server = false, $username = false, $password = false ) {
		parent::__construct();

		if ( $server && $username && $password && ! $this->_conn ) {
			$this->_connect( $server, $username, $password );
		}
	}

	/**
	 * Disconnect ftp connection
	 */
	public function __destruct() {
		$this->_disconnect();
	}

	/**
	 * Check to see if an ftp connection exists
	 * @return boolean
	 */
	public function is_connected() {
		if ( $this->_conn ) {
			return true;
		}

		return false;
	}

	/**
	 * Ftp Connect
	 *
	 * Connect to FTP Server with server and auth credentials
	 *
	 * @param  string $server
	 * @param  string $username
	 * @param  string $password
	 *
	 * @return void
	 */
	private function _connect( $server = '', $username = '', $password = '' ) {

		if ( ! $this->_conn ) {
			$this->_conn = ftp_connect( $server, 21, 5 );
		}

		if ( $this->_conn ) {
			$login_result = ftp_login( $this->_conn, $username, $password );
		}
	}

	/**
	 * Disconnect from FTP
	 * @return void
	 */
	private function _disconnect() {

		if ( $this->_conn ) {
			ftp_close( $this->_conn );
		}
	}

	/**
	 * Fetch Image from server
	 *
	 * If image exists on server, download via ftp to specified destination
	 *
	 * @param  string $src remote destination
	 * @param  string $dest local destination
	 *
	 * @return string filename
	 */
	public function fetch_image( $src = '', $dest = '' ) {

		if ( ! $this->_conn ) {
			$this->_errors[] = 'Unable to connect to ftp server';

			return false;
		}

		if ( ftp_size( $this->_conn, $src ) >= 0 ) {
			return ftp_get( $this->_conn, $dest, $src, FTP_BINARY );
		} else {
			$this->_errors[] = 'File doesn`t exist on server';
		}

		return false;
	}
}

require_once 'attachment.php';