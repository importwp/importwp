<?php

/**
 * Abstract Template class
 *
 * All templates extend this class, initializing core template hooks
 * @since v0.0.1
 */
abstract class IWP_Template {

	public $_name;

	public $_field_groups = array();

	/**
	 * List of identifier fields
	 * @var array $_unique
	 */
	public $_unique = array();

	/**
	 * @var string $_group
	 */
	protected $_group;

	/**
	 * @var string $_import_type Template Map (post, tax, user)
	 */
	protected $_import_type;

	/**
	 * @var string $_import_type_name
	 */
	protected $_import_type_name;

	/**
	 * @var array $_fields List of template fields
	 */
	protected $_fields = [];

	/**
	 * @var array $_settings
	 */
	protected $_settings = [];

	/**
	 * @var int $_template_version
	 */
	protected $_template_version;

	protected $_virtual_fields = [];

	protected $_repeater_fields = [];

	protected $_repeater_values = [];

	protected $_sections = [];

	protected $_enable_fields = [];

	public function __construct() {

		$this->_field_groups = array(
			$this->_group => array(
				'import_type' => $this->_import_type,
				'import_type_name' => $this->_import_type_name,
				'field_type' => 'single',
				'post_status' => isset($this->_settings['post_status']) ? $this->_settings['post_status'] : 'any',
				'group' => $this->_group,
				'key' => isset($this->_settings['key']) ? $this->_settings['key'] : array(),
				'relationship' => array(),
				'attachments' => isset($this->_settings['attachments']) ? $this->_settings['attachments'] : 0,
				'taxonomies' => isset($this->_settings['taxonomies']) ? $this->_settings['taxonomies'] : 0,
				'map' => array()
			)
		);

		add_action( 'jci/before_' . $this->get_name() . '_row_save', array(
			$this,
			'alter_template_unique_fields'
		), 1, 0 );

		// before record gets imported
		if ( method_exists( $this, 'before_template_save' ) ) {
			add_action( 'jci/before_' . $this->get_name() . '_row_save', array(
				$this,
				'before_template_save'
			), 10, 2 );
		}

		// before group save
		if ( method_exists( $this, 'before_group_save' ) ) {
			add_filter( 'jci/before_' . $this->get_name() . '_group_save', array( $this, 'before_group_save' ), 10, 2 );
		}

		// after group save
		if ( method_exists( $this, 'after_group_save' ) ) {
			add_filter( 'jci/after_' . $this->get_name() . '_group_save', array( $this, 'after_group_save' ), 10, 2 );
		}

		// after record has been imported
		if ( method_exists( $this, 'after_template_save' ) ) {
			add_action( 'jci/after_' . $this->get_name() . '_row_save', array( $this, 'after_template_save' ), 10, 2 );
		}

		add_filter( 'jci/before_' . $this->get_name() . '_group_save', array( $this, 'remove_virtual_fields' ), 100, 1 );
		add_action( 'iwp_after_row_save', array( $this, 'after_row_import' ), 5, 3 );
		add_action('jci/before_import', array( $this, 'iwp_before_import'));
		add_filter('iwp/js/iwp_settings', array( $this, 'iwp_settings'));

		$this->register_fields();

		do_action('iwp/template/register_fields', $this);
	}

	protected abstract function register_fields();

	public function get_name() {
		return $this->_name;
	}

	public function get_groups() {
		return apply_filters( 'jci/template/get_groups', $this->_field_groups );
	}

	/**
	 * Add in identifier fields to be parsed with data, allowing to be referenced from other fields
	 *
	 * @param array $groups Field Groups
	 *
	 * @return array
	 */
	public function add_reference_fields( $groups = array() ) {

		foreach ( $this->_field_groups as $k => $group ) {
			if ( isset( $groups[ $k ] ) ) {
				if ( isset( $group['identifiers'] ) && ! empty( $group['identifiers'] ) ) {
					foreach ( $group['identifiers'] as $field => $map ) {
						$groups[ $k ]['fields'][ '_jci_ref_' . $field ] = $map;
					}
				}
			}
		}

		return $groups;
	}

