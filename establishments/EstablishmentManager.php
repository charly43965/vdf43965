<?php

require_once __DIR__ . '/../core/PocketBase.php';
require_once __DIR__ . '/../core/PocketBaseHelpers.php';

class EstablishmentManager {
    use PocketBaseHelpers;

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Read
    //==============
    /** getAll()
     * Returns a paginated list of establishments, with an optional raw PocketBase filter string.
     * 
     * Used internally by most other getBy*() methods to avoid code duplication.
     *
     * @param int $page Page number (1-based).
     * @param int $perPage Number of records per page.
     * @param string|null $filter Optional raw PocketBase filter string (e.g. '(city_id="abc123")').
     * @return array Array of establishment records.
     * @throws RuntimeException If the request fails.
     */
     public function getAll(int $page = 1, int $perPage = 30, ?string $filter = null): array {
        $endpoint = "/api/collections/establishments/records?page=$page&perPage=$perPage";
        if ($filter) $endpoint .= '&filter=' . urlencode($filter);
        $response = $this->pocketBase->sendRequest('GET', $endpoint);
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    /** getById()
     * Returns a single establishment by its ID, or null if not found.
     *
     * @param string $id Establishment's ID.
     * @return array|null Establishment, or null if not found.
     * @throws RuntimeException If the request fails (non-404 error).
     */
     public function getById(string $id): ?array {
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/establishments/records/$id");
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    /** getBySlug()
     * Returns a single establishment by its URL slug, or null if not found.
     *
     * @param string $slug Establishment's slug.
     * @return array|null Establishment, or null if not found.
     * @throws RuntimeException If the request fails.
     */
     public function getBySlug(string $slug): ?array {
        $filter   = urlencode("(slug='$slug')");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/establishments/records?filter=$filter");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    /** getByCity()
     * Returns a paginated list of all establishments in a given city.
     *
     * @param string $cityId City's ID.
     * @param int $page Page number (1-based).
     * @param int $perPage Number of records per page.
     * @return array Establishments array.
     * @throws RuntimeException If the request fails.
     */
     public function getByCity(string $cityId, int $page = 1, int $perPage = 30): array {
        return $this->getAll($page, $perPage, "(city_id='$cityId')");
    }

    /** getByUser()
     * Returns all establishments owned by a given user.
     *
     * @param string $userId User's ID.
     * @return array Establishments array.
     * @throws RuntimeException If the request fails.
     */
     public function getByUser(string $userId): array {
        return $this->getAll(filter: "(user_id='$userId')");
    }

    /** getPublished()
     * Returns a paginated list of all published establishments.
     *
     * @param int $page Page number (1-based).
     * @param int $perPage Number of records per page.
     * @return array Establishments array.
     * @throws RuntimeException If the request fails.
     */
     public function getPublished(int $page = 1, int $perPage = 30): array {
        return $this->getAll($page, $perPage, '(publication_status="published")');
    }

    /** getPublishedByCity()
     * Returns a paginated list of published establishments in a given city.
     *
     * @param string $cityId City's ID..
     * @param int $page Page number (1-based).
     * @param int $perPage Number of records per page.
     * @return array Establishments array.
     * @throws RuntimeException If the request fails.
     */
     public function getPublishedByCity(string $cityId, int $page = 1, int $perPage = 30): array {
        return $this->getAll($page, $perPage, "(city_id='$cityId'&&publication_status='published')");
    }

    /** getNearby()
     * Returns establishments within a given radius of a GPS coordinate.
     * 
     * Uses PocketBase's @nearby filter on the gps_coordinates field.
     * 
     * Returns up to 200 results.
     *
     * @param float $gpsLatitude Latitude of the center point.
     * @param float $gpsLongitude Longitude of the center point.
     * @param int $radiusKm Search radius in kilometers (default: 10).
     * @return array Establishments array within the radius.
     * @throws RuntimeException If the request fails.
     */
     public function getNearby(float $gpsLatitude, float $gpsLongitude, int $radiusKm = 10): array {
        $filter = urlencode("geoDistance(gps_coordinates.lon, gps_coordinates.lat, $gpsLongitude, $gpsLatitude) < $radiusKm");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/establishments/records?filter=$filter&perPage=200");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    //==============
    // Create
    //==============
    /** create()
     * Creates a new establishment record.
     *
     * @param string $cityId City's ID.
     * @param string $userId Owner ID.
     * @param string $name Establishment's name.
     * @param string $slug Establishment's slug.
     * @param string $description Establishment's description.
     * @param string $publicationStatus Publication status: 'draft', 'published', or 'hidden' (default: 'draft').
     * @param string|null $streetNumber Optional street number.
     * @param string|null $streetName Optional street name.
     * @param string|null $addressComplement Optional address complement (e.g. building name).
     * @param string|null $addressFloor Optional floor or unit information.
     * @param float|null $gpsLatitude Optional GPS latitude. Required if $gpsLongitude is provided.
     * @param float|null $gpsLongitude Optional GPS longitude. Required if $gpsLatitude is provided.
     * @param string|null $websiteUrl Optional website URL.
     * @param string|null $gmbUrl Optional Google My Business URL.
     * @param int|null $employeesCount Optional number of employees.
     * @param int|null $foundationYear Optional founding year.
     * @param bool $hasPhysicalStore Whether the establishment has a physical storefront (default: false).
     * @param bool $hasAccessibleCarPark Whether an accessible car park is available (default: false).
     * @param bool $hasAccessibleEntrance Whether the entrance is wheelchair accessible (default: false).
     * @param bool $doesMobilePayments Whether mobile payments are accepted (default: false).
     * @param bool $doesCreditCardPayments Whether credit card payments are accepted (default: false).
     * @param bool $isVerified Whether the establishment is verified (default: false).
     * @param bool $isPremium Whether the establishment has a premium listing (default: false).
     * @return array Created establishment.
     * @throws RuntimeException If the request fails.
     */
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
            'city_id'                => $cityId,
            'user_id'                => $userId,
            'name'                   => $name,
            'slug'                   => $slug,
            'description'            => $description,
            'publication_status'     => $publicationStatus,
            'street_number'          => $streetNumber,
            'street_name'            => $streetName,
            'address_complement'     => $addressComplement,
            'address_floor'          => $addressFloor,
            'gps_coordinates'        => $this->geo($gpsLatitude, $gpsLongitude),
            'website_url'            => $websiteUrl,
            'gmb_url'                => $gmbUrl,
            'employees_count'        => $employeesCount,
            'foundation_year'        => $foundationYear,
            'hasPhysicalStore'       => $hasPhysicalStore,
            'hasAccessibleCarPark'   => $hasAccessibleCarPark,
            'hasAccessibleEntrance'  => $hasAccessibleEntrance,
            'doesMobilePayments'     => $doesMobilePayments,
            'doesCreditCardPayments' => $doesCreditCardPayments,
            'isVerified'             => $isVerified,
            'isPremium'              => $isPremium,
        ], fn($v) => $v !== null);

        $response = $this->pocketBase->sendRequest('POST', '/api/collections/establishments/records', $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Update
    //==============
    /** update()
     * Updates one or more fields of an establishment by ID.
     * 
     * At least one field must be provided.
     * 
     * For publication status, verification, premium, and file uploads, prefer the dedicated shorthand methods.
     *
     * @param string $id Establishment's ID.
     * @param string|null $cityId New city ID.
     * @param string|null $userId New owner user ID.
     * @param string|null $name New display name.
     * @param string|null $slug New slug.
     * @param string|null $description New description.
     * @param string|null $publicationStatus New publication status: 'draft', 'published', or 'hidden'.
     * @param string|null $streetNumber New street number.
     * @param string|null $streetName New street name.
     * @param string|null $addressComplement New address complement.
     * @param string|null $addressFloor New floor or unit information.
     * @param float|null $gpsLatitude New GPS latitude.
     * @param float|null $gpsLongitude New GPS longitude.
     * @param string|null $websiteUrl New website URL.
     * @param string|null $gmbUrl New Google My Business URL.
     * @param int|null $employeesCount New number of employees.
     * @param int|null $foundationYear New founding year.
     * @param bool|null $hasPhysicalStore New physical store flag.
     * @param bool|null $hasAccessibleCarPark New accessible car park flag.
     * @param bool|null $hasAccessibleEntrance New accessible entrance flag.
     * @param bool|null $doesMobilePayments New mobile payments flag.
     * @param bool|null $doesCreditCardPayments New credit card payments flag.
     * @param bool|null $isVerified New verified flag.
     * @param bool|null $isPremium New premium flag.
     * @return array Updated establishment.
     * @throws InvalidArgumentException If no fields are provided.
     * @throws RuntimeException If the request fails.
     */
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
            'city_id'                => $cityId,
            'user_id'                => $userId,
            'name'                   => $name,
            'slug'                   => $slug,
            'description'            => $description,
            'publication_status'     => $publicationStatus,
            'street_number'          => $streetNumber,
            'street_name'            => $streetName,
            'address_complement'     => $addressComplement,
            'address_floor'          => $addressFloor,
            'gps_coordinates'        => $this->geo($gpsLatitude, $gpsLongitude),
            'website_url'            => $websiteUrl,
            'gmb_url'                => $gmbUrl,
            'employees_count'        => $employeesCount,
            'foundation_year'        => $foundationYear,
            'hasPhysicalStore'       => $hasPhysicalStore,
            'hasAccessibleCarPark'   => $hasAccessibleCarPark,
            'hasAccessibleEntrance'  => $hasAccessibleEntrance,
            'doesMobilePayments'     => $doesMobilePayments,
            'doesCreditCardPayments' => $doesCreditCardPayments,
            'isVerified'             => $isVerified,
            'isPremium'              => $isPremium,
        ], fn($v) => $v !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");

        $response = $this->pocketBase->sendRequest('PATCH', '/api/collections/establishments/records/' . $id, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    /** setPublish()
     * Sets the establishment's publication status to 'published'.
     * 
     * @param string $id Establishment's ID.
     * @return array Updated establishment.
     * @throws RuntimeException If the request fails.
     */
     public function setPublish(string $id): array   {
        return $this->update($id, publicationStatus: 'published');
    }

    /** setUnpublish()
     * Sets the establishment's publication status to 'hidden'.
     * 
     * @param string $id Establishment's ID.
     * @return array Updated establishment.
     * @throws RuntimeException If the request fails.
     */
     public function setUnpublish(string $id): array {
        return $this->update($id, publicationStatus: 'hidden');
    }

    /** setDraft()
     * Sets the establishment's publication status to 'draft'.
     * 
     * @param string $id Establishment's ID.
     * @return array Updated establishment.
     * @throws RuntimeException If the request fails.
     */
     public function setDraft(string $id): array {
        return $this->update($id, publicationStatus: 'draft');
    }

    /** setVerified()
     * Marks the establishment as verified.
     * 
     * @param string $id Establishment's ID.
     * @return array Updated establishment.
     * @throws RuntimeException If the request fails.
     */
     public function setVerified(string $id): array {
        return $this->update($id, isVerified: true);
    }

    /** setUnverified()
     * Marks the establishment as not verified.
     * 
     * @param string $id Establishment's ID.
     * @return array Updated establishment.
     * @throws RuntimeException If the request fails.
     */
     public function setUnverified(string $id): array {
        return $this->update($id, isVerified: false);
    }
    
    /** setPremium()
     * Marks the establishment as premium.
     * 
     * @param string $id Establishment's ID.
     * @return array Updated establishment.
     * @throws RuntimeException If the request fails.
     */
     public function setPremium(string $id): array {
        return $this->update($id, isPremium: true);
    }
    
    /** setBasic()
     * Marks the establishment as non-premium.
     * 
     * @param string $id Establishment's ID.
     * @return array Updated establishment.
     * @throws RuntimeException If the request fails.
     */
     public function setBasic(string $id): array {
        return $this->update($id, isPremium: false);
    }

    /** updateLogo()
     * Updates the establishment's logo via multipart upload.
     *
     * @param string $id Establishment's ID.
     * @param string $filePath Absolute path to the image file to upload.
     * @return array Updated establishment.
     * @throws InvalidArgumentException If the file does not exist.
     * @throws RuntimeException If the upload fails.
     */
     public function updateLogo(string $id, string $filePath): array  {
        return $this->uploadFile('establishments', $id, 'logo',  $filePath);
    }

    /** updateImage()
     * Updates the establishment's main image via multipart upload.
     *
     * @param string $id Establishment's ID.
     * @param string $filePath Absolute path to the image file to upload.
     * @return array Updated establishment.
     * @throws InvalidArgumentException If the file does not exist.
     * @throws RuntimeException If the upload fails.
     */
     public function updateImage(string $id, string $filePath): array {
        return $this->uploadFile('establishments', $id, 'image', $filePath);
    }

    //==============
    // Delete
    //==============
    /** delete()
     * Permanently deletes an establishment by ID.
     *
     * @param string $id Establishment's ID.
     * @return void
     * @throws RuntimeException If the request fails.
     */
     public function delete(string $id): void {
        $this->throwIfError($this->pocketBase->sendRequest('DELETE', '/api/collections/establishments/records/' . $id));
    }
}