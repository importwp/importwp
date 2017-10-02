<?php
/**
 * FTP_Attachments
 *
 * Fetch and insert Attachments from an FTP Server to any wordpress posts
 *
 * @author James Collings <james@jclabs.co.uk>
 * @version 0.1
 * @package ImportWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class JCI_FTP_Attachments
 */
class JCI_FTP_Attachments extends JCI_Attachment {

	/**
	 * FTP Connection
	 *
	 * @var resource $_conn FTP Stream
	 */
	private $_conn = null;

	/**
	 * JCI_FTP_Attachments constructor.
	 *
	 * @param string $server Server host.
	 * @param string $username Server username.
	 * @param string $password Server password.
	 */
	public function __construct( $server = '', $username = '', $password = '' ) {
		parent::__construct();

		if ( $server && $username && $password && ! $this->_conn ) {
			$this->_connect( $server, $username, $password );
		}
	}

	/**
	 * Ftp Connect
	 *
	 * Connect to FTP Server with server and auth credentials
	 *
	 * @param string $server Server host.
	 * @param string $username Server username.
	 * @param string $password Server password.
	 *
	 * @return void
	 */
	private function _connect( $server = '', $username = '', $password = '' ) {

		if ( ! $this->_conn ) {
			$this->_conn = ftp_connect( $server, 21, 5 );
		}

		if ( $this->_conn ) {
			ftp_login( $this->_conn, $username, $password );
		}
	}

	/**
	 * Disconnect ftp connection
	 */
	public function __destruct() {
		$this->_disconnect();
	}

	/**
	 * Disconnect from FTP
	 *
	 * @return void
	 */
	private function _disconnect() {

		if ( $this->_conn ) {
			ftp_close( $this->_conn );
		}
	}

	/**
	 * Check to see if an ftp connection exists
	 *
	 * @return boolean
	 */
	public function is_connected() {
		if ( $this->_conn ) {
			return true;
		}

		return false;
	}

	/**
	 * Fetch Image from server
	 *
	 * If image exists on server, download via ftp to specified destination
	 *
	 * @param  string $src remote destination.
	 * @param  string $dest local destination.
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

require_once 'class-jci-attachment.php';
