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
}
