<?php

namespace ImportWPTests\Common\Rest;

use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Rest\RestManager;
use ImportWP\Container;
use ImportWPTests\Utils\ProtectedPropertyTrait;

class RestManagerTest extends \WP_UnitTestCase
{
    use ProtectedPropertyTrait;

    /**
     * @var RestManager
     */
    private $rest_manager;

    public function setUp(): void
    {
        parent::setUp();
        $this->rest_manager = Container::getInstance()->get('rest_manager');
        wp_set_current_user($this->factory()->user->create(['role' => 'administrator']));
    }

    /**
     * REST body params are already unslashed. Packed map JSON that contains
     * quoted modifier args must still decode (double wp_unslash breaks it).
     */
    public function testDecodePackedFieldsKeepsQuotedModifierArgs()
    {
        $map = [
            'post.post_title' => '{1}',
            'price._regular_price' => '{4}',
            'shipping.dimensions._length' => '[iwphd1359_seperate_dimensions("{9}", "0")]',
            'shipping.dimensions._width' => '[iwphd1359_seperate_dimensions("{9}", "1")]',
            'shipping.dimensions._height' => '[iwphd1359_seperate_dimensions("{9}", "2")]',
            'shipping.dimensions._weight' => '[iwp_convert_kg_to_g({8})]',
        ];

        // Simulate WP_REST_Server::set_body_params( wp_unslash( $_POST ) ):
        // magic-quoted POST is unslashed once before RestManager sees it.
        $packed = wp_json_encode($map);
        $rest_params = [
            'map' => wp_unslash(addslashes($packed)),
            'enabled' => wp_unslash(addslashes(wp_json_encode(['shipping.dimensions' => true]))),
        ];

        $decoded = $this->invokeDecodePackedFields($rest_params);

        $this->assertIsArray($decoded['map'], 'Packed map JSON should decode when REST params are already unslashed');
        $this->assertSame('{1}', $decoded['map']['post.post_title']);
        $this->assertSame('{4}', $decoded['map']['price._regular_price']);
        $this->assertSame(
            '[iwphd1359_seperate_dimensions("{9}", "0")]',
            $decoded['map']['shipping.dimensions._length']
        );
        $this->assertSame(
            '[iwphd1359_seperate_dimensions("{9}", "1")]',
            $decoded['map']['shipping.dimensions._width']
        );
        $this->assertSame(
            '[iwphd1359_seperate_dimensions("{9}", "2")]',
            $decoded['map']['shipping.dimensions._height']
        );
        $this->assertSame(
            '[iwp_convert_kg_to_g({8})]',
            $decoded['map']['shipping.dimensions._weight']
        );
        $this->assertIsArray($decoded['enabled']);
        $this->assertTrue($decoded['enabled']['shipping.dimensions']);
    }

    /**
     * Slashed form payloads (pre-REST unslash) should still decode via fallback.
     */
    public function testDecodePackedFieldsAcceptsSlashedFormData()
    {
        $map = [
            'shipping.dimensions._length' => '[iwphd1359_seperate_dimensions("{9}", "0")]',
        ];

        $decoded = $this->invokeDecodePackedFields([
            'map' => addslashes(wp_json_encode($map)),
        ]);

        $this->assertIsArray($decoded['map']);
        $this->assertSame(
            '[iwphd1359_seperate_dimensions("{9}", "0")]',
            $decoded['map']['shipping.dimensions._length']
        );
    }

    /**
     * Saving an importer with packed map values that include quoted modifier
     * args must persist those mappings (and other fields in the same payload).
     */
    public function testSaveImporterPersistsPackedMapWithQuotedModifiers()
    {
        $importer = new ImporterModel([
            'name' => 'Packed Map Quoted Modifiers',
            'template' => 'post',
            'template_type' => '',
            'parser' => 'csv',
        ]);
        $importer_id = $importer->save();
        $this->assertGreaterThan(0, $importer_id);

        $map = [
            'post.post_title' => '{1} UPDATED',
            'price._regular_price' => '[iwp_increase_and_round_price({4})]',
            'shipping.dimensions._length' => '[iwphd1359_seperate_dimensions("{9}", "0")]',
            'shipping.dimensions._width' => '[iwphd1359_seperate_dimensions("{9}", "1")]',
            'shipping.dimensions._height' => '[iwphd1359_seperate_dimensions("{9}", "2")]',
            'shipping.dimensions._weight' => '[iwp_convert_kg_to_g({8})]',
        ];
        $enabled = [
            'shipping.dimensions' => true,
        ];

        $packed_map = wp_json_encode($map);
        $packed_enabled = wp_json_encode($enabled);

        $request = new \WP_REST_Request('POST', '/iwp/v1/importer/' . $importer_id);
        $request->set_body_params([
            'id' => $importer_id,
            'name' => 'Packed Map Quoted Modifiers',
            // Already-unslashed, as REST provides.
            'map' => wp_unslash(addslashes($packed_map)),
            'enabled' => wp_unslash(addslashes($packed_enabled)),
        ]);

        $response = $this->rest_manager->save_importer($request);
        $this->assertIsArray($response);
        $this->assertSame('S', $response['status'], is_string($response['data'] ?? null) ? $response['data'] : 'Save failed');

        $saved = new ImporterModel($importer_id);
        $saved_map = $saved->getMap();

        $this->assertArrayHasKey('post.post_title', $saved_map, 'Packed map with quoted modifiers should be applied on save');
        $this->assertSame('{1} UPDATED', $saved_map['post.post_title']);
        $this->assertSame('[iwp_increase_and_round_price({4})]', $saved_map['price._regular_price']);
        $this->assertSame(
            '[iwphd1359_seperate_dimensions("{9}", "0")]',
            $saved_map['shipping.dimensions._length']
        );
        $this->assertSame(
            '[iwphd1359_seperate_dimensions("{9}", "1")]',
            $saved_map['shipping.dimensions._width']
        );
        $this->assertSame(
            '[iwphd1359_seperate_dimensions("{9}", "2")]',
            $saved_map['shipping.dimensions._height']
        );
        $this->assertSame('[iwp_convert_kg_to_g({8})]', $saved_map['shipping.dimensions._weight']);

        wp_delete_post($importer_id, true);
    }

    private function invokeDecodePackedFields(array $post_data)
    {
        $method = new \ReflectionMethod(RestManager::class, 'decode_packed_fields');
        $method->setAccessible(true);

        return $method->invoke($this->rest_manager, $post_data);
    }
}
