<?php

require_once __DIR__ . '/../core/PocketBase.php';
require_once __DIR__ . '/../core/PocketBaseHelpers.php';

class ReviewManager {
    use PocketBaseHelpers;

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Helpers
    //==============
    /** validateRating()
     * Validates that a rating is within the accepted 1–5 range.
     *
     * @param int $rating The rating validate.
     * @return void
     * @throws InvalidArgumentException If $rating is not between 1 and 5.
     */
     private function validateRating(int $rating): void {
        if ($rating < 1 || $rating > 5) throw new InvalidArgumentException("Rating must be 1-5, got $rating.");
    }

    //==============
    // Read
    //==============
    /** getForEstablishment()
     * Returns a paginated list of reviews for a given establishment, sorted by most recent first.
     *
     * @param string $establishmentId Establishment's ID.
     * @param int $page Page number (1-based).
     * @param int $perPage Number of records per page.
     * @return array Array of reviews.
     * @throws RuntimeException If the request fails.
     */
     public function getForEstablishment(string $establishmentId, int $page = 1, int $perPage = 30): array {
        $filter = urlencode('(establishment_id="' . $establishmentId . '")');
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/reviews/records?filter=$filter&page=$page&perPage=$perPage&sort=-created_at");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    /** getForUser()
     * Returns a paginated list of reviews written by a given user, sorted by most recent first.
     *
     * @param string $userId User's ID.
     * @param int $page Page number (1-based).
     * @param int $perPageNumber of records per page.
     * @return array Array of review records.
     * @throws RuntimeException If the request fails.
     */
     public function getForUser(string $userId, int $page = 1, int $perPage = 30): array {
        $filter = urlencode('(user_id="' . $userId . '")');
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/reviews/records?filter=$filter&page=$page&perPage=$perPage&sort=-created_at");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    /** getAverageRating()
     * Computes the average rating for a given establishment, rounded to 2 decimal places.
     * 
     * Fetches up to 500 reviews in a single call.
     * 
     * Returns 0.0 if no reviews exist.
     *
     * @param string $establishmentId Establishment's ID.
     * @return float Average rating, or 0.0 if there are no reviews.
     * @throws RuntimeException If the request fails.
     */
     public function getAverageRating(string $establishmentId): float {
        $reviews = $this->getForEstablishment($establishmentId, perPage: 500);
        if (empty($reviews)) return 0.0;
        return round(array_sum(array_column($reviews, 'rating')) / count($reviews), 2);
    }

    //==============
    // Create
    //==============
    /** create()
     * Creates a new review for an establishment by a user.
     *
     * @param string $userId User's ID.
     * @param string $establishmentId Establishment's ID.
     * @param int $rating Rating value, must be between 1 and 5.
     * @param string|null $comment Optional written comment.
     * @return array Created review.
     * @throws InvalidArgumentException If $rating is not between 1 and 5.
     * @throws RuntimeException If the request fails.
     */
     public function create(string $userId, string $establishmentId, int $rating, ?string $comment = null): array {
        $this->validateRating($rating);

        $body = array_filter([
            'user_id'           => $userId,
            'establishment_id'  => $establishmentId,
            'rating'            => $rating,
            'comment'           => $comment
        ], fn($v) => $v !== null);

        $response = $this->pocketBase->sendRequest('POST', '/api/collections/reviews/records', $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Update
    //==============
    /** update()
     * Updates the rating and/or comment of an existing review by ID.
     * At least one field must be provided.
     *
     * @param string $id Review's ID.
     * @param int|null $rating New rating, must be between 1 and 5 if provided.
     * @param string|null $comment New comment.
     * @return array Updated review.
     * @throws InvalidArgumentException If $rating is not between 1 and 5, or if no fields are provided.
     * @throws RuntimeException If the request fails.
     */
     public function update(string $id, ?int $rating = null, ?string $comment = null): array {
        if ($rating !== null) $this->validateRating($rating);

        $body = array_filter([
            'rating' => $rating,
            'comment' => $comment
        ], fn($v) => $v !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");

        $response = $this->pocketBase->sendRequest('PATCH', '/api/collections/reviews/records/' . $id, $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Delete
    //==============
    /** delete()
     * Permanently deletes a review.
     *
     * @param string $id Review's ID.
     * @return void
     * @throws RuntimeException If the request fails.
     */
     public function delete(string $id): void {
        $this->throwIfError($this->pocketBase->sendRequest('DELETE', '/api/collections/reviews/records/' . $id));
    }
}