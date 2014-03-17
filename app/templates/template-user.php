<?php
class JC_User_Template extends JC_Importer_Template{

	public $_name = 'user';
	public $_unique = array('user_email');
	
	public $_field_groups = array(

		'user' => array(
			'import_type' => 'user',
			'import_type_name' => 'user',
			'field_type' => 'single',
			'group' => 'user', // for backwards compatability
			'key' => array(),
			'unique' => array('user_email'),
			'relationship' => array(),
			'map' => array(
				array(
					'title' => 'First Name',
					'field' => 'first_name'
				),
				array(
					'title' => 'Last Name',
					'field' => 'last_name'
				),
				array(
					'title' => 'Email',
					'field' => 'user_email'
				),
				array(
					'title' => 'Username',
					'field' => 'user_login'
				),
				
				array(
					'title' => 'Role',
					'field' => 'role',
				),
				array(
					'title' => 'Password',
					'field' => 'user_pass'
				),
				array(
					'title' => 'Nice Name',
					'field' => 'user_nicename',
				),
				array(
					'title' => 'Display Name',
					'field' => 'display_name',
				),
				array(
					'title' => 'Nickname',
					'field' => 'nickname',
				),
				array(
					'title' => 'Description',
					'field' => 'description',
				),
				array(
					'title' => 'Website',
					'field' => 'user_url'
				),
			)
		)
	);

	public function before_template_save($data, $group_id){

		if(empty($data['user']['user_login'])){
			throw new JCI_Exception("No Username Found", JCI_WARN);
		}

		if(empty($data['user']['user_email'])){
			throw new JCI_Exception("No Email Found", JCI_WARN);
		}

		if(!empty($data['user']['user_email']) && !is_email($data['user']['user_email'] )){
			throw new JCI_Exception(strval($data['user']['user_email'])." is not a valid email address", JCI_WARN);
		}

		global $jcimporter;
		$importer_id = $jcimporter->importer->ID;

		// generate password
		$generate_pass = ImporterModel::getImporterMetaArr($importer_id, array('_template_settings','generate_pass'));
		if($generate_pass == 1){
			$data['user']['user_pass'] = wp_generate_password( 10);
		}
	}

	public function __construct(){
		parent::__construct();
		add_action('jci/after_template_fields', array($this, 'field_settings'));
		add_action('jci/save_template', array($this, 'save_template'));

		add_filter('jci/log_user_columns', array($this, 'log_user_columns'));
		add_action('jci/log_user_content', array($this, 'log_user_content'), 10, 2);
	}

	/**
	 * Display template settings
	 * @param  int $id 
	 * @return void
	 */
	public function field_settings($id){

		$template = ImporterModel::getImportSettings($id, 'template');
		if($template == $this->_name){

			$enable_pass = ImporterModel::getImporterMetaArr($id, array('_template_settings','enable_pass'));
			$generate_pass = ImporterModel::getImporterMetaArr($id, array('_template_settings','generate_pass'));
			$notify_pass = ImporterModel::getImporterMetaArr($id, array('_template_settings','notify_pass'));
			$notify_reg = ImporterModel::getImporterMetaArr($id, array('_template_settings','notify_reg'));
			?>
			<div class="jci-group-settings jci-group-section" data-section-id="settings">
				<h4>Passwords:</h4>
				<?php 
				echo JCI_FormHelper::checkbox('template_settings[enable_pass]', array('label' => 'Enable Password Field', 'checked' => $enable_pass));
				echo JCI_FormHelper::checkbox('template_settings[generate_pass]', array('label' => 'Generate Password', 'checked' => $generate_pass));
				?>

				<h4>Notifications:</h4>
				<?php 
				echo JCI_FormHelper::checkbox('template_settings[notify_pass]', array('label' => 'Send new Password', 'checked' => $notify_pass));
				echo JCI_FormHelper::checkbox('template_settings[notify_reg]', array('label' => 'Send User Registration', 'checked' => $notify_reg));
				?>
			</div>

			<script type="text/javascript">
			jQuery(document).ready(function($){
				
				// ID Field
				$('input[name="jc-importer_template_settings[enable_pass]"]').change(function(){
					var elem = $('#jc-importer_field-user-user_pass').parent();
					if(!$(this).is(':checked')){
						elem.hide();
					}else{
						elem.show();
					}
				});

				$('input[name="jc-importer_template_settings[enable_pass]"]').trigger('change');
			});
			</script>
			<?php
		}
	}

	/**
	 * Save template fields
	 * @param  int $id 
	 * @return void
	 */
	public function save_template($id){

		$template = ImporterModel::getImportSettings($id, 'template');
		if($template == $this->_name){

			// get template settings
			$enable_pass = isset($_POST['jc-importer_template_settings']['enable_pass']) ? $_POST['jc-importer_template_settings']['enable_pass'] : 0;
			$generate_pass = isset($_POST['jc-importer_template_settings']['generate_pass']) ? $_POST['jc-importer_template_settings']['generate_pass'] : 0;
			$notify_pass = isset($_POST['jc-importer_template_settings']['notify_pass']) ? $_POST['jc-importer_template_settings']['notify_pass'] : 0;
			$notify_reg = isset($_POST['jc-importer_template_settings']['notify_reg']) ? $_POST['jc-importer_template_settings']['notify_reg'] : 0;

			// update template settings
			ImporterModel::setImporterMeta($id, array('_template_settings','enable_pass'), $enable_pass);
			ImporterModel::setImporterMeta($id, array('_template_settings','generate_pass'), $generate_pass);
			ImporterModel::setImporterMeta($id, array('_template_settings','notify_pass'), $notify_pass);
			ImporterModel::setImporterMeta($id, array('_template_settings','notify_reg'), $notify_reg);
		}
	}

	/**
	 * Register Post Columns
	 * @param  array $columns 
	 * @return array
	 */
	public function log_user_columns($columns){
		
		$columns['user_id'] = 'User ID';
		$columns['username'] = 'Username';
		$columns['email'] = 'Email';
		$columns['name'] = 'Name';
		$columns['method'] = 'Method';

		return $columns;
	}

	/**
	 * Output column data
	 * @param  array $column 
	 * @param  array $data   
	 * @return void
	 */
	public function log_user_content($column, $data){

		switch ($column) {
			case 'user_id':
				echo $data['user']['ID'];
			break;
			case 'username':
				echo $data['user']['user_login'];
			break;
			case 'name':
				echo $data['user']['first_name'] .' '. $data['user']['last_name'];
			break;
			case 'email':
				echo $data['user']['user_email'];
			break;
			case 'method':
				
				if($data['user']['_jci_type'] == 'I'){
					echo 'Inserted';
				}elseif($data['user']['_jci_type'] == 'U'){
					echo 'Updated';
				}
			break;
		}
	}

}

add_filter('jci/register_template', 'register_user_template', 10, 1);
function register_user_template($templates = array()){
	$template = new JC_User_Template();
	$templates[$template->get_name()] = $template;
	return $templates;
}