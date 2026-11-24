<?php declare(strict_types=1);

namespace Magento\Search\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Escaper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const QUERY_VAR_NAME = 'q';
    private const XML_PATH_MAX_QUERY_LENGTH = 'catalog/search/max_query_length';
    private const DEFAULT_MAX_QUERY_LENGTH = 128;

    /**
     * @var array
     */
    private array $messages = [];

    /**
     * @param Context $context
     * @param Escaper $escaper
     */
    public function __construct(
        Context $context,
        private readonly Escaper $escaper
    ) {
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function isMinQueryLength(): bool
    {
        return $this->getQueryText() === '';
    }

    /**
     * @return string
     */
    public function getEscapedQueryText(): string
    {
        return (string)$this->escaper->escapeHtml($this->getQueryText());
    }

    /**
     * @param string|null $query
     * @return string
     */
    public function getResultUrl($query = null): string
    {
        return $this->_getUrl(
            'catalogsearch/result',
            ['_query' => [self::QUERY_VAR_NAME => $query], '_secure' => $this->_request->isSecure()]
        );
    }

    /**
     * @return string
     */
    public function getSuggestUrl(): string
    {
        return $this->_getUrl('search/ajax/suggest', ['_secure' => $this->_request->isSecure()]);
    }

    /**
     * @return string
     */
    public function getSearchTermUrl(): string
    {
        return $this->_getUrl('search/term/popular');
    }

    /**
     * @param mixed $store
     * @return int
     */
    public function getMinQueryLength($store = null): int
    {
        return 0;
    }

    /**
     * @param mixed $store
     * @return int
     */
    public function getMaxQueryLength($store = null): int
    {
        $maxQueryLength = (int)$this->scopeConfig->getValue(
            self::XML_PATH_MAX_QUERY_LENGTH,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $maxQueryLength > 0 ? $maxQueryLength : self::DEFAULT_MAX_QUERY_LENGTH;
    }

    /**
     * @param mixed $message
     * @return $this
     */
    public function addNoteMessage($message)
    {
        $this->messages[] = $message;
        return $this;
    }

    /**
     * @param array $messages
     * @return $this
     */
    public function setNoteMessages(array $messages)
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * @return array
     */
    public function getNoteMessages(): array
    {
        return $this->messages;
    }

    /**
     * @param mixed $store
     * @return $this
     */
    public function checkNotes($store = null)
    {
        return $this;
    }

    /**
     * @return string
     */
    public function getQueryParamName(): string
    {
        return self::QUERY_VAR_NAME;
    }

    /**
     * @return string
     */
    private function getQueryText(): string
    {
        $queryText = $this->_request->getParam(self::QUERY_VAR_NAME);
        if (!is_string($queryText)) {
            return '';
        }

        return mb_substr(trim($queryText, " \f\n\r\t\v\x00"), 0, $this->getMaxQueryLength());
    }
}
