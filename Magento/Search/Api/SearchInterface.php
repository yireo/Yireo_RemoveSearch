<?php declare(strict_types=1);

namespace Magento\Search\Api;

use Magento\Framework\Api\Search\SearchCriteriaInterface;

interface SearchInterface
{
    public function search(SearchCriteriaInterface $searchCriteria);
}
