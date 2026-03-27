<?php

require_once 'PocketBase.php';
require_once 'PocketBaseHelpers.php';

class UserManager {
    use PocketBaseHelpers;

    public function __construct(private PocketBase $pocketbase) {}

    //==============
    // Read
    //==============
    /** getAll()
     * Returns a paginated list of all users.
     *
     * @param int $page Page number (1-based).
     * @param int $perPage Number of records per page.
     * @return array Array of users.
     * @throws RuntimeException If the request fails.
     */
     public function getAll(int $page = 1, int $perPage = 30): array {
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/users/records?page=$page&perPage=$perPage");
        $this->throwIfError($response);
        return $response['body']['items'];
    }

    /** getById()
     * Returns a single user by their ID, or null if not found.
     *
     * @param string $id User ID.
     * @return array|null The user.
     * @throws RuntimeException If the request fails (non-404 error because User Not Founs is a normal outcome).
     */
     public function getById(string $id): ?array {
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/users/records/$id");
        if ($response['status'] === 404) return null;
        $this->throwIfError($response);
        return $response['body'];
    }

    /** getByEmail()
     * Returns a user by their e-mail address, or null if not found.
     *
     * @param string $email User's e-mail.
     * @return array|null The user, or null if no user is associated with that e-mail.
     * @throws RuntimeException If the request fails.
     */
     public function getByEmail(string $email): ?array {
        $filter = urlencode("email='$email'");
        $response = $this->pocketbase->sendRequest('GET', "/api/collections/users/records?filter=($filter)");
        $this->throwIfError($response);
        return $response['body']['totalItems'] > 0 ? $response['body']['items'][0] : null;
    }

