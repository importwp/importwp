<?php
class IWP_Base_Template extends JC_Importer_Template{

	/**
	 * @var string $_group
	 */
	private $_group;

	/**
	 * @var string $_import_type Template Map (post, tax, user)
	 */
	private $_import_type;

	/**
	 * @var string $_import_type_name
	 */
	private $_import_type_name;

	/**
	 * @var array $_fields List of template fields
	 */
	private $_fields = [];

	/**
	 * @var int $_template_version
	 */
	protected $_template_version;

	public function __construct($group, $import_type, $import_type_name, $settings = array()) {

		$this->_group = $group;
		$this->_import_type = $import_type;
		$this->_import_type_name = $import_type_name;

		$this->_field_groups = array(
			$group => array(
				'import_type' => $import_type,
				'import_type_name' => $import_type_name,
				'field_type' => 'single',
				'post_status' => isset($settings['post_status']) ? $settings['post_status'] : 'any',
				'group' => $group,
				'key' => isset($settings['key']) ? $settings['key'] : array(),
				'relationship' => array(),
				'attachments' => isset($settings['attachments']) ? $settings['attachments'] : 0,
				'taxonomies' => isset($settings['taxonomies']) ? $settings['taxonomies'] : 0,
				'map' => array()
			)
		);

		parent::__construct();

		add_filter( 'jci/before_' . $this->get_name() . '_group_save', array( $this, 'remove_virtual_fields' ), 100, 1 );
		add_action( 'iwp_after_row_save', array( $this, 'process_attachment_fields' ), 5, 3 );
	}

	protected function register_basic_field($label, $key, $args = array()){

		$field = $args;
		$field['title'] = $label;
		$field['field'] = $key;

		// Backwards compatibility
		$this->_field_groups[$this->_group]['map'][] = $field;

		$this->_fields[$key] = $field;
	}

	/**
	 * Register a field that is not mapped
	 *
	 * @param $label
	 * @param $key
	 * @param array $args
	 */
	protected function register_virtual_field($label, $key, $args = array()){

		$args['virtual'] = true;
		$this->register_basic_field($label, $key, $args);
	}

	/**
	 * Register an attachment field
	 *
	 * @param $label
	 * @param $key
	 * @param array $args
	 */
	protected function register_attachment_field($label, $key, $args = array()){
		$args['type'] = 'attachment';
		$this->register_basic_field($label, $key, $args);
	}

	/**
	 * Remove all virtual fields before the data is mapped
	 *
	 * @param $data
	 *
	 * @return array
	 */
	public function remove_virtual_fields($data){
		$virtual_fields = $this->get_virtual_fields();
		if(!empty($virtual_fields)){
			foreach($virtual_fields as $field){
				if(isset($data[$field['field']])){
					unset($data[$field['field']]);
				}
			}
		}

		return $data;
	}

