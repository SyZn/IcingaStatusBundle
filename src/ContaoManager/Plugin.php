<?php

namespace RavuAlHemio\IcingaStatusBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Contao\CoreBundle\ContaoCoreBundle;
use RavuAlHemio\IcingaStatusBundle\IcingaStatusBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(IcingaStatusBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
