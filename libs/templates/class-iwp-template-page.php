<?php

class IWP_Template_Page extends IWP_Template_Post {

	public $_name = 'page';

	public $_unique = array( 'post_name', 'ID' );

	protected $_group = 'page';

	protected $_import_type = 'post';

	protected $_import_type_name = 'page';

	public function register_fields() {

		parent::register_fields();

        $templates                = array( 'default' => 'Default Template' );
        $templates                = array_merge( $templates, wp_get_theme()->get_page_templates() );
		$this->register_basic_field('Page Template', '_wp_page_template', array(
            'options' => $templates,
            'options_default' => 'default'
        ));

		$this->register_enable_toggle('Enable Page Template Field', 'enable_page_template', 'settings', array(
			'_wp_page_template',
		));
	}
}

add_filter( 'jci/register_template', 'register_page_template', 10, 1 );
function register_page_template( $templates = array() ) {
	$templates['page'] = 'IWP_Template_Page';

	return $templates;
}