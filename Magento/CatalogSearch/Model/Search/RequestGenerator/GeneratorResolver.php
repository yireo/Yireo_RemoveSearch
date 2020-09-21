<?php declare(strict_types=1);

namespace Magento\CatalogSearch\Model\Search\RequestGenerator;

use Magento\CatalogSearch\Model\Search\RequestGenerator;
use Magento\Framework\ObjectManagerInterface;

class GeneratorResolver
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * @param string $type
     * @return RequestGenerator
     */
    public function getGeneratorForType(string $type): RequestGenerator
    {
        return $this->objectManager->create(RequestGenerator::class);
    }
}
