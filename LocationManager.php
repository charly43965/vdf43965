<?php

require_once 'PocketBase.php';

class LocationManager {

    public function __construct(private PocketBase $pocketbase) {}

    //==============
    // Helpers
    //==============
    private function throwIfError(array $response): void {
        if ($response['status'] >= 400) throw new RuntimeException("Error {$response['status']}: " . json_encode($response['body']));
    }

    private function geo(?float $gpsLatitude, ?float $gpsLongitude): ?array {
        if ($gpsLatitude === null || $gpsLongitude === null) return null;
        return ['lat' => $gpsLatitude, 'lon' => $gpsLongitude];
    }

    //==============
    // Regions
    //==============
    public function getAllRegions(): array {
        $response = $this->pocketbase->sendRequest('GET', '/api/collections/regions/records?perPage=200');
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function getRegion(string $id): ?array {
        $response = $this->pocketbase->sendRequest('GET', '/api/collections/regions/records/' . $id);
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    public function createRegion(string $code, string $name, string $slug): array {
        $response = $this->pocketbase->sendRequest('POST', '/api/collections/regions/records', [
            'code' => $code,
            'name' => $name,
            'slug' => $slug,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function updateRegion(string $id, ?string $code = null, ?string $name = null, ?string $slug = null): array {
        $body = array_filter([
            'code' => $code,
            'name' => $name,
            'slug' => $slug
        ], fn($value) => $value !== null);
        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketbase->sendRequest('PATCH', '/api/collections/regions/records/' . $id, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function deleteRegion(string $id): void {
        $response = $this->pocketbase->sendRequest('DELETE', '/api/collections/regions/records/' . $id);
        $this->throwIfError($response);
    }
    
    //==============
    // Departments
    //==============
    public function getAllDepartments(?string $regionId = null): array {
        $endpoint = '/api/collections/departments/records?perPage=200';
        if ($regionId) $endpoint .= '&filter=' . urlencode('(region_id="' . $regionId . '")');
        $response = $this->pocketbase->sendRequest('GET', $endpoint);
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function getDepartment(string $id): ?array {
        $response = $this->pocketbase->sendRequest('GET', '/api/collections/departments/records/' . $id);
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    public function createDepartment(string $regionId, string $code, string $name, string $slug): array {
        $response = $this->pocketbase->sendRequest('POST', '/api/collections/departments/records', [
            'region_id' => $regionId,
            'code'      => $code,
            'name'      => $name,
            'slug'      => $slug,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function updateDepartment(string  $id, ?string $regionId = null, ?string $code = null, ?string $name = null, ?string $slug = null, ?string $prefectureId = null): array {
        $body = array_filter([
            'region_id'          => $regionId,
            'code'               => $code,
            'name'               => $name,
            'slug'               => $slug,
            'prefecture_city_id' => $prefectureId,
        ], fn($value) => $value !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketbase->sendRequest('PATCH', '/api/collections/departments/records/' . $id, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function deleteDepartment(string $id): void {
        $response = $this->pocketbase->sendRequest('DELETE', '/api/collections/departments/records/' . $id);
        $this->throwIfError($response);
    }
    //==============
    // Cities
    //==============
    public function getAllCities(?string $departmentId = null, int $page = 1, int $perPage = 50): array {
        $endpoint = "/api/collections/cities/records?page=$page&perPage=$perPage";
        if ($departmentId) $endpoint .= '&filter=' . urlencode('(department_id="' . $departmentId . '")');
        $response = $this->pocketbase->sendRequest('GET', $endpoint);
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function getCity(string $id): ?array {
        $response = $this->pocketbase->sendRequest('GET', '/api/collections/cities/records/' . $id);
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    public function getCityByInsee(string $inseeCode): ?array {
        $filter   = urlencode('(insee_code="' . $inseeCode . '")');
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/cities/records?filter=$filter");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    public function getCityBySlug(string $slug): ?array {
        $filter   = urlencode('(slug="' . $slug . '")');
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/cities/records?filter=$filter");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    public function getCitiesNear(float $gpsLatitude, float $gpsLongitude, int $radiusKm = 50): array {
        $filter   = urlencode("gps_coordinates?@nearby('{$gpsLatitude},{$gpsLongitude}," . ($radiusKm * 1000) . "')");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/cities/records?filter=$filter&perPage=200");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function createCity(
            string $departmentId,
            string $postalCode,
            string $inseeCode,
            string $name,
            string $slug,
            ?float $gpsLatitude  = null,
            ?float $gpsLongitude = null,
        ): array {
        $body = array_filter([
            'department_id'   => $departmentId,
            'postal_code'     => $postalCode,
            'insee_code'      => $inseeCode,
            'name'            => $name,
            'slug'            => $slug,
            'gps_coordinates' => $this->geo($gpsLatitude, $gpsLongitude),
        ], fn($value) => $value !== null);

        $response = $this->pocketbase->sendRequest('POST', '/api/collections/cities/records', $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function updateCity(
            string  $id,
            ?string $departmentId   = null,
            ?string $postalCode     = null,
            ?string $inseeCode      = null,
            ?string $name           = null,
            ?string $slug           = null,
            ?float  $gpsLatitude    = null,
            ?float  $gpsLongitude   = null,
        ): array {
        $body = array_filter([
            'department_id'   => $departmentId,
            'postal_code'     => $postalCode,
            'insee_code'      => $inseeCode,
            'name'            => $name,
            'slug'            => $slug,
            'gps_coordinates' => $this->geo($gpsLatitude, $gpsLongitude),
        ], fn($value) => $value !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketbase->sendRequest('PATCH', '/api/collections/cities/records/' . $id, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function deleteCity(string $id): void {
        $response = $this->pocketbase->sendRequest('DELETE', '/api/collections/cities/records/' . $id);
        $this->throwIfError($response);
    }
}