    //==============
    // Create
    //==============
    /** create()
     * Creates a new user.
     *
     * @param string $email User's e-mail.
     * @param string $password User's password.
     * @param string $passwordConfirm Must match $password.
     * @param string $firstName User's first name.
     * @param string $lastName User's last name.
     * @param string $role User's role (default: 'basic').
     * @param bool $emailVisibility Whether the user's e-mail is publicly visible (default: false).
     * @param bool $verified Whether the user is marked as verified (default: false).
     * @param string|null $phoneNumber Optional user's phone number.
     * @return array The created user.
     * @throws RuntimeException If the request fails.
     */
     public function create(
        string  $email,
        string  $password,
        string  $passwordConfirm,
        string  $firstName,
        string  $lastName,
        string  $role            = 'basic',
        bool    $emailVisibility = false,
        bool    $verified        = false,
        ?string $phoneNumber     = null,
     ): array {
        $body = array_filter([
            'email'           => $email,
            'password'        => $password,
            'passwordConfirm' => $passwordConfirm,
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'role'            => $role,
            'emailVisibility' => $emailVisibility,
            'verified'        => $verified,
            'phone_number'    => $phoneNumber,
        ], fn($v) => $v !== null);

        $response = $this->pocketbase->sendRequest('POST', '/api/collections/users/records', $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Update
    //==============
    /** update()
     * Updates a user.
     * At least one optional field must be provided.
     * 
     * For sensitive changes (password, email, phone number), use the dedicated methods.
     *
     * @param string $id User's ID.
     * @param string|null $firstName Newfirst name.
     * @param string|null $lastName Newlast name.
     * @param bool|null $emailVisibility Newe-mail visibility setting.
     * @param string|null $role Newrole (e.g. 'basic', 'premium').
     * @param bool|null $verified Newverification status.
     * @return array Updated user.
     * @throws InvalidArgumentException If no fields are provided.
     * @throws RuntimeException If the request fails.
     */
     public function update(
        string  $id,
        ?string $firstName       = null,
        ?string $lastName        = null,
        ?bool   $emailVisibility = null,
        ?string $role            = null,
        ?bool   $verified        = null,
     ): array {
        $body = array_filter([
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'emailVisibility' => $emailVisibility,
            'role'            => $role,
            'verified'        => $verified,
        ], fn($v) => $v !== null);

        if (empty($body)) throw new InvalidArgumentException("Nothing to update.");

        $response = $this->pocketbase->sendRequest('PATCH', "/api/collections/users/records/$id", $body);
        $this->throwIfError($response);
        return $response['body'];
    }

    /** changePassword()
     * Changes the password of a user.
     *
     * @param string $id User's ID.
     * @param string $oldPassword User's current password.
     * @param string $newPassword Newpassword to set.
     * @param string $passwordConfirm Must match $newPassword.
     * @return array Updated user.
     * @throws InvalidArgumentException If $newPassword and $passwordConfirm do not match.
     * @throws RuntimeException If the request fails.
     */
     public function changePassword(
        string $id,
        string $oldPassword,
        string $newPassword,
        string $passwordConfirm,
     ): array {
        if ($newPassword !== $passwordConfirm) throw new InvalidArgumentException("New password and confirmation do not match.");

        $response = $this->pocketbase->sendRequest('PATCH', "/api/collections/users/records/$id", [
            'oldPassword'     => $oldPassword,
            'password'        => $newPassword,
            'passwordConfirm' => $passwordConfirm,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }

    /** changeEmail()
     * Changes the email address of a user.
     *
     * @param string $id User's ID.
     * @param string $currentEmail User's current e-mail.
     * @param string $newEmail Newe-mail to set.
     * @param string $newEmailConfirm Must match $newEmail.
     * @return array Updated user.
     * @throws InvalidArgumentException If $newEmail and $newEmailConfirm do not match, the user is not found, or $currentEmail does not match.
     * @throws RuntimeException If the request fails.
     */
     public function changeEmail(
        string $id,
        string $currentEmail,
        string $newEmail,
        string $newEmailConfirm,
     ): array {
        if ($newEmail !== $newEmailConfirm) throw new InvalidArgumentException("New email and confirmation do not match.");

        $user = $this->getById($id);
        if ($user === null) throw new InvalidArgumentException("User not found: $id");
        if ($user['email'] !== $currentEmail) throw new InvalidArgumentException("Current email does not match.");

        $response = $this->pocketbase->sendRequest('PATCH', "/api/collections/users/records/$id", ['email' => $newEmail]);
        $this->throwIfError($response);
        return $response['body'];
    }

    /** changePhoneNumber()
     * Changes the phone number of a user.
     *
     * @param string $id User's ID.
     * @param string $currentPhoneNumber Current phone number.
     * @param string $newPhoneNumber New phone number to set.
     * @param string $newPhoneNumberConfirm Must match $newPhoneNumber.
     * @return array Updated user.
     * @throws InvalidArgumentException If $newPhoneNumber and $newPhoneNumberConfirm do not match, the user is not found, or $currentPhoneNumber does not match.
     * @throws RuntimeException If the request fails.
     */
     public function changePhoneNumber(
        string $id,
        string $currentPhoneNumber,
        string $newPhoneNumber,
        string $newPhoneNumberConfirm,
     ): array {
        if ($newPhoneNumber !== $newPhoneNumberConfirm) throw new InvalidArgumentException("New phone number and confirmation do not match.");

        $user = $this->getById($id);
        if ($user === null) throw new InvalidArgumentException("User not found: $id");
        if ($user['phone_number'] !== $currentPhoneNumber) throw new InvalidArgumentException("Current phone number does not match.");

        $response = $this->pocketbase->sendRequest('PATCH', "/api/collections/users/records/$id", ['phone_number' => $newPhoneNumber]);
        $this->throwIfError($response);
        return $response['body'];
    }

    /** updateAvatar()
     * Updates the avatar of a user.
     * 
     * Uses a multipart upload since PocketBase stores files in an S3.
     *
     * @param string $id User's ID.
     * @param string $filePath Absolute path to the image file to upload.
     * @return array Updated user.
     * @throws InvalidArgumentException If the file does not exist.
     * @throws RuntimeException If the upload request fails.
     */
     public function updateAvatar(string $id, string $filePath): array {
        return $this->uploadFile('users', $id, 'avatar', $filePath);
    }

    //==============
    // Roles
    //==============
    /** promote()
     * Promotes a user to premium.
     *
     * @param string $id User's ID.
     * @return array Updated user.
     * @throws RuntimeException If the request fails.
     */
     public function promote(string $id): array {
        return $this->update($id, role: 'premium');
    }

    /** demote()
     * Demotes a user to basic.
     *
     * @param string $id User's ID.
     * @return array Updated user.
     * @throws RuntimeException If the request fails.
     */
     public function demote(string $id): array {
        return $this->update($id, role: 'basic');
    }

    //==============
    // Verification
    //==============
    /** verify()
     * Marks a user as verified.
     *
     * @param string $id User's ID.
     * @return array Updated user.
     * @throws RuntimeException If the request fails.
     */
     public function verify(string $id): array {
        return $this->update($id, verified: true);
    }

    /** unverify()
     * Marks a user as unverified.
     *
     * @param string $id User's ID.
     * @return array Updated user.
     * @throws RuntimeException If the request fails.
     */
     public function unverify(string $id): array {
        return $this->update($id, verified: false);
    }

    //==============
    // Ban / Timeout / Unban
    //==============
    /** timeout()
     * Temporarily bans a user for a given number of days.
     * 
     * Sets 'banned_until' to now + $days (UTC) and ensures 'is_banned' remains false.
     * 
     * To lift the timeout early, call unban().
     *
     * @param string $id User's ID.
     * @param int $days Number of days to ban the user for. Must be at least 1.
     * @return array Updated user.
     * @throws InvalidArgumentException If $days is less than 1.
     * @throws RuntimeException If the request fails.
     */
     public function timeout(string $id, int $days): array {
        if ($days <= 0) throw new InvalidArgumentException("Timeout duration must be at least 1 day.");

        // Datetime object representing the current time set to UTC.
        $bannedUntil = (new DateTimeImmutable("now", new DateTimeZone('UTC')))
        // Creates a new Datetime object with the days added to original value.
        // DateTimeImmutable never mutates; modify() always produces a new object, leaving the original untouched.
        ->modify("+$days days")
        // Formats the result into the string PocketBase expects for its Date fields (e.g. 2026-04-10 14:32:00.000Z).
        // The milliseconds are hardcoded; accuracy is not needed.
        // The Z suffix is added as a string to set the time to UTC on PocketBase.
        ->format('Y-m-d H:i:s.000') . 'Z';

        $response = $this->pocketbase->sendRequest('PATCH', "/api/collections/users/records/$id", [
            'is_banned'    => false,
            'banned_until' => $bannedUntil,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }

    /** ban()
     * Permanently bans a user.
     * 
     * Sets 'is_banned' to true and clears 'banned_until'.
     * 
     * To lift the ban, call unban().
     *
     * @param string $id User's ID.
     * @return array Updated user.
     * @throws RuntimeException If the request fails.
     */
     public function ban(string $id): array {
        $response = $this->pocketbase->sendRequest('PATCH', "/api/collections/users/records/$id", [
            'is_banned'    => true,
            'banned_until' => null,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }

    /** unban()
     * Lifts an active ban or timeout from a user.
     * 
     * Resets 'is_banned' to false and clears 'banned_until'.
     *
     * @param string $id User's ID.
     * @return array Updated user.
     * @throws RuntimeException If the request fails.
     */
     public function unban(string $id): array {
        $response = $this->pocketbase->sendRequest('PATCH', "/api/collections/users/records/$id", [
            'is_banned'    => false,
            'banned_until' => null,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }

    //==============
    // Delete
    //==============
    /** delete()
     * Permanently deletes a user.
     *
     * @param string $id User's ID.
     * @return void
     * @throws RuntimeException If the request fails.
     */
     public function delete(string $id): void {
        $this->throwIfError($this->pocketbase->sendRequest('DELETE', "/api/collections/users/records/$id"));
    }

    /** anonymize()
     * Anonymizes a user's personal data by replacing identifying fields with anonymized values.
     * 
     * Useful for GDPR compliance or account deletion workflows where the record must be retained.
     * 
     * .invalid is a reserved top-level domain (TLD) that guarantees to never resolve to a real domain, making it a safe placeholder.
     * 
     * Source: Request for Comments (RFC) 2606 published by the Internet Engineering Task Force (IETF).
     *
     * @param string $id User's ID.
     * @return array Updated user.
     * @throws RuntimeException If the request fails.
     */
     public function anonymize(string $id): array {
        $response = $this->pocketbase->sendRequest('PATCH', "/api/collections/users/records/$id", [
            'first_name'      => 'Deleted',
            'last_name'       => 'User',
            'email'           => "deleted+$id@deleted.invalid",
            'phone_number'    => null,
            'emailVisibility' => false,
            'verified'        => false,
            'avatar'          => null,
        ]);
        $this->throwIfError($response);
        return $response['body'];
    }
}