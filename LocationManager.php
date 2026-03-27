<?php

require_once 'PocketBase.php';
require_once 'PocketbaseHelpers.php';

class LocationManager {
    use PocketBaseHelpers;

    public function __construct(private PocketBase $pocketbase) {}

    //==============
    // Regions
    //==============
    public function getRegionList(int $page = 1, int $perPage = 200): array {
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/regions/records?page=$page&perPage=$perPage&sort=name");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function getRegionById(string $id): ?array {
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/regions/records/$id");
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    public function getRegionByName(string $name): ?array {
        $filter = urlencode("name='{$name}'");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/regions/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    public function getRegionByCode(string $code): ?array {
        $filter = urlencode("code='{$code}'");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/regions/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    public function getRegionBySlug(string $slug): ?array {
        $filter = urlencode("slug='{$slug}'");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/regions/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
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

    public function updateRegionById(string $id, ?string $code = null, ?string $name = null, ?string $slug = null): array {
        $body = array_filter([
            'code' => $code,
            'name' => $name,
            'slug' => $slug
        ], fn($value) => $value !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketbase->sendRequest('PATCH', "/api/collections/regions/records/$id", $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function updateRegionByName(string $currentName, ?string $code = null, ?string $newName = null, ?string $slug = null): array {
        $region = $this->getRegionByName($currentName);
        if (!$region) throw new RuntimeException("Region '$currentName' not found.");
        return $this->updateRegionById($region['id'], $code, $newName, $slug);
    }

    public function updateRegionByCode(string $currentCode, ?string $newCode = null, ?string $name = null, ?string $slug = null): array {
        $region = $this->getRegionByCode($currentCode);
        if (!$region) throw new RuntimeException("Region with code '$currentCode' not found.");
        return $this->updateRegionById($region['id'], $newCode, $name, $slug);
    }

    public function deleteRegionById(string $id): void {
        $this->throwIfError($this->pocketbase->sendRequest('DELETE', "/api/collections/regions/records/$id"));
    }

    public function deleteRegionByName(string $name): void {
        $region = $this->getRegionByName($name);
        if ($region) $this->deleteRegionById($region['id']);
    }

    public function deleteRegionByCode(string $code): void {
        $region = $this->getRegionByCode($code);
        if ($region) $this->deleteRegionById($region['id']);
    }

    public function deleteRegionListById(array $ids): array {
        $report = ['success' => 0, 'errors' => []];
        foreach ($ids as $id) {
            try {
                $this->deleteRegionById($id);
                $report['success']++;
            } catch (Exception $e) {
                $report['errors'][] = ["id" => $id, "error" => $e->getMessage()];
            }
        }
        return $report;
    }

    public function deleteRegionListByName(array $names): array {
        $ids = [];
        foreach ($names as $name) {
            $item = $this->getRegionByName($name);
            if ($item) $ids[] = $item['id'];
        }
        return $this->deleteRegionListById($ids);
    }

    public function deleteRegionListByCode(array $codes): array {
        $ids = [];
        foreach ($codes as $code) {
            $item = $this->getRegionByCode($code);
            if ($item) $ids[] = $item['id'];
        }
        return $this->deleteRegionListById($ids);
    }
    
    //==============
    // Departments
    //==============
    public function getDepartmentList(?string $regionId = null): array {
        $endpoint = '/api/collections/departments/records?perPage=200';
        if ($regionId) $endpoint .= '&filter=' . urlencode("(region_id='{$regionId}')");
        $response = $this->pocketbase->sendRequest('GET', $endpoint);
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function getDepartmentById(string $id): ?array {
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/departments/records/$id");
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    public function getDepartmentByName(string $name): ?array {
        $filter = urlencode("name='{$name}'");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/departments/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    public function getDepartmentByCode(string $code): ?array {
        $filter = urlencode("code='{$code}'");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/departments/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    public function getDepartmentBySlug(string $slug): ?array {
        $filter = urlencode("slug='{$slug}'");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/departments/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
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

    public function updateDepartmentById(string $id, ?string $regionId = null, ?string $code = null, ?string $name = null, ?string $slug = null, ?string $prefectureCityId = null): array {
        $body = array_filter([
            'region_id'          => $regionId,
            'code'               => $code,
            'name'               => $name,
            'slug'               => $slug,
            'prefecture_city_id' => $prefectureCityId,
        ], fn($value) => $value !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketbase->sendRequest('PATCH', "/api/collections/departments/records/$id", $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function updateDepartmentByName(string $currentName, ?string $regionId = null, ?string $code = null, ?string $newName = null, ?string $slug = null, ?string $prefectureId = null): array {
        $department = $this->getDepartmentByName($currentName);
        if (!$department) throw new RuntimeException("Department '$currentName' not found.");
        return $this->updateDepartmentById($department['id'], $regionId, $code, $newName, $slug, $prefectureId);
    }

    public function updateDepartmentByCode(string $currentCode, ?string $regionId = null, ?string $newCode = null, ?string $name = null, ?string $slug = null, ?string $prefectureId = null): array {
        $department = $this->getDepartmentByCode($currentCode);
        if (!$department) throw new RuntimeException("Department with code '$currentCode' not found.");
        return $this->updateDepartmentById($department['id'], $regionId, $newCode, $name, $slug, $prefectureId);
    }

    public function deleteDepartmentById(string $id): void {
        $this->throwIfError($this->pocketbase->sendRequest('DELETE', "/api/collections/departments/records/$id"));
    }

    public function deleteDepartmentByName(string $name): void {
        $item = $this->getDepartmentByName($name);
        if ($item) $this->deleteDepartmentById($item['id']);
    }

    public function deleteDepartmentByCode(string $code): void {
        $item = $this->getDepartmentByCode($code);
        if ($item) $this->deleteDepartmentById($item['id']);
    }

    public function deleteDepartmentListById(array $ids): array {
        $report = ['success' => 0, 'errors' => []];
        foreach ($ids as $id) {
            try {
                $this->deleteDepartmentById($id);
                $report['success']++;
            } catch (Exception $e) {
                $report['errors'][] = ["id" => $id, "error" => $e->getMessage()];
            }
        }
        return $report;
    }

    public function deleteDepartmentListByName(array $names): array {
        $ids = [];
        foreach ($names as $name) {
            $item = $this->getDepartmentByName($name);
            if ($item) $ids[] = $item['id'];
        }
        return $this->deleteDepartmentListById($ids);
    }

    public function deleteDepartmentListByCode(array $codes): array {
        $ids = [];
        foreach ($codes as $code) {
            $item = $this->getDepartmentByCode($code);
            if ($item) $ids[] = $item['id'];
        }
        return $this->deleteDepartmentListById($ids);
    }

    //==============
    // Cities
    //==============
    public function getCityList(?string $departmentId = null, int $page = 1, int $perPage = 50): array {
        $endpoint = "/api/collections/cities/records?page=$page&perPage=$perPage";
        if ($departmentId) $endpoint .= '&filter=' . urlencode("(department_id=$departmentId)");
        $response = $this->pocketbase->sendRequest('GET', $endpoint);
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function getCityById(string $id): ?array {
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/cities/records/$id");
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    public function getCityByInsee(string $inseeCode): ?array {
        $filter   = urlencode("insee_code='{$inseeCode}'");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/cities/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    public function getCityBySlug(string $slug): ?array {
        $filter   = urlencode("slug='{$slug}'");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/cities/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    public function getCityByPostal(string $postalCode): array|null {
        $filter   = urlencode("postal_code='{$postalCode}'");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/cities/records?filter=($filter)&perPage=200");
        $this->throwIfError($response);
        $items = $response['body']['items'];
        if (count($items) === 0) return null;
        if (count($items) === 1) return $items[0];
        return $items;
    }

    public function getCityByName(string $name): array|null {
        $filter   = urlencode("name='{$name}'");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/cities/records?filter=($filter)&perPage=200");
        $this->throwIfError($response);
        $items = $response['body']['items'];
        if (count($items) === 0) return null;
        if (count($items) === 1) return $items[0];
        return $items;
    }

    public function getCitiesNear(float $gpsLatitude, float $gpsLongitude, int $radiusKm = 50): array {
        $filter   = urlencode("gps_coordinates?@nearby('{$gpsLatitude},{$gpsLongitude}," . ($radiusKm * 1000) . "')");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/cities/records?filter=($filter)&perPage=200");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function createCity(string $departmentId, string $postalCode, string $inseeCode, string $name, string $slug, ?float $gpsLatitude = null, ?float $gpsLongitude = null): array {
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

    public function updateCity(string $id, ?string $departmentId = null, ?string $postalCode = null, ?string $inseeCode = null, ?string $name = null, ?string $slug = null, ?float $gpsLatitude = null, ?float $gpsLongitude = null): array {
        $body = array_filter([
            'department_id'   => $departmentId,
            'postal_code'     => $postalCode,
            'insee_code'      => $inseeCode,
            'name'            => $name,
            'slug'            => $slug,
            'gps_coordinates' => $this->geo($gpsLatitude, $gpsLongitude),
        ], fn($value) => $value !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketbase->sendRequest('PATCH', "/api/collections/cities/records/$id", $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function updateCityByInsee(string $inseeCode, ?string $departmentId = null, ?string $postalCode = null, ?string $newInseeCode = null, ?string $name = null, ?string $slug = null, ?float $gpsLatitude = null, ?float $gpsLongitude = null): array {
        $city = $this->getCityByInsee($inseeCode);
        if (!$city) throw new RuntimeException("City with INSEE code '$inseeCode' not found.");
        return $this->updateCity($city['id'], $departmentId, $postalCode, $newInseeCode, $name, $slug, $gpsLatitude, $gpsLongitude);
    }

    public function updateCityByPostal(string $postalCode, ?string $departmentId = null, ?string $newPostalCode = null, ?string $inseeCode = null, ?string $name = null, ?string $slug = null, ?float $gpsLatitude = null, ?float $gpsLongitude = null): array {
        $result = $this->getCityByPostal($postalCode);
        if ($result === null) throw new RuntimeException("City with postal code '$postalCode' not found.");
        if (isset($result[0])) throw new RuntimeException("Could not update: multiple cities have this postal code.");
        return $this->updateCity($result['id'], $departmentId, $newPostalCode, $inseeCode, $name, $slug, $gpsLatitude, $gpsLongitude);
    }

    public function updateCityByName(string $name, ?string $departmentId = null, ?string $postalCode = null, ?string $inseeCode = null, ?string $newName = null, ?string $slug = null, ?float $gpsLatitude = null, ?float $gpsLongitude = null): array {
        $result = $this->getCityByName($name);
        if ($result === null) throw new RuntimeException("City '$name' not found.");
        if (isset($result[0])) throw new RuntimeException("Could not update: multiple cities have this name.");
        return $this->updateCity($result['id'], $departmentId, $postalCode, $inseeCode, $newName, $slug, $gpsLatitude, $gpsLongitude);
    }

    

    public function deleteCityById(string $id): void {
        $this->throwIfError($this->pocketbase->sendRequest('DELETE', "/api/collections/cities/records/$id"));
    }

    public function deleteCityByName(string $name): void {
        $city = $this->getCityByInsee($name);
        if ($city) $this->deleteCityById($city['id']);
    }

    public function deleteCityByInsee(string $inseeCode): void {
        $city = $this->getCityByInsee($inseeCode);
        if ($city) $this->deleteCityById($city['id']);
    }

    public function deleteCityListById(array $ids): array {
        $report = ['success' => 0, 'errors' => []];
        foreach ($ids as $id) {
            try {
                $this->deleteCityById($id);
                $report['success']++;
            } catch (Exception $e) {
                $report['errors'][] = ["id" => $id, "error" => $e->getMessage()];
            }
        }
        return $report;
    }

    public function deleteCityListByName(array $names): array {
        $ids = [];
        foreach ($names as $name) {
            $city = $this->getCityByInsee($name);
            if ($city) $ids[] = $city['id'];
        }
        return $this->deleteCityListById($ids);
    }

    public function deleteCityListByInsee(array $inseeCodes): array {
        $ids = [];
        foreach ($inseeCodes as $code) {
            $city = $this->getCityByInsee($code);
            if ($city) $ids[] = $city['id'];
        }
        return $this->deleteCityListById($ids);
    }
}