<?php
function create_csv_importer($post_id = null, $template = '', $file = '', $fields){
	
	$name = 'Importer Test';
	$import_type = 'upload';
	$general = array();
    $attachment =  new JC_Attachment();
    $file = $attachment->attach_local_file($file);

	// init importer
	if(is_null($post_id)){
		$post_id = ImporterModel::insertImporter(0, array('name' => $name));
	}
	

	// add csv file to importer
	$attach_id = $attachment->wp_insert_attachment($post_id, $file);

	$result = array(
		'type' => 'csv',
		'id' => $attach_id
	);
	
	// save importer and set csv file
	$post_id = ImporterModel::insertImporter($post_id, array(
        'name' => $name,
        'settings' => array(
            'import_type' => $import_type,
            'template' => $template,
            'template_type' => $result['type'],
            'import_file' => $result['id'],
            'general' => $general,
            'permissions' => array('create' => 1, 'update' => 1, 'delete' => 0)
        ),
        'fields' => $fields,
    )); 

    return $post_id;

}

function create_xml_importer($post_id = null, $template = '', $file = '', $fields = array(), $parser_settings = array()){

	// setup vars
    $name = 'Importer Test';
    $import_type = 'upload';
    $general = array();
    $attachment =  new JC_Attachment();
    $file = $attachment->attach_local_file($file);


    // init importer
    if(is_null($post_id)){
	    $post_id = ImporterModel::insertImporter(0, array('name' => $name));
	}

    // add file to importer
    $attach_id = $attachment->wp_insert_attachment($post_id, $file);

    $result = array(
        'type' => 'xml',
        'id' => $attach_id
    );
    
    // save importer and set csv file
    $post_id = ImporterModel::insertImporter($post_id, array(
        'name' => $name,
        'settings' => array(
            'import_type' => $import_type,
            'template' => $template,
            'template_type' => $result['type'],
            'import_file' => $result['id'],
            'general' => $general,
            'permissions' => array('create' => 1, 'update' => 1, 'delete' => 0)
        ),
        'fields' => $fields,
    ));  

    ImporterModel::setImporterMeta($post_id, '_parser_settings', $parser_settings );

    return $post_id;

}
?>