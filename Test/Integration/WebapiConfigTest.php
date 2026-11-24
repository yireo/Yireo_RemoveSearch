<?php

declare(strict_types=1);

namespace Yireo\RemoveSearch\Test\Integration;

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Webapi\Model\Config;
use PHPUnit\Framework\TestCase;

class WebapiConfigTest extends TestCase
{
    public function testSearchRouteIsNotAnonymous(): void
    {
        $routes = Bootstrap::getObjectManager()->get(Config::class)->getServices()['routes'] ?? [];
        if (!isset($routes['/V1/search']['GET'])) {
            $this->markTestSkipped('The route /V1/search is not registered');
        }

        $resources = array_keys($routes['/V1/search']['GET']['resources']);
        $this->assertContains('Magento_Catalog::products', $resources);
    }
}
