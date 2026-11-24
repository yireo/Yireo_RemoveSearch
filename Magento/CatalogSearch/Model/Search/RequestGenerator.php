<?php declare(strict_types=1);

namespace Magento\CatalogSearch\Model\Search;

class RequestGenerator
{
    public const FILTER_SUFFIX = '_filter';
    public const BUCKET_SUFFIX = '_bucket';

    /**
     * @return array
     */
    public function generate(): array
    {
        return [];
    }

    /**
     * @param mixed $attribute
     * @param string $bucketName
     * @return array
     */
    public function getAggregationData(mixed $attribute = null, string $bucketName = ''): array
    {
        return [];
    }

    /**
     * @param mixed $attribute
     * @param string $filterName
     * @return array
     */
    public function getFilterData(mixed $attribute = null, string $filterName = ''): array
    {
        return [];
    }
}
