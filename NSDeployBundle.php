<?php

namespace NS\DeployBundle;

use NS\CoreBundle\Bundle\CoreBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class NSDeployBundle extends Bundle implements CoreBundle
{
    /**
     * Retrieves human-readable bundle title
     *
     * @return string
     */
    public function getTitle()
    {
        return '';
    }
}
