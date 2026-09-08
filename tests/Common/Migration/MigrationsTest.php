<?php

namespace ImportWPTests\Common\Migration;

use ImportWP\Common\Migration\Migrations;

class MigrationsTest extends \WP_UnitTestCase
{
    public function test_migration_11_prefixes_custom_methods_in_importer_map()
    {
        $data = [
            'map' => [
                'post.post_title' => '[strtoupper("{0}")]',
                'post.post_content' => '[strtoupper("{1}")] [strtolower("{2}")]',
                'post.post_excerpt' => '[iwp:trim("{3}")]',
                'custom_fields.0.value' => 'plain text',
            ],
            'settings' => [
                'filters' => [
                    'filters.0.0.left' => '[trim("{0}")]',
                    'filters.0.0.right' => 'yes',
                ],
            ],
        ];

        $id = wp_insert_post([
            'post_type' => IWP_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => 'Migration 11 Test',
            'post_content' => serialize($data),
        ]);
        $this->assertGreaterThan(0, $id);

        $migrations = new Migrations();
        $migrations->migration_11_prefix_custom_methods(true);

        $updated = maybe_unserialize(get_post($id)->post_content);

        $this->assertEquals('[iwp:strtoupper("{0}")]', $updated['map']['post.post_title']);
        $this->assertEquals(
            '[iwp:strtoupper("{1}")] [iwp:strtolower("{2}")]',
            $updated['map']['post.post_content']
        );
        $this->assertEquals('[iwp:trim("{3}")]', $updated['map']['post.post_excerpt']);
        $this->assertEquals('plain text', $updated['map']['custom_fields.0.value']);
        $this->assertEquals('[iwp:trim("{0}")]', $updated['settings']['filters']['filters.0.0.left']);
        $this->assertEquals('yes', $updated['settings']['filters']['filters.0.0.right']);
    }

    public function test_migration_11_is_idempotent()
    {
        $data = [
            'map' => [
                'post.post_title' => '[iwp:strtoupper("{0}")]',
            ],
        ];

        $id = wp_insert_post([
            'post_type' => IWP_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => 'Migration 11 Idempotent',
            'post_content' => serialize($data),
        ]);

        $migrations = new Migrations();
        $migrations->migration_11_prefix_custom_methods(true);
        $migrations->migration_11_prefix_custom_methods(true);

        $updated = maybe_unserialize(get_post($id)->post_content);
        $this->assertEquals('[iwp:strtoupper("{0}")]', $updated['map']['post.post_title']);
    }
}
