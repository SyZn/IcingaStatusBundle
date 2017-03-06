<?php

namespace RavuAlHemio\IcingaStatusBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $objTreeBuilder = new TreeBuilder();
        $objRootNode = $objTreeBuilder->root('icingastatus');

        $objRootNode
            ->children()
                ->scalarNode('database_connection')
                    ->defaultValue('default')
                ->end()
            ->end()
        ;
    }
}
