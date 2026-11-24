<?php

declare(strict_types=1);

namespace Yireo\RemoveSearch\Test\Integration\Model;

use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Search\Api\SearchInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Yireo\RemoveSearch\Model\RepositorySearch;

#[AppArea('frontend')]
class RepositorySearchTest extends TestCase
{
    private ?SearchCriteriaInterface $repositoryCriteria = null;

    public function testSearchInterfaceResolvesToRepositorySearch(): void
    {
        $search = Bootstrap::getObjectManager()->get(SearchInterface::class);
        $this->assertInstanceOf(RepositorySearch::class, $search);
    }

    public function testForcesStatusVisibilityAndWebsiteFilters(): void
    {
        $this->search([['sku', 'foo', 'eq']]);

        $websiteId = (string)Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getWebsite()->getId();
        $filters = $this->getRepositoryFilters();
        $this->assertContains(['sku', 'foo', 'eq'], $filters);
        $this->assertContains(['status', 1, 'eq'], $filters);
        $this->assertContains(['visibility', $this->getVisibility()->getVisibleInSearchIds(), 'in'], $filters);
        $this->assertContains(['website_id', $websiteId, 'eq'], $filters);
    }

    public function testCatalogRequestsUseCatalogVisibility(): void
    {
        $this->search([], 'catalog_view_container');

        $this->assertContains(
            ['visibility', $this->getVisibility()->getVisibleInCatalogIds(), 'in'],
            $this->getRepositoryFilters()
        );
    }

    public function testCallerCannotOverrideStatusOrVisibility(): void
    {
        $this->search([
            ['status', '2', 'eq'],
            ['visibility', '1', 'eq'],
            ['website_id', '0', 'eq'],
        ]);

        $fields = array_column($this->getRepositoryFilters(), 0);
        $this->assertSame(['status', 'visibility', 'website_id'], $fields);
        $this->assertNotContains(['status', '2', 'eq'], $this->getRepositoryFilters());
    }

    public function testIgnoresFieldsAndConditionsThatAreNotAllowed(): void
    {
        $this->search([
            ['cost', '999', 'eq'],
            ['sku', 'foo', 'nin'],
            ['entity_id) OR (1=1', '1', 'eq'],
            ['name', ['nested' => ['array']], 'in'],
            ['name', str_repeat('a', 256), 'eq'],
        ]);

        $this->assertSame(['status', 'visibility', 'website_id'], array_column($this->getRepositoryFilters(), 0));
    }

    public function testSearchTermIsMappedToEscapedNameFilter(): void
    {
        $this->search([['search_term', ' Unique_Term 100% ', 'eq']]);

        $this->assertContains(['name', '%Unique\_Term 100\%%', 'like'], $this->getRepositoryFilters());
        $this->assertContains(
            ['visibility', $this->getVisibility()->getVisibleInSearchIds(), 'in'],
            $this->getRepositoryFilters()
        );
    }

    public function testCategoryIdsAreMappedToCategoryId(): void
    {
        $this->search([['category_ids', '3', 'eq']]);

        $this->assertContains(['category_id', '3', 'eq'], $this->getRepositoryFilters());
    }

    public function testPageSizeIsCapped(): void
    {
        $this->search([], 'quick_search_container', 1000000, 2);

        $this->assertSame(5, $this->repositoryCriteria->getPageSize());
        $this->assertSame(2, $this->repositoryCriteria->getCurrentPage());
    }

    public function testMissingPageSizeUsesMaximum(): void
    {
        $this->search([], 'quick_search_container', 0, -3);

        $this->assertSame(5, $this->repositoryCriteria->getPageSize());
        $this->assertSame(1, $this->repositoryCriteria->getCurrentPage());
    }

