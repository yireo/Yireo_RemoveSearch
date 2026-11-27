# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 27 November 2026
### Added
- Removes Magento_Search, Magento_CatalogSearch and the Elasticsearch/OpenSearch modules (via composer `replace`), shipping minimal stubs so Magento runs without any search engine
- `SearchInterface` is served by the product repository instead of a search engine

### Changed
- Dropped PHP 8.1 support, added PHP 8.5 support

### Fixed
- 500 errors on every storefront page, GraphQL `products` and `setup:install` when core search modules are still installed next to this module
- Search engine validator no longer fails during configuration checks
- Declared missing module dependencies (Catalog, CatalogSearch, Search, Eav, Store, Webapi)

### Security
- GraphQL `products` and REST `/V1/search` passed arbitrary search criteria to the product repository anonymously, exposing disabled, invisible and other-website products; filters and sort fields are now allow-listed, status/visibility/website filters are enforced, page size is capped at 100 and `/V1/search` requires `Magento_Catalog::products`
