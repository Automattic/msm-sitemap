<?php
/**
 * Base Repository Interface
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Domain\Contracts;

/**
 * Base interface for all repositories.
 *
 * This interface defines common operations that all repositories should implement.
 * Specific repository interfaces should extend this interface to add domain-specific methods.
 */
interface RepositoryInterface {

	/**
	 * Find an entity by its identifier.
	 *
	 * @param int|string $id The entity identifier.
	 * @return object|null The entity if found, null otherwise.
	 */
	public function find( $id ): ?object;

	/**
	 * Find multiple entities by criteria.
	 *
	 * @param array $criteria Search criteria.
	 * @param int $limit Maximum number of results to return.
	 * @param int $offset Number of results to skip.
	 * @return array Array of entities.
	 */
	public function find_by( array $criteria, int $limit = 100, int $offset = 0 ): array;

	/**
	 * Save an entity (create or update).
	 *
	 * @param mixed $entity The entity to save.
	 * @return bool True on success, false on failure.
	 */
	public function save( $entity ): bool;

	/**
	 * Delete an entity by its identifier.
	 *
	 * @param int|string $id The entity identifier.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $id ): bool;

	/**
	 * Count entities matching criteria.
	 *
	 * @param array $criteria Search criteria.
	 * @return int Number of matching entities.
	 */
	public function count( array $criteria = array() ): int;

	/**
	 * Check if an entity exists.
	 *
	 * @param int|string $id The entity identifier.
	 * @return bool True if entity exists, false otherwise.
	 */
	public function exists( $id ): bool;
}
