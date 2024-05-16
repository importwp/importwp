<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\EventHandler;

class AttachmentTemplate extends PostTemplate implements TemplateInterface
{
    protected $name = 'Attachment';
    protected $mapper = 'attachment';

    protected $field_map = [
        'ID' => 'post.ID',
        'post_name' => 'post.post_name',
        'post_title' => 'post.post_title',
        'post_content' => 'post.post_content',
        'post_excerpt' => 'post.post_excerpt',
        'post_status' => 'post.post_status',
        'menu_order' => 'post.menu_order',
        'post_password' => 'post.post_password',
        'post_date' => 'post.post_date',
        'comment_status' => 'post.comment_status',
        'ping_status' => 'post.ping_status',
        'post_parent' => 'post._parent.parent',
        '_wp_attachment_image_alt' => 'post._wp_attachment_image_alt',
    ];

    protected $optional_fields = [];

    public function __construct(EventHandler $event_handler)
    {
        parent::__construct($event_handler);

        $this->groups[] = 'post';
        $this->default_template_options['unique_field'] = ['ID', 'post_name', 'src'];
        $this->default_template_options['post_type'] = 'attachment';

        $this->optional_fields[] = '_wp_attachment_image_alt';
    }

    public function register()
    {
        return [
            $this->register_group(__('Attachment', 'jc-importer'), 'post', [
                $this->register_field(__('ID', 'jc-importer'), 'ID', [
                    'tooltip' => __('ID is only used to reference existing records', 'jc-importer')
                ]),
                $this->register_field(__('Title', 'jc-importer'), 'post_title', [
                    'tooltip' => __('Title of the post.', 'jc-importer')
                ]),
                $this->register_field(__('Description', 'jc-importer'), 'post_content', [
                    'tooltip' => __('Main WYSIWYG editor content of the post.', 'jc-importer')
                ]),
                $this->register_field(__('Caption', 'jc-importer'), 'post_excerpt', [
                    'tooltip' => __('A custom short extract for the post.', 'jc-importer')
                ]),
                $this->register_field(__('Alt Text', 'jc-importer'), '_wp_attachment_image_alt', [
                    'tooltip' => __('The slug is the user friendly and URL valid name of the post.', 'jc-importer')
                ]),
                $this->register_field(__('Slug', 'jc-importer'), 'post_name', [
                    'tooltip' => __('The slug is the user friendly and URL valid name of the post.', 'jc-importer')
                ]),
                $this->register_attachment_fields(__('File', 'jc-importer'), 'file', __('Media File', 'jc-importer'), [], ['disabled_fields' => ['_meta', '_featured']]),
                $this->register_field(__('Date', 'jc-importer'), 'post_date', [
                    'tooltip' => __('The date of the post , enter in the format "YYYY-MM-DD HH:ii:ss"', 'jc-importer')
                ]),
                $this->register_group(__('Parent Settings', 'jc-importer'), '_parent', [
                    $this->register_field(__('Parent', 'jc-importer'), 'parent', [
                        'default' => '',
                        'options' => 'callback',
                        'tooltip' => __('Set this for the post it belongs to', 'jc-importer')
                    ]),
                    $this->register_field(__('Parent Field Type', 'jc-importer'), '_parent_type', [
                        'default' => 'id',
                        'options' => [
                            ['value' => 'id', 'label' => __('ID', 'jc-importer')],
                            ['value' => 'slug', 'label' => __('Slug', 'jc-importer')],
                            ['value' => 'name', 'label' => __('Name', 'jc-importer')],
                            ['value' => 'column', 'label' => __('Reference Column', 'jc-importer')],
                        ],
                        'type' => 'select',
                        'tooltip' => __('Select how the parent field should be handled', 'jc-importer')
                    ]),
                    $this->register_field(__('Parent Reference Column', 'jc-importer'), '_parent_ref', [
                        'condition' => ['_parent_type', '==', 'column'],
                        'tooltip' => __('Select the column/node that the parent field is referencing', 'jc-importer')
                    ])
                ]),
                $this->register_field(__('Order', 'jc-importer'), 'menu_order', [
                    'tooltip' => __('The order the post should be displayed in', 'jc-importer')
                ]),
                $this->register_group(__('Author Settings', 'jc-importer'), '_author', [
                    $this->register_field(__('Author', 'jc-importer'), 'post_author', [
                        'tooltip' => __('The user of who added this post', 'jc-importer')
                    ]),
                    $this->register_field(__('Author Field Type', 'jc-importer'), '_author_type', [
                        'default' => 'id',
                        'options' => [
                            ['value' => 'id', 'label' => __('ID', 'jc-importer')],
                            ['value' => 'login', 'label' => __('Login', 'jc-importer')],
                            ['value' => 'email', 'label' => __('Email', 'jc-importer')],
                        ],
                        'tooltip' => __('Select how the author field should be handled', 'jc-importer')
                    ])
                ]),
                $this->register_field(__('Password', 'jc-importer'), 'post_password', [
                    'tooltip' => __('The password to access the post', 'jc-importer')
                ]),
                $this->register_field(__('Allow Comments', 'jc-importer'), 'comment_status', [
                    'options' => [
                        ['value' => '0', 'label' => __('Disabled', 'jc-importer')],
                        ['value' => '1', 'label' => __('Enabled', 'jc-importer')]
                    ],
                    'default' => '0',
                    'tooltip' => __('Whether the post can accept comments', 'jc-importer')
                ]),
                $this->register_field(__('Allow Pingbacks', 'jc-importer'), 'ping_status', [
                    'options' => [
                        ['value' => 'closed', 'label' => __('Closed', 'jc-importer')],
                        ['value' => 'open', 'label' => __('Open', 'jc-importer')]
                    ],
                    'default' => 'closed',
                    'tooltip' => __('Whether the post can accept pings', 'jc-importer')
                ])
            ]),
            $this->register_taxonomy_fields()
        ];
    }

