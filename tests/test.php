<?php

/**
 * test.php
 *
 * Tests every method across all manager classes against a live PocketBase instance.
 * Creates fake data and deletes it afterward. Stops immediately on the first failure.
 *
 * Run from the /tests directory: php test.php
 */

require_once __DIR__ . '/../core/PocketBase.php';
require_once __DIR__ . '/../core/UserManager.php';
require_once __DIR__ . '/../locations/RegionManager.php';
require_once __DIR__ . '/../locations/DepartmentManager.php';
require_once __DIR__ . '/../locations/CityManager.php';
require_once __DIR__ . '/../establishments/EstablishmentManager.php';
require_once __DIR__ . '/../establishments/ReviewManager.php';
require_once __DIR__ . '/../establishments/ScheduleManager.php';
require_once __DIR__ . '/../establishments/CategoryManager.php';

//==============
// Config
//==============
const PB_EMAIL    = 'charly@solidevs.tech';
const PB_PASSWORD = 'ipsyvml-LKJHGJfhdtyryfgh';

//==============
// Helpers
//==============
function pass(string $method): void {
    echo "[PASS] $method" . PHP_EOL;
}

function fail(string $method, string $reason): void {
    echo "[FAIL] $method — $reason" . PHP_EOL;
    exit(1);
}

function assert_not_null(mixed $value, string $method): void {
    if ($value === null) fail($method, "Expected a result but got null.");
}

function assert_field(array $record, string $field, mixed $expected, string $method): void {
    if (!isset($record[$field])) fail($method, "Field '$field' missing from response.");
    if ($record[$field] !== $expected) fail($method, "Expected '$field' to be " . json_encode($expected) . ", got " . json_encode($record[$field]) . ".");
}

function assert_not_empty(array $items, string $method): void {
    if (empty($items)) fail($method, "Expected a non-empty array but got an empty one.");
}

function log_section(string $title): void {
    echo PHP_EOL . "=== $title ===" . PHP_EOL;
}

//==============
// Bootstrap
//==============
echo "Connecting to PocketBase..." . PHP_EOL;
try {
    $pb = new PocketBase(PB_EMAIL, PB_PASSWORD);
} catch (Exception $e) {
    fail('PocketBase::__construct', $e->getMessage());
}
echo "Connected." . PHP_EOL;

$users         = new UserManager($pb);
$regions       = new RegionManager($pb);
$departments   = new DepartmentManager($pb);
$cities        = new CityManager($pb);
$establishments = new EstablishmentManager($pb);
$reviews       = new ReviewManager($pb);
$schedules     = new ScheduleManager($pb);
$categories    = new CategoryManager($pb);

// IDs collected during tests, cleaned up at the end
$createdUserIds         = [];
$createdEstablishmentIds = [];
$createdReviewIds       = [];
$createdScheduleIds     = [];
$createdCategoryIds     = [];

// Fetch a real city and department to anchor establishment/schedule tests
$testCity       = null;
$testDepartment = null;
$testRegion     = null;
$testUserId     = null;

//==============
// UserManager
//==============
log_section('UserManager');

// create()
try {
    $user = $users->create(
        email:           'test.user.delete.me@test.invalid',
        password:        'TestPassword123!',
        passwordConfirm: 'TestPassword123!',
        firstName:       'Test',
        lastName:        'User',
        role:            'basic',
        verified:        false,
    );
    $createdUserIds[] = $user['id'];
    $testUserId = $user['id'];
    pass('UserManager::create');
} catch (Exception $e) { fail('UserManager::create', $e->getMessage()); }

// getAll()
try {
    $all = $users->getAll();
    assert_not_empty($all, 'UserManager::getAll');
    pass('UserManager::getAll');
} catch (Exception $e) { fail('UserManager::getAll', $e->getMessage()); }

// getById()
try {
    $found = $users->getById($testUserId);
    assert_not_null($found, 'UserManager::getById');
    assert_field($found, 'id', $testUserId, 'UserManager::getById');
    pass('UserManager::getById');
} catch (Exception $e) { fail('UserManager::getById', $e->getMessage()); }

// getByEmail()
try {
    $found = $users->getByEmail('test.user.delete.me@test.invalid');
    assert_not_null($found, 'UserManager::getByEmail');
    assert_field($found, 'id', $testUserId, 'UserManager::getByEmail');
    pass('UserManager::getByEmail');
} catch (Exception $e) { fail('UserManager::getByEmail', $e->getMessage()); }

