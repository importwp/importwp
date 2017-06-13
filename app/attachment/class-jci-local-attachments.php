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
	 * Scaffold for child classes to fetch an image
	 *
	 * @param  string $src Attachment Source.
	 * @param  string $dest Local attachment destination.
	 *
	 * @return bool
	 */
	public function fetch_image( $src, $dest ) {

		if(file_exists($src)){
			return copy($src, $dest);
		}else{
			$this->_errors[] = 'File doesn`t exist on local filesystem';
		}

		return false;
	}
}