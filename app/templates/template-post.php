<?php
class JC_Post_Template extends JC_Importer_Template{

	public $_name = 'post';
	
	public $_field_groups = array(

		'post' => array(
			'import_type' => 'post',				// which map to use
			'import_type_name' => 'post',			// post_type
			'field_type' => 'single',				// single|repeater
			'post_status' => 'publish',				// default group publish
			'group' => 'post',						// group name, for old time sake
			'unique' => array('post_name'),
			'key' => array( 'ID', 'post_name' ),
			'relationship' => array(),
			'attachments' => 1,
			'taxonomies' => 1,
			'map' => array(
				array(
					'title' => 'ID',
					'field' => 'ID'
				),
				array(
					'title' => 'Content',
					'field' => 'post_content'
				),
				array(
					'title' => 'Slug',
					'field' => 'post_name'
				),
				array(
					'title' => 'Title',
					'field' => 'post_title'
				),
				array(
					'title' => 'Status',
					'field' => 'post_status'
				),
				array(
					'title' => 'Author',
					'field' => 'post_author'
				),
				array(
					'title' => 'Parent',
					'field' => 'post_parent'
				),
				array(
					'title' => 'Order',
					'field' => 'menu_order'
				),
				array(
					'title' => 'Password',
					'field' => 'post_password'
				),
				array(
					'title' => 'Excerpt',
					'field' => 'post_excerpt'
				),
				array(
					'title' => 'Date',
					'field' => 'post_date'
				),
				array(
					'title' => 'Allow Comments',
					'field' => 'comment_status',
					'values' => array(0,1)
				),
				array(
					'title' => 'Allow Pingbacks',
					'field' => 'ping_status',
					'values' => array('closed','open')
				),
				array(
					'title' => 'Page Template',
					'field' => 'page_template'
				),

			)
		)
	);

	public function __construct(){
		parent::__construct();
		add_action('jci/after_template_fields', array($this, 'field_settings'));
		add_action('jci/save_template', array($this, 'save_template'));
		
		add_filter('jci/log_post_columns', array($this, 'log_post_columns'));
		add_action('jci/log_post_content', array($this, 'log_post_content'), 10, 2);
	}

	public function field_settings($id){

		$template = ImporterModel::getImportSettings($id, 'template');
		if($template == $this->_name){

			$enable_id = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_id'));
			$enable_order = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_order'));
			$enable_password = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_password'));
			$enable_template = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_template'));
			$enable_comments = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_comments'));
			$enable_ping = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_ping'));

			?>
			<div class="jci-group-settings jci-group-section" data-section-id="settings">
				<div id="jci_post_enable_fields">
				<h4>Fields:</h4>
				<?php 
				echo JCI_FormHelper::checkbox('template_settings[enable_id]', array('label' => 'Enable ID Field', 'checked' => $enable_id));
				echo JCI_FormHelper::checkbox('template_settings[enable_order]', array('label' => 'Enable Order Field', 'checked' => $enable_order));
				echo JCI_FormHelper::checkbox('template_settings[enable_password]', array('label' => 'Enable Password Field', 'checked' => $enable_password));
				echo JCI_FormHelper::checkbox('template_settings[enable_template]', array('label' => 'Enable Template Field', 'checked' => $enable_template));
				echo JCI_FormHelper::checkbox('template_settings[enable_comments]', array('label' => 'Enable Comments Field', 'checked' => $enable_comments));
				echo JCI_FormHelper::checkbox('template_settings[enable_ping]', array('label' => 'Enable Trackbacks Field', 'checked' => $enable_ping));
				?>
				</div>
			</div>
			<script type="text/javascript">
			jQuery(document).ready(function($){
				
				// ID Field
				$('input[name="jc-importer_template_settings[enable_id]"]').change(function(){
					var elem = $('#jc-importer_field-post-ID').parent();
					if(!$(this).is(':checked')){
						elem.hide();
					}else{
						elem.show();
					}
				});

				// order field
				$('input[name="jc-importer_template_settings[enable_order]"]').change(function(){
					var elem = $('#jc-importer_field-post-menu_order').parent();
					if(!$(this).is(':checked')){
						elem.hide();
					}else{
						elem.show();
					}
				});

				// password field
				$('input[name="jc-importer_template_settings[enable_password]"]').change(function(){
					var elem = $('#jc-importer_field-post-post_password').parent();
					if(!$(this).is(':checked')){
						elem.hide();
					}else{
						elem.show();
					}
				});

				// template field
				$('input[name="jc-importer_template_settings[enable_template]"]').change(function(){
					var elem = $('#jc-importer_field-post-page_template').parent();
					if(!$(this).is(':checked')){
						elem.hide();
					}else{
						elem.show();
					}
				});

				// comments field
				$('input[name="jc-importer_template_settings[enable_comments]"]').change(function(){
					var elem = $('#jc-importer_field-post-comment_status').parent();
					if(!$(this).is(':checked')){
						elem.hide();
					}else{
						elem.show();
					}
				});

				//ping_status
				$('input[name="jc-importer_template_settings[enable_ping]"]').change(function(){
					var elem = $('#jc-importer_field-post-ping_status').parent();
					if(!$(this).is(':checked')){
						elem.hide();
					}else{
						elem.show();
					}
				});

				$('#jci_post_enable_fields input').trigger('change');
			});
			</script>
			<?php
		}
	}

