# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2025-07-08

### Added
- Full Laravel 12.x compatibility
- Enhanced transaction support with proper event system integration
- Improved connection management with direct client calls
- Comprehensive test suite with 103 assertions across 29 tests

### Changed
- **BREAKING**: Updated minimum Laravel version requirement to 12.x
- **BREAKING**: Updated minimum PHP version requirement to 8.2
- Updated all method signatures for Laravel 12 compatibility
- Refactored connection layer to use laudis/neo4j-php-client v3.3 API
- Enhanced grammar classes with proper constructor signatures
- Improved transaction handling with proper rollback capabilities

### Fixed
- Grammar constructor compatibility with Laravel 12
- Method signature mismatches (getIndexes, spatialIndex, wrapTable, getDefaultValue, build)
- Neo4j client session management for v3.3 API changes
- Event system integration for transaction lifecycle
- Property declaration requirements for PHP 8.2+

### Removed
- Support for Laravel 10.x and 11.x (use v1.x for older Laravel versions)
- Deprecated session-based connection patterns
- Legacy PHP 8.1 support

### Security
- Enhanced credential handling and validation
- Improved query parameter binding
- Better connection timeout and SSL configuration

## [1.0.0] - 2025-07-08

### Added
- Initial release with Laravel 10.x and 11.x support
- Complete Neo4j database driver integration
- Migration system with graph-specific operations
- Query builder with Cypher compilation
- Schema management for nodes, relationships, and constraints
- Transaction support with rollback capabilities
- Artisan commands for database management
- Comprehensive testing suite
- Full documentation and examples

### Features
- Neo4j connection management
- Laravel-style migrations for graph databases
- Fluent query builder interface
- Schema builder for constraints and indexes
- Service provider integration
- Configuration management
- Performance optimization features
- British English localization support