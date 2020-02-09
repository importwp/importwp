<?php

namespace ImportWP\Common\Importer;

interface IndexedFileInterface
{

    function loadIndex();
    function saveIndex();
    function generateIndex();
}
