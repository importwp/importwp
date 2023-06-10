<?php

namespace ImportWPTests\Utils;

use ImportWP\Common\Importer\Mapper\PostMapper;
use ImportWP\Common\Importer\Mapper\TermMapper;
use ImportWP\Common\Importer\Mapper\UserMapper;
use ImportWP\Common\Importer\Permission\Permission;
use ImportWP\Common\Importer\Template\PostTemplate;
use ImportWP\Common\Importer\Template\TermTemplate;
use ImportWP\Common\Importer\Template\UserTemplate;
use ImportWP\Common\Model\ImporterModel;

trait MockDataTrait
{
    protected $mock_data = [
        'user' => [
            'user_pass' => 'password',
            'user_login' => 'user_one',
            'user_nicename' => 'user-one',
            'user_url' => 'http://www.google.com',
            'user_email' => 'user@one.com',
            'display_name' => 'User One',
            'nickname' => 'User Nickname',
            'first_name' => 'User',
            'last_name' => 'One',
            'description' => 'Test User',
            'rich_editing' => 'yes',
            'user_registered' => '2020-01-16 08:03:00',
            'role' => 'administrator',
            'jabber' => 'user-one-jabber',
            'aim' => 'user-one-aim',
            'yim' => 'user-one-yim'
        ],
        'post' => [
            'post_author' => 1,
            'post_date' => '2020-01-16 08:03:00',
            'post_date_gmt' => '2020-01-16 08:03:00',
            'post_content' => 'Test Post Content',
            'post_title' => 'Test Post Title',
            'post_excerpt' => 'Test post excerpt',
            'post_status' => 'draft',
            'post_type' => 'post',
            'comment_status' => 'open',
            'ping_status' => 'open',
            'post_password' => '',
            'post_name' => 'test-post-title',
            'post_modified' => '2020-01-16 08:03:00',
            'post_modified_gmt' => '2020-01-16 08:03:00',
            'post_parent' => 0,
            'menu_order' => 0,
            'post_mime_type' => '',
            'guid' => '',

        ]
    ];

    public function create_post_mapper($importer_model = null, $template = null, $permission = null)
    {
        if (is_null($importer_model)) {
            $importer_model = $this->createMock(ImporterModel::class);
        }

        if (is_null($template)) {
            $template = $this->createMock(PostTemplate::class);
        }

        if (is_null($permission)) {
            $permission = $this->createMock(Permission::class);
        }

        return new PostMapper($importer_model, $template, $permission);
    }

    public function create_term_mapper($taxonomy = 'category', $importer_model = null, $template = null, $permission = null)
    {
        if (is_null($importer_model)) {
            $importer_model = $this->createMock(ImporterModel::class);
            $importer_model->method('getSetting')->will($this->returnCallback(function ($key) use ($taxonomy) {
                return $key === 'taxonomy' ? $taxonomy : null;
            }));
        }

        if (is_null($template)) {
            $template = $this->createMock(TermTemplate::class);
        }

        if (is_null($permission)) {
            $permission = $this->createMock(Permission::class);
        }

        return new TermMapper($importer_model, $template, $permission);
    }

    public function create_user_mapper($importer_model = null, $template = null, $permission = null)
    {
        if (is_null($importer_model)) {
            $importer_model = $this->createMock(ImporterModel::class);
        }

        if (is_null($template)) {
            $template = $this->createMock(UserTemplate::class);
        }

        if (is_null($permission)) {
            $permission = $this->createMock(Permission::class);
        }

        return new UserMapper($importer_model, $template, $permission);
    }

    public function create_attachment_mock($index, $mock_data = [], $type = 'remote')
    {

        $location = isset($mock_data['location']) ? $mock_data['location'] : 'test.png';

        $data = [
            // core
            'attachments.' . $index . '.location' => $location,
            'attachments.' . $index . '._download' => $type,
            'attachments.' . $index . '._featured' => '',

            // meta
            'attachments.' . $index . '._meta._title' => '',
            'attachments.' . $index . '._meta._alt' => '',
            'attachments.' . $index . '._meta._caption' => '',
            'attachments.' . $index . '._meta._description' => '',
        ];

        if ('remote' === $type) {
            $data = array_merge($data, [
                'attachments.' . $index . '._remote_url' => '',
            ]);
        } elseif ('ftp' === $type) {
            $data = array_merge($data, [
                'attachments.' . $index . '._ftp_user' => '',
                'attachments.' . $index . '._ftp_host' => '',
                'attachments.' . $index . '._ftp_pass' => '',
                'attachments.' . $index . '._ftp_path' => '',
            ]);
        } elseif ('local' === $type) {
            $data = array_merge($data, [
                'attachments.' . $index . '._local_url' => '',
            ]);
        }

        return $data;
    }

    public function create_taxonomy_mock($index, $mock_data = [])
    {
        $data = [
            'taxonomies.' . $index . '.term' => isset($mock_data['term']) ? $mock_data['term'] : '',
            'taxonomies.' . $index . '.tax' => isset($mock_data['tax']) ? $mock_data['tax'] : '',
            'taxonomies.' . $index . '.settings._hierarchy' => isset($mock_data['hierarchy']) ? $mock_data['hierarchy'] : 'no',
            'taxonomies.' . $index . '.settings._hierarchy_character' => isset($mock_data['hierarchy_character']) ? $mock_data['hierarchy_character'] : '>',
        ];

        if (isset($mock_data['type'])) {
            $data['taxonomies.' . $index . '.settings._type'] = $mock_data['type'];
        }

        return $data;
    }
}
