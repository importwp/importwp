<?php 
/**
 * Core Admin Class
 */
class JC_Importer_Admin{

    private $config = null;

	public function __construct(&$config){
        $this->config = $config;

		// add_action( 'admin_init', array($this, 'register_settings' ));
		add_action( 'admin_menu', array($this, 'settings_menu' ));

        add_action('wp_loaded', array($this, 'process_forms'));
        
        add_action('admin_init', array($this, 'admin_enqueue_styles'));

        // ajax import
        add_action('wp_ajax_jc_import_row', array($this, 'admin_ajax_import_row'));

        // add_action( 'init');
        $this->setup_forms();

        // $this->config->parsers = apply_filters( 'jci/register_parser', array() );
	}

    public function admin_enqueue_styles(){
        wp_enqueue_style( 'jc-importer-style', $this->config->plugin_url.'/app/assets/css/style.css');
    }

	public function settings_menu(){

        add_menu_page( 'jc-importer', 'JC Importer', 'manage_options', 'jci-importers', array($this, 'admin_imports_view'));
        add_submenu_page( 'jci-importers', 'Importers', 'Importers', 'manage_options', 'jci-importers', array($this, 'admin_imports_view') );
        add_submenu_page( 'jci-importers', 'Add New', 'Add New', 'manage_options', 'jci-importers&action=add', array($this, 'admin_imports_view') );
        // add_submenu_page( 'jci-importers', 'Templates', 'Templates', 'manage_options', 'jci-templates', array($this, 'admin_templates_view') );
        // add_submenu_page( 'jci-importers', 'Addons', 'Addons', 'manage_options', 'jci-addons', array($this, 'admin_addons_view') );
        // add_submenu_page( 'jci-importers', 'Settings', 'Settings', 'manage_options', 'jci-settings', array($this, 'admin_settings_view') );
    }

    public function admin_imports_view(){
    	require 'view/home.php';
    }

    public function admin_templates_view(){
        require 'view/templates.php';
    }

    public function admin_addons_view(){
        require 'view/addons.php';
    }

    public function admin_settings_view(){
        require 'view/settings.php';
    }

    public function setup_forms(){

        // static validation rules
        $this->config->forms = array(
            'CreateImporter' => array(
                'validation' => array(
                    'name' => array(
                        'rule' => array('required'),
                        'message' => 'This Field is required'
                    )
                )
            ),
            'EditImporter' => array()
        );

        // dynamic validation rules
        if(isset($_POST['jc-importer_form_action']) && $_POST['jc-importer_form_action'] == 'CreateImporter'){

            // set extra validation rules based on the select
            switch($_POST['jc-importer_import_type']){
                    
                // file upload settings
                case 'upload':

                    // @TODO : include file upload validation  
                    $this->config->forms['CreateImporter']['validation']['import_file'] = array(
                        'rule' => array('required'),
                        'message' => 'This Field is required',
                        'type' => 'file'
                    );
                break;
                
                // remote/curl settings
                case 'remote':

                    $this->config->forms['CreateImporter']['validation']['remote_url'] = array(
                        'rule' => array('required'),
                        'message' => 'This Field is required'
                    );
                break;

            }

            $this->config->forms = apply_filters( 'jci/setup_forms', $this->config->forms, $_POST['jc-importer_import_type'] );
        }    
    }

    public function process_forms(){

        JCI_FormHelper::init($this->config->forms);
        if(isset($_POST['jc-importer_form_action'])){

            switch($_POST['jc-importer_form_action']){
                case 'CreateImporter':
                    $this->process_import_create_from();
                break;
                case 'EditImporter':
                    $this->process_import_edit_from();
                break;
            }

        }

        // trash importers
        // @todo: quick fix
        $action = isset($_GET['action']) && !empty($_GET['action']) ? $_GET['action'] : 'index';
        $importer = isset($_GET['import']) && intval($_GET['import']) > 0 ? intval($_GET['import']) : false;
        $template = isset($_GET['template']) && intval($_GET['template']) > 0 ? intval($_GET['template']) : false;

        if($action == 'trash' && ($importer || $template)){

            if($importer){

                wp_delete_post( $importer);
            }elseif($template){

                wp_delete_post( $template);
            }

            wp_redirect( '/wp-admin/admin.php?page=jci-importers&message=1&trash=1' );
            exit();
        }
    }