	public function save_template($id){

		$template = ImporterModel::getImportSettings($id, 'template');
		if($template == $this->_name){

			// get template settings
			$enable_id = isset($_POST['jc-importer_template_settings']['enable_id']) ? $_POST['jc-importer_template_settings']['enable_id'] : 0;
			$enable_order = isset($_POST['jc-importer_template_settings']['enable_order']) ? $_POST['jc-importer_template_settings']['enable_order'] : 0;
			$enable_password = isset($_POST['jc-importer_template_settings']['enable_password']) ? $_POST['jc-importer_template_settings']['enable_password'] : 0;
			$enable_template = isset($_POST['jc-importer_template_settings']['enable_template']) ? $_POST['jc-importer_template_settings']['enable_template'] : 0;
			$enable_comments = isset($_POST['jc-importer_template_settings']['enable_comments']) ? $_POST['jc-importer_template_settings']['enable_comments'] : 0;
			$enable_ping = isset($_POST['jc-importer_template_settings']['enable_ping']) ? $_POST['jc-importer_template_settings']['enable_ping'] : 0;

			// update template settings
			ImporterModel::setImporterMeta($id, array('_template_settings','enable_id'), $enable_id);
			ImporterModel::setImporterMeta($id, array('_template_settings','enable_order'), $enable_order);
			ImporterModel::setImporterMeta($id, array('_template_settings','enable_password'), $enable_password);
			ImporterModel::setImporterMeta($id, array('_template_settings','enable_template'), $enable_template);
			ImporterModel::setImporterMeta($id, array('_template_settings','enable_comments'), $enable_comments);
			ImporterModel::setImporterMeta($id, array('_template_settings','enable_ping'), $enable_ping);
		}
	}

	public function before_template_save( $data, $current_row ){

		global $jcimporter;
		$id = $jcimporter->importer->ID;

		$this->enable_id = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_id'));
		$this->enable_order = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_order'));
		$this->enable_password = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_password'));
		$this->enable_template = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_template'));
		$this->enable_comments = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_comments'));
		$this->enable_ping = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_ping'));
	}

	public function before_group_save( $data, $group_id ){

		global $jcimporter;
		$id = $jcimporter->importer->ID;

		if($this->enable_id == 0){
			unset($data['ID']);
		}
		if($this->enable_order == 0){
			unset($data['menu_order']);
		}
		if($this->enable_password == 0){
			unset($data['post_password']);
		}
		if($this->enable_template == 0){
			unset($data['page_template']);
		}
		if($this->enable_comments == 0){
			unset($data['comment_status']);
		}
		if($this->enable_ping == 0){
			unset($data['ping_status']);
		}

		return $data;
	}

	/**
	 * Register Post Columns
	 * @param  array $columns 
	 * @return array
	 */
	public function log_post_columns($columns){
		
		$columns['post'] = 'Post';
		$columns['taxonomies'] = 'Taxonomies';
		$columns['attachments'] = 'Attachments';
		$columns['method'] = 'Method';

		return $columns;
	}

	/**
	 * Output column data
	 * @param  array $column 
	 * @param  array $data   
	 * @return void
	 */
	public function log_post_content($column, $data){

		switch ($column) {
			case 'post':
				echo $data['post']['post_title'];
			break;
			case 'method':
				
				if($data['post']['_jci_type'] == 'I'){
					echo 'Inserted';
				}elseif($data['post']['_jci_type'] == 'U'){
					echo 'Updated';
				}
			break;
		}
	}

}

add_filter('jci/register_template', 'register_post_template', 10, 1);
function register_post_template($templates = array()){
	$template = new JC_Post_Template();
	$templates[$template->get_name()] = $template;
	return $templates;
}