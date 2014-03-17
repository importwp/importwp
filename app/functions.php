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

function get_templates($template = ''){
	$post_map = array(
		'post' => array(
			'import_type' => 'post',
			'import_type_name' => 'post',
			'post_status' =>'publish',
			'key' => array('ID'),
			'field_type' => 'single',
			'relationship' => array(),
			'group' => 'post',
			'attachments' => 0,
			'taxonomies' => 1,
			'map' => array(
				array('title' => 'id' , 'field' => 'ID'),
				array('title' => 'Author ID' , 'field' => 'post_author'),
				array('title' => 'Content' , 'field' => 'post_content'),
				array('title' => 'Title' , 'field' => 'post_title'),
				array('title' => 'Excerpt' , 'field' => 'post_excerpt'),
				array('title' => 'Slug' , 'field' => 'post_name', 'unique' => true),
				// array('title' => 'Post Status' , 'field' => 'post_status'),
				// array('title' => 'Post Type' , 'field' => 'post_type'),
			)
		)
	);

	$csv_map = array(
		'product' => array(
			'import_type' => 'post',
			'import_type_name' => 'post',
			'post_status' => 'publish',
			'field_type' => 'single',
			'group' => 'product',
			'key' => array('id'),
			'relationship' => array(),
			'attachments' => 0,
			'taxonomies' => 1,
			'map' => array(
				array('title' => 'ISBN', 'type' => 'post_meta', 'field' =>'_wpsc_sku', 'unique' => true), 
				array('title' => 'Title', 'type' => 'post', 'field' => 'post_title'), 
				array('title' => 'Sub Heading', 'type' => 'post_meta', 'field' => '_sub_heading'), 
				array('title' => 'Author', 'type' => 'post_meta', 'field' => '_item_author'), 
				array('title' => 'Imprint', 'type' => 'post_meta', 'field' => '_item_imprint'), 
				array('title' => 'Page Count', 'type' => 'post_meta', 'field' => '_item_page_count'), 
				array('title' => 'Barcode', 'type' => 'post_meta', 'field' => '_item_barcode'), 
				array('title' => 'Format', 'type' => 'post_meta', 'field' => '_item_format'), 
				array('title' => 'Series', 'type' => 'post_meta', 'field' => '_item_series'), 
				array('title' => 'Published', 'type' => 'post_meta', 'field' => '_item_pub_date'), 
				array('title' => 'RRP', 'type' => 'post_meta', 'field' => '_wpsc_price'), 
				array('title' => 'Quantity', 'type' => 'post_meta', 'field' => '_wpsc_stock'), 
				array('title' => 'Summary', 'type' => 'post', 'field' => 'post_excerpt'), 
				array('title' => 'Copy', 'type' => 'post',  'field' => 'post_content'), 
			)
		)
	);

	$xml_map = array(
		'order' => array(
			'import_type' => 'post',
			'import_type_name' => 'wpsc-product',
			'post_status' => 'publish',
			'field_type' => 'single',
			'group' => 'order',
			'key' => array('_order_id' ),
			'relationship' => array(),
			'map' => array(
				array('title' => 'Order Id', 'type' => 'post_meta', 'field' => '_order_id', 'unique' => true),
				array('title' => 'Order Date', 'type' => 'post_meta', 'field' => '_order_date'),
				array('title' => 'Order Time', 'type' => 'post_meta', 'field' => '_order_time'),
				array('title' => 'Order Total', 'type' => 'post_meta', 'field' => '_order_total'),
				array('title' => 'Title', 'type' => 'post_meta', 'field' => '_cust_title'),
				array('title' => 'Forname', 'type' => 'post_meta', 'field' => '_cust_forname'),
				array('title' => 'Surname', 'type' => 'post_meta', 'field' => '_cust_surname'),
				array('title' => 'Phone', 'type' => 'post_meta', 'field' => '_cust_phone'),
				array('title' => 'Email', 'type' => 'post_meta', 'field' => '_cust_email'),
			)
		)
	);

	$xml_map_complex = array(
		'order' => array(
			'id' => 'abcd1234',
			'import_type' => 'post',
			'import_type_name' => 'wpsc-product',
			'post_status' => 'publish',
			'group' => 'order',
			'field_type' => 'single',
			'key' => array('ID', '_order_id' ),
			'relationship' => array(),
			'attachments' => 0,
			'taxonomies' => 1,
			'map' => array(
				array('title' => 'Order Id', 'type' => 'post_meta', 'field' => '_order_id', 'unique' => true),
				array('title' => 'Order Date', 'type' => 'post_meta', 'field' => '_order_date'),
				array('title' => 'Order Time', 'type' => 'post_meta', 'field' => '_order_time'),
				array('title' => 'Order Total', 'type' => 'post_meta', 'field' => '_order_total'),
				array('title' => 'Title', 'type' => 'post_meta', 'field' => '_cust_title'),
				array('title' => 'Forname', 'type' => 'post_meta', 'field' => '_cust_forname'),
				array('title' => 'Surname', 'type' => 'post_meta', 'field' => '_cust_surname'),
				array('title' => 'Phone', 'type' => 'post_meta', 'field' => '_cust_phone'),
				array('title' => 'Email', 'type' => 'post_meta', 'field' => '_cust_email'),
			)
		),
		'order_items' => array(
			'id' => 'efgh5678',
			'import_type' => 'post',
			'import_type_name' => 'wpsc-order',
			'post_status' => 'publish',
			'group' => 'order_items',
			'field_type' => 'repeater',
			'key' => array( 'ID' ),
			'attachments' => 0,
			'taxonomies' => 1,
			'relationship' => array(
				'_order_id' => '{order.ID}',
				'post_title' => 'Order ID: {order.ID}',
				'post_name' => '{order.ID}_{this._wpsc_sku}'
			),
			'map' => array(
				array('title' => 'SKU', 'type' => 'post_meta', 'field' => '_wpsc_sku'),
				array('title' => 'Stock', 'type' => 'post_meta', 'field' => '_wpsc_stock'),
				array('title' => 'Vat', 'type' => 'post_meta', 'field' => '_wpsc_vat'),
				array('title' => 'Total', 'type' => 'post_meta', 'field' => '_wpsc_total'),
			)
		)
	);

	$xml_map_complexer = array(
		'order' => array(
			'id' => 'abcd1234',
			'import_type' => 'post',
			'import_type_name' => 'post',
			'post_status' => 'publish',
			'group' => 'order',
			'field_type' => 'single',
			'key' => array('ID','_order_id' ),
			'attachments' => 0,
			'taxonomies' => 1,
			'foreignKey' => array('_user_id'),
			'relationship' => array(
				'_user_id' => '{customer.ID}'
			),
			'map' => array(
				array('title' => 'Order Id', 'field' => '_order_id', 'unique' => true),
				array('title' => 'Order Name', 'field' => 'post_title'),
				array('title' => 'Order Date', 'field' => '_order_date'),
				array('title' => 'Order Time', 'field' => '_order_time'),
				array('title' => 'Order Total', 'field' => '_order_total'),
			)
		),
		'customer' => array(
			'import_type' => 'user',
			'import_type_name' => '',
			'post_status' => 'publish',
			'group' => 'customer',
			'field_type' => 'single',
			'key' => array('ID'),
			'foreignKey' => array(),
			'relationship' => array(),
			'attachments' => 0,
			'taxonomies' => 0,
			'map' => array(
				array('title' => 'Title', 'field' => 'user_title'),
				array('title' => 'Forname', 'field' => 'first_name'),
				array('title' => 'Surname', 'field' => 'last_name'),
				array('title' => 'Username' , 'field' => 'user_login'),
				array('title' => 'Phone', 'field' => 'jabber'),
				array('title' => 'Email', 'field' => 'user_email', 'unique' => true),
			)
		),
		'order_items' => array(
			'id' => 'efgh5678',
			'import_type' => 'post',
			'import_type_name' => 'post',
			'post_status' => 'publish',
			'group' => 'order_items',
			'field_type' => 'repeater',
			'key' => array( 'ID' ),
			'foreignKey' => array('_order_id'),
			'relationship' => array(
				'_order_id' => '{order.ID}',
			),
			'attachments' => 0,
			'taxonomies' => 0,
			'map' => array(
				array('title' => 'SKU', 'field' => '_wpsc_sku'),
				array('title' => 'Order Item:', 'field' => 'post_title'),
				array('title' => 'Stock', 'field' => '_wpsc_stock'),
				array('title' => 'Vat', 'field' => '_wpsc_vat'),
				array('title' => 'Total', 'field' => '_wpsc_total'),
			)
		)
	);

	switch($template){
		case 'xml':
			return $xml_map;
		break;
		case 'xml_complex':
			return $xml_map_complex;
		break;
		case 'xml_complexer':
			return $xml_map_complexer;
		break;
		case 'csv':
			return $csv_map;
		break;
		case 'post':
			return $post_map;
		break;
	}

	return array(
		'xml' => 'XML',
		'xml_complex' => 'XML Complex',
		'xml_complexer' => 'XML Complexer',
 		'csv' => 'CSV',
 		'post' => 'Post'
	);
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