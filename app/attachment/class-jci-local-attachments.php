<?php
/**
 * Local_Attachments
 *
 * Fetch and insert Attachments from the local filesystem to any wordpress posts
 *
 * @author James Collings <james@jclabs.co.uk>
 * @version 0.1
 * @package ImportWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class JCI_Local_Attachments extends JCI_Attachment{

	/**
	 * Local directory to search for files
	 * 
	 * Is false if not set, otherwise is a full path to a directory
	 *
	 * @var boolean|string
	 */
	private $_local_dir = false;

	/**
	 * Set local diretory to fetch attachments from
	 * 
	 * Check to see if the directory exists and is readable
	 *
	 * @param string $base_path
	 * 
	 * @return bool
	 */
	public function set_local_dir($base_path){
		
		if(!is_readable($base_path)){
			return false;	
		}

		$this->_local_dir = $base_path;

		return true;
	}

	/**
	 * Get local directory if it has been set, otherwise return ""
	 *
	 * @return string
	 */
	private function get_local_dir(){
		if($this->_local_dir === false){
			return "";
		}

		return $this->_local_dir;
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

		$local_dir = $this->get_local_dir();

		if(file_exists($local_dir . $src)){
			return copy($local_dir . $src, $dest);
		}else{
			$this->_errors[] = 'File doesn`t exist on local filesystem';
		}

		return false;
	}
}