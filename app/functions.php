<?php

/**
 * Get list of installed importer templates
 * 
 * @param bool $class_name output class name as array value
 * @return array
 */
function get_template_list($class_name = true) {

	$templates = array();
	foreach ( JCI()->get_templates() as $key => $template ) {

		if($class_name){

			// output class name as value
			$templates[ $key ] 	= $template;
		}else{

			// output human readable version of key
			$temp 				= $key;
			$temp 				= str_replace('-', ' ', $temp);
			$temp 				= ucfirst($temp);
			$templates[ $key ] 	= $temp;
		}
	}

	return $templates;
}

/**
 * Get Import Template
 *
 * @param  string $template
 *
 * @return array
 */
function get_import_template( $template ) {

	return JCI()->get_template($template);
}

function load_import_parser( $import_id ) {

	$template_type = ImporterModel::getImportSettings( $import_id, 'template_type' );
	return JCI()->parsers[ $template_type ];
}

if ( ! function_exists( 'mime_content_type' ) ) {

	function mime_content_type( $filename ) {

		$mime_types = array(

			'txt'  => 'text/plain',
			'htm'  => 'text/html',
			'html' => 'text/html',
			'php'  => 'text/html',
			'css'  => 'text/css',
			'js'   => 'application/javascript',
			'json' => 'application/json',
			'xml'  => 'application/xml',
			'swf'  => 'application/x-shockwave-flash',
			'flv'  => 'video/x-flv',
			'csv'  => 'text/csv',
			// images
			'png'  => 'image/png',
			'jpe'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpg'  => 'image/jpeg',
			'gif'  => 'image/gif',
			'bmp'  => 'image/bmp',
			'ico'  => 'image/vnd.microsoft.icon',
			'tiff' => 'image/tiff',
			'tif'  => 'image/tiff',
			'svg'  => 'image/svg+xml',
			'svgz' => 'image/svg+xml',
			// archives
			'zip'  => 'application/zip',
			'rar'  => 'application/x-rar-compressed',
			'exe'  => 'application/x-msdownload',
			'msi'  => 'application/x-msdownload',
			'cab'  => 'application/vnd.ms-cab-compressed',
			// audio/video
			'mp3'  => 'audio/mpeg',
			'qt'   => 'video/quicktime',
			'mov'  => 'video/quicktime',
			// adobe
			'pdf'  => 'application/pdf',
			'psd'  => 'image/vnd.adobe.photoshop',
			'ai'   => 'application/postscript',
			'eps'  => 'application/postscript',
			'ps'   => 'application/postscript',
			// ms office
			'doc'  => 'application/msword',
			'rtf'  => 'application/rtf',
			'xls'  => 'application/vnd.ms-excel',
			'ppt'  => 'application/vnd.ms-powerpoint',
			// open office
			'odt'  => 'application/vnd.oasis.opendocument.text',
			'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
		);

		$ext = explode( '.', $filename );
		$ext = array_pop( $ext );
		$ext = strtolower( $ext );
		if ( array_key_exists( $ext, $mime_types ) ) {
			return $mime_types[ $ext ];
		} elseif ( function_exists( 'finfo_open' ) ) {
			$finfo    = finfo_open( FILEINFO_MIME );
			$mimetype = finfo_file( $finfo, $filename );
			finfo_close( $finfo );

			return $mimetype;
		} else {
			return 'application/octet-stream';
		}
	}
}

/**
 * Output column data
 *
 * @param  array $column
 * @param  array $data
 *
 * @return void
 */
function log_content( $column, $data ) {

	switch ( $column ) {
		case 'attachments':
			if ( isset( $data['attachments'] ) ) {

				//
				$attachments = 0;
				foreach ( $data['attachments'] as $result ) {
					if ( $result['status'] == 'E' ) {

						// error
						echo "Error: " . $result['msg'];
					} elseif ( $result['status'] == 'S' ) {

						$attachments ++;
					}
				}

				if ( $attachments > 0 ) {
					if ( $attachments == 1 ) {
						echo $attachments . ' Attachment Inserted';
					} else {
						echo $attachments . ' Attachments Inserted';
					}

				}

			} else {
				echo 'No Attachments Inserted';
			}

			break;
		case 'taxonomies':
			if ( isset( $data['taxonomies'] ) ) {

				foreach ( $data['taxonomies'] as $tax => $terms ) {
					echo "<strong>{$tax}</strong>: " . implode( ',', $terms ) . '<br />';
				}
			} else {
				echo 'No Taxonomies Inserted';
			}
			break;
	}
}

function jci_error_message($e){

	/**
	 * @global JC_Importer $jcimporter
	 */
	global $jcimporter;

	if($jcimporter->is_debug()){
		return $e->getMessage() . ' in file ' . $e->getFile() . ' on line ' . $e->getLine();
	}else{
		return $e->getMessage();
	}
}

function jci_display_messages(){

	// output messages
	if ( isset( $_GET['message'] ) && $_GET['message'] >= 0 ) {

		switch ( intval( $_GET['message'] ) ) {
			case 0:
				// success in uploading and creating importer
				echo '<div id="message" class="error_msg warn updated below-h2"><p>Importer Has been Created, Enter the fields or columns you wish to map the data to.</p></div>';
				break;
			case 1:
				// save importer settings
				echo '<div id="message" class="error_msg warn updated below-h2"><p>Importer Settings has been saved</p></div>';
				break;
			case 2:
				// save importer settings
				echo '<div id="message" class="error_msg warn updated below-h2"><p>Importer has been deleted</p></div>';
				break;
		}
	}
}

/**
 * Fetch all posts
 */
function jci_get_post_list($post_type = ''){

	$posts = new WP_Query(array(
		'post_type' => $post_type,
		'posts_per_page' => -1
	));

	$ordered_posts = array(null => 'None');

	if($posts->have_posts()){
		while($posts->have_posts()){
			$posts->the_post();
			$ordered_posts[get_the_ID()] = get_the_title();
		}
		wp_reset_postdata();
	}

	return $ordered_posts;
}

/**
 * Fetch all users
 */
function jci_get_user_list(){

	$users = get_users(array('fields' => 'all'));
	$temp_list = array();
	foreach($users as $u){
		$temp_list[$u->data->ID] = $u->data->user_nicename;
	}

	return $temp_list;
}