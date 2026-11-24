<?php

declare(strict_types=1);

namespace Yireo\RemoveSearch\Test\Integration\Stub;

use Magento\Framework\App\RequestInterface;
use Magento\Search\Helper\Data;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea('frontend')]
class HelperDataTest extends TestCase
{
    public function testQueryParamName(): void
    {
        $this->assertSame('q', $this->createHelper('')->getQueryParamName());
    }

    public function testEscapesQueryText(): void
    {
        $helper = $this->createHelper('"><script>alert(1)</script>');

        $escapedQueryText = $helper->getEscapedQueryText();
        $this->assertStringNotContainsString('<script>', $escapedQueryText);
        $this->assertStringNotContainsString('">', $escapedQueryText);
    }

    public function testIgnoresNonStringQueryText(): void
    {
        $this->assertSame('', $this->createHelper(['foo' => 'bar'])->getEscapedQueryText());
    }

    #[Config('catalog/search/max_query_length', '5', 'store', 'default')]
    public function testTruncatesQueryText(): void
    {
        $this->assertSame('abcde', $this->createHelper('abcdefgh')->getEscapedQueryText());
    }

    public function testUrls(): void
    {
        $helper = $this->createHelper('');

        $this->assertStringContainsString('catalogsearch/result', $helper->getResultUrl('foo'));
        $this->assertStringContainsString('search/ajax/suggest', $helper->getSuggestUrl());
        $this->assertStringContainsString('search/term/popular', $helper->getSearchTermUrl());
    }

    private function createHelper(mixed $queryText): Data
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(RequestInterface::class)->setParams(['q' => $queryText]);

        return $objectManager->create(Data::class);
    }
}
