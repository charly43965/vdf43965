<?php

require_once 'PocketBase.php';
require_once 'PocketbaseHelpers.php';

class CategoryManager {
    use PocketBaseHelpers;

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Read
    //==============
    public function getAll(int $perPage = 200): array {
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/categories/records?perPage=$perPage&sort=name");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function get(string $id): ?array {
        $response = $this->pocketBase->sendRequest('GET', '/api/collections/categories/records/' . $id);
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Create
    //==============
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
    public function update(string $id, ?string $name = null, ?string $slug = null): array {
        $body = array_filter(['name' => $name, 'slug' => $slug], fn($value) => $value !== null);
        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");

        $response = $this->pocketBase->sendRequest('PATCH', '/api/collections/categories/records/' . $id, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function updateIcon(string $id, string $filePath): array {
        return $this->uploadFile('categories', $id, 'icon', $filePath);
    }

    //==============
    // Delete
    //==============
    public function delete(string $id): void {
        $this->throwIfError($this->pocketBase->sendRequest('DELETE', '/api/collections/categories/records/' . $id));
    }

    //==============
    // Relationships operations
    //==============
    public function getForEstablishment(string $establishmentId): array {
        $filter   = urlencode('(establishment_id="' . $establishmentId . '")');
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/categories_establishments/records?filter=$filter&expand=category_id");
        $this->throwIfError($response);
        return $response['body']['items'];
    }
    
    public function link(string $categoryId, string $establishmentId): array {
        $response = $this->pocketBase->sendRequest('POST', '/api/collections/categories_establishments/records', [
            'category_id'      => $categoryId,
            'establishment_id' => $establishmentId,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function unlink(string $categoryId, string $establishmentId): void {
        $filter   = urlencode('(category_id="' . $categoryId . '"&&establishment_id="' . $establishmentId . '")');
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/categories_establishments/records?filter=$filter");
        $this->throwIfError($response);

        if ($response['body']['totalItems'] === 0) return;

        $linkId = $response['body']['items'][0]['id'];
        $this->throwIfError($this->pocketBase->sendRequest('DELETE', '/api/collections/categories_establishments/records/' . $linkId));
    }
}