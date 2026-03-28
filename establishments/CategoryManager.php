<?php

require_once __DIR__ . '/../core/PocketBase.php';
require_once __DIR__ . '/../core/PocketBaseHelpers.php';

class CategoryManager {
    use PocketBaseHelpers;

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Read
    //==============
    /** getAll()
     * Returns all categories, sorted alphabetically by name.
     *
     * @param int $perPage Maximum number of records to return (default: 1000).
     * @return array Array of category records.
     * @throws RuntimeException If the request fails.
     */
     public function getAll(int $perPage = 1000): array {
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/categories/records?perPage=$perPage&sort=name");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    /** get()
     * Returns a category, or null if not found.
     *
     * @param string $id Category's ID.
     * @return array|null Category, or null if not found.
     * @throws RuntimeException If the request fails (non-404 error).
     */
     public function get(string $id): ?array {
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/categories/records/$id");
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Create
    //==============
    /** create()
     * Creates a new category.
     *
     * @param string $name Category's name.
     * @param string $slug Category's slug.
     * @return array Created category.
     * @throws RuntimeException If the request fails.
     */
     public function create(string $name, string $slug): array {
        $response = $this->pocketBase->sendRequest('POST', '/api/collections/categories/records', [
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
     * Updates one or more fields of a category by ID.
     * 
     * At least one field must be provided.
     *
     * @param string $id Category's ID.
     * @param string|null $name New name.
     * @param string|null $slug New slug.
     * @return array Updated category.
     * @throws InvalidArgumentException If no fields are provided.
     * @throws RuntimeException If the request fails.
     */
     public function update(string $id, ?string $name = null, ?string $slug = null): array {
        $body = array_filter(['name' => $name, 'slug' => $slug], fn($v) => $v !== null);
        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");

        $response = $this->pocketBase->sendRequest('PATCH', "/api/collections/categories/records/$id", $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    /** updateIcon()
     * Updates the category's icon via multipart upload.
     *
     * @param string $id Category's ID.
     * @param string $filePath Absolute path to the image file to upload.
     * @return array Updated category.
     * @throws InvalidArgumentException If the file does not exist.
     * @throws RuntimeException If the upload fails.
     */
     public function updateIcon(string $id, string $filePath): array {
        return $this->uploadFile('categories', $id, 'icon', $filePath);
    }

    //==============
    // Delete
    //==============
    /** delete()
     * Permanently deletes a category by ID.
     *
     * @param string $id The category's PocketBase record ID.
     * @return void
     * @throws RuntimeException If the request fails.
     */
     public function delete(string $id): void {
        $this->throwIfError($this->pocketBase->sendRequest('DELETE', "/api/collections/categories/records/$id"));
    }

    //==============
    // Relationship operations
    //==============
    /** getForEstablishment()
     * Returns all categories linked to a given establishment.
     * 
     * Queries the categories_establishments pivot collection and expands the category_id relation,
     * 
     * so each returned item includes the full category record under the 'expand' key.
     *
     * @param string $establishmentId Establishment's ID.
     * @return array Array of categories_establishments with expanded category data.
     * @throws RuntimeException If the request fails.
     */
     public function getForEstablishment(string $establishmentId): array {
        $filter = urlencode("(establishment_id='$establishmentId')");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/categories_establishments/records?filter=$filter&expand=category_id");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    /** link()
     * Links a category to an establishment by creating a record in the categories_establishments pivot collection.
     * Does not check for duplicate links — calling this twice with the same arguments will create two records.
     *
     * @param string $categoryId Category's ID.
     * @param string $establishmentId Establishment's ID.
     * @return array Created categories_establishments.
     * @throws RuntimeException If the request fails.
     */
     public function link(string $categoryId, string $establishmentId): array {
        $response = $this->pocketBase->sendRequest('POST', '/api/collections/categories_establishments/records', [
            'category_id'      => $categoryId,
            'establishment_id' => $establishmentId,
        ]);

        $this->throwIfError($response);
        return $response['body'];
    }

    /** unlink()
     * Unlinks a category from an establishment by deleting the corresponding pivot record.
     * 
     * Fetches the pivot record first by filtering on both IDs, then deletes it by its own ID.
     * 
     * Silently does nothing if no matching link exists.
     *
     * @param string $categoryId Category's ID.
     * @param string $establishmentId Establishment's ID.
     * @return void
     * @throws RuntimeException If the lookup or deletion request fails.
     */
     public function unlink(string $categoryId, string $establishmentId): void {
        $filter = urlencode("(category_id='$categoryId'&&establishment_id='$establishmentId')");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/categories_establishments/records?filter=$filter");
        $this->throwIfError($response);
        if ($response['body']['totalItems'] === 0) return;
        $linkId = $response['body']['items'][0]['id'];
        $this->throwIfError($this->pocketBase->sendRequest('DELETE', "/api/collections/categories_establishments/records/$linkId"));
    }
}