    /**
     * Create Importer Form
     * @return void
     */
    public function process_import_create_from(){

        JCI_FormHelper::process_form('CreateImporter');
        if(JCI_FormHelper::is_complete()){

            // general importer fields
            $name = $_POST['jc-importer_name'];
            $template = $_POST['jc-importer_template'];

            $post_id = ImporterModel::insertImporter(0, array('name' => $name));
            $general = array();

            // @todo: add error messages, e.g. unable to connect
            $import_type = $_POST['jc-importer_import_type'];
            switch($import_type){
                    
                // file upload settings
                case 'upload':

                    // upload
                    $attach = new JC_Upload_Attachments();
                    $result = $attach->attach_upload( $post_id, $_FILES['jc-importer_import_file']);
                break;
                
                // remote/curl settings
                case 'remote':

                    // download
                    $src = $_POST['jc-importer_remote_url'];
                    $dest = basename($src);
                    $attach = new JC_CURL_Attachments();
                    $result = $attach->attach_remote_file($post_id, $src, $dest);
                    $general['remote_url'] = $src;
                break;

                // no attachment
                default:
                    $result = false;
                break;
            }

            $general = apply_filters('jci/process_create_form', $general, $import_type, $post_id );
            $result = apply_filters( 'jci/process_create_file', $result, $import_type, $post_id );

            


            // process results
            if($result && is_array($result)){

                $post_id = ImporterModel::insertImporter($post_id, array(
                    'name' => $name,
                    'settings' => array(
                        'import_type' => $import_type,
                        'template' => $template,
                        'template_type' => $result['type'],
                        'import_file' => $result['id'],
                        'general' => $general
                    ),
                ));

                wp_redirect( '/wp-admin/admin.php?page=jci-importers&import='.$post_id.'&action=edit');
                exit();
            }
        }
    }

    /**
     * Edit Importer Form
     * @return void
     */
    public function process_import_edit_from(){

        JCI_FormHelper::process_form('EditImporter');
        if(JCI_FormHelper::is_complete()){

            $id = intval($_POST['jc-importer_import_id']);

            // uploading a new file
            if(isset($_POST['jc-importer_upload_file']) && !empty($_POST['jc-importer_upload_file'])){

                $attach = new JC_Upload_Attachments();
                $result = $attach->attach_upload( $id, $_FILES['jc-importer_import_file']);
                ImporterModel::setImportFile($id, $result);

                wp_redirect( '/wp-admin/admin.php?page=jci-importers&import='.$id.'&action=edit');
                exit();
            }


            if(isset($_POST['jc-importer_permissions'])){
                $settings['permissions'] = $_POST['jc-importer_permissions'];
            }
            if(isset($_POST['jc-importer_start-line'])){
                $settings['start_line'] = $_POST['jc-importer_start-line'];
            }
            if(isset($_POST['jc-importer_row-count'])){
                $settings['row_count'] = $_POST['jc-importer_row-count'];
            }

            $settings = apply_filters('jci/process_edit_form', $settings );

            // $settings = array(
            //     'start_line' => $_POST['jc-importer_start-line'],
            //     'row_count' => $_POST['jc-importer_row-count'],
            //     'permissions' => $_POST['jc-importer_permissions'],
            // );

            $fields = isset($_POST['jc-importer_field']) ? $_POST['jc-importer_field'] : array();
            $attachments = isset( $_POST['jc-importer_attachment'] ) ? $_POST['jc-importer_attachment'] : array();
            $taxonomies = isset($_POST['jc-importer_taxonomies']) ? $_POST['jc-importer_taxonomies'] : array();

            // load parser settings
            
            $template_type = ImporterModel::getImportSettings($id, 'template_type');
            $this->_parser = load_import_parser($id);
            $parser_settings = apply_filters( 'jci/register_'.$template_type.'_addon_settings', array('general' => array(), 'group' => array()) );

            // select file to use for import
            $selected_import_id = intval($_POST['jc-importer_file_select']);
            $attachment_check = new WP_Query(array('post_type' => 'attachment', 'post_parent' => $id, 'post_status' => 'any', 'p' => $selected_import_id));
            if($attachment_check->post_count == 1){
                $settings['import_file'] = $selected_import_id;
            }

            $result = ImporterModel::update($id, array(
                'fields' => $fields,
                'attachments' => $attachments,
                'taxonomies' => $taxonomies,
                'settings' => $settings
            ));

            do_action( 'jci/save_template', $id, $template_type );

            if(isset($_POST['jc-importer_btn-continue'])){
                wp_redirect( '/wp-admin/admin.php?page=jci-importers&import='.$result.'&action=logs');
            }else{
                wp_redirect( '/wp-admin/admin.php?page=jci-importers&import='.$result.'&action=edit');
            }
            exit();
        }
    }

