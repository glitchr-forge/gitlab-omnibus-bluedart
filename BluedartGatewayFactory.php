<?php

namespace Omnibus\Bluedart;

use Omnibus\Core\GatewayFactory;

class BluedartGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'omnibus.factory_name' => 'bluedart',
            'omnibus.factory_title' => 'Bluedart',
        ]);
    }
}