<?php
/**
 * Create csv importer function
 *
 * Generate a basic csv importer, kept for backwards compatability
 *
 * @param  int $post_id
 * @param  string $template
 * @param  string $file
 * @param  array $fields
 *
 * @return int
 */
function create_csv_importer( $post_id = null, $template = '', $file = '', $fields ) {

	$post_id = create_importer( $post_id, array(
		'template'      => $template,
		'file'          => $file,
		'fields'        => $fields,
		'template_type' => 'csv'
	) );

	return $post_id;

}

/**
 * Create XML importer function
 *
 * Generate a basic xml importer, kept for backwards compatability
 *
 * @param  int $post_id
 * @param  string $template
 * @param  string $file
 * @param  array $fields
 * @param  array $parser_settings
 *
 * @return int
 */
function create_xml_importer( $post_id = null, $template = '', $file = '', $fields = array(), $parser_settings = array() ) {

	$post_id = create_importer( $post_id, array(
		'template'      => $template,
		'file'          => $file,
		'fields'        => $fields,
		'template_type' => 'xml'
	) );

	ImporterModel::setImporterMeta( $post_id, '_parser_settings', $parser_settings );

	return $post_id;

}

/**
 * Generate Importers for UnitTests
 *
 * Create any importer
 *
 * @param  int $post_id
 * @param  array $args
 *
 * @return int
 */
function create_importer( $post_id = null, $args = array() ) {

	$name                 = 'Importer Test';
	$import_type          = 'upload';
	$template_type        = 'xml';
	$template             = 'post';
	$general              = array();
	$fields               = array();
	$importer_permissions = array( 'create' => 1, 'update' => 1, 'delete' => 1 );

	// overwrite variables
	extract( $args );

	// init importer
	if ( is_null( $post_id ) ) {
		$post_id = ImporterModel::insertImporter( 0, array( 'name' => $name ) );
	}

	// attach file
	if ( isset( $file ) && ! empty( $file ) ) {
		$attachment = new JC_Attachment();
		$file       = $attachment->attach_local_file( $file );
		$attach_id  = $attachment->wp_insert_attachment( $post_id, $file );

		$result = array(
			'type' => $template_type,
			'id'   => $attach_id
		);
	}

	$settings = array(
		'import_type'   => $import_type,
		'template'      => $template,
		'template_type' => $template_type,
		'permissions'   => $importer_permissions,
		'general'       => $general,
	);

	if ( isset( $result ) ) {
		$settings['import_file'] = $result['id'];
	}

	$post_id = ImporterModel::insertImporter( $post_id, array(
		'name'     => $name,
		'settings' => $settings,
		'fields'   => $fields,
	) );

	return $post_id;
}

?>