// update()
try {
    $updated = $users->update($testUserId, firstName: 'UpdatedFirst');
    assert_field($updated, 'first_name', 'UpdatedFirst', 'UserManager::update');
    pass('UserManager::update');
} catch (Exception $e) { fail('UserManager::update', $e->getMessage()); }

// changePassword()
try {
    $users->changePassword($testUserId, 'TestPassword123!', 'NewPassword456!', 'NewPassword456!');
    pass('UserManager::changePassword');
} catch (Exception $e) { fail('UserManager::changePassword', $e->getMessage()); }

// changeEmail()
try {
    $users->changeEmail($testUserId, 'test.user.delete.me@test.invalid', 'test.user.updated@test.invalid', 'test.user.updated@test.invalid');
    pass('UserManager::changeEmail');
} catch (Exception $e) { fail('UserManager::changeEmail', $e->getMessage()); }

// changePhoneNumber()
try {
    $users->changePhoneNumber($testUserId, '', '+33600000001', '+33600000001');
    pass('UserManager::changePhoneNumber');
} catch (Exception $e) { fail('UserManager::changePhoneNumber', $e->getMessage()); }

// promote()
try {
    $promoted = $users->promote($testUserId);
    assert_field($promoted, 'role', 'premium', 'UserManager::promote');
    pass('UserManager::promote');
} catch (Exception $e) { fail('UserManager::promote', $e->getMessage()); }

// demote()
try {
    $demoted = $users->demote($testUserId);
    assert_field($demoted, 'role', 'basic', 'UserManager::demote');
    pass('UserManager::demote');
} catch (Exception $e) { fail('UserManager::demote', $e->getMessage()); }

// verify()
try {
    $verified = $users->verify($testUserId);
    assert_field($verified, 'verified', true, 'UserManager::verify');
    pass('UserManager::verify');
} catch (Exception $e) { fail('UserManager::verify', $e->getMessage()); }

// unverify()
try {
    $unverified = $users->unverify($testUserId);
    assert_field($unverified, 'verified', false, 'UserManager::unverify');
    pass('UserManager::unverify');
} catch (Exception $e) { fail('UserManager::unverify', $e->getMessage()); }

// timeout()
try {
    $timedOut = $users->timeout($testUserId, 1);
    assert_field($timedOut, 'is_banned', false, 'UserManager::timeout');
    pass('UserManager::timeout');
} catch (Exception $e) { fail('UserManager::timeout', $e->getMessage()); }

// ban()
try {
    $banned = $users->ban($testUserId);
    assert_field($banned, 'is_banned', true, 'UserManager::ban');
    pass('UserManager::ban');
} catch (Exception $e) { fail('UserManager::ban', $e->getMessage()); }

// unban()
try {
    $unbanned = $users->unban($testUserId);
    assert_field($unbanned, 'is_banned', false, 'UserManager::unban');
    pass('UserManager::unban');
} catch (Exception $e) { fail('UserManager::unban', $e->getMessage()); }

// anonymize()
try {
    $anon = $users->anonymize($testUserId);
    assert_field($anon, 'first_name', 'Deleted', 'UserManager::anonymize');
    pass('UserManager::anonymize');
} catch (Exception $e) { fail('UserManager::anonymize', $e->getMessage()); }

//==============
// RegionManager
//==============
log_section('RegionManager');

// getAll()
try {
    $allRegions = $regions->getAll();
    assert_not_empty($allRegions, 'RegionManager::getAll');
    $testRegion = $allRegions[0];
    pass('RegionManager::getAll');
} catch (Exception $e) { fail('RegionManager::getAll', $e->getMessage()); }

// getById()
try {
    $found = $regions->getById($testRegion['id']);
    assert_not_null($found, 'RegionManager::getById');
    pass('RegionManager::getById');
} catch (Exception $e) { fail('RegionManager::getById', $e->getMessage()); }

// getByName()
try {
    $found = $regions->getByName($testRegion['name']);
    assert_not_null($found, 'RegionManager::getByName');
    pass('RegionManager::getByName');
} catch (Exception $e) { fail('RegionManager::getByName', $e->getMessage()); }