	/**
	 * Find existing post/page/custom by reference field
	 *
	 * @param string $field Reference Field Name
	 * @param string $value Value to check against reference
	 * @param string $group_id Template group Id
	 *
	 * @return bool
	 */
	protected function get_post_by_cf( $field, $value, $group_id ) {

		if ( ! isset( $this->_field_groups[ $group_id ] ) ) {
			return false;
		}

		$post_type = $this->_field_groups[ $group_id ]['import_type_name'];

		$query = new WP_Query( array(
			'post_type'      => $post_type,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'cache_results' => false,
			'update_post_meta_cache' => false,
			'meta_query'     => array(
				array(
					'key'   => '_jci_ref_' . $field,
					'value' => $value
				)
			)
		) );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return false;
	}

	public function get_template_group_id(){

		reset( $this->_field_groups );
		return key( $this->_field_groups );
	}

	final public function alter_template_unique_fields(){

		// Allow user to overwrite unique field without using filter.
		$unique_field = JCI()->importer->get_template_unique_field();
		if(!empty($unique_field)){
			$this->_unique = explode(',', $unique_field);
		}

		$this->_unique = apply_filters('iwp/template_unique_fields', $this->_unique);
		$this->_unique = apply_filters(sprintf('iwp/template_%s_unique_fields', $this->get_name()), $this->_unique);
	}

	public function iwp_settings($settings){
		$settings['enable_fields'] = $this->_enable_fields;
		return $settings;
	}

	public function iwp_before_import(){
		add_filter('jci/importer/get_groups', array($this, 'importer_get_groups'));
	}

	public function importer_get_groups($groups){

		if(!empty($this->_repeater_fields)){
			foreach($this->_repeater_fields as $repeater_key => $repeater_field){

				$repeater_values = $this->get_repeater_values($repeater_key);
				$groups[$this->get_group()]['fields'][sprintf('_iwpr_%s', $repeater_key)] = count($repeater_values);
				foreach($repeater_values as $index => $field){
					foreach($field as $field_k => $field_v){
						$field_key = sprintf('_iwpr_%s_%d_%s',$repeater_key, $index, $field_k);
						$groups[$this->get_group()]['fields'][$field_key] = $field_v;
					}
				}
			}
		}

		return $groups;
	}

	public function register_section($label, $key){
		$this->_sections[$key] = array(
			'label' => $label,
			'key' => $key
		);
	}

