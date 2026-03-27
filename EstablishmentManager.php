<?php

require_once 'PocketBase.php';
require_once 'PocketbaseHelpers.php';

class EstablishmentManager {
    use PocketBaseHelpers;

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Read
    //==============
    public function getAll(int $page = 1, int $perPage = 30, ?string $filter = null): array {
        $endpoint = "/api/collections/establishments/records?page=$page&perPage=$perPage";
        if ($filter) $endpoint .= '&filter=' . urlencode($filter);
        $response = $this->pocketBase->sendRequest('GET', $endpoint);
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function getById(string $id): ?array {
        $response = $this->pocketBase->sendRequest('GET', '/api/collections/establishments/records/' . $id);
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    public function getBySlug(string $slug): ?array {
        $filter   = urlencode('(slug="' . $slug . '")');
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/establishments/records?filter=$filter");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    public function getByCity(string $cityId, int $page = 1, int $perPage = 30): array {
        return $this->getAll($page, $perPage, '(city_id="' . $cityId . '")');
    }

    public function getByUser(string $userId): array {
        return $this->getAll(filter: '(user_id="' . $userId . '")');
    }

    public function getPublished(int $page = 1, int $perPage = 30): array {
        return $this->getAll($page, $perPage, '(publication_status="published")');
    }

    public function getPublishedByCity(string $cityId, int $page = 1, int $perPage = 30): array {
        return $this->getAll($page, $perPage, '(city_id="' . $cityId . '"&&publication_status="published")');
    }

    public function getNearby(float $gpsLatitude, float $gpsLongitude, int $radiusKm = 10): array {
        $filter = urlencode("gps_coordinates?@nearby('{$gpsLatitude},{$gpsLongitude}," . ($radiusKm * 1000) . "')");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/establishments/records?filter=$filter&perPage=200");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    //==============
    // Create
    //==============
    public function create(
            string  $cityId, string  $userId, string  $name, string  $slug, string  $description,
            string  $publicationStatus = 'draft', ?string $streetNumber = null, ?string $streetName = null,
            ?string $addressComplement = null, ?string $addressFloor = null, ?float  $gpsLatitude = null,
            ?float  $gpsLongitude = null, ?string $websiteUrl = null, ?string $gmbUrl = null,
            ?int    $employeesCount = null, ?int    $foundationYear = null, bool $hasPhysicalStore = false,
            bool $hasAccessibleCarPark = false, bool $hasAccessibleEntrance = false, bool $doesMobilePayments = false,
            bool $doesCreditCardPayments = false, bool $isVerified = false, bool $isPremium = false
        ): array {
        $body = array_filter([
            'city_id' => $cityId, 'user_id' => $userId, 'name' => $name, 'slug' => $slug, 'description' => $description,
            'publication_status' => $publicationStatus, 'street_number' => $streetNumber, 'street_name' => $streetName,
            'address_complement' => $addressComplement, 'address_floor' => $addressFloor,
            'gps_coordinates' => $this->geo($gpsLatitude, $gpsLongitude), 'website_url' => $websiteUrl, 'gmb_url' => $gmbUrl,
            'employees_count' => $employeesCount, 'foundation_year' => $foundationYear, 'hasPhysicalStore' => $hasPhysicalStore,
            'hasAccessibleCarPark' => $hasAccessibleCarPark, 'hasAccessibleEntrance' => $hasAccessibleEntrance,
            'doesMobilePayments' => $doesMobilePayments, 'doesCreditCardPayments' => $doesCreditCardPayments,
            'isVerified' => $isVerified, 'isPremium' => $isPremium,
        ], fn($value) => $value !== null);

        $response = $this->pocketBase->sendRequest('POST', '/api/collections/establishments/records', $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Update
    //==============
    public function update(
            string $id, ?string $cityId = null, ?string $userId = null, ?string $name = null, ?string $slug = null,
            ?string $description = null, ?string $publicationStatus = null, ?string $streetNumber = null, ?string $streetName = null,
            ?string $addressComplement = null, ?string $addressFloor = null, ?float $gpsLatitude = null, ?float $gpsLongitude = null,
            ?string $websiteUrl = null, ?string $gmbUrl = null, ?int $employeesCount = null, ?int $foundationYear = null,
            ?bool $hasPhysicalStore = null, ?bool $hasAccessibleCarPark = null, ?bool $hasAccessibleEntrance = null,
            ?bool $doesMobilePayments = null, ?bool $doesCreditCardPayments = null, ?bool $isVerified = null, ?bool $isPremium = null
        ): array {
        $body = array_filter([
            'city_id' => $cityId, 'user_id' => $userId, 'name' => $name, 'slug' => $slug, 'description' => $description,
            'publication_status' => $publicationStatus, 'street_number' => $streetNumber, 'street_name' => $streetName,
            'address_complement' => $addressComplement, 'address_floor' => $addressFloor,
            'gps_coordinates' => $this->geo($gpsLatitude, $gpsLongitude), 'website_url' => $websiteUrl, 'gmb_url' => $gmbUrl,
            'employees_count' => $employeesCount, 'foundation_year' => $foundationYear, 'hasPhysicalStore' => $hasPhysicalStore,
            'hasAccessibleCarPark' => $hasAccessibleCarPark, 'hasAccessibleEntrance' => $hasAccessibleEntrance,
            'doesMobilePayments' => $doesMobilePayments, 'doesCreditCardPayments' => $doesCreditCardPayments,
            'isVerified' => $isVerified, 'isPremium' => $isPremium,
        ], fn($value) => $value !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketBase->sendRequest('PATCH', '/api/collections/establishments/records/' . $id, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function setPublish(string $id): array { return $this->update($id, publicationStatus: 'published'); }
    public function setUnpublish(string $id): array { return $this->update($id, publicationStatus: 'hidden'); }
    public function setDraft(string $id): array { return $this->update($id, publicationStatus: 'draft'); }
    public function setVerify(string $id): array { return $this->update($id, isVerified: true); }
    public function setPremium(string $id): array { return $this->update($id, isPremium: true); }
    public function updateLogo(string $id, string $filePath): array { return $this->uploadFile('establishments', $id, 'logo', $filePath); }
    public function updateImage(string $id, string $filePath): array { return $this->uploadFile('establishments', $id, 'image', $filePath); }
    
    //==============
    // Delete
    //==============
    public function delete(string $id): void { $this->throwIfError($this->pocketBase->sendRequest('DELETE', '/api/collections/establishments/records/' . $id)); }
}