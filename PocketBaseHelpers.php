<?php

trait PocketBaseHelpers {
    private function throwIfError(array $response): void {
        if ($response['status'] >= 400) {
            throw new RuntimeException("Error {$response['status']}: " . json_encode($response['body']));
        }
    }

    private function geo(?float $gpsLatitude, ?float $gpsLongitude): ?array {
        if ($gpsLatitude === null || $gpsLongitude === null) return null;
        return ['lat' => $gpsLatitude, 'lon' => $gpsLongitude];
    }

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