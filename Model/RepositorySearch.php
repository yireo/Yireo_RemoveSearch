<?php declare(strict_types=1);

namespace Yireo\RemoveSearch\Model;

use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Search\Api\SearchInterface;

class RepositorySearch implements SearchInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * RepositorySearch constructor.
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository
    ) {
        $this->productRepository = $productRepository;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return ProductSearchResultsInterface
     */
    public function search(SearchCriteriaInterface $searchCriteria)
    {
        return $this->productRepository->getList($searchCriteria);
    }
}
