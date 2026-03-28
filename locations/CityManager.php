<?php

require_once __DIR__ . '/../core/PocketBase.php';
require_once __DIR__ . '/../core/PocketBaseHelpers.php';

class CityManager {
    use PocketBaseHelpers;

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Read
    //==============
    /** getAll()
     * Returns a paginated list of cities, optionally filtered by department.
     *
     * @param string|null $departmentId If provided, only returns cities belonging to this department ID.
     * @param int $page Page number (1-based).
     * @param int $perPage Number of records per page.
     * @return array Array of city records.
     * @throws RuntimeException If the request fails.
     */
     public function getAll(?string $departmentId = null, int $page = 1, int $perPage = 50): array {
        $endpoint = "/api/collections/cities/records?page=$page&perPage=$perPage";
        if ($departmentId) $endpoint .= '&filter=' . urlencode("(department_id='$departmentId')");
        $response = $this->pocketBase->sendRequest('GET', $endpoint);
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    /** getById()
     * Returns a city, or null if not found.
     *
     * @param string $id City's ID.
     * @return array|null City, or null if not found.
     * @throws RuntimeException If the request fails (non-404 error).
     */
     public function getById(string $id): ?array {
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/cities/records/$id");
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    /** getByInsee()
     * Returns a single city by its INSEE code, or null if not found.
     *
     * @param string $inseeCode City's INSEE code.
     * @return array|null City, or null if not found.
     * @throws RuntimeException If the request fails.
     */
     public function getByInsee(string $inseeCode): ?array {
        $filter = urlencode("insee_code='$inseeCode'");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/cities/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    /** getBySlug()
     * Returns a single city by its slug, or null if not found.
     *
     * @param string $slug City's slug.
     * @return array|null City, or null if not found.
     * @throws RuntimeException If the request fails.
     */
     public function getBySlug(string $slug): ?array {
        $filter = urlencode("slug='$slug'");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/cities/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    /** getByPostal()
     * Returns one or more cities matching a postal code.
     * 
     * Returns null if none found, a city if exactly one matches, or a city array if multiple cities share the same postal code.
     *
     * @param string $postalCode Postal code.
     * @return array|null A city, an array of cities, or null.
     * @throws RuntimeException If the request fails.
     */
     public function getByPostal(string $postalCode): array|null {
        $filter = urlencode("postal_code='$postalCode'");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/cities/records?filter=($filter)&perPage=200");
        $this->throwIfError($response);
        $items = $response['body']['items'];
        if (count($items) === 0) return null;
        if (count($items) === 1) return $items[0];
        return $items;
    }

    /** getByName()
     * Returns one or more cities matching a name.
     * 
     * Returns null if none found, a city if exactly one matches, or a city array if multiple cities share the same name.
     *
     * @param string $name City's name.
     * @return array|null A city, an array of cities, or null.
     * @throws RuntimeException If the request fails.
     */
     public function getByName(string $name): array|null {
        $escapedName = str_replace("'", "\\'", $name);
        $filter = urlencode("name='$escapedName'");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/cities/records?filter=($filter)&perPage=200");
        $this->throwIfError($response);
        $items = $response['body']['items'];
        if (count($items) === 0) return null;
        if (count($items) === 1) return $items[0];
        return $items;
    }

    /** getNear()
     * Returns all cities within a given radius of a GPS coordinate.
     * 
     * Uses PocketBase's @nearby filter on the gps_coordinates field.
     *
     * @param float $gpsLatitude Center point's latitude.
     * @param float $gpsLongitude Center point's longitude.
     * @param int $radiusKm Search radius in kilometers (default: 50).
     * @return array Array of city records within the radius.
     * @throws RuntimeException If the request fails.
     */
     public function getNear(float $gpsLatitude, float $gpsLongitude, int $radiusKm = 50): array {
        $filter = urlencode("geoDistance(gps_coordinates.lon, gps_coordinates.lat, $gpsLongitude, $gpsLatitude) < $radiusKm");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/cities/records?filter=($filter)&perPage=200");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    //==============
    // Create
    //==============
    /** create()
     * Creates a new city record.
     *
     * @param string $departmentId Parent department's ID.
     * @param string $postalCode City's postal code.
     * @param string $inseeCode City's INSEE code.
     * @param string $name City's name.
     * @param string $slug City's slug.
     * @param float|null $gpsLatitude Optional GPS latitude.
     * @param float|null $gpsLongitude Optional GPS longitude. Required if $gpsLatitude is provided.
     * @return array Created city record.
     * @throws RuntimeException If the request fails.
     */
     public function create(string $departmentId, string $postalCode, string $inseeCode, string $name, string $slug, ?float $gpsLatitude = null, ?float $gpsLongitude = null): array {
        $body = array_filter([
            'department_id'   => $departmentId,
            'postal_code'     => $postalCode,
            'insee_code'      => $inseeCode,
            'name'            => $name,
            'slug'            => $slug,
            'gps_coordinates' => $this->geo($gpsLatitude, $gpsLongitude),
        ], fn($v) => $v !== null);

        $response = $this->pocketBase->sendRequest('POST', '/api/collections/cities/records', $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Update
    //==============
    /** updateById()
     * Updates one or more fields of a city.
     * 
     * At least one field must be provided.
     *
     * @param string $id City's ID..
     * @param string|null $departmentId New parent department's ID.
     * @param string|null $postalCode New postal code.
     * @param string|null $inseeCode New INSEE code.
     * @param string|null $name New name.
     * @param string|null $slug New slug.
     * @param float|null $gpsLatitude New GPS latitude.
     * @param float|null $gpsLongitude New GPS longitude.
     * @return array Updated city.
     * @throws InvalidArgumentException If no fields are provided.
     * @throws RuntimeException If the request fails.
     */
     public function updateById(string $id, ?string $departmentId = null, ?string $postalCode = null, ?string $inseeCode = null, ?string $name = null, ?string $slug = null, ?float $gpsLatitude = null, ?float $gpsLongitude = null): array {
        $body = array_filter([
            'department_id'   => $departmentId,
            'postal_code'     => $postalCode,
            'insee_code'      => $inseeCode,
            'name'            => $name,
            'slug'            => $slug,
            'gps_coordinates' => $this->geo($gpsLatitude, $gpsLongitude),
        ], fn($v) => $v !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");

        $response = $this->pocketBase->sendRequest('PATCH', "/api/collections/cities/records/$id", $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    /** updateByInsee()
     * Updates a city looked up by its INSEE code.
     *
     * @param string $inseeCode City's current INSEE code.
     * @param string|null $departmentId New parent department's ID.
     * @param string|null $postalCode New postal code.
     * @param string|null $newInseeCode New INSEE code.
     * @param string|null $name New name.
     * @param string|null $slug New slug.
     * @param float|null $gpsLatitude New GPS latitude.
     * @param float|null $gpsLongitude New GPS longitude.
     * @return array Updated city.
     * @throws RuntimeException If the city is not found or the request fails.
     * @throws InvalidArgumentException If no fields are provided.
     */
     public function updateByInsee(string $inseeCode, ?string $departmentId = null, ?string $postalCode = null, ?string $newInseeCode = null, ?string $name = null, ?string $slug = null, ?float $gpsLatitude = null, ?float $gpsLongitude = null): array {
        $city = $this->getByInsee($inseeCode);
        if (!$city) throw new RuntimeException("City with INSEE code '$inseeCode' not found.");
        return $this->updateById($city['id'], $departmentId, $postalCode, $newInseeCode, $name, $slug, $gpsLatitude, $gpsLongitude);
    }

    /** updateByPostal()
     * Updates a city looked up by its postal code.
     * 
     * Throws if multiple cities share the same postal code, as the target wouldnot be clearly defined.
     *
     * @param string $postalCode City's current postal code (used for lookup).
     * @param string|null $departmentId New parent department ID.
     * @param string|null $newPostalCode New postal code.
     * @param string|null $inseeCode New INSEE code.
     * @param string|null $name New name.
     * @param string|null $slug New slug.
     * @param float|null $gpsLatitude New GPS latitude.
     * @param float|null $gpsLongitude New GPS longitude.
     * @return array Updated city.
     * @throws RuntimeException If the city is not found, multiple cities match, or the request fails.
     * @throws InvalidArgumentException If no fields are provided.
     */
     public function updateByPostal(string $postalCode, ?string $departmentId = null, ?string $newPostalCode = null, ?string $inseeCode = null, ?string $name = null, ?string $slug = null, ?float $gpsLatitude = null, ?float $gpsLongitude = null): array {
        $result = $this->getByPostal($postalCode);
        if ($result === null) throw new RuntimeException("City with postal code '$postalCode' not found.");
        if (isset($result[0]))  throw new RuntimeException("Could not update: multiple cities have this postal code.");
        return $this->updateById($result['id'], $departmentId, $newPostalCode, $inseeCode, $name, $slug, $gpsLatitude, $gpsLongitude);
    }

    /** updateByName()
     * Updates a city looked up by its name.
     * Throws if multiple cities share the same name, as the target would not be clearly defined.
     *
     * @param string $name City's current name (used for lookup).
     * @param string|null $departmentId New parent department ID.
     * @param string|null $postalCode New postal code.
     * @param string|null $inseeCode New INSEE code.
     * @param string|null $newName New name.
     * @param string|null $slug New slug.
     * @param float|null $gpsLatitude New GPS latitude.
     * @param float|null $gpsLongitude New GPS longitude.
     * @return array Updated city.
     * @throws RuntimeException If the city is not found, multiple cities match, or the request fails.
     * @throws InvalidArgumentException If no fields are provided.
     */
     public function updateByName(string $name, ?string $departmentId = null, ?string $postalCode = null, ?string $inseeCode = null, ?string $newName = null, ?string $slug = null, ?float $gpsLatitude = null, ?float $gpsLongitude = null): array {
        $result = $this->getByName($name);
        if ($result === null) throw new RuntimeException("City '$name' not found.");
        if (isset($result[0]))  throw new RuntimeException("Could not update: multiple cities have this name.");
        return $this->updateById($result['id'], $departmentId, $postalCode, $inseeCode, $newName, $slug, $gpsLatitude, $gpsLongitude);
    }

    //==============
    // Delete
    //==============
    /** delete()
     * Permanently deletes a city.
     *
     * @param string $id City's ID.
     * @return void
     * @throws RuntimeException If the request fails.
     */
     public function delete(string $id): void {
        $this->throwIfError($this->pocketBase->sendRequest('DELETE', "/api/collections/cities/records/$id"));
    }

    /** deleteByInsee()
     * Permanently deletes a city by its INSEE code.
     * 
     * Does nothing if no city with that INSEE code exists.
     *
     * @param string $inseeCode City's INSEE code.
     * @return void
     * @throws RuntimeException If the deletion request fails.
     */
     public function deleteByInsee(string $inseeCode): void {
        $city = $this->getByInsee($inseeCode);
        if ($city) $this->delete($city['id']);
    }
}