    public function register_settings()
    {
        return [];
    }

    public function register_options()
    {
        return [];
    }

    public function pre_process(ParsedData $data)
    {
        $data = parent::pre_process($data);

        $field_map = $data->getData();

        $field_id = sprintf('post.%s', '_wp_attachment_image_alt');
        $value = $data->getValue($field_id, 'post');
        if ($value !== false && $this->importer->isEnabledField($field_id)) {
            $field_map['_wp_attachment_image_alt'] = $value;
        }

        $file_location = $data->getValue('post.file.location');
        if ($file_location !== false && $this->importer->isEnabledField('post.file')) {

            // Added so it can be used as a unique identifier
            // But not in default so it isnt automatically inserted.
            $data->add(['src' => $file_location], 'post');
        }

        $data->replace($field_map, 'default');

        return $data;
    }

    /**
     * Convert fields/headings to data map
     * 
     * @param mixed $fields
     * @param ImporterModel $importer
     * @return array 
     */
    public function generate_field_map($fields, $importer)
    {
        $result = parent::generate_field_map($fields, $importer);
        $map = $result['map'];
        $enabled = $result['enabled'];

        foreach ($fields as $index => $field) {

            if ($field === 'custom_fields._wp_attachment_image_alt') {

                $field_key = $this->field_map[$field];
                $map[$field_key] = sprintf('{%s}', $index);
                $enabled[] = $field_key;

                add_filter('iwp/importer/generate_field_map/custom_fields', function ($custom_fields) {

                    if (isset($custom_fields['_wp_attachment_image_alt'])) {
                        unset($custom_fields['_wp_attachment_image_alt']);
                    }

                    return $custom_fields;
                });
            } elseif ($field === 'url') {


                $map['post.file.location'] = sprintf('{%s}', $index);
                $enabled[] = 'post.file';
            }
        }

        return [
            'map' => $map,
            'enabled' => $enabled
        ];
    }

    public function get_permission_fields($importer_model)
    {
        $permission_fields = parent::get_permission_fields($importer_model);

        $permission_fields['core']['_wp_attachment_image_alt'] = __('Alt Text', 'jc-importer');
        $permission_fields['core']['file'] =  __('Attachment File', 'jc-importer');

        return $permission_fields;
    }

    public function get_unique_identifier_options($importer_model, $unique_fields = [])
    {
        $output = parent::get_unique_identifier_options($importer_model, $unique_fields);

        $mapped_data = $importer_model->getMap();

        if (isset($mapped_data['post.file.location']) && !empty($mapped_data['post.file.location']) && $importer_model->isEnabledField('post.file')) {
            $output['src'] = [
                'value' => 'src',
                'label' => 'src',
                'uid' => true,
                'active' => true,
            ];
        }

        return array_merge(
            $output,
            $this->get_unique_identifier_options_from_map($importer_model, $unique_fields, $this->field_map, $this->optional_fields)
        );
    }
}
