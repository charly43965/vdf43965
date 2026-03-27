<?php

require_once 'PocketBase.php';

class UserManager {

    public function __construct(private PocketBase $pocketbase) {}

    //==============
    // Helpers
    //==============
    private function throwIfError(array $response): void {
        if ($response['status'] >= 400) {
            throw new RuntimeException("Error {$response['status']}: " . json_encode($response['body']));
        }
    }

    //==============
    // Read
    //==============
    public function getAll(int $page = 1, int $perPage = 30): array {
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/users/records?page=$page&perPage=$perPage");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function getById(string $id): ?array {
        $response = $this->pocketbase->sendRequest('GET', '/api/collections/users/records/' . $id);
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    public function getByEmail(string $email): ?array {
        $filter   = urlencode('(email="' . $email . '")');
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/users/records?filter=$filter");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    //==============
    // Create
    //==============
    public function create(string $email, string $password, string $passwordConfirm, string $firstName, string $lastName, string $role = 'basic', bool $emailVisibility = false, bool $verified = false, ?string $phoneNumber = null): array {
        $body = array_filter([
            'email'             => $email,
            'password'          => $password,
            'passwordConfirm'   => $passwordConfirm,
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'role'              => $role,
            'emailVisibility'   => $emailVisibility,
            'verified'          => $verified,
            'phone_number'      => $phoneNumber,
        ], fn($value) => $value !== null);

        $response = $this->pocketbase->sendRequest('POST', '/api/collections/users/records', $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Update
    //==============
    public function update(string $id, ?string $email = null, ?bool $emailVisibility = null, ?string $firstName = null, ?string $lastName = null, ?string $role = null, ?bool $verified = null, ?string $phoneNumber = null, ?string $oldPassword = null, ?string $password = null, ?string $passwordConfirm = null): array {
        $body = array_filter([
            'email'             => $email,
            'emailVisibility'   => $emailVisibility,
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'role'              => $role,
            'verified'          => $verified,
            'phone_number'      => $phoneNumber,
            'oldPassword'       => $oldPassword,
            'password'          => $password,
            'passwordConfirm'   => $passwordConfirm,
        ], fn($value) => $value !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketbase->sendRequest('PATCH', '/api/collections/users/records/' . $id, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    public function promoteStatus(string $id): array { return $this->update($id, role: 'premium'); }
    public function demoteStatus(string $id): array  { return $this->update($id, role: 'basic'); }
    public function verifyUser(string $id): array  { return $this->update($id, verified: true); }

    // File upload
    public function updateAvatar(string $id, string $filePath): array {
        if (!file_exists($filePath)) throw new InvalidArgumentException("File not found: $filePath");

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL             => $this->pocketbase->getUrl() . '/api/collections/users/records/' . $id,
            CURLOPT_CAINFO          => __DIR__ . DIRECTORY_SEPARATOR . CERTIFICATE_NAME,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT_MS      => 30_000,
            CURLOPT_CUSTOMREQUEST   => 'PATCH',
            CURLOPT_HTTPHEADER      => ['Authorization: Bearer ' . $this->pocketbase->token],
            CURLOPT_POSTFIELDS      => ['avatar' => new CURLFile($filePath)],
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
    // Delete
    //==============
    public function delete(string $id): void {
        $response = $this->pocketbase->sendRequest('DELETE', '/api/collections/users/records/' . $id);
        $this->throwIfError($response);
    }
}
