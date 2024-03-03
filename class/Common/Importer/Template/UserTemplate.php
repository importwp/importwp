<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\EventHandler;

class UserTemplate extends Template implements TemplateInterface
{
    protected $name = 'User';
    protected $mapper = 'user';

    protected $field_map = [
        'ID' => 'user.ID',
        'user_login' => 'user.user_login',
        'user_email' => 'user.user_email',
        'first_name' => 'user.first_name',
        'last_name' => 'user.last_name',
        'role' => 'user.role',
        'user_url' => 'user.user_url',
        'user_pass' => 'user.user_pass',
        'user_nicename' => 'user.user_nicename',
        'display_name' => 'user.display_name',
        'description' => 'user.description',
    ];

    protected $optional_fields = [
        'ID',
        'user_url',
        'user_pass',
        'role',
        'user_nicename',
        'display_name',
        'description'
    ];

    public function __construct(EventHandler $event_handler)
    {
        parent::__construct($event_handler);

        $this->groups[] = 'user';
        $this->field_options = array_merge($this->field_options, [
            'user.role' => [$this, 'get_user_role_options'],
        ]);
    }

    public function register()
    {
        $groups = [];

        // User
        $groups[] = $this->register_group(__('User Fields', 'jc-importer'), 'user', [
            $this->register_field(__('ID', 'jc-importer'), 'ID', [
                'tooltip' => __('ID is only used to reference existing records', 'jc-importer')
            ]),
            $this->register_core_field(__('Username', 'jc-importer'), 'user_login', [
                'tooltip' => __('The user\'s login username', 'jc-importer')
            ]),
            $this->register_core_field(__('Email', 'jc-importer'), 'user_email', [
                'tooltip' => __('The user email address', 'jc-importer')
            ]),
            $this->register_core_field(__('First Name', 'jc-importer'), 'first_name', [
                'tooltip' => __('The user\'s first name', 'jc-importer')
            ]),
            $this->register_core_field(__('Last Name', 'jc-importer'), 'last_name', [
                'tooltip' => __('The user\'s last name', 'jc-importer')
            ]),
            $this->register_field(__('Role', 'jc-importer'), 'role', [
                'tooltip' => __('The plain-text user password', 'jc-importer'),
                'options' => 'callback',
                'default' => 'subscriber'
            ]),
            $this->register_field(__('Website', 'jc-importer'), 'user_url', [
                'tooltip' => __('The user\'s website address', 'jc-importer')
            ]),
            $this->register_field(__('Password', 'jc-importer'), 'user_pass', [
                'tooltip' => __('The plain-text user password', 'jc-importer')
            ]),
            $this->register_field(__('Nicename', 'jc-importer'), 'user_nicename', []),
            $this->register_field(__('Display Name', 'jc-importer'), 'display_name', []),
            $this->register_field(__('Description', 'jc-importer'), 'description', []),
        ], ['link' => 'https://www.importwp.com/docs/wordpress-user-importer-template/']);

        return $groups;
    }



    public function register_settings()
    {
        return [
            $this->register_field(__('Enable user notifications.', 'jc-importer'), 'notify_users', [
                'type' => 'checkbox',
                'tooltip' => __('Trigger the action to send WordPress notification emails', 'jc-importer')
            ]),
            $this->register_field(__('Generate user password.', 'jc-importer'), 'generate_pass', [
                'type' => 'checkbox',
                'tooltip' => __('Enable the generation of user passwords', 'jc-importer')
            ]),
        ];
    }

    public function register_options()
    {
        return [];
    }

