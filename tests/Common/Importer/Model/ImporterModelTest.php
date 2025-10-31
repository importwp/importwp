<?php

namespace ImportWPTests\Common\Importer;

class ImporterModelTest extends \WP_UnitTestCase
{

    public function testBasicSave()
    {
        $importer = new \ImportWP\Common\Model\ImporterModel();
        $id = $importer->save();
        $this->assertGreaterThan(0, $id);
        $this->assertNotFalse(unserialize(get_post($id)->post_content));
    }

    public function testBasicSaveWithSlash()
    {
        $importer = new \ImportWP\Common\Model\ImporterModel();
        $importer->setFileSetting('escape', '\\');
        $id = $importer->save();
        $this->assertGreaterThan(0, $id);

        $importer = new \ImportWP\Common\Model\ImporterModel($id);
        $this->assertEquals('\\', $importer->getFileSetting('escape'));
    }
}