// getByCode()
try {
    $found = $regions->getByCode($testRegion['code']);
    assert_not_null($found, 'RegionManager::getByCode');
    pass('RegionManager::getByCode');
} catch (Exception $e) { fail('RegionManager::getByCode', $e->getMessage()); }

//==============
// DepartmentManager
//==============
log_section('DepartmentManager');

// getAll()
try {
    $allDepts = $departments->getAll();
    assert_not_empty($allDepts, 'DepartmentManager::getAll');
    $testDepartment = $allDepts[0];
    pass('DepartmentManager::getAll');
} catch (Exception $e) { fail('DepartmentManager::getAll', $e->getMessage()); }

// getAll() filtered by region
try {
    $filtered = $departments->getAll($testRegion['id']);
    assert_not_empty($filtered, 'DepartmentManager::getAll (filtered by region)');
    pass('DepartmentManager::getAll (filtered by region)');
} catch (Exception $e) { fail('DepartmentManager::getAll (filtered by region)', $e->getMessage()); }

// getById()
try {
    $found = $departments->getById($testDepartment['id']);
    assert_not_null($found, 'DepartmentManager::getById');
    pass('DepartmentManager::getById');
} catch (Exception $e) { fail('DepartmentManager::getById', $e->getMessage()); }

// getByName()
try {
    $found = $departments->getByName($testDepartment['name']);
    assert_not_null($found, 'DepartmentManager::getByName');
    pass('DepartmentManager::getByName');
} catch (Exception $e) { fail('DepartmentManager::getByName', $e->getMessage()); }

// getByCode()
try {
    $found = $departments->getByCode($testDepartment['code']);
    assert_not_null($found, 'DepartmentManager::getByCode');
    pass('DepartmentManager::getByCode');
} catch (Exception $e) { fail('DepartmentManager::getByCode', $e->getMessage()); }

// getBySlug()
try {
    $found = $departments->getBySlug($testDepartment['slug']);
    assert_not_null($found, 'DepartmentManager::getBySlug');
    pass('DepartmentManager::getBySlug');
} catch (Exception $e) { fail('DepartmentManager::getBySlug', $e->getMessage()); }

//==============
// CityManager
//==============
log_section('CityManager');

// getAll()
try {
    $allCities = $cities->getAll();
    assert_not_empty($allCities, 'CityManager::getAll');
    $testCity = $allCities[0];
    pass('CityManager::getAll');
} catch (Exception $e) { fail('CityManager::getAll', $e->getMessage()); }

// getAll() filtered by department
try {
    $filtered = $cities->getAll($testDepartment['id']);
    assert_not_empty($filtered, 'CityManager::getAll (filtered by department)');
    pass('CityManager::getAll (filtered by department)');
} catch (Exception $e) { fail('CityManager::getAll (filtered by department)', $e->getMessage()); }

// getById()
try {
    $found = $cities->getById($testCity['id']);
    assert_not_null($found, 'CityManager::getById');
    pass('CityManager::getById');
} catch (Exception $e) { fail('CityManager::getById', $e->getMessage()); }

// getByInsee()
try {
    $found = $cities->getByInsee($testCity['insee_code']);
    assert_not_null($found, 'CityManager::getByInsee');
    pass('CityManager::getByInsee');
} catch (Exception $e) { fail('CityManager::getByInsee', $e->getMessage()); }

// getBySlug()
try {
    $found = $cities->getBySlug($testCity['slug']);
    assert_not_null($found, 'CityManager::getBySlug');
    pass('CityManager::getBySlug');
} catch (Exception $e) { fail('CityManager::getBySlug', $e->getMessage()); }

// getByPostal()
try {
    $found = $cities->getByPostal($testCity['postal_code']);
    assert_not_null($found, 'CityManager::getByPostal');
    pass('CityManager::getByPostal');
} catch (Exception $e) { fail('CityManager::getByPostal', $e->getMessage()); }

// getByName()
try {
    $found = $cities->getByName($testCity['name']);
    assert_not_null($found, 'CityManager::getByName');
    pass('CityManager::getByName');
} catch (Exception $e) { fail('CityManager::getByName', $e->getMessage()); }

// getNear() — using Besançon coordinates as a fixed reference point
try {
    $nearby = $cities->getNear(47.2378, 6.0241, 10);
    assert_not_empty($nearby, 'CityManager::getNear');
    pass('CityManager::getNear');
} catch (Exception $e) { fail('CityManager::getNear', $e->getMessage()); }

