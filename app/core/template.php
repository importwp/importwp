<?php 
class JC_Template_Core{

	var $post_type = 'jc-import-template';
	private $config = null;

	public function __construct(&$config){
		$this->config = $config;

		add_action('init', array($this, 'init'));

		// register templates
		$this->config->templates = apply_filters( 'jci/register_template', $this->config->templates );
	}

	public function init(){

		register_post_type( $this->post_type, array(
			'public' => false,
			'has_archive' => false,
			'show_in_nav_menus' => false,
			'label' => 'Template'
		));
	}

	
}
?>