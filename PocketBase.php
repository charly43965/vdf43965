<?php

const CERTIFICATE_NAME = 'cacert.pem';

class PocketBase {

    private string $url = 'https://ville-de-france-pocketbase-scmmqh-b67095-57-131-30-117.traefik.me';
    private string $email;
    private string $password;
    public string $token = '';

    public function __construct(string $email, string $password) {
        $this->email = $email;
        $this->password = $password;
        $this->authenticate();
    }

    public function getUrl(): string {
        return $this->url;
    }

    public function sendRequest(string $method, string $endpoint, array $body = [], bool $auth = true): array {
        $curl = curl_init();

        $headers = ['Content-Type: application/json'];
        if ($auth) $headers[] = 'Authorization: Bearer ' . $this->token;

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
                $options[CURLOPT_POST]       = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($body);
                break;
            case 'PATCH':
                $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                $options[CURLOPT_POSTFIELDS]    = json_encode($body);
                break;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

        curl_setopt_array($curl, $options);

        $data   = curl_exec($curl);
        $error  = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($error) throw new RuntimeException("cURL error: $error");

        return [
            'status' => $status,
            'body'   => json_decode($data, true),
        ];
    }

    private function authenticate(): void {
        $response = $this->sendRequest(
            method:   'POST',
            endpoint: '/api/collections/_superusers/auth-with-password',
            body:     [
                'identity' => $this->email,
                'password' => $this->password
            ],
            auth:     false,
        );

        if (!isset($response['body']['token'])) throw new RuntimeException("Authentication failed ({$response['status']})\nBody: " . json_encode($response['body']));

        $this->token = $response['body']['token'];
    }
}
