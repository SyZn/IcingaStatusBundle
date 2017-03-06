<?php

namespace RavuAlHemio\IcingaStatusBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use RavuAlHemio\IcingaStatusBundle\DependencyInjection\IcingaStatusExtension;

class RavuAlHemioIcingaStatusBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new IcingaStatusExtension();
    }
}
