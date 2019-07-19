<?php

class IWP_Template_Post extends IWP_Template {

	public $_name = 'post';

	public $_unique = array( 'post_name', 'ID' );

	protected $_group = 'post';

	protected $_import_type = 'post';

	protected $_import_type_name = 'post';

	protected $_settings = array(
		'post_status' => 'any',
		'unique' => array( 'post_name' ),
		'key' => array('ID', 'post_name'),
		'attachments' => 1,
		'taxonomies' => 1,
	);

	public function __construct() {

		parent::__construct();

		add_filter( sprintf( 'jci/log_%s_columns', $this->_name ), array( $this, 'log_columns' ) );
		add_action( sprintf( 'jci/log_%s_content', $this->_name ), array( $this, 'log_content' ), 10, 2 );

		add_action( 'jci/before_import', array( $this, 'before_import' ) );

		// Run after base template has removed virtual fields
		add_filter( 'jci/before_' . $this->get_name() . '_group_save', array( $this, 'override_post_status' ), 110, 1 );
	}

	public function register_fields() {
		$this->register_basic_field('ID', 'ID');
		$this->register_basic_field('Title', 'post_title');
		$this->register_basic_field('Content', 'post_content');
		$this->register_basic_field('Excerpt', 'post_excerpt');
		$this->register_basic_field('Slug', 'post_name');
		$this->register_basic_field('Status', 'post_status', array(
			'options'         => array(
				'draft'   => 'Draft',
				'publish' => 'Published',
				'pending' => 'Pending',
				'future'  => 'Future',
				'private' => 'Private',
				'trash'   => 'Trash'
			),
			'options_default' => 'publish'
		));
		$this->register_basic_field('Author', 'post_author', array(
			'options' => jci_get_user_list(),
			'after' => array( $this, 'after_post_author')
		));
		$this->register_basic_field('Parent', 'post_parent', array(
			'after' => array( $this, 'after_post_parent')
		));
		$this->register_basic_field('Order', 'menu_order');
		$this->register_basic_field('Password', 'post_password');
		$this->register_basic_field('Date', 'post_date');
		$this->register_basic_field('Allow Comments', 'comment_status', array(
			'options'         => array( 0 => 'Disabled', 1 => 'Enabled' ),
			'options_default' => 0
		));
		$this->register_basic_field('Allow Pingbacks', 'ping_status', array(
			'options'         => array( 'closed' => 'Closed', 'open' => 'Open' ),
			'options_default' => 'closed'
		));

		// Settings Tab
		$this->register_section('Settings', 'settings');
		$this->register_enable_toggle('Enable ID Field', 'enable_id', 'settings', array(
			'ID',
		));
		$this->register_enable_toggle('Enable Post Status Field', 'enable_post_status', 'settings', array(
			'post_status',
		));
		$this->register_enable_toggle('Enable Author Field', 'enable_post_author', 'settings', array(
			'post_author',
		));
		$this->register_enable_toggle('Enable Parent Field', 'enable_post_parent', 'settings', array(
			'post_parent',
		));
		$this->register_enable_toggle('Enable Order Field', 'enable_menu_order', 'settings', array(
			'menu_order',
		));
		$this->register_enable_toggle('Enable Password Field', 'enable_post_password', 'settings', array(
			'post_password',
		));
		$this->register_enable_toggle('Enable Date Field', 'enable_post_date', 'settings', array(
			'post_date',
		));
		$this->register_enable_toggle('Enable Comment Field', 'enable_comment_status', 'settings', array(
			'comment_status',
		));
		$this->register_enable_toggle('Enable Ping Field', 'enable_ping_status', 'settings', array(
			'ping_status',
		));
	}

	public function after_post_author($field_id){
		?>
        <div class="iwp__sub-fields">
			<?php
			echo IWP_FormBuilder::select( 'field[' . $this->get_group() . '][' . $field_id . '_field_type]', array(
				'label' => 'Type',
				'options' => array(
					'id' => 'ID',
					'login' => 'Login',
					'email' => 'Email',
				),
				'default' => $this->get_field_value($field_id.'_field_type', 'id')
			));
			?>
        </div>
		<?php
    }

	public function after_post_parent($field_id){
		?>
        <div class="iwp__sub-fields">
			<?php
			echo IWP_FormBuilder::select( 'field[' . $this->get_group() . '][' . $field_id . '_field_type]', array(
				'label' => 'Type',
				'options' => array(
					'id' => 'ID',
					'slug' => 'Slug',
					'name' => 'Name',
					'column' => 'Reference Column',
				),
				'default' => $this->get_field_value($field_id.'_field_type', 'id')
			));

			$key = $field_id.'_ref';
			echo IWP_FormBuilder::text( 'field[' . $this->get_group() . '][' . $key . ']', array(
				'label'   => 'Parent Reference Column',
				'tooltip' => '',
				'default' => $this->get_field_value($key),
				'class'   => 'xml-drop jci-group field__input field__input--'.$key,
				'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
				'wrapper_data' => array(
					'iwp-options' => 'false',
					'iwp-name' => 'field[' . $this->get_group() . '][' . $key . ']'
				),
				'data'    => array(
					'jci-field' => $key,
				)
			) );
			?>
        </div>
        <script type="text/javascript">
            (function($, window){

                var post_type = '<?php echo esc_attr($this->_import_type_name); ?>';
                $('#jc-importer_field-'+post_type+'-post_parent_field_type').on('change', function(){
                    if($(this).val() === 'column'){
                        $('.field__input--post_parent_ref').show();
                    }else{
                        $('.field__input--post_parent_ref').hide();
                    }
                });

                window.iwp.onProcessComplete.add(function(){
                    $('#jc-importer_field-'+post_type+'-post_parent_field_type').trigger('change');
                });

            })(jQuery, window);
        </script>
		<?php
    }

