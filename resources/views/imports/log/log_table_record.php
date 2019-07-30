<?php
$template = IWP_Importer_Settings::getImportSettings( $importer_id, 'template' );
$columns  = apply_filters( "jci/log_{$template}_columns", array() );

// print_r($columns);

// attach default columns for attachments 
add_action( "jci/log_{$template}_content", 'log_content', 5, 2 );

$error = false;

if ( ! is_array( $data ) ) {
	$error = 'No Record Data';
} else {
	$data = array_shift( $data );
}

if ( isset( $data['_jci_status'] ) && $data['_jci_status'] != 'S' ) {
	$error = $data['_jci_msg'];
}

if ( ! $error ) {

	if ( ! $error && $data['_jci_status'] == 'S' ) {
		// success
		?>
        <tr>
            <td><?php echo esc_html($row); ?></td>
			<?php foreach ( $columns as $key => $col ): ?>
                <td><?php do_action( "jci/log_{$template}_content", $key, $data ); ?></td>
			<?php endforeach; ?>
        </tr>
		<?php

	}

}

// display error message
if ( $error !== false ) {
	?>
    <tr>
        <td colspan="<?php echo count( $columns ) + 1; ?>">Error: <?php echo esc_html($error); ?></td>
    </tr>
	<?php
}