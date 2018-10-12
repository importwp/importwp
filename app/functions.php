<?php

/**
 * Get list of installed importer templates
 *
 * @param bool $class_name output class name as array value
 *
 * @return array
 */
function get_template_list( $class_name = true ) {

	$templates = array();
	foreach ( JCI()->get_templates() as $key => $template ) {

		if ( $class_name ) {

			// output class name as value
			$templates[ $key ] = $template;
		} else {

			// output human readable version of key
			$temp              = $key;
			$temp              = str_replace( '-', ' ', $temp );
			$temp              = ucfirst( $temp );
			$templates[ $key ] = $temp;
		}
	}

	// todo: move this to class-importwp-premium
	if ( ! class_exists( 'ImportWP_CustomPostTypes' ) ) {
		$templates['custom-post-type'] = 'Custom Post Type';
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

	return JCI()->get_template( $template );
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

function jci_error_message( $e ) {

	/**
	 * @global JC_Importer $jcimporter
	 */
	global $jcimporter;

	if ( $jcimporter->is_debug() ) {
		return $e->getMessage() . ' in file ' . $e->getFile() . ' on line ' . $e->getLine();
	} else {
		return $e->getMessage();
	}
}

function jci_display_messages() {

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
 * Fetch all users
 */
function jci_get_user_list() {

	$users     = get_users( array( 'fields' => 'all' ) );
	$temp_list = array();
	foreach ( $users as $u ) {
		$temp_list[ $u->data->ID ] = $u->data->user_nicename;
	}

	return $temp_list;
}

function iwp_output_pagination( $current = 1, $max_results, $per_page, $max_show = 4 ) {

	$max = ceil( $max_results / $per_page );

	$start = $current - floor( $max_show / 2 );
	$end   = $current + floor( $max_show / 2 );

	if ( $start < 1 ) {
		$start = 1;
		$end   = $start + $max_show;
	}

	if ( $end > $max ) {
		$end = $max;
	}

	if ( $max >= 1 ): ?>
        <div class="iwp-pagination__wrapper">
            <p>Showing Page <?php echo $current; ?> of <?php echo $max; ?></p>

            <ul class="iwp-pagination">

				<?php if ( $max > $max_show && $current > 2 ): ?>
                    <li class="iwp-pagination__link"><a href="<?php echo remove_query_arg( 'iwp_page' ); ?>">&laquo;</a>
                    </li>
				<?php endif; ?>
				<?php if ( $max > 1 && $current > 1 ): ?>
                    <li class="iwp-pagination__link"><a href="<?php echo add_query_arg( 'iwp_page', $current - 1 ); ?>">&larr;</a>
                    </li>
				<?php endif; ?>
				<?php for ( $i = $start; $i <= $end; $i ++ ): ?>

					<?php if ( $current == $i ): ?>
                        <li class="iwp-pagination__link iwp-pagination__link--active"><span><?php echo $i; ?></span>
                        </li>
					<?php else: ?>
                        <li class="iwp-pagination__link"><a
                                    href="<?php echo add_query_arg( 'iwp_page', $i ); ?>"><?php echo $i; ?></a></li>
					<?php endif; ?>
				<?php endfor; ?>
				<?php if ( $max > 1 && $current < $max ): ?>
                    <li class="iwp-pagination__link"><a href="<?php echo add_query_arg( 'iwp_page', $current + 1 ); ?>">&rarr;</a>
                    </li>
				<?php endif; ?>
				<?php if ( $max > $max_show && $current < $max - 1 ): ?>
                    <li class="iwp-pagination__link"><a
                                href="<?php echo add_query_arg( 'iwp_page', $max ); ?>">&raquo;</a></li>
				<?php endif; ?>
            </ul>
        </div>
	<?php endif;
}

function iwp_return_bytes($val) {
	$val = trim($val);
	$last = strtolower($val[strlen($val)-1]);
	$val = intval($val);
	switch($last) {
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
			$val *= (1024 * 1024 * 1024); //1073741824
			break;
		case 'm':
			$val *= (1024 * 1024); //1048576
			break;
		case 'k':
			$val *= 1024;
			break;
	}

	return $val;
}

/**
 * Fallback for pre WP 4.7 systems that dont have wp_doing_ajax function
 */
if ( ! function_exists( 'wp_doing_ajax' ) ) {

	function wp_doing_ajax() {
		return apply_filters( 'wp_doing_ajax', defined( 'DOING_AJAX' ) && DOING_AJAX );
	}
}