	/**
	 * @param JC_Importer_Template $template
	 * @param \ImportWP\Importer\ParsedData $data
	 * @param \ImportWP\Importer $importer
	 */
	public function process_attachment_fields($template, $data, $importer){

	    if($template->get_name() !== $this->get_name()){
	        return;
        }

		$mapper = $importer->getMapper();
		if ( ! method_exists( $mapper, 'update_custom_field' ) ) {
			return;
		}

		$core_fields   = $data->getData();

	    foreach($this->_fields as $key => $field){
	        $type = isset($field['type']) ? $field['type'] : 'default';
	        if($type === 'attachment'){

		        $field_value = $core_fields[$key];
		        $attachment_download = $this->get_field_value($key.'_attachment_download');
                $attachment_value = $this->get_field_value($key.'_attachment_value');
		        $attachment_return = $this->get_field_value($key.'_attachment_return');
		        $attachment_base_url = $this->get_field_value($key.'_attachment_base_url');
		        $attachment_ftp_server = $this->get_field_value($key.'_attachment_ftp_server');
		        $attachment_ftp_user = $this->get_field_value($key.'_attachment_ftp_user');
		        $attachment_ftp_pass = $this->get_field_value($key.'_attachment_ftp_pass');
		        $attachment_feature_first = $this->get_field_value($key.'_attachment_feature_first');

		        $feature = false;
		        if($attachment_feature_first == 1){
		            $feature = true;
                }

		        $output = array();
		        $values = array();

		        if($attachment_value === 'csv'){
			        $values = explode(',', $field_value);
		        }else{
			        $values[] = $field_value;
		        }

		        foreach($values as $row_value){

			        $row_value = trim($attachment_base_url) . trim($row_value);
			        $class               = null;
			        switch ( $attachment_download ) {
				        case 'local':
					        $class           = new JCI_Local_Attachments();
					        $class->set_local_dir( '' );
					        break;
				        case 'url':
					        $class            = new JCI_CURL_Attachments();
					        break;
				        case 'ftp':
					        $ftp_server = $attachment_ftp_server;
					        $ftp_user   = $attachment_ftp_user;
					        $ftp_pass   = $attachment_ftp_pass;
					        $class      = new JCI_FTP_Attachments( $ftp_server, $ftp_user, $ftp_pass );
					        break;
			        }

			        $attachment = $class->attach_remote_file( $core_fields['ID'], $row_value,
				        basename( $row_value ), array(
					        'feature'           => $feature,
					        'restrict_filetype' => false
				        ) );
			        $value      = '';
			        if ( $attachment && intval( $attachment['id'] ) > 0 ) {
				        if ( $attachment_return == 'id' ) {
					        $value = $attachment['id'];
				        } else {
					        $value = wp_get_attachment_url( $attachment['id'] );
				        }
			        }

			        if(!empty($value)){
				        $feature = false;
				        $output[] = $value;
			        }
		        }

		        $mapper->update_custom_field( $core_fields['ID'], $key, implode(',', $output) );
            }
        }
    }

	/**
	 * Filter registered virtual fields
	 *
	 * @return array
	 */
	private function get_virtual_fields(){

		$result = array();
		if(!empty($this->_field_groups[$this->_group]['map'])){
			foreach($this->_field_groups[$this->_group]['map'] as $field){
				if(isset($field['virtual']) && $field['virtual'] === true){
					$result[] = $field;
				}
			}
		}
		return $result;
	}

	public function get_template_version(){
		return $this->_template_version;
	}