	public function register_basic_field($label, $key, $args = array()){

		$field = $args;
		$field['title'] = $label;
		$field['field'] = $key;
		$field['section'] = isset($args['section']) ? $args['section'] : 'default';

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
	public function register_virtual_field($label, $key, $args = array()){

		$args['virtual'] = true;
		$this->register_basic_field($label, $key, $args);
	}

	public function register_hidden_field($label, $key, $args = array()){

		$args['hidden'] = true;
		$this->register_virtual_field($label, $key, $args);
	}

	/**
	 * Register an attachment field
	 *
	 * @param $label
	 * @param $key
	 * @param array $args
	 */
	public function register_attachment_field($label, $key, $args = array()){
		$args['type'] = 'attachment';
		$this->register_basic_field($label, $key, $args);
	}

	public function register_repeater_field($label, $key, $fields, $args = array()){
		$this->register_basic_field($label, $key, array(
			'type' => 'repeater',
			'section' => isset($args['section']) ? $args['section'] : 'default',
		));
		$this->_repeater_fields[$key] = array(
			'label' => $label,
			'key' => $key,
			'process_callback' => isset($args['process_callback']) ? $args['process_callback'] : null,
			'process_callback_after' => isset($args['process_callback_after']) ? $args['process_callback_after'] : null,
			'section' => isset($args['section']) ? $args['section'] : 'default',
			'fields' => $fields
		);
	}

	public function register_repeater_sub_field($label, $key, $args = array()){
		return array(
			'label' => $label,
			'key' => $key,
			'default' => isset($args['default']) ? $args['default'] : '',
			'tooltip' => isset($args['tooltip']) ? $args['tooltip'] : '',
			'type' => isset($args['type']) ? $args['type'] : 'text',
			'options' => isset($args['options']) ? $args['options'] : array(),
		);
	}

	public function register_enable_toggle($label, $key, $section = 'default', $fields = array()){
		$args = array(
			'type' => 'checkbox',
			'label' => $label,
			'section' => $section,
		);

		if(!isset($this->_enable_fields[$key])){
			$this->_enable_fields[$key] = array();
		}
		$this->_enable_fields[$key] = array_merge($this->_enable_fields[$key], $fields);

		$this->register_virtual_field($label, $key, $args);
	}

	public function register_checkbox($label, $key, $section = 'default'){
		$this->register_virtual_field($label, $key, array(
			'type' => 'checkbox',
			'section' => $section
		));
	}

	/**
	 * Remove all virtual fields before the data is mapped
	 *
	 * @param $data
	 *
	 * @return array
	 */
	public final function remove_virtual_fields($data){

		// remove fields that are not enabled
		if(!empty($this->_enable_fields)){
			foreach($this->_enable_fields as $enable_field => $fields){
				if( isset($data[$enable_field]) && $data[$enable_field] !== '1' && !empty($fields) ){
					foreach($fields as $field_key){
						if(isset($data[$field_key])){
							unset($data[$field_key]);
						}
					}
				}
			}
		}

		// Remove Virtual Fields
		$this->_virtual_fields = [];
		$virtual_fields = $this->get_virtual_fields();
		if(!empty($virtual_fields)){
			foreach($virtual_fields as $field){
				if(isset($data[$field['field']])){
					$this->_virtual_fields[$field['field']] = $data[$field['field']];
					unset($data[$field['field']]);
				}
			}
		}

		$this->process_repeater_fields($data);
		$this->process_repeater_callback('process_callback');

		return $data;
	}

	/**
	 * @param IWP_Template $template
	 * @param \ImportWP\Importer\ParsedData $data
	 * @param \ImportWP\Importer $importer
	 */
	public final function after_row_import($template, $data, $importer){

		$this->process_repeater_callback('process_callback_after', $data->getData());

		$this->process_attachment_fields($template, $data, $importer);

		do_action('iwp/template/after_row_import', $data, $template, $importer);
	}

	/**
	 * @param IWP_Template $template
	 * @param \ImportWP\Importer\ParsedData $data
	 * @param \ImportWP\Importer $importer
	 */
	public final function process_attachment_fields($template, $data, $importer){

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

				if($field['virtual'] === true) {
					$field_value = $this->get_virtual_field($key);
				}else{
					$field_value = $core_fields[$key];
				}

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
							$class           = new IWP_Attachment_Local();
							$class->set_local_dir( '' );
							break;
						case 'url':
							$class            = new IWP_Attachment_CURL();
							break;
						case 'ftp':
							$ftp_server = $attachment_ftp_server;
							$ftp_user   = $attachment_ftp_user;
							$ftp_pass   = $attachment_ftp_pass;
							$class      = new IWP_Attachment_FTP( $ftp_server, $ftp_user, $ftp_pass );
							break;
					}

					$attachment = $class->attach_remote_file( $core_fields['ID'], $row_value,
						basename( $row_value ), array(
							'feature'           => $feature,
							'restrict_filetype' => false
						) );
					$value      = '';

					if ( $attachment && intval( $attachment['id'] ) > 0 ) {

						$attachment_data = array(
							'id' => $attachment['id'],
							'url' => wp_get_attachment_url( $attachment['id'] )
						);

						if($field['virtual'] === true) {
							$value = $attachment_data;
						}else{
							if ( $attachment_return == 'id' ) {
								$value = $attachment_data['id'];
							} else {
								$value = $attachment_data['url'];
							}
						}
					}

					if(!empty($value)){
						$feature = false;
						$output[] = $value;
					}
				}

