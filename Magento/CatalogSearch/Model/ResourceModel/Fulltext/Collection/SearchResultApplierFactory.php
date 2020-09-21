<?php declare(strict_types=1);

namespace Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;

use Magento\Framework\ObjectManagerInterface;

class SearchResultApplierFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * SearchResultApplierFactory constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * @return SearchResultApplier
     */
    public function create(): SearchResultApplier
    {
        return $this->objectManager->create(SearchResultApplier::class);
    }
}
