<?php

namespace ImportWP\Common\Importer\Mapper;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\PermissionInterface;
use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\Common\Model\ImporterModel;

class AbstractMapper
{
    protected $ID;
    /**
     * Importer Template
     *
     * @var TemplateInterface
     */
    protected $template;
    /**
     * Importer Data
     *
     * @var ImporterModel
     */
    protected $importer;
    /**
     * Importer Permissions
     *
     * @var PermissionInterface
     */
    protected $permission;

    public function __construct(ImporterModel $importer, Template $template, PermissionInterface $permission = null)
    {
        $this->importer = $importer;
        $this->template = $template;
        $this->permission = $permission;
    }

    public function permission()
    {
        if (is_null($this->permission)) {
            return false;
        }

        return $this->permission;
    }

    public function getUniqueIdentifiers($unique_fields = [])
    {

        // set via importer interface
        $unique_identifier = $this->importer->getSetting('unique_identifier');
        if (empty($unique_identifier)) {
            return $unique_fields;
        }

        $parts = array_filter(array_map('trim', explode(',', $unique_identifier)));
        if (empty($parts)) {
            return $unique_fields;
        }

        return $parts;
    }
}
