<?php

require_once 'PocketBase.php';

class ReviewManager {

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Helpers
    //==============
    private function throwIfError(array $response): void {
        if ($response['status'] >= 400) throw new RuntimeException("Error {$response['status']}: " . json_encode($response['body']));
    }

    private function validateRating(int $rating): void {
        if ($rating < 1 || $rating > 5) throw new InvalidArgumentException("Rating must be between 1 and 5, got $rating.");
    }

    //==============
    // Read
    //==============
    public function getForEstablishment(string $establishmentId, int $page = 1, int $perPage = 30): array {
        $filter   = urlencode('(establishment_id="' . $establishmentId . '")');
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/reviews/records?filter=$filter&page=$page&perPage=$perPage&sort=-created_at");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function getForUser(string $userId, int $page = 1, int $perPage = 30): array {
        $filter   = urlencode('(user_id="' . $userId . '")');
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/reviews/records?filter=$filter&page=$page&perPage=$perPage&sort=-created_at");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    public function getAverageRating(string $establishmentId): float {
        $reviews = $this->getForEstablishment($establishmentId, perPage: 500);
        if (empty($reviews)) return 0.0;
        return round(array_sum(array_column($reviews, 'rating')) / count($reviews), 2);
    }

    //==============
    // Create
    //==============
    public function create(string $userId, string $establishmentId, int $rating, ?string $comment = null): array {
        $this->validateRating($rating);

        $body = array_filter([
            'user_id'          => $userId,
            'establishment_id' => $establishmentId,
            'rating'           => $rating,
            'comment'          => $comment,
        ], fn($value) => $value !== null);

        $response = $this->pocketBase->sendRequest('POST', '/api/collections/reviews/records', $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Update
    //==============
    public function update(string $id, ?int $rating = null, ?string $comment = null): array {
        if ($rating !== null) $this->validateRating($rating);

        $body = array_filter(['rating' => $rating, 'comment' => $comment], fn($value) => $value !== null);
        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");

        $response = $this->pocketBase->sendRequest('PATCH', '/api/collections/reviews/records/' . $id, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Delete
    //==============
    public function delete(string $id): void {
        $response = $this->pocketBase->sendRequest('DELETE', '/api/collections/reviews/records/' . $id);
        $this->throwIfError($response);
    }
}