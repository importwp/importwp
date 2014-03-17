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

	public function attach_upload($post_id, $attachment){

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

            switch($attachment['type']){
                case 'text/comma-separated-values':
                case 'text/csv':
                case 'application/csv':
                case 'application/excel':
                case 'application/vnd.ms-excel':
                case 'application/vnd.msexcel':
                case 'text/anytext':
                    $template_type = 'csv';
                break;
                case 'text/xml':
                case 'application/xml':
                case 'application/x-xml':
                    $template_type = 'xml';
                break;
            }

            $wp_upload_dir = wp_upload_dir();
            $wp_dest = $wp_upload_dir['path'] . '/' . $a_name;

            $dest = wp_unique_filename( $wp_upload_dir['path'], $a_name);
            $wp_dest = $wp_upload_dir['path'] . '/' . $dest;

            if(move_uploaded_file($a_tmp_name, $wp_dest)){

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