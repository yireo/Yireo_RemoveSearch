<?php declare(strict_types=1);

namespace Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;

class SearchResultApplier implements SearchResultApplierInterface
{
    /**
     * @param AbstractDb|null $collection
     * @param SearchResultInterface|null $searchResult
     */
    public function __construct(
        private readonly ?AbstractDb $collection = null,
        private readonly ?SearchResultInterface $searchResult = null
    ) {
    }

    /**
     * @return void
     */
    public function apply()
    {
        if ($this->collection === null) {
            return;
        }

        $ids = [];
        foreach ($this->searchResult?->getItems() ?? [] as $item) {
            $id = (int)$item->getId();
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            $this->collection->getSelect()->where('NULL');
            return;
        }

        $this->collection->getSelect()
            ->where('e.entity_id IN (?)', $ids)
            ->reset(Select::ORDER)
            ->order(new Expression('FIELD(e.entity_id, ' . implode(',', $ids) . ')'));
    }
}
