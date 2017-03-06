<?php

namespace RavuAlHemio\IcingaStatusBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class IcingaStatusExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $objConfig = new Configuration();
        $objProcessedConfig = $this->processConfiguration($objConfig, $configs);

        $container->setParameter('icingastatus.database_connection', $objProcessedConfig['database_connection']);
    }
}