    /**
     * Process data before record is importer.
     * 
     * Alter data that is passed to the mapper.
     *
     * @param ParsedData $data
     * @return ParsedData
     */
    public function pre_process(ParsedData $data)
    {
        $data = parent::pre_process($data);

        $user_field_map = [
            'ID' => $data->getValue('user.ID'),
            'user_pass' => $data->getValue('user.user_pass'),
            'user_login' => $data->getValue('user.user_login'),
            'user_nicename' => $data->getValue('user.user_nicename'),
            'user_url' => $data->getValue('user.user_url'),
            'user_email' => $data->getValue('user.user_email'),
            'display_name' => $data->getValue('user.display_name'),
            'nickname' => $data->getValue('user.nickname'),
            'first_name' => $data->getValue('user.first_name'),
            'last_name' => $data->getValue('user.last_name'),
            'description' => $data->getValue('user.description'),
            'rich_editing' => $data->getValue('user.rich_editing'),
            'user_registered' => $data->getValue('user.user_registered'),
            'role' => $data->getValue('user.role'),
            'jabber' => $data->getValue('user.jabber'),
            'aim' => $data->getValue('user.aim'),
            'yim' => $data->getValue('user.yim'),
        ];

        // remove fields that have not been set
        foreach ($user_field_map as $field_key => $field_map) {

            if ($field_map === false) {
                unset($user_field_map[$field_key]);
                continue;
            }
        }

        foreach ($this->optional_fields as $optional_field) {
            if (true !== $this->importer->isEnabledField('user.' . $optional_field)) {
                unset($user_field_map[$optional_field]);
            }
        }

        $generate_pass = $this->importer->getSetting('generate_pass');
        if ($generate_pass === true) {
            $user_field_map['user_pass'] = wp_generate_password(10);
        }

        $notify_users = $this->importer->getSetting('notify_users');
        if ($notify_users !== true) {

            // Disable password changed email
            if (isset($user_field_map['user_pass'])) {
                add_filter('send_password_change_email', '__return_false');
            }

            // Disable user_email changed email
            if (isset($user_field_map['user_email'])) {
                add_filter('send_email_change_email', '__return_false');
            }
        }

        foreach ($user_field_map as $key => $value) {
            $user_field_map[$key] = apply_filters('iwp/template/process_field', $value, $key, $this->importer);
        }

        $data->replace($user_field_map, 'default');

        return $data;
    }

    /**
     * Process data after record is importer.
     * 
     * Use data that is returned from the mapper.
     *
     * @param int $user_id
     * @param ParsedData $data
     * @return void
     */
    final public function post_process($user_id, ParsedData $data)
    {
        $notify_users = $this->importer->getSetting('notify_users');
        if ($notify_users === true) {
            wp_new_user_notification($user_id, null, 'user');
        }

        parent::post_process($user_id, $data);
    }

    /**
     * Get list of posts
     *
     * @return array|\WP_Error
     */
    public function get_user_role_options($importer_data)
    {
        $result = [];

        global $wp_roles;
        foreach ($wp_roles->roles as $role => $role_arr) {
            $result[] = [
                'value' => '' . $role,
                'label' => $role_arr['name']
            ];
        }

        return $result;
    }

    /**
     * Convert fields/headings to data map
     * 
     * @param mixed $fields
     * @return array 
     */
    public function generate_field_map($fields, $importer)
    {
        $result = parent::generate_field_map($fields, $importer);
        $map = $result['map'];
        $enabled = $result['enabled'];

        foreach ($fields as $index => $field) {
            if (isset($this->field_map[$field])) {

                // Handle core fields
                $field_key = $this->field_map[$field];
                $map[$field_key] = sprintf('{%s}', $index);

                if (in_array($field, $this->optional_fields)) {
                    $enabled[] = $field_key;
                }

                if (in_array($field, ['role'])) {
                    $map[$field_key . '._enable_text'] = 'yes';
                }
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

        $permission_fields['core'] = [
            'ID' => __('ID', 'jc-importer'),
            'user_pass' => __('Password', 'jc-importer'),
            'user_login' => __('Login', 'jc-importer'),
            'user_nicename' => __('Nice name', 'jc-importer'),
            'user_url' => __('Url', 'jc-importer'),
            'user_email' => __('Email', 'jc-importer'),
            'display_name' => __('Display Name', 'jc-importer'),
            'nickname' => __('Nick name', 'jc-importer'),
            'first_name' => __('First name', 'jc-importer'),
            'last_name' => __('Last name', 'jc-importer'),
            'description' => __('Description', 'jc-importer'),
            'rich_editing' => __('Rich Editing', 'jc-importer'),
            'user_registered' => __('User Registered', 'jc-importer'),
            'role' => __('Role', 'jc-importer'),
            'jabber' => __('Jabber', 'jc-importer'),
            'aim' => __('Aim', 'jc-importer'),
            'yim' => __('Yim', 'jc-importer'),
        ];

        return $permission_fields;
    }

    public function get_unique_identifier_options($importer_model, $unique_fields = [])
    {
        $output = parent::get_unique_identifier_options($importer_model, $unique_fields);

        return array_merge(
            $output,
            $this->get_unique_identifier_options_from_map($importer_model, $unique_fields, $this->field_map, $this->optional_fields)
        );
    }
}