    /**
     * Process Import Ajax
     * @return json
     */
    public function admin_ajax_import_row(){

        global $jcimporter;
        
        $row = intval($_POST['row']);
        $importer_id = intval($_POST['id']);

        $jcimporter->importer = new JC_Importer_Core($importer_id);
        $data = $jcimporter->importer->run_import($row);

        $template = ImporterModel::getImportSettings($importer_id, 'template');
        $columns = apply_filters( "jci/log_{$template}_columns", array());

        // attach default columns for attachments 
        add_action("jci/log_{$template}_content", array($this, 'loc_content'), 5, 2);

        $error = false;

        if(!is_array($data)){
            $error = 'No Record Data';
        }else{
            $data = array_shift($data);
        }

        if(isset($data['_jci_status']) && $data['_jci_status'] != 'S'){
            $error = $data['_jci_msg'];
        }

        if(!$error){
            ImportLog::insert($importer_id, $row, $data);

            if(!$error && $data['_jci_status'] == 'S'){
                // success
                ?>
            <tr>
                <td><?php echo $row; ?></td>
                <?php foreach($columns as $key => $col): ?>
                <td><?php do_action( "jci/log_{$template}_content", $key, $data ); ?></td>
                <?php endforeach; ?>
            </tr>
                <?php

            }

        }

        // display error message
        if($error !== false){
            ?>
        <tr>
            <td colspan="<?php echo count($columns) + 1; ?>">Error: <?php echo $error; ?></td>
        </tr>
            <?php
        }

        die();
    }

    /**
     * Output column data
     * @param  array $column 
     * @param  array $data   
     * @return void
     */
    public function loc_content($column, $data){

        switch ($column) {
            case 'attachments':
                if(isset($data['attachments'])){

                    //
                    $attachments = 0;
                    foreach($data['attachments'] as $result){
                        if($result['status'] == 'E'){

                            // error
                            echo "Error: ".$result['msg'];
                        }elseif($result['status'] == 'S'){

                            $attachments++;
                        }
                    }

                    if($attachments > 0){
                        echo $attachments.' Attachments Inserted';
                    }
                    
                }else{
                    echo 'No Attachments Inserted';
                }
                
            break;
            case 'taxonomies':

                if(isset($data['taxonomies'])){

                    foreach($data['taxonomies'] as $tax => $terms){
                        echo "<strong>{$tax}</strong>: ".implode(',', $terms).'<br />';
                    }
                }else{
                    echo 'No Taxonomies Inserted';
                }
            break;
        }
    }
}
?>