    public function testOnlyAllowedSortOrdersArePassed(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $unsafeSortOrder = $objectManager->create(SortOrder::class);
        $unsafeSortOrder->setField('cost');
        $unsafeSortOrder->setDirection(SortOrder::SORT_ASC);
        $sortOrder = $objectManager->create(SortOrder::class);
        $sortOrder->setField('name');
        $sortOrder->setDirection('desc');

        $this->search([], 'quick_search_container', 10, 1, [$unsafeSortOrder, $sortOrder]);

        $sortOrders = $this->repositoryCriteria->getSortOrders();
        $this->assertCount(1, $sortOrders);
        $this->assertSame('name', $sortOrders[0]->getField());
        $this->assertSame(SortOrder::SORT_DESC, $sortOrders[0]->getDirection());
    }

    public function testSortOrdersAsFieldDirectionMapAreSupported(): void
    {
        $this->search([], 'quick_search_container', 10, 1, ['price' => 'DESC', 'relevance' => 'DESC']);

        $sortOrders = $this->repositoryCriteria->getSortOrders();
        $this->assertCount(1, $sortOrders);
        $this->assertSame('price', $sortOrders[0]->getField());
        $this->assertSame(SortOrder::SORT_DESC, $sortOrders[0]->getDirection());
    }

    public function testReturnsDocumentsWithIdsOnly(): void
    {
        $result = $this->search([['sku', 'foo', 'eq']]);

        $this->assertSame(42, $result->getTotalCount());
        $items = $result->getItems();
        $this->assertCount(1, $items);
        $item = reset($items);
        $this->assertInstanceOf(DocumentInterface::class, $item);
        $this->assertSame(7, (int)$item->getId());
        $this->assertNull($item->getCustomAttribute('cost'));
    }

    /**
     * @param array<int, array{0: string, 1: mixed, 2: string}> $filters
     * @param array<int|string, SortOrder|string> $sortOrders
     */
    private function search(
        array $filters,
        string $requestName = 'quick_search_container',
        int $pageSize = 10,
        int $currentPage = 1,
        array $sortOrders = []
    ): \Magento\Framework\Api\Search\SearchResultInterface {
        $objectManager = Bootstrap::getObjectManager();
        $filterGroups = [];
        foreach ($filters as [$field, $value, $condition]) {
            $filter = $objectManager->create(Filter::class);
            $filter->setField($field)->setValue($value)->setConditionType($condition);
            $filterGroup = $objectManager->create(FilterGroup::class);
            $filterGroup->setFilters([$filter]);
            $filterGroups[] = $filterGroup;
        }

        $searchCriteria = $objectManager->create(SearchCriteria::class);
        $searchCriteria->setRequestName($requestName);
        $searchCriteria->setFilterGroups($filterGroups);
        $searchCriteria->setPageSize($pageSize);
        $searchCriteria->setCurrentPage($currentPage);
        $searchCriteria->setSortOrders($sortOrders);

        $search = $objectManager->create(RepositorySearch::class, [
            'productRepository' => $this->createProductRepository(),
            'maxPageSize' => 5,
        ]);

        return $search->search($searchCriteria);
    }

    private function createProductRepository(): ProductRepositoryInterface
    {
        $product = Bootstrap::getObjectManager()->create(Product::class);
        $product->setId(7);
        $product->setData('cost', 5);

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$product]);
        $searchResults->method('getTotalCount')->willReturn(42);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('getList')->willReturnCallback(
            function (SearchCriteriaInterface $searchCriteria) use ($searchResults) {
                $this->repositoryCriteria = $searchCriteria;
                return $searchResults;
            }
        );

        return $productRepository;
    }

    /**
     * @return array<int, array{0: string, 1: mixed, 2: string}>
     */
    private function getRepositoryFilters(): array
    {
        $filters = [];
        foreach ($this->repositoryCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $filters[] = [$filter->getField(), $filter->getValue(), $filter->getConditionType() ?? 'eq'];
            }
        }

        return $filters;
    }

    private function getVisibility(): Visibility
    {
        return Bootstrap::getObjectManager()->get(Visibility::class);
    }
}
