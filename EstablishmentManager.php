<?php

require_once 'PocketBase.php';

class EstablishmentManager {

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Helpers
    //==============
    private function throwIfError(array $response): void {
        if ($response['status'] >= 400) throw new RuntimeException("Error {$response['status']}: " . json_encode($response['body']));
    }

    private function geo(?float $gpsLatitude, ?float $gpsLongitude): ?array {
        if ($gpsLatitude === null || $gpsLongitude === null) return null;
        return ['lat' => $gpsLongitude, 'lon' => $gpsLatitude];
    }

    private function uploadFile(string $collection, string $id, string $field, string $filePath): array {
        if (!file_exists($filePath)) throw new InvalidArgumentException("File not found: $filePath");

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL             => $this->pocketBase->getUrl() . "/api/collections/$collection/records/$id",
            CURLOPT_CAINFO          => __DIR__ . DIRECTORY_SEPARATOR . CERTIFICATE_NAME,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT_MS      => 30_000,
            CURLOPT_CUSTOMREQUEST   => 'PATCH',
            CURLOPT_HTTPHEADER      => ['Authorization: Bearer ' . $this->pocketBase->token],
            CURLOPT_POSTFIELDS      => [$field => new CURLFile($filePath)],
        ]);

        $data   = curl_exec($curl);
        $error  = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($error) throw new RuntimeException("cURL error: $error");

        $response = ['status' => $status, 'body' => json_decode($data, true)];
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Establishments
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

    public function create(
            string  $cityId,
            string  $userId,
            string  $name,
            string  $slug,
            string  $description,
            string  $publicationStatus      = 'draft',
            ?string $streetNumber           = null,
            ?string $streetName             = null,
            ?string $addressComplement      = null,
            ?string $addressFloor           = null,
            ?float  $gpsLatitude            = null,
            ?float  $gpsLongitude           = null,
            ?string $websiteUrl             = null,
            ?string $gmbUrl                 = null,
            ?int    $employeesCount         = null,
            ?int    $foundationYear         = null,
            bool    $hasPhysicalStore       = false,
            bool    $hasAccessibleCarPark   = false,
            bool    $hasAccessibleEntrance  = false,
            bool    $doesMobilePayments     = false,
            bool    $doesCreditCardPayments = false,
            bool    $isVerified             = false,
            bool    $isPremium              = false,
        ): array {
        $body = array_filter([
            'city_id'                  => $cityId,
            'user_id'                  => $userId,
            'name'                     => $name,
            'slug'                     => $slug,
            'description'              => $description,
            'publication_status'       => $publicationStatus,
            'street_number'            => $streetNumber,
            'street_name'              => $streetName,
            'address_complement'       => $addressComplement,
            'address_floor'            => $addressFloor,
            'gps_coordinates'          => $this->geo($gpsLatitude, $gpsLongitude),
            'website_url'              => $websiteUrl,
            'gmb_url'                  => $gmbUrl,
            'employees_count'          => $employeesCount,
            'foundation_year'          => $foundationYear,
            'hasPhysicalStore'         => $hasPhysicalStore,
            'hasAccessibleCarPark'     => $hasAccessibleCarPark,
            'hasAccessibleEntrance'    => $hasAccessibleEntrance,
            'doesMobilePayments'       => $doesMobilePayments,
            'doesCreditCardPayments'   => $doesCreditCardPayments,
            'isVerified'               => $isVerified,
            'isPremium'                => $isPremium,
        ], fn($value) => $value !== null);

        $response = $this->pocketBase->sendRequest('POST', '/api/collections/establishments/records', $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function update(
            string  $id,
            ?string $cityId                 = null,
            ?string $userId                 = null,
            ?string $name                   = null,
            ?string $slug                   = null,
            ?string $description            = null,
            ?string $publicationStatus      = null,
            ?string $streetNumber           = null,
            ?string $streetName             = null,
            ?string $addressComplement      = null,
            ?string $addressFloor           = null,
            ?float  $gpsLatitude            = null,
            ?float  $gpsLongitude           = null,
            ?string $websiteUrl             = null,
            ?string $gmbUrl                 = null,
            ?int    $employeesCount         = null,
            ?int    $foundationYear         = null,
            ?bool   $hasPhysicalStore       = null,
            ?bool   $hasAccessibleCarPark   = null,
            ?bool   $hasAccessibleEntrance  = null,
            ?bool   $doesMobilePayments     = null,
            ?bool   $doesCreditCardPayments = null,
            ?bool   $isVerified             = null,
            ?bool   $isPremium              = null,
        ): array {
        $body = array_filter([
            'city_id'                  => $cityId,
            'user_id'                  => $userId,
            'name'                     => $name,
            'slug'                     => $slug,
            'description'              => $description,
            'publication_status'       => $publicationStatus,
            'street_number'            => $streetNumber,
            'street_name'              => $streetName,
            'address_complement'       => $addressComplement,
            'address_floor'            => $addressFloor,
            'gps_coordinates'          => $this->geo($gpsLatitude, $gpsLongitude),
            'website_url'              => $websiteUrl,
            'gmb_url'                  => $gmbUrl,
            'employees_count'          => $employeesCount,
            'foundation_year'          => $foundationYear,
            'hasPhysicalStore'         => $hasPhysicalStore,
            'hasAccessibleCarPark'     => $hasAccessibleCarPark,
            'hasAccessibleEntrance'    => $hasAccessibleEntrance,
            'doesMobilePayments'       => $doesMobilePayments,
            'doesCreditCardPayments'   => $doesCreditCardPayments,
            'isVerified'               => $isVerified,
            'isPremium'                => $isPremium,
        ], fn($value) => $value !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketBase->sendRequest('PATCH', '/api/collections/establishments/records/' . $id, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    // Shortcut status methods
    public function setPublish(string $id): array { return $this->update($id, publicationStatus: 'published'); }
    public function setUnpublish(string $id): array { return $this->update($id, publicationStatus: 'hidden'); }
    public function setDraft(string $id): array { return $this->update($id, publicationStatus: 'draft'); }
    public function setPending(string $id): array { return $this->update($id, publicationStatus: 'pending'); }
    public function setReject(string $id): array { return $this->update($id, publicationStatus: 'rejected'); }
    public function setVerify(string $id): array { return $this->update($id, isVerified: true); }
    public function setPremium(string $id): array { return $this->update($id, isPremium: true); }

    // File uploads
    public function updateLogo(string $id, string $filePath): array {return $this->uploadFile('establishments', $id, 'logo', $filePath);}
    public function updateImage(string $id, string $filePath): array {return $this->uploadFile('establishments', $id, 'image', $filePath);}

    public function delete(string $id): void {
        $response = $this->pocketBase->sendRequest('DELETE', '/api/collections/establishments/records/' . $id);
        $this->throwIfError($response);
    }

    //==============
    // Categories
    //==============
    public function getAllCategories(): array {
        $response = $this->pocketBase->sendRequest('GET', '/api/collections/categories/records?perPage=200');
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function getCategory(string $id): ?array {
        $response = $this->pocketBase->sendRequest('GET', '/api/collections/categories/records/' . $id);
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    public function createCategory(string $name, string $slug): array {
        $response = $this->pocketBase->sendRequest('POST', '/api/collections/categories/records', [
            'name' => $name,
            'slug' => $slug,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function updateCategory(string $id, ?string $name = null, ?string $slug = null): array {
        $body = array_filter(['name' => $name, 'slug' => $slug], fn($value) => $value !== null);
        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketBase->sendRequest('PATCH', '/api/collections/categories/records/' . $id, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    // Icon is a file stored on S3 — upload separately
    public function updateCategoryIcon(string $id, string $filePath): array {
        return $this->uploadFile('categories', $id, 'icon', $filePath);
    }

    public function deleteCategory(string $id): void {
        $response = $this->pocketBase->sendRequest('DELETE', '/api/collections/categories/records/' . $id);
        $this->throwIfError($response);
    }

    // Category <-> Establishment links
    public function getCategoriesForEstablishment(string $establishmentId): array {
        $filter   = urlencode('(establishment_id="' . $establishmentId . '")');
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/categories_establishments/records?filter=$filter&perPage=200&expand=category_id");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function addCategoryToEstablishment(string $categoryId, string $establishmentId): array {
        $response = $this->pocketBase->sendRequest('POST', '/api/collections/categories_establishments/records', [
            'category_id'      => $categoryId,
            'establishment_id' => $establishmentId,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function removeCategoryFromEstablishment(string $categoryId, string $establishmentId): void {
        $filter   = urlencode('(category_id="' . $categoryId . '"&&establishment_id="' . $establishmentId . '")');
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/categories_establishments/records?filter=$filter");
        $this->throwIfError($response);

        if ($response['body']['totalItems'] === 0) return;

        $linkId = $response['body']['items'][0]['id'];
        $del = $this->pocketBase->sendRequest('DELETE', '/api/collections/categories_establishments/records/' . $linkId);
        $this->throwIfError($del);
    }

    //==============
    // Schedules
    //==============
    const VALID_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function getSchedules(string $establishmentId): array {
        $filter   = urlencode('(establishment_id="' . $establishmentId . '")');
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/schedules/records?filter=$filter&perPage=200&sort=day_of_the_week,opens_at");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function addSchedule(string $establishmentId, string $dayOfWeek, string $opensAt, string $closesAt): array {
        if (!in_array($dayOfWeek, self::VALID_DAYS, true)) {
            throw new InvalidArgumentException("Invalid day: $dayOfWeek. Must be one of: " . implode(', ', self::VALID_DAYS));
        }

        $response = $this->pocketBase->sendRequest('POST', '/api/collections/schedules/records', [
            'establishment_id' => $establishmentId,
            'day_of_the_week'  => $dayOfWeek,  // matches DB field name
            'opens_at'         => $opensAt,
            'closes_at'        => $closesAt,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function updateSchedule(string $scheduleId, ?string $dayOfWeek = null, ?string $opensAt = null, ?string $closesAt = null): array {
        $body = array_filter([
            'day_of_the_week' => $dayOfWeek,
            'opens_at'        => $opensAt,
            'closes_at'       => $closesAt,
        ], fn($value) => $value !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketBase->sendRequest('PATCH', '/api/collections/schedules/records/' . $scheduleId, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function deleteSchedule(string $scheduleId): void {
        $response = $this->pocketBase->sendRequest('DELETE', '/api/collections/schedules/records/' . $scheduleId);
        $this->throwIfError($response);
    }

    public function deleteAllSchedules(string $establishmentId): void {
        foreach ($this->getSchedules($establishmentId) as $schedule) $this->deleteSchedule($schedule['id']);
    }
}