				if($field['virtual'] === true){
					$this->_virtual_fields[$key] = $output;
				}else{
					$mapper->update_custom_field( $core_fields['ID'], $key, implode(',', $output) );
				}
			}
		}
	}

	/**
	 * @param array $data
	 */
	protected final function process_repeater_fields(&$data){

		// TODO: Loop through repeater groups and format data
		$this->_repeater_values = array();
		foreach($this->_repeater_fields as $repeater_id => $repeater_field){

			$this->_repeater_values[$repeater_id] = array();

			foreach($data as $data_key => $data_value) {
				$matches = false;
				if ( preg_match( '/^_iwpr_' . $repeater_id . '_([0-9]+)_([a-zA-Z_-]+)$/', $data_key, $matches ) === 1 ){

					if(!isset($this->_repeater_values[$repeater_id][$matches[1]])){
						$this->_repeater_values[$repeater_id][$matches[1]] = array();
					}

					$this->_repeater_values[$repeater_id][$matches[1]][$matches[2]] = $data_value;
				}
			}
		}

		// Remove Repeater Fields (field prefix: _iwpr_)
		foreach(array_keys($data) as $k){
			if(strpos($k, '_iwpr_') === 0){
				unset($data[$k]);
			}
		}
	}

	private final function process_repeater_callback($callback = 'process_callback', $data = array()){

		foreach($this->_repeater_fields as $repeater_id => $repeater_field){

			// If repeater has a callback trigger it
			if($repeater_field[$callback] != null){
				foreach($this->_repeater_values[$repeater_id] as $row){
					call_user_func_array($repeater_field[$callback], array($row, $data));
				}
			}
		}

	}

	/**
	 * Filter registered virtual fields
	 *
	 * @return array
	 */
	private final function get_virtual_fields(){

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

	public function display_fields($section = 'default'){
		$groups = JCI()->importer->get_template_groups();
		$group = $groups[$this->_group];

		foreach ( $group['fields'] as $key => $value ) {

			if($this->get_field_section($key) !== $section){
				continue;
			}

			switch($this->get_field_type($key)){
				case 'checkbox':
					include JCI()->get_plugin_dir() . 'views/fields/checkbox.php';
					break;
				case 'repeater':
					$repeater = $this->get_repeater_field($key);
					include JCI()->get_plugin_dir() . 'views/fields/repeater.php';
					break;
				case 'attachment':
					include JCI()->get_plugin_dir() . 'views/fields/attachment.php';
					break;
				case 'default':
				default:
					$this->display_field($key, $value);
					break;
			}
		}
	}

	public function display_sections(){
		foreach(array_keys($this->_sections) as $section){
			$this->display_section($section);
		}
	}

	public function display_section($section){
		echo sprintf('<div class="jci-group-fields jci-group-section" data-section-id="%s">', $section);
		$this->display_fields($section);
		echo '</div>';
	}

	public function display_field($key, $value, $settings = array()){
		include JCI()->get_plugin_dir() . 'views/fields/text.php';
	}

	public function get_field_tooltip($key){
		return isset($this->_fields[$key]['tooltip']) ? $this->_fields[$key]['tooltip'] : sprintf( JCI()->text()->get( sprintf( 'template.default.%s', $key ) ), $this->_import_type_name );
	}

	public function get_field_title($key){
		return isset($this->_fields[$key]['title']) ? $this->_fields[$key]['title'] : $key;
	}

	public function get_field_type($key){
		return isset($this->_fields[$key]['type']) ? $this->_fields[$key]['type'] : 'default';
	}

	public function get_field_section($key){
		return isset($this->_fields[$key]['section']) ? $this->_fields[$key]['section'] : 'default';
	}

	public function get_field_value($key, $default = ''){
		$fields = IWP_Importer_Settings::getImporterMeta( JCI()->importer->get_ID(), 'fields' );
		return isset($fields[$this->_group][$key]) ? $fields[$this->_group][$key] : $default;
	}

	public function get_group(){
		return $this->_group;
	}

	public function get_virtual_field($key){
		return isset($this->_virtual_fields[$key]) ? $this->_virtual_fields[$key] : false;
	}

	public function get_repeater_field($key){
		return isset($this->_repeater_fields[$key]) ? $this->_repeater_fields[$key] : array();
	}

	public function get_repeater_values($key){
		$fields = IWP_Importer_Settings::getImporterMeta( JCI()->importer->get_ID(), 'fields' );
		$result = array();
		foreach($fields[$this->get_group()] as $k => $v){
			$matches = [];
			if(preg_match('/_iwpr_'.$key.'_(\d+)_(\w+)/', $k, $matches) === 1){
				if(!isset($result[$matches[1]])){
					$result[$matches[1]] = array();
				}

				$result[$matches[1]][$matches[2]] = $v;
			}
		}

		return $result;
	}

	public function get_import_type_name(){
		return $this->_import_type_name;
	}

	public function get_import_type(){
		return $this->_import_type;
	}
}