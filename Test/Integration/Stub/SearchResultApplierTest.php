<?php

declare(strict_types=1);

namespace Yireo\RemoveSearch\Test\Integration\Stub;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchResultApplier;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchResultApplierFactory;
use Magento\Framework\Api\Search\Document;
use Magento\Framework\Api\Search\SearchResult;
use Magento\Framework\DB\Select;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class SearchResultApplierTest extends TestCase
{
    public function testFactoryPassesArguments(): void
    {
        $collection = Bootstrap::getObjectManager()->create(Collection::class);
        $searchResult = Bootstrap::getObjectManager()->create(SearchResult::class);

        $applier = Bootstrap::getObjectManager()->get(SearchResultApplierFactory::class)->create([
            'collection' => $collection,
            'searchResult' => $searchResult,
        ]);
        $applier->apply();

        $this->assertInstanceOf(SearchResultApplier::class, $applier);
        $this->assertStringContainsString('NULL', $this->getWhere($collection));
    }

    public function testEmptySearchResultMatchesNothing(): void
    {
        $collection = $this->apply([]);

        $this->assertStringContainsString('(NULL)', $this->getWhere($collection));
    }

    public function testRestrictsCollectionToSearchResultIds(): void
    {
        $collection = $this->apply(['3', '1 OR 1=1']);

        $where = $this->getWhere($collection);
        $this->assertMatchesRegularExpression("/e\.entity_id IN\s*\('?3'?, '?1'?\)/", $where);
        $this->assertStringNotContainsString('OR 1=1', $where);
    }

    /**
     * @param string[] $ids
     */
    private function apply(array $ids): Collection
    {
        $objectManager = Bootstrap::getObjectManager();
        $documents = [];
        foreach ($ids as $id) {
            $document = $objectManager->create(Document::class);
            $document->setId($id);
            $documents[] = $document;
        }

        $searchResult = $objectManager->create(SearchResult::class);
        $searchResult->setItems($documents);

        $collection = $objectManager->create(Collection::class);
        $objectManager->create(SearchResultApplier::class, [
            'collection' => $collection,
            'searchResult' => $searchResult,
        ])->apply();

        return $collection;
    }

    private function getWhere(Collection $collection): string
    {
        return implode(' ', $collection->getSelect()->getPart(Select::WHERE));
    }
}
