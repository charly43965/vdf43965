<?php

/** The filename of the SSL certificate used to verify the PocketBase server's identity. */
const CERTIFICATE_NAME = 'cacert.pem';

/** 
 * Handles communication with a PocketBase instance.
 * 
 * Authenticates as a superuser on instantiation and exposes a generic sendRequest() method used by all manager classes to interact with the PocketBase REST API.
 */
class PocketBase {

    private string $url = 'https://ville-de-france-pocketbase-scmmqh-b67095-57-131-30-117.traefik.me';
    private string $email;
    private string $password;

    /** The bearer token obtained after successful authentication. Used in all subsequent requests. */
    public string $token = '';

    /** __construct()
     * New PocketBase instance that authenticates as a superuser.
     *
     * @param string $email Superuser's e-mail.
     * @param string $password Superuser's password.
     * @throws RuntimeException If authentication fails.
     */
     public function __construct(string $email, string $password) {
        $this->email = $email;
        $this->password = $password;
        $this->authenticate();
    }

    /** getUrl()
     * Returnsthe PocketBase instance's URL.
     * 
     * Used by PocketBaseHelpers::uploadFile() to build multipart request URLs.
     *
     * @return string The base URL (without trailing slash).
     */
     public function getUrl(): string {
        return $this->url;
    }

    /** sendRequest()
     * Sends an cURL / HTTP request to the PocketBase API and returns the status code and the decoded body.
     *
     * @param string $method HTTP method: 'GET', 'POST', 'PATCH', or 'DELETE'.
     * @param string $endpoint API endpoint path, e.g. '/api/collections/users/records'.
     * @param array $body Request body, JSON-encoded automatically. Ignored for GET and DELETE.
     * @param bool $auth Whether to include the Authorization bearer token header (default: true).
     * @return array Associative array with 'status' (int) and 'body' (array).
     * @throws RuntimeException If a cURL transport error occurs.
     */
     public function sendRequest(string $method, string $endpoint, array $body = [], bool $auth = true): array {
        $curl = curl_init();

        $headers = ['Content-Type: application/json'];
        if ($auth) $headers[] = "Authorization: Bearer $this->token";

        $options = [
            CURLOPT_URL            => $this->url . $endpoint,
            CURLOPT_CAINFO         => __DIR__ . DIRECTORY_SEPARATOR . CERTIFICATE_NAME,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS     => 10_000,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($body);
                break;
            case 'PATCH':
                $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                $options[CURLOPT_POSTFIELDS] = json_encode($body);
                break;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

        curl_setopt_array($curl, $options);

        $data = curl_exec($curl);
        $error = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($error) throw new RuntimeException("cURL error: $error");

        return [
            'status' => $status,
            'body'   => json_decode($data, true),
        ];
    }

    /** authenticate()
     * Authenticates with the PocketBase superusers collection and stores the token.
     * 
     * Called automatically by the constructor.
     * 
     * Uses auth: false to avoid a chicken-and-egg problem where the token is needed before it has been obtained.
     *
     * @return void
     * @throws RuntimeException If the response does not contain a token.
     */
     private function authenticate(): void {
        $response = $this->sendRequest(
            method:   'POST',
            endpoint: '/api/collections/_superusers/auth-with-password',
            body:     [
                'identity' => $this->email,
                'password' => $this->password,
            ],
            auth: false,
        );

        if (!isset($response['body']['token'])) throw new RuntimeException("Authentication failed ({$response['status']})\nBody: " . json_encode($response['body']));

        $this->token = $response['body']['token'];
    }
}