<?php

require_once __DIR__ . '/../core/PocketBase.php';
require_once __DIR__ . '/../core/PocketBaseHelpers.php';

class DepartmentManager {
    use PocketBaseHelpers;

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Read
    //==============
    /** getAll()
     * Returns a list of departments, optionally filtered by region.
     *
     * @param string|null $regionId If provided, only returns departments belonging to this region ID.
     * @return array Array of departments.
     * @throws RuntimeException If the request fails.
     */
     public function getAll(?string $regionId = null): array {
        $endpoint = '/api/collections/departments/records?perPage=200';
        if ($regionId) $endpoint .= '&filter=' . urlencode("(region_id='$regionId')");
        $response = $this->pocketBase->sendRequest('GET', $endpoint);
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    /** getById()
     * Returns a department by its ID, or null if not found.
     *
     * @param string $id Department's ID.
     * @return array|null Department, or null if not found.
     * @throws RuntimeException If the request fails (non-404 error).
     */
     public function getById(string $id): ?array {
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/departments/records/$id");
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    /** getByName()
     * Returns a department by its name, or null if not found.
     *
     * @param string $name Department's name.
     * @return array|null Department, or null if not found.
     * @throws RuntimeException If the request fails.
     */
     public function getByName(string $name): ?array {
        $filter = urlencode("name='{$name}'");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/departments/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    /** getByCode()
     * Returns a department by its code, or null if not found.
     *
     * @param string $code Department's code (e.g. '25' for Doubs).
     * @return array|null Department, or null if not found.
     * @throws RuntimeException If the request fails.
     */
     public function getByCode(string $code): ?array {
        $filter = urlencode("code='$code'");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/departments/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    /** getBySlug()
     * Returns a single department by its slug, or null if not found.
     *
     * @param string $slug Department's slug (e.g. 'doubs').
     * @return array|null Department, or null if not found.
     * @throws RuntimeException If the request fails.
     */
     public function getBySlug(string $slug): ?array {
        $filter = urlencode("slug='$slug'");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/departments/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    //==============
    // Create
    //==============
    /** create()
     * Creates a new department.
     *
     * @param string $regionId Parent region's ID.
     * @param string $code Department's administrative code.
     * @param string $name Department's name.
     * @param string $slug Department's slug.
     * @return array Created department.
     * @throws RuntimeException If the request fails.
     */
     public function create(string $regionId, string $code, string $name, string $slug): array {
        $response = $this->pocketBase->sendRequest('POST', '/api/collections/departments/records', [
            'region_id' => $regionId,
            'code'      => $code,
            'name'      => $name,
            'slug'      => $slug,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Update
    //==============
    /** update()
     * Updates one or more fields of a department by ID.
     * 
     * At least one field must be provided.
     *
     * @param string $id Department's ID.
     * @param string|null $regionId New parent region's ID.
     * @param string|null $code New administrative code.
     * @param string|null $name New name.
     * @param string|null $slug New slug.
     * @param  string|null $prefectureId New prefecture city ID.
     * @return array Updated department.
     * @throws InvalidArgumentException If no fields are provided.
     * @throws RuntimeException If the request fails.
     */
     public function update(string $id, ?string $regionId = null, ?string $code = null, ?string $name = null, ?string $slug = null, ?string $prefectureId = null): array {
        $body = array_filter([
            'region_id'     => $regionId,
            'code'          => $code,
            'name'          => $name,
            'slug'          => $slug,
            'prefecture_id' => $prefectureId,
        ], fn($v) => $v !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");

        $response = $this->pocketBase->sendRequest('PATCH', "/api/collections/departments/records/$id", $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    /** updateByName()
     * Updates a department looked up by its current name.
     *
     * @param string $currentName Department's current name (used for lookup).
     * @param string|null $regionId New parent region ID.
     * @param string|null $code New administrative code.
     * @param string|null $newName New name.
     * @param string|null $slug New slug.
     * @return array Updated department.
     * @throws RuntimeException If the department is not found or the request fails.
     * @throws InvalidArgumentException If no fields are provided.
     */
     public function updateByName(string $currentName, ?string $regionId = null, ?string $code = null, ?string $newName = null, ?string $slug = null): array {
        $department = $this->getByName($currentName);
        if (!$department) throw new RuntimeException("Department '$currentName' not found.");
        return $this->update($department['id'], $regionId, $code, $newName, $slug);
    }

    /** updateByCode()
     * Updates a department looked up by its current code.
     *
     * @param string $   Department's current code (used for lookup).
     * @param string|null $regionId New parent region ID.
     * @param string|null $newCode New administrative code.
     * @param string|null $name New name.
     * @param string|null $slug New slug.
     * @return array Updated department.
     * @throws RuntimeException If the department is not found or the request fails.
     * @throws InvalidArgumentException If no fields are provided.
     */
     public function updateByCode(string $currentCode, ?string $regionId = null, ?string $newCode = null, ?string $name = null, ?string $slug = null): array {
        $department = $this->getByCode($currentCode);
        if (!$department) throw new RuntimeException("Department with code '$currentCode' not found.");
        return $this->update($department['id'], $regionId, $newCode, $name, $slug);
    }

    //==============
    // Delete
    //==============
    /** delete()
     * Permanently deletes a department.
     *
     * @param string $id Department's ID.
     * @return void
     * @throws RuntimeException If the request fails.
     */
     public function delete(string $id): void {
        $this->throwIfError($this->pocketBase->sendRequest('DELETE', "/api/collections/departments/records/$id"));
    }

    /** deleteByName()
     * Permanently deletes a department looked up by its name.
     * 
     * Does nothing if no department with that name exists.
     *
     * @param string $name Department's name.
     * @return void
     * @throws RuntimeException If the deletion request fails.
     */
     public function deleteByName(string $name): void {
        $department = $this->getByName($name);
        if ($department) $this->delete($department['id']);
    }

    /** deleteByCode()
     * Permanently deletes a department looked up by its code.
     * 
     * Does nothing if no department with that code exists.
     *
     * @param  string $code  Department's administrative code.
     * @return void
     * @throws RuntimeException If the deletion request fails.
     */
     public function deleteByCode(string $code): void {
        $department = $this->getByCode($code);
        if ($department) $this->delete($department['id']);
    }
}
