<?php
/**
* Upload_Attachments
*
* Fetch and insert Attachments from a remote url
* 
* @author James Collings <james@jclabs.co.uk>
* @version 0.1
*/
class JC_Upload_Attachments extends JC_Attachment{

    /**
     * Attach uploaded file to post
     * @param  int $post_id    
     * @param  $_FILES $attachment 
     * @return array/bool
     */
	public function attach_upload($post_id, $attachment){

        // check for upload status
        switch ($attachment['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                $this->set_error('No file sent.');
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $this->set_error('Exceeded filesize limit.');
                break;
            default:
                $this->set_error('Unknown errors.');
                break;
        }

		if(isset($attachment['error']) 
			&& $attachment['error'] == UPLOAD_ERR_OK){

            // uploaded without errors
            $a_name = $attachment['name'];
            $a_tmp_name = $attachment['tmp_name'];
            $template_type = '';

            // determine file type from mimetype
            $template_type = $this->check_mime_header($attachment['type']);

            $wp_upload_dir = wp_upload_dir();
            $wp_dest = $wp_upload_dir['path'] . '/' . $a_name;

            $dest = wp_unique_filename( $wp_upload_dir['path'], $a_name);
            $wp_dest = $wp_upload_dir['path'] . '/' . $dest;

            // check to see if file was created
            if(move_uploaded_file($a_tmp_name, $wp_dest)){

                // return result array
            	return array(
            		'dest' => $wp_dest,
            		'type' => $template_type,
                    'id' => $this->wp_insert_attachment( $post_id, $wp_dest, array())
            	);
            }
        }

        return false;
	}
}

require_once 'attachment.php';