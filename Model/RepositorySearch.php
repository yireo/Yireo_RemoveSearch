<?php declare(strict_types=1);

namespace Yireo\RemoveSearch\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\DocumentFactory;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Search\Api\SearchInterface;
use Magento\Store\Model\StoreManagerInterface;

class RepositorySearch implements SearchInterface
{
    private const SEARCH_TERM_FIELD = 'search_term';
    private const MAX_VALUE_LENGTH = 255;
    private const MAX_VALUES = 100;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param SearchResultFactory $searchResultFactory
     * @param DocumentFactory $documentFactory
     * @param Visibility $visibility
     * @param StoreManagerInterface $storeManager
     * @param int $maxPageSize
     * @param string[] $allowedFields
     * @param string[] $allowedConditions
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly SearchResultFactory $searchResultFactory,
        private readonly DocumentFactory $documentFactory,
        private readonly Visibility $visibility,
        private readonly StoreManagerInterface $storeManager,
        private readonly int $maxPageSize = 100,
        private readonly array $allowedFields = ['sku', 'name', 'price', 'category_id'],
        private readonly array $allowedConditions = ['eq', 'in', 'like', 'gteq', 'lteq', 'from', 'to']
    ) {
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultInterface
     */
    public function search(SearchCriteriaInterface $searchCriteria)
    {
        $products = $this->productRepository->getList($this->buildSearchCriteria($searchCriteria));

        $documents = [];
        foreach ($products->getItems() as $product) {
            $document = $this->documentFactory->create();
            $document->setId((int)$product->getId());
            $documents[] = $document;
        }

        $searchResult = $this->searchResultFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($documents);
        $searchResult->setTotalCount($products->getTotalCount());

        return $searchResult;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Magento\Framework\Api\SearchCriteria
     */
    private function buildSearchCriteria(SearchCriteriaInterface $searchCriteria)
    {
        $isSearch = $searchCriteria->getRequestName() === 'quick_search_container';
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $filters = [];
            foreach ($filterGroup->getFilters() as $filter) {
                if ($filter->getField() === self::SEARCH_TERM_FIELD) {
                    $isSearch = true;
                }

                $sanitizedFilter = $this->sanitizeFilter($filter);
                if ($sanitizedFilter instanceof Filter) {
                    $filters[] = $sanitizedFilter;
                }
            }

            if ($filters !== []) {
                $this->searchCriteriaBuilder->addFilters($filters);
            }
        }

        $this->searchCriteriaBuilder->addFilter('status', Status::STATUS_ENABLED);
        $this->searchCriteriaBuilder->addFilter(
            'visibility',
            $this->getVisibilityIds($searchCriteria, $isSearch),
            'in'
        );
        $this->searchCriteriaBuilder->addFilter('website_id', (string)$this->storeManager->getWebsite()->getId());

        $this->addSortOrders((array)$searchCriteria->getSortOrders());

        $pageSize = (int)$searchCriteria->getPageSize();
        $this->searchCriteriaBuilder->setPageSize(
            $pageSize > 0 ? min($pageSize, $this->maxPageSize) : $this->maxPageSize
        );
        $this->searchCriteriaBuilder->setCurrentPage(max(1, (int)$searchCriteria->getCurrentPage()));

        return $this->searchCriteriaBuilder->create();
    }

    /**
     * @param array<int|string, mixed> $sortOrders
     * @return void
     */
    private function addSortOrders(array $sortOrders): void
    {
        foreach ($sortOrders as $key => $sortOrder) {
            $field = $sortOrder instanceof SortOrder ? $sortOrder->getField() : $key;
            $direction = $sortOrder instanceof SortOrder ? $sortOrder->getDirection() : $sortOrder;
            if (!in_array($field, $this->allowedFields, true)) {
                continue;
            }

            $this->searchCriteriaBuilder->addSortOrder(
                $this->sortOrderBuilder
                    ->setField($field)
                    ->setDirection($this->getDirection($direction))
                    ->create()
            );
        }
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function getSearchTerm(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return mb_substr(trim((string)$value, " \f\n\r\t\v\x00"), 0, self::MAX_VALUE_LENGTH);
    }

    /**
     * @param Filter $filter
     * @return Filter|null
     */
    private function sanitizeFilter(Filter $filter): ?Filter
    {
        $field = (string)$filter->getField();
        $value = $filter->getValue();

        if ($field === self::SEARCH_TERM_FIELD) {
            $searchTerm = $this->getSearchTerm($value);
            if ($searchTerm === '') {
                return null;
            }

            return $this->filterBuilder
                ->setField('name')
                ->setConditionType('like')
                ->setValue('%' . strtr($searchTerm, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%')
                ->create();
        }

        if ($field === 'category_ids') {
            $field = 'category_id';
        }

        $conditionType = (string)($filter->getConditionType() ?: 'eq');
        if (!in_array($field, $this->allowedFields, true)
            || !in_array($conditionType, $this->allowedConditions, true)
            || !$this->isValidValue($value)) {
            return null;
        }

        return $this->filterBuilder
            ->setField($field)
            ->setConditionType($conditionType)
            ->setValue($value)
            ->create();
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function isValidValue(mixed $value): bool
    {
        if (is_array($value)) {
            if ($value === [] || count($value) > self::MAX_VALUES) {
                return false;
            }

            foreach ($value as $item) {
                if (!is_scalar($item) || strlen((string)$item) > self::MAX_VALUE_LENGTH) {
                    return false;
                }
            }

            return true;
        }

        return is_scalar($value) && strlen((string)$value) <= self::MAX_VALUE_LENGTH;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @param bool $isSearch
     * @return int[]
     */
    private function getVisibilityIds(SearchCriteriaInterface $searchCriteria, bool $isSearch): array
    {
        if ($isSearch) {
            return $this->visibility->getVisibleInSearchIds();
        }

        if ($searchCriteria->getRequestName() === 'catalog_view_container') {
            return $this->visibility->getVisibleInCatalogIds();
        }

        return $this->visibility->getVisibleInSiteIds();
    }

    /**
     * @param mixed $direction
     * @return string
     */
    private function getDirection(mixed $direction): string
    {
        return is_string($direction) && strtoupper($direction) === SortOrder::SORT_DESC
            ? SortOrder::SORT_DESC
            : SortOrder::SORT_ASC;
    }
}
