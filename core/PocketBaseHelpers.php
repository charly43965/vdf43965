<?php

/**
 * Provides shared utility methods for PocketBase manager classes.
 * Intended to be used as a trait so these methods are available
 * in any manager without inheritance or code duplication.
 */
trait PocketBaseHelpers {

    /** throwIfError()
     * Throws a RuntimeException if the response status code indicates an error.
     * 
     * Any status code of 400 or above is treated as a failure.
     * 
     * Called after every sendRequest() in manager classes to centralise error handling.
     *
     * @param array $response The response array returned by PocketBase::sendRequest(), containing 'status' (int) and 'body' (array).
     * @return void
     * @throws RuntimeException If the status code is 400 or higher.
     */
     private function throwIfError(array $response): void {
        if ($response['status'] >= 400) {
            throw new RuntimeException("Error {$response['status']}: " . json_encode($response['body']));
        }
    }

    /** geo()
     * Converts a pair of GPS coordinates into the array format expected by PocketBase's geo field.
     * 
     * Returns null if either coordinate is missing, which array_filter() will then strip from the body.
     *
     * @param float|null $gpsLatitude Latitude coordinate.
     * @param float|null $gpsLongitude Longitude coordinate.
     * @return array|null ['lat' => float, 'lon' => float], or null if either value is null.
     */
     private function geo(?float $gpsLatitude, ?float $gpsLongitude): ?array {
        if ($gpsLatitude === null || $gpsLongitude === null) return null;
        return ['lat' => $gpsLatitude, 'lon' => $gpsLongitude];
    }

    /** uploadFile()
     * Uploads a file to a PocketBase record using a multipart PATCH request.
     * 
     * This is necessary because PocketBase stores files in S3, which requires multipart/form-data encoding rather than the standard JSON body used by sendRequest().
     * 
     * The timeout is set to 30 seconds instead of the usual 10 to account for upload time.
     *
     * @param string $collection Collection name (e.g. 'users').
     * @param string $id Record ID.
     * @param string $field Field name (e.g. 'avatar').
     * @param string $filePath Absolute path to the file to upload.
     * @return array Updated record.
     * @throws InvalidArgumentException If the file does not exist at $filePath.
     * @throws RuntimeException If a cURL transport error occurs or the request fails.
     */
     private function uploadFile(string $collection, string $id, string $field, string $filePath): array {
        if (!file_exists($filePath)) throw new InvalidArgumentException("File not found: $filePath");

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->pocketBase->getUrl() . "/api/collections/$collection/records/$id",
            CURLOPT_CAINFO         => __DIR__ . DIRECTORY_SEPARATOR . CERTIFICATE_NAME,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS     => 30_000,
            CURLOPT_CUSTOMREQUEST  => 'PATCH',
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->pocketBase->token],
            CURLOPT_POSTFIELDS     => [$field => new CURLFile($filePath)],
        ]);

        $data   = curl_exec($curl);
        $error  = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($error) throw new RuntimeException("cURL error: $error");

        $response = ['status' => $status, 'body' => json_decode($data, true)];
        $this->throwIfError($response);
        return $response['body'];
    }
}