//==============
// CategoryManager
//==============
log_section('CategoryManager');

// create()
$testCategory = null;
try {
    $testCategory = $categories->create('Test Category Delete Me', 'test-category-delete-me');
    $createdCategoryIds[] = $testCategory['id'];
    pass('CategoryManager::create');
} catch (Exception $e) { fail('CategoryManager::create', $e->getMessage()); }

// getAll()
try {
    $allCats = $categories->getAll();
    assert_not_empty($allCats, 'CategoryManager::getAll');
    pass('CategoryManager::getAll');
} catch (Exception $e) { fail('CategoryManager::getAll', $e->getMessage()); }

// get()
try {
    $found = $categories->get($testCategory['id']);
    assert_not_null($found, 'CategoryManager::get');
    pass('CategoryManager::get');
} catch (Exception $e) { fail('CategoryManager::get', $e->getMessage()); }

// update()
try {
    $updated = $categories->update($testCategory['id'], name: 'Test Category Updated');
    assert_field($updated, 'name', 'Test Category Updated', 'CategoryManager::update');
    pass('CategoryManager::update');
} catch (Exception $e) { fail('CategoryManager::update', $e->getMessage()); }

//==============
// EstablishmentManager
//==============
log_section('EstablishmentManager');

$testEstablishment = null;
try {
    $testEstablishment = $establishments->create(
        cityId:      $testCity['id'],
        userId:      $testUserId,
        name:        'Test Establishment Delete Me',
        slug:        'test-establishment-delete-me',
        description: 'This is a test establishment created by the test suite. Safe to delete.',
        streetNumber: '1',
        streetName:   'Rue de Test',
        gpsLatitude:  47.2378,
        gpsLongitude: 6.0241,
        foundationYear: 2024,
        hasPhysicalStore: true,
    );
    $createdEstablishmentIds[] = $testEstablishment['id'];
    pass('EstablishmentManager::create');
} catch (Exception $e) { fail('EstablishmentManager::create', $e->getMessage()); }

// getAll()
try {
    $all = $establishments->getAll();
    assert_not_empty($all, 'EstablishmentManager::getAll');
    pass('EstablishmentManager::getAll');
} catch (Exception $e) { fail('EstablishmentManager::getAll', $e->getMessage()); }

// getById()
try {
    $found = $establishments->getById($testEstablishment['id']);
    assert_not_null($found, 'EstablishmentManager::getById');
    pass('EstablishmentManager::getById');
} catch (Exception $e) { fail('EstablishmentManager::getById', $e->getMessage()); }

// getBySlug()
try {
    $found = $establishments->getBySlug('test-establishment-delete-me');
    assert_not_null($found, 'EstablishmentManager::getBySlug');
    pass('EstablishmentManager::getBySlug');
} catch (Exception $e) { fail('EstablishmentManager::getBySlug', $e->getMessage()); }

// getByCity()
try {
    $found = $establishments->getByCity($testCity['id']);
    assert_not_empty($found, 'EstablishmentManager::getByCity');
    pass('EstablishmentManager::getByCity');
} catch (Exception $e) { fail('EstablishmentManager::getByCity', $e->getMessage()); }

// getByUser()
try {
    $found = $establishments->getByUser($testUserId);
    assert_not_empty($found, 'EstablishmentManager::getByUser');
    pass('EstablishmentManager::getByUser');
} catch (Exception $e) { fail('EstablishmentManager::getByUser', $e->getMessage()); }

// getNearby()
try {
    $nearby = $establishments->getNearby(47.2378, 6.0241, 10);
    assert_not_empty($nearby, 'EstablishmentManager::getNearby');
    pass('EstablishmentManager::getNearby');
} catch (Exception $e) { fail('EstablishmentManager::getNearby', $e->getMessage()); }

// update()
try {
    $updated = $establishments->update($testEstablishment['id'], name: 'Test Establishment Updated');
    assert_field($updated, 'name', 'Test Establishment Updated', 'EstablishmentManager::update');
    pass('EstablishmentManager::update');
} catch (Exception $e) { fail('EstablishmentManager::update', $e->getMessage()); }

