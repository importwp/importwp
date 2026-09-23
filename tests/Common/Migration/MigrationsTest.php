<?php

namespace ImportWPTests\Common\Migration;

use ImportWP\Common\Migration\Migrations;
use ImportWP\Common\Model\ImporterModel;

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

    /**
     * Imported configs (Tools → Import) are stored via wp_slash(serialize()).
     * wp_update_post() does not slash incoming fields — only the copy loaded
     * from the DB — so writing serialize() without wp_slash lets wp_insert_post()
     * stripslashes() eat the CSV escape backslash and corrupt the payload.
     * ImporterModel then treats the broken serialize as an empty importer.
     */
    public function test_migration_11_does_not_empty_imported_config_with_backslash_escape()
    {
        $data = [
            'template' => 'woocommerce-product',
            'template_type' => '',
            'parser' => 'csv',
            'file' => [
                'settings' => [
                    'enclosure' => '"',
                    'delimiter' => ',',
                    'escape' => '\\',
                    'show_headings' => true,
                ],
            ],
            'datasource' => [
                'type' => 'remote',
                'settings' => [
                    'remote_url' => 'https://example.com/products.csv',
                ],
            ],
            'map' => [
                'post.post_title' => '{1}',
                'shipping.dimensions._weight' => '[iwp_convert_kg_to_g({8})]',
                'shipping.dimensions._length' => '[iwphd1359_seperate_dimensions("{9}", "0")]',
                'shipping.dimensions._width' => '[iwphd1359_seperate_dimensions("{9}", "1")]',
                'shipping.dimensions._height' => '[iwphd1359_seperate_dimensions("{9}", "2")]',
                'product_gallery.0.location' => '[iwp_generate_image_urls_list({19})]',
            ],
            'enabled' => [
                'shipping.dimensions' => true,
            ],
            'settings' => [
                'unique_identifier' => '_sku',
            ],
        ];

        $id = wp_insert_post([
            'post_type' => IWP_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => 'PMC (New)',
            'post_content' => wp_slash(serialize($data)),
        ]);
        $this->assertGreaterThan(0, $id);

        $before = new ImporterModel($id);
        $this->assertSame('woocommerce-product', $before->getTemplate());
        $this->assertSame('\\', $before->getFileSetting('escape'));
        $this->assertSame(
            '[iwp_convert_kg_to_g({8})]',
            $before->getMap()['shipping.dimensions._weight']
        );

        $migrations = new Migrations();
        $migrations->migration_11_prefix_custom_methods(true);

        $raw = get_post($id)->post_content;
        $updated = maybe_unserialize($raw);
        $this->assertIsArray($updated, 'Migrated post_content must remain unserializable');
        $this->assertArrayHasKey('map', $updated);
        $this->assertNotEmpty($updated['map']);

        $after = new ImporterModel($id);
        $this->assertSame('woocommerce-product', $after->getTemplate(), 'Importer template should survive migration');
        $this->assertSame('\\', $after->getFileSetting('escape'));
        $this->assertSame('"', $after->getFileSetting('enclosure'));
        $this->assertSame('{1}', $after->getMap()['post.post_title']);
        $this->assertSame(
            '[iwp:iwp_convert_kg_to_g({8})]',
            $after->getMap()['shipping.dimensions._weight']
        );
        $this->assertSame(
            '[iwp:iwphd1359_seperate_dimensions("{9}", "0")]',
            $after->getMap()['shipping.dimensions._length']
        );
        $this->assertSame(
            '[iwp:iwphd1359_seperate_dimensions("{9}", "1")]',
            $after->getMap()['shipping.dimensions._width']
        );
        $this->assertSame(
            '[iwp:iwphd1359_seperate_dimensions("{9}", "2")]',
            $after->getMap()['shipping.dimensions._height']
        );
        $this->assertSame(
            '[iwp:iwp_generate_image_urls_list({19})]',
            $after->getMap()['product_gallery.0.location']
        );
    }

    public function test_import_runs_pending_migrations_before_loading_importer()
    {
        update_option('iwp_db_version', 10);
        delete_option('iwp_is_migrating');

        $id = wp_insert_post([
            'post_type' => IWP_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => 'Import Path Migration',
            'post_content' => serialize([
                'map' => [
                    'post.post_title' => '[strtoupper("{0}")]',
                ],
                'settings' => [
                    'post_type' => 'post',
                ],
                'template' => 'post',
            ]),
        ]);
        $this->assertGreaterThan(0, $id);

        $manager = \ImportWP\Container::getInstance()->get('importer_manager');
        try {
            $manager->import($id, uniqid('wptest'));
        } catch (\Throwable $e) {
            // Import may fail without a source file; migrations must still have run.
        }

        $this->assertSame(11, intval(get_option('iwp_db_version')));

        $updated = maybe_unserialize(get_post($id)->post_content);
        $this->assertIsArray($updated);
        $this->assertSame('[iwp:strtoupper("{0}")]', $updated['map']['post.post_title']);
    }
}
