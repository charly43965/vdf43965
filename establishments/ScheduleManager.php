<?php

require_once __DIR__ . '/../core/PocketBase.php';
require_once __DIR__ . '/../core/PocketBaseHelpers.php';

class ScheduleManager {
    use PocketBaseHelpers;

    /** @var string[] List of accepted day values for $dayOfWeek parameters. */
    const VALID_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Read
    //==============
    /** getByEstablishment()
     * Returns all schedule slots for a given establishment, sorted by day then opening time.
     *
     * @param string $establishmentId Establishment's ID.
     * @return array Array of schedule slots.
     * @throws RuntimeException If the request fails.
     */
     public function getByEstablishment(string $establishmentId): array {
        $filter = urlencode("establishment_id='$establishmentId'");
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/schedules/records?filter=($filter)&perPage=200&sort=day_of_the_week,opens_at");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    //==============
    // Create
    //==============
    /** create()
     * Creates a new schedule slot for an establishment.
     * 
     * An establishment can have multiple slots per day (e.g. lunch and dinner services or morning and afternoon).
     *
     * @param string $establishmentId Establishment's ID.
     * @param string $dayOfWeek Day of the week (case-insensitive). Must be one of VALID_DAYS.
     * @param string $opensAt Opening time in HH:MM format (e.g. '09:00').
     * @param string $closesAt Closing time in HH:MM format (e.g. '18:00').
     * @return array Created schedule slot.
     * @throws InvalidArgumentException If $dayOfWeek is not a valid day.
     * @throws RuntimeException If the request fails.
     */
     public function create(string $establishmentId, string $dayOfWeek, string $opensAt, string $closesAt): array {
        if (!in_array(strtolower($dayOfWeek), self::VALID_DAYS, true)) throw new InvalidArgumentException("Invalid day: $dayOfWeek.");

        $response = $this->pocketBase->sendRequest('POST', '/api/collections/schedules/records', [
            'establishment_id' => $establishmentId,
            'day_of_the_week'  => strtolower($dayOfWeek),
            'opens_at'         => $opensAt,
            'closes_at'        => $closesAt,
        ]);

        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Update
    //==============
    /** update()
     * Updates one or more fields of an existing schedule slot.
     * 
     * At least one field must be provided.
     *
     * @param string $id Establishment's ID.
     * @param string|null $dayOfWeek New day of the week (case-insensitive). Must be one of VALID_DAYS if provided.
     * @param string|null $opensAt New opening time in HH:MM format (e.g. '09:00').
     * @param string|null $closesAt New closing time in HH:MM format (e.g. '18:00').
     * @return array Updated schedule slot.
     * @throws InvalidArgumentException If no fields are provided.
     * @throws RuntimeException If the request fails.
     */
     public function update(string $id, ?string $dayOfWeek = null, ?string $opensAt = null, ?string $closesAt = null): array {
        $body = array_filter([
            'day_of_the_week' => $dayOfWeek ? strtolower($dayOfWeek) : null,
            'opens_at' => $opensAt, 'closes_at' => $closesAt
        ], fn($v) => $v !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketBase->sendRequest('PATCH', "/api/collections/schedules/records/$id", $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Delete
    //==============
    /** delete()
     * Permanently deletes a schedule slot.
     *
     * @param string $id Schedule's ID.
     * @return void
     * @throws RuntimeException If the request fails.
     */
     public function delete(string $id): void {
        $this->throwIfError($this->pocketBase->sendRequest('DELETE', "/api/collections/schedules/records/$id"));
    }

    /** deleteAllFromEstablishment()
     * Permanently deletes all schedule slots for a given establishment.
     * 
     * Fetches all slots first via getByEstablishment(), then deletes them one by one.
     * 
     * If any deletion fails, the error is collected and the loop continues.
     * 
     * A RuntimeException is thrown at the end if any deletions failed, with a summary of the failed IDs.
     * 
     * If any deletion has failed due to a server error, it might be good to consider running the command again.
     *
     * @param  string $establishmentId Establishment's ID.
     * @return void
     * @throws RuntimeException If one or more deletions failed, with the list of failed IDs in the message.
     */
     public function deleteAllFromEstablishment(string $establishmentId): void {
        $failed = [];

        foreach ($this->getByEstablishment($establishmentId) as $item) {
            try {
                $this->delete($item['id']);
            } catch (RuntimeException $e) {
                $failed[] = $item['id'];
            }
        }

        if (!empty($failed)) throw new RuntimeException("Failed to delete the following schedule slots: " . implode(', ', $failed));
    }
}