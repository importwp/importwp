<?php

namespace ImportWP\Common\Importer;

use ImportWP\Common\Model\ImporterModel;

interface TemplateInterface
{
    public function register();
    public function register_settings();
    public function register_options();
    public function get_permission_fields($importer_model);

    /**
     * Process data before record is importer.
     *
     * Alter data that is passed to the mapper.
     *
     * @param ParsedData $data
     * @return ParsedData
     */
    public function pre_process(ParsedData $data);

    /**
     * Process data after record is imported, but before custom fields.
     *
     * Use data that is returned from the mapper.
     *
     * @param int $post_id
     * @param ParsedData $data
     * @param ImporterModel $importer_model
     * @return ParsedData
     */
    public function process($post_id, ParsedData $data, ImporterModel $importer_model);

    /**
     * Process data after record is importer.
     *
     * Use data that is returned from the mapper.
     *
     * @param int $post_id
     * @param ParsedData $data
     * @return void
     */
    public function post_process($post_id, ParsedData $data);
}
