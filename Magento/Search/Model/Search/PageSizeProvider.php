<?php declare(strict_types=1);

namespace Magento\Search\Model\Search;

class PageSizeProvider
{
    public function getMaxPageSize(): int
    {
        return 1000000000;
    }
}
