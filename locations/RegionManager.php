<?php

require_once __DIR__ . '/../core/PocketBase.php';
require_once __DIR__ . '/../core/PocketBaseHelpers.php';

class RegionManager {
    use PocketBaseHelpers;

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Read
    //==============
    /** getAll()
     * Returns a paginated list of all regions, sorted alphabetically by name.
     *
     * @param int $page Page number (1-based).
     * @param int $perPage Number of records per page.
     * @return array Array of region records.
     * @throws RuntimeException If the request fails.
     */
     public function getAll(int $page = 1, int $perPage = 200): array {
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/regions/records?page=$page&perPage=$perPage&sort=name");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    /** getById()
     * Returns a single region by its ID, or null if not found.
     *
     * @param string $id Region's ID.
     * @return array|null Region, or null if not found.
     * @throws RuntimeException If the request fails (non-404 error).
     */
     public function getById(string $id): ?array {
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/regions/records/$id");
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    /** getByName()
     * Returns a single region by its name, or null if not found.
     *
     * @param string $name Region's name.
     * @return array|null Region, or null if not found.
     * @throws RuntimeException If the request fails.
     */
     public function getByName(string $name): ?array {
        $filter = urlencode("name='{$name}'");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/regions/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    /** getByCode()
     * Returns a single region by its code, or null if not found.
     *
     * @param string $code Region's code (e.g. '84' for Auvergne-Rhône-Alpes).
     * @return array|null Region, or null if not found.
     * @throws RuntimeException If the request fails.
     */
     public function getByCode(string $code): ?array {
        $filter = urlencode("code='{$code}'");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/regions/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    //==============
    // Create
    //==============
    /** create()
     * Creates a new region.
     *
     * @param string $code Region's administrative code.
     * @param string $name Region's name.
     * @param string $slug Region's slug.
     * @return array Created region.
     * @throws RuntimeException If the request fails.
     */
     public function create(string $code, string $name, string $slug): array {
        $response = $this->pocketBase->sendRequest('POST', '/api/collections/regions/records', [
            'code' => $code,
            'name' => $name,
            'slug' => $slug,
        ]);

        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Update
    //==============
    /** update()
     * Updates one or more fields of a region by ID.
     * 
     * At least one field must be provided.
     *
     * @param string $id Region's ID.
     * @param string|null $code New administrative code.
     * @param string|null $name New name.
     * @param string|null $slug New slug.
     * @return array Updated region.
     * @throws InvalidArgumentException If no fields are provided.
     * @throws RuntimeException If the request fails.
     */
     public function update(string $id, ?string $code = null, ?string $name = null, ?string $slug = null): array {
        $body = array_filter([
            'code' => $code,
            'name' => $name,
            'slug' => $slug,
        ], fn($v) => $v !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");

        $response = $this->pocketBase->sendRequest('PATCH', "/api/collections/regions/records/$id", $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    /** updateByName()
     * Updates a region looked up by its current name.
     *
     * @param string $currentName Region's current name (used for lookup).
     * @param string|null $code New code.
     * @param string|null $newName New name.
     * @param string|null $slug New slug.
     * @return array Updated region.
     * @throws RuntimeException If the region is not found or the request fails.
     * @throws InvalidArgumentException If no fields are provided.
     */
     public function updateByName(string $currentName, ?string $code = null, ?string $newName = null, ?string $slug = null): array {
        $region = $this->getByName($currentName);
        if (!$region) throw new RuntimeException("Region '$currentName' not found.");
        return $this->update($region['id'], $code, $newName, $slug);
    }

    /** updateByCode()
     * Updates a region looked up by its current code.
     *
     * @param string $currentCode Region's current code (used for lookup).
     * @param string|null $newCode New code.
     * @param string|null $name New name.
     * @param string|null $slug New slug.
     * @return array Updated region.
     * @throws RuntimeException If the region is not found or the request fails.
     * @throws InvalidArgumentException If no fields are provided.
     */
     public function updateByCode(string $currentCode, ?string $newCode = null, ?string $name = null, ?string $slug = null): array {
        $region = $this->getByCode($currentCode);
        if (!$region) throw new RuntimeException("Region with code '$currentCode' not found.");
        return $this->update($region['id'], $newCode, $name, $slug);
    }

    //==============
    // Delete
    //==============
    /** delete()
     * Permanently deletes a region by ID.
     *
     * @param string $id Region's ID.
     * @return void
     * @throws RuntimeException If the request fails.
     */
     public function delete(string $id): void {
        $this->throwIfError($this->pocketBase->sendRequest('DELETE', "/api/collections/regions/records/$id"));
    }

    /** deleteByName()
     * Permanently deletes a region looked up by its name.
     * 
     * Does nothing if no region with that name exists.
     *
     * @param string $name Region's name.
     * @return void
     * @throws RuntimeException If the deletion request fails.
     */
     public function deleteByName(string $name): void {
        $region = $this->getByName($name);
        if ($region) $this->delete($region['id']);
    }

    /** deleteByCode()
     * Permanently deletes a region looked up by its code.
     * 
     * Does nothing if no region with that code exists.
     *
     * @param string $code Region's code.
     * @return void
     * @throws RuntimeException If the deletion request fails.
     */
     public function deleteByCode(string $code): void {
        $region = $this->getByCode($code);
        if ($region) $this->delete($region['id']);
    }
}
