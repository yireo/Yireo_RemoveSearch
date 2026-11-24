<?php declare(strict_types=1);

namespace Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;

use Magento\Framework\ObjectManagerInterface;

class SearchResultApplierFactory
{
    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * @param array $data
     * @return SearchResultApplier
     */
    public function create(array $data = []): SearchResultApplier
    {
        return $this->objectManager->create(SearchResultApplier::class, $data);
    }
}