	/**
	 * Attach template_group filters on import only
	 *
	 * @return void
	 */
	public function before_import() {

	    // disable term counting for this insert/update
		wp_defer_term_counting( true );
		wp_defer_comment_counting( false );

		$enable_post_parent =  $this->get_field_value('enable_post_parent');
		if($enable_post_parent){
			$post_parent_ref =  $this->get_field_value('post_parent_ref');
			if ( ! empty( $post_parent_ref ) ) {
				$this->_field_groups[$this->get_group()]['identifiers'] = array( 'post_parent' => $post_parent_ref );
			}

			add_filter( 'jci/importer/get_groups', array( $this, 'add_reference_fields' ), 999 );
        }
	}

	public function before_group_save( $data, $group_id ) {

		$id = JCI()->importer->get_ID();

		$enable_id =  $this->get_field_value('enable_id');
        $enable_post_author =  $this->get_field_value('enable_post_author');
        $enable_post_parent =  $this->get_field_value('enable_post_parent');
        $enable_menu_order =  $this->get_field_value('enable_menu_order');
        $enable_post_password =  $this->get_field_value('enable_post_password');
        $enable_post_date =  $this->get_field_value('enable_post_date');
        $enable_comment_status =  $this->get_field_value('enable_comment_status');
        $enable_ping_status =  $this->get_field_value('enable_ping_status');

		/**
		 * Check to see if post_parent
		 */
		if ( $enable_post_parent === '1' && ! empty( $data['post_parent'] ) ) {

			$parent_field_type = $this->get_field_value('post_parent_field_type');

			$page_id = 0;

			if ( $parent_field_type == 'name' || $parent_field_type == 'slug' ) {

				// name or slug
				$page = get_posts( array( 'name' => sanitize_title( $data['post_parent'] ), 'post_type' => $this->get_import_type_name() ) );
				if ( $page ) {
					$page_id = intval( $page[0]->ID );
				}

			} elseif ( $parent_field_type == 'id' ) {

				// ID
				$page_id = intval( $data['post_parent'] );
			} elseif ( $parent_field_type == 'column' ) {

				// Reference Column
				$parent_id = $this->get_post_by_cf( 'post_parent', $data['post_parent'], $group_id );
				if ( intval( $parent_id > 0 ) ) {
					$page_id = intval( $parent_id );
				}
			}

			// set post parent to int or clear
			$data['post_parent'] = $page_id;
		}

		/**
		 * Check to see if post_author
		 */
		if ( $enable_post_author === '1' && ! empty( $data['post_author'] ) ) {

			$author_field_type = $this->get_field_value('post_author_field_type');
			$user_id = 0;

			if ( $author_field_type == 'login' ) {

				// login
				$user = get_user_by( 'login', $data['post_author'] );
				if ( $user ) {
					$user_id = intval( $user->ID );
				}


			} elseif ( $author_field_type == 'email' ) {

				// email
				$user = get_user_by( 'email', $data['post_author'] );
				if ( $user ) {
					$user_id = intval( $user->ID );
				}

			} elseif ( $author_field_type == 'id' ) {

				// ID
				$user_id = intval( $data['post_author'] );
			}

			// set post parent to int or clear
			$data['post_author'] = $user_id;

		}

		/**
		 * If Post Date is enabled try to convert it to a date, otherwise clear it.
		 */
		if( $enable_post_date === '1' && !empty( $data['post_date'])){
		    $time = strtotime($data['post_date']);
		    if($time) {
			    $data['post_date'] = date( 'Y-m-d H:i:s', $time );
		    }else{
		        $data['post_date'] = '';
            }
        }

		// generate slug from title if no slug present
		if ( empty( $data['post_name'] ) ) {
			$data['post_name'] = sanitize_title( $data['post_title'] );
		}

		return $data;
	}

	public function override_post_status($data){

		// set publish as default post_status
		$enable_post_status =  $this->get_field_value('enable_post_status');
		if( $enable_post_status !== '1') {
			$data['post_status'] = 'publish';
		}

	    return $data;
    }

	/**
	 * Register Post Columns
	 *
	 * @param  array $columns
	 *
	 * @return array
	 */
	public function log_columns( $columns ) {

		$columns['post']        = 'Post';
		$columns['taxonomies']  = 'Taxonomies';
		$columns['attachments'] = 'Attachments';
		$columns['method']      = 'Method';

		return $columns;
	}

	/**
	 * Output column data
	 *
	 * @param  array $column
	 * @param  array $data
	 *
	 * @return void
	 */
	public function log_content( $column, $data ) {

		switch ( $column ) {
			case 'post':
				edit_post_link( get_the_title($data['post']['ID']) . ' #' . $data['post']['ID'], '', '', $data['post']['ID'] );
				break;
			case 'method':

				if ( $data['post']['_jci_type'] == 'I' ) {
					echo 'Inserted';
				} elseif ( $data['post']['_jci_type'] == 'U' ) {
					echo 'Updated';
				}
				break;
		}
	}

}

add_filter( 'jci/register_template', 'register_post_template', 10, 1 );
function register_post_template( $templates = array() ) {
	$templates['post'] = 'IWP_Template_Post';

	return $templates;
}