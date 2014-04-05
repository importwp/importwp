<?php

/**
 * Get list of templates for
 * @return array
 */
function get_template_list(){
	
	global $jcimporter;

	$templates = array();
	foreach($jcimporter->templates as $template){
		$templates[$template->get_name()] = $template->get_name();
	}

	return $templates;
}

/**
 * Get Import Template
 * @param  string $template 
 * @return array
 */
function get_import_template($template){

	global $jcimporter;

	return $jcimporter->templates[$template];
}

function load_import_parser($import_id){
	global $jcimporter;

	$template_type = ImporterModel::getImportSettings($import_id, 'template_type');
    return $jcimporter->parsers[$template_type];
    // return new $parser();
}

if(!function_exists('mime_content_type')) {

    function mime_content_type($filename) {

        $mime_types = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',
            'csv' => 'text/csv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $ext = strtolower(array_pop(explode('.',$filename)));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        }
        else {
            return 'application/octet-stream';
        }
    }
}

/**
 * Output column data
 * @param  array $column 
 * @param  array $data   
 * @return void
 */
function log_content($column, $data){

    switch ($column) {
        case 'attachments':
            if(isset($data['attachments'])){

                //
                $attachments = 0;
                foreach($data['attachments'] as $result){
                    if($result['status'] == 'E'){

                        // error
                        echo "Error: ".$result['msg'];
                    }elseif($result['status'] == 'S'){

                        $attachments++;
                    }
                }

                if($attachments > 0){
                    if($attachments == 1){
                        echo $attachments.' Attachment Inserted';
                    }else{
                        echo $attachments.' Attachments Inserted';
                    }
                    
                }
                
            }else{
                echo 'No Attachments Inserted';
            }
            
        break;
        case 'taxonomies':
            if(isset($data['taxonomies'])){

                foreach($data['taxonomies'] as $tax => $terms){
                    echo "<strong>{$tax}</strong>: ".implode(',', $terms).'<br />';
                }
            }else{
                echo 'No Taxonomies Inserted';
            }
        break;
    }
}