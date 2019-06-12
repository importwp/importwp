<?php
/**
 * Attachment field template
 *
 * @var string $key
 * @var string $value
 * @var IWP_Template $this
 */
?>
<div class="iwp-attachment__wrapper">
	<?php
	$this->display_field($key, $value);
	?>
	<div class="iwp-attachment__settings iwp-field-toggle-wrapper">
		<?php
		echo IWP_FormBuilder::checkbox( 'field[' . $this->_group . '][' . $key . '_attachment_feature_first]', array(
			'label' => 'Set first image as featured image',
			'checked' => $this->get_field_value($key.'_attachment_feature_first', 0)
		));
		?>
		<?php echo IWP_FormBuilder::select( 'field[' . $this->_group . '][' . $key . '_attachment_download]',
			array(
				'options' => array('ftp' => 'FTP', 'url' => 'Remote URL', 'local' => 'Local Filesystem'),
				'label'   => 'Download',
				'default' => $this->get_field_value($key.'_attachment_download', 'url'),
				'class'   => 'iwp-field-toggle-trigger'
			)); ?>
		<?php
		$return_value = isset($this->_fields[$key]['attachment_permission']) ? $this->_fields[$key]['attachment_permission'] : array('all' => 'Download all attachments', 'new' => 'Download new attachments');
		if(is_array($return_value)) {
		    $default = isset($this->_fields[$key]['attachment_permission_default']) ? $this->_fields[$key]['attachment_permission_default'] : 'new';
			echo IWP_FormBuilder::select( 'field[' . $this->_group . '][' . $key . '_attachment_permission]',
				array(
					'options' => array( 'all' => 'Download all attachments', 'new' => 'Download new attachments' ),
					'label'   => 'Permissions',
					'default' => $this->get_field_value( $key . '_attachment_permission', $default ),
					'class'   => 'iwp__attachment-permission'
				)
            );
		}
		?>
		<?php
		$return_value = isset($this->_fields[$key]['attachment_value']) ? $this->_fields[$key]['attachment_value'] : array('single' => 'Single Value', 'csv' => 'Comma separated values');
		if(is_array($return_value)):
			echo IWP_FormBuilder::select( 'field[' . $this->_group . '][' . $key . '_attachment_value]', array(
				'options' => $return_value,
				'label'   => 'Value',
				'default' => $this->get_field_value($key.'_attachment_value', 'single'),
				'class'   => 'iwp__attachment-value'
			));
		else:
			echo IWP_FormBuilder::hidden( 'field[' . $this->_group . '][' . $key . '_attachment_value]', array( 'value' => $return_value));
		endif;
		?>
		<?php
		$return_value = isset($this->_fields[$key]['attachment_return_value']) ? $this->_fields[$key]['attachment_return_value'] : array('url' => 'Url', 'id' => 'ID');
		if(is_array($return_value)):
			echo IWP_FormBuilder::select( 'field[' . $this->_group . '][' . $key . '_attachment_return]', array(
				'options' => array('url' => 'Url', 'id' => 'ID'),
				'label'   => 'Return Value',
				'default' => $this->get_field_value($key.'_attachment_return', 'url'),
				'class'   => 'iwpcf__return-type iwpcf__show-attachment'
			));
		else:
			echo IWP_FormBuilder::hidden( 'field[' . $this->_group . '][' . $key . '_attachment_return]', array( 'value' => $return_value));
		endif;
		?>
		<div class="iwp-field-toggle-show--url iwp-field-toggle-show--local iwp-field-toggle-show--ftp">
			<?php echo IWP_FormBuilder::text( 'field[' . $this->_group . '][' . $key . '_attachment_base_url]',
				array(
					'label'   => 'Base Url',
					'default' => $this->get_field_value($key.'_attachment_base_url')
				)); ?>
		</div>
		<div class="iwp-field-toggle-show--ftp">
			<?php echo IWP_FormBuilder::text( 'field[' . $this->_group . '][' . $key . '_attachment_ftp_server]',
				array(
					'label'   => 'FTP Server',
					'default' => $this->get_field_value($key.'_attachment_ftp_server')
				)); ?>
			<?php echo IWP_FormBuilder::text( 'field[' . $this->_group . '][' . $key . '_attachment_ftp_user]',
				array(
					'label'   => 'FTP User',
					'default' => $this->get_field_value($key.'_attachment_ftp_user')
				)); ?>
			<?php echo IWP_FormBuilder::text( 'field[' . $this->_group . '][' . $key . '_attachment_ftp_pass]',
				array(
					'label'   => 'FTP Pass',
					'default' => $this->get_field_value($key.'_attachment_ftp_pass')
				)); ?>
		</div>
        <div>
            <p><strong>Attachment Metadata:</strong></p>
	        <?php echo IWP_FormBuilder::text( 'field[' . $this->_group . '][' . $key . '_attachment_alt]',
		        array(
			        'label'   => 'Alternative Text',
			        'default' => $this->get_field_value($key.'_attachment_alt'),
			        'class'   => 'xml-drop',
			        'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
			        'data'    => array(
				        'jci-field' => $key.'_attachment_alt',
			        )
		        )); ?>
	        <?php echo IWP_FormBuilder::text( 'field[' . $this->_group . '][' . $key . '_attachment_title]',
		        array(
			        'label'   => 'Title',
			        'default' => $this->get_field_value($key.'_attachment_title'),
			        'class'   => 'xml-drop',
			        'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
			        'data'    => array(
				        'jci-field' => $key.'_attachment_title',
			        )
		        )); ?>
	        <?php echo IWP_FormBuilder::text( 'field[' . $this->_group . '][' . $key . '_attachment_caption]',
		        array(
			        'label'   => 'Caption',
			        'default' => $this->get_field_value($key.'_attachment_caption'),
			        'class'   => 'xml-drop',
			        'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
			        'data'    => array(
				        'jci-field' => $key.'_attachment_caption',
			        )
		        )); ?>
	        <?php echo IWP_FormBuilder::text( 'field[' . $this->_group . '][' . $key . '_attachment_description]',
		        array(
			        'label'   => 'Description',
			        'default' => $this->get_field_value($key.'_attachment_description'),
			        'class'   => 'xml-drop',
			        'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
			        'data'    => array(
				        'jci-field' => $key.'_attachment_description',
			        )
		        )); ?>
        </div>
	</div>
</div>