	public function display_fields(){
		$groups = JCI()->importer->get_template_groups();
		$group = $groups[$this->_group];

		foreach ( $group['fields'] as $key => $value ) {
			switch($this->get_field_type($key)){
				case 'attachment':
				    ?>
                    <div class="iwp-attachment__wrapper">
                    <?php
					$this->display_field($key, $value);
					?>
					<div class="iwp-attachment__settings">
						<?php
						echo JCI_FormHelper::checkbox('field[' . $this->_group . ']['.$key.'_attachment_feature_first]', array(
							'label' => 'Set first image as featured image',
							'checked' => $this->get_field_value($key.'_attachment_feature_first', 0)
						));
						?>
						<?php echo JCI_FormHelper::select('field[' . $this->_group . ']['.$key.'_attachment_download]',
							array(
								'options' => array('ftp' => 'FTP', 'url' => 'Remote URL', 'local' => 'Local Filesystem'),
								'label'   => 'Download',
								'default' => $this->get_field_value($key.'_attachment_download', 'url'),
								'class'   => 'iwp__attachment-type iwp__show-attachment'
							)); ?>
                        <?php
                        $return_value = isset($this->_fields[$key]['attachment_value']) ? $this->_fields[$key]['attachment_value'] : array('single' => 'Single Value', 'csv' => 'Comma separated values');
                        if(is_array($return_value)):
                            echo JCI_FormHelper::select('field[' . $this->_group . ']['.$key.'_attachment_value]', array(
                                'options' => $return_value,
                                'label'   => 'Value',
                                'default' => $this->get_field_value($key.'_attachment_value', 'single'),
                                'class'   => 'iwpcf__field-values iwpcf__show-attachment'
                            ));
                        else:
                            echo JCI_FormHelper::hidden('field[' . $this->_group . ']['.$key.'_attachment_value]', array('value' => $return_value));
                        endif;
                        ?>
						<?php
						$return_value = isset($this->_fields[$key]['attachment_return_value']) ? $this->_fields[$key]['attachment_return_value'] : array('url' => 'Url', 'id' => 'ID');
                        if(is_array($return_value)):
                            echo JCI_FormHelper::select('field[' . $this->_group . ']['.$key.'_attachment_return]', array(
                                'options' => array('url' => 'Url', 'id' => 'ID'),
                                'label'   => 'Return Value',
                                'default' => $this->get_field_value($key.'_attachment_return', 'url'),
                                'class'   => 'iwpcf__return-type iwpcf__show-attachment'
                            ));
                        else:
                            echo JCI_FormHelper::hidden('field[' . $this->_group . ']['.$key.'_attachment_return]', array('value' => $return_value));
                        endif;
                        ?>
						<div class="iwp__attachment iwp-attachment--ftp iwp__show-attachment iwp__show-attachment-url iwp__show-attachment-local iwp__show-attachment-ftp">
							<?php echo JCI_FormHelper::text('field[' . $this->_group . ']['.$key.'_attachment_base_url]',
								array(
									'label'   => 'Base Url',
									'default' => $this->get_field_value($key.'_attachment_base_url')
								)); ?>
						</div>
						<div class="iwp__attachment iwp-attachment--ftp iwp__show-attachment iwp__show-attachment-ftp">
							<?php echo JCI_FormHelper::text('field[' . $this->_group . ']['.$key.'_attachment_ftp_server]',
								array(
									'label'   => 'FTP Server',
									'default' => $this->get_field_value($key.'_attachment_ftp_server')
								)); ?>
							<?php echo JCI_FormHelper::text('field[' . $this->_group . ']['.$key.'_attachment_ftp_user]',
								array(
									'label'   => 'FTP User',
									'default' => $this->get_field_value($key.'_attachment_ftp_user')
								)); ?>
							<?php echo JCI_FormHelper::text('field[' . $this->_group . ']['.$key.'_attachment_ftp_pass]',
								array(
									'label'   => 'FTP Pass',
									'default' => $this->get_field_value($key.'_attachment_ftp_pass')
								)); ?>
						</div>
					</div>
                    </div>
					<?php
					break;
				case 'default':
				default:
					$this->display_field($key, $value);
					break;
			}
		}
	}

	public function display_field($key, $value, $settings = array()){

		echo JCI_FormHelper::text( 'field[' . $this->_group . '][' . $key . ']', array(
			'label'   => $this->get_field_title($key),
			'tooltip' => $this->get_field_tooltip($key),
			'default' => esc_attr($value),
			'class'   => 'xml-drop jci-group',
			'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
			'data'    => array(
				'jci-field' => $key,
			)
		) );
	}

	private function get_field_tooltip($key){
		return isset($this->_fields[$key]['tooltip']) ? $this->_fields[$key]['tooltip'] : sprintf( JCI()->text()->get( sprintf( 'template.default.%s', $key ) ), $this->_import_type_name );
	}

	private function get_field_title($key){
		return isset($this->_fields[$key]['title']) ? $this->_fields[$key]['title'] : $key;
	}

	private function get_field_type($key){
		return isset($this->_fields[$key]['type']) ? $this->_fields[$key]['type'] : 'default';
	}

	private function get_field_value($key, $default = ''){
		$fields = ImporterModel::getImporterMeta( JCI()->importer->get_ID(), 'fields' );
		return isset($fields[$this->_group][$key]) ? $fields[$this->_group][$key] : $default;
    }
}