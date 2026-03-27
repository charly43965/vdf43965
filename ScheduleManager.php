<?php

require_once 'PocketBase.php';
require_once 'PocketbaseHelpers.php';

class ScheduleManager {
    use PocketBaseHelpers;
    const VALID_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function __construct(private PocketBase $pocketBase) {}

    //==============
    // Read
    //==============
    public function getByEstablishment(string $establishmentId): array {
        $filter   = urlencode('(establishment_id="' . $establishmentId . '")');
        $response = $this->pocketBase->sendRequest('GET', "/api/collections/schedules/records?filter=$filter&perPage=200&sort=day_of_the_week,opens_at");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    //==============
    // Create
    //==============
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
    public function update(string $id, ?string $dayOfWeek = null, ?string $opensAt = null, ?string $closesAt = null): array {
        $body = array_filter(['day_of_the_week' => $dayOfWeek ? strtolower($dayOfWeek) : null, 'opens_at' => $opensAt, 'closes_at' => $closesAt], fn($v) => $v !== null);
        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");
        $response = $this->pocketBase->sendRequest('PATCH', "/api/collections/schedules/records/$id", $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Delete
    //==============
    public function delete(string $id): void { $this->throwIfError($this->pocketBase->sendRequest('DELETE', "/api/collections/schedules/records/$id")); }

    public function deleteAll(string $establishmentId): void {
        foreach ($this->getByEstablishment($establishmentId) as $item) $this->delete($item['id']);
    }
}