// setPublish()
try {
    $result = $establishments->setPublish($testEstablishment['id']);
    assert_field($result, 'publication_status', 'published', 'EstablishmentManager::setPublish');
    pass('EstablishmentManager::setPublish');
} catch (Exception $e) { fail('EstablishmentManager::setPublish', $e->getMessage()); }

// getPublished()
try {
    $published = $establishments->getPublished();
    assert_not_empty($published, 'EstablishmentManager::getPublished');
    pass('EstablishmentManager::getPublished');
} catch (Exception $e) { fail('EstablishmentManager::getPublished', $e->getMessage()); }

// getPublishedByCity()
try {
    $published = $establishments->getPublishedByCity($testCity['id']);
    assert_not_empty($published, 'EstablishmentManager::getPublishedByCity');
    pass('EstablishmentManager::getPublishedByCity');
} catch (Exception $e) { fail('EstablishmentManager::getPublishedByCity', $e->getMessage()); }

// setUnpublish()
try {
    $result = $establishments->setUnpublish($testEstablishment['id']);
    assert_field($result, 'publication_status', 'hidden', 'EstablishmentManager::setUnpublish');
    pass('EstablishmentManager::setUnpublish');
} catch (Exception $e) { fail('EstablishmentManager::setUnpublish', $e->getMessage()); }

// setDraft()
try {
    $result = $establishments->setDraft($testEstablishment['id']);
    assert_field($result, 'publication_status', 'draft', 'EstablishmentManager::setDraft');
    pass('EstablishmentManager::setDraft');
} catch (Exception $e) { fail('EstablishmentManager::setDraft', $e->getMessage()); }

// setVerified()
try {
    $result = $establishments->setVerified($testEstablishment['id']);
    assert_field($result, 'isVerified', true, 'EstablishmentManager::setVerified');
    pass('EstablishmentManager::setVerified');
} catch (Exception $e) { fail('EstablishmentManager::setVerified', $e->getMessage()); }

// setUnverified()
try {
    $result = $establishments->setUnverified($testEstablishment['id']);
    assert_field($result, 'isVerified', false, 'EstablishmentManager::setUnverified');
    pass('EstablishmentManager::setUnverified');
} catch (Exception $e) { fail('EstablishmentManager::setUnverified', $e->getMessage()); }

// setPremium()
try {
    $result = $establishments->setPremium($testEstablishment['id']);
    assert_field($result, 'isPremium', true, 'EstablishmentManager::setPremium');
    pass('EstablishmentManager::setPremium');
} catch (Exception $e) { fail('EstablishmentManager::setPremium', $e->getMessage()); }

// setBasic()
try {
    $result = $establishments->setBasic($testEstablishment['id']);
    assert_field($result, 'isPremium', false, 'EstablishmentManager::setBasic');
    pass('EstablishmentManager::setBasic');
} catch (Exception $e) { fail('EstablishmentManager::setBasic', $e->getMessage()); }

// CategoryManager::link()
try {
    $categories->link($testCategory['id'], $testEstablishment['id']);
    pass('CategoryManager::link');
} catch (Exception $e) { fail('CategoryManager::link', $e->getMessage()); }

// CategoryManager::getForEstablishment()
try {
    $linked = $categories->getForEstablishment($testEstablishment['id']);
    assert_not_empty($linked, 'CategoryManager::getForEstablishment');
    pass('CategoryManager::getForEstablishment');
} catch (Exception $e) { fail('CategoryManager::getForEstablishment', $e->getMessage()); }

// CategoryManager::unlink()
try {
    $categories->unlink($testCategory['id'], $testEstablishment['id']);
    pass('CategoryManager::unlink');
} catch (Exception $e) { fail('CategoryManager::unlink', $e->getMessage()); }

//==============
// ReviewManager
//==============
log_section('ReviewManager');

$testReview = null;

// create()
try {
    $testReview = $reviews->create($testUserId, $testEstablishment['id'], 4, 'Test review, safe to delete.');
    $createdReviewIds[] = $testReview['id'];
    pass('ReviewManager::create');
} catch (Exception $e) { fail('ReviewManager::create', $e->getMessage()); }

// getForEstablishment()
try {
    $found = $reviews->getForEstablishment($testEstablishment['id']);
    assert_not_empty($found, 'ReviewManager::getForEstablishment');
    pass('ReviewManager::getForEstablishment');
} catch (Exception $e) { fail('ReviewManager::getForEstablishment', $e->getMessage()); }

// getForUser()
try {
    $found = $reviews->getForUser($testUserId);
    assert_not_empty($found, 'ReviewManager::getForUser');
    pass('ReviewManager::getForUser');
} catch (Exception $e) { fail('ReviewManager::getForUser', $e->getMessage()); }

// getAverageRating()
try {
    $avg = $reviews->getAverageRating($testEstablishment['id']);
    if ($avg <= 0) fail('ReviewManager::getAverageRating', "Expected a positive average, got $avg.");
    pass('ReviewManager::getAverageRating');
} catch (Exception $e) { fail('ReviewManager::getAverageRating', $e->getMessage()); }

// update()
try {
    $updated = $reviews->update($testReview['id'], rating: 5, comment: 'Updated test review.');
    assert_field($updated, 'rating', 5, 'ReviewManager::update');
    pass('ReviewManager::update');
} catch (Exception $e) { fail('ReviewManager::update', $e->getMessage()); }

//==============
// ScheduleManager
//==============
log_section('ScheduleManager');

$testSchedule = null;

// create()
try {
    $testSchedule = $schedules->create($testEstablishment['id'], 'monday', '09:00', '18:00');
    $createdScheduleIds[] = $testSchedule['id'];
    pass('ScheduleManager::create');
} catch (Exception $e) { fail('ScheduleManager::create', $e->getMessage()); }

// getByEstablishment()
try {
    $found = $schedules->getByEstablishment($testEstablishment['id']);
    assert_not_empty($found, 'ScheduleManager::getByEstablishment');
    pass('ScheduleManager::getByEstablishment');
} catch (Exception $e) { fail('ScheduleManager::getByEstablishment', $e->getMessage()); }

// update()
try {
    $updated = $schedules->update($testSchedule['id'], opensAt: '10:00');
    assert_field($updated, 'opens_at', '10:00', 'ScheduleManager::update');
    pass('ScheduleManager::update');
} catch (Exception $e) { fail('ScheduleManager::update', $e->getMessage()); }

//==============
// Cleanup
//==============
log_section('Cleanup');

// ScheduleManager::delete()
foreach ($createdScheduleIds as $id) {
    try {
        $schedules->delete($id);
        pass('ScheduleManager::delete');
    } catch (Exception $e) { fail('ScheduleManager::delete', $e->getMessage()); }
}

// ScheduleManager::deleteAllFromEstablishment() — create two fresh slots first, then delete them both
try {
    $schedules->create($testEstablishment['id'], 'tuesday',  '09:00', '12:00');
    $schedules->create($testEstablishment['id'], 'wednesday', '14:00', '18:00');
    $schedules->deleteAllFromEstablishment($testEstablishment['id']);
    $remaining = $schedules->getByEstablishment($testEstablishment['id']);
    if (!empty($remaining)) fail('ScheduleManager::deleteAllFromEstablishment', "Expected 0 slots remaining, got " . count($remaining) . ".");
    pass('ScheduleManager::deleteAllFromEstablishment');
} catch (Exception $e) { fail('ScheduleManager::deleteAllFromEstablishment', $e->getMessage()); }

// ReviewManager::delete()
foreach ($createdReviewIds as $id) {
    try {
        $reviews->delete($id);
        pass('ReviewManager::delete');
    } catch (Exception $e) { fail('ReviewManager::delete', $e->getMessage()); }
}

// EstablishmentManager::delete()
foreach ($createdEstablishmentIds as $id) {
    try {
        $establishments->delete($id);
        pass('EstablishmentManager::delete');
    } catch (Exception $e) { fail('EstablishmentManager::delete', $e->getMessage()); }
}

// CategoryManager::delete()
foreach ($createdCategoryIds as $id) {
    try {
        $categories->delete($id);
        pass('CategoryManager::delete');
    } catch (Exception $e) { fail('CategoryManager::delete', $e->getMessage()); }
}

// UserManager::delete()
foreach ($createdUserIds as $id) {
    try {
        $users->delete($id);
        pass('UserManager::delete');
    } catch (Exception $e) { fail('UserManager::delete', $e->getMessage()); }
}

echo PHP_EOL . "All tests passed. No data was left behind." . PHP_EOL;
