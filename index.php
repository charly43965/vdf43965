<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'PocketBase.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'PocketBaseHelpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'UserManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'LocationManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'CategoryManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'EstablishmentManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'ScheduleManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'ReviewManager.php';

try {
    $pocketbase = new PocketBase('charly@solidevs.tech', 'ipsyvml-LKJHGJfhdtyryfgh');
    $dbUsers = new UserManager($pocketbase);
    $dbLocations = new LocationManager($pocketbase);
    $dbCategories = new CategoryManager($pocketbase);
    $dbEstablishments = new EstablishmentManager($pocketbase);
    $dbSchedules = new ScheduleManager($pocketbase);
    $dbReviews = new ReviewManager($pocketbase);
} catch (Exception $e) {
    die("Could not connect to PocketBase: {$e->getMessage()} {PHP_EOL}");
}





/* Users -- OK -- Missing updateAvatar()
-- Create
create()

-- Read
getAll()
getById()
getByEmail()

-- Update
update()
updateAvatar()
changePassword()
changeEmail()
changePhoneNumber()
promote()
demote()
verify()
unverify()
timeout()
ban()
unban()

-- Delete
delete()
anonymize()
*/




/* Regions
-- Create
create()

-- Read
getAll()
getById()
getByName()
getByCode()

-- Update
update()
?updateImage()

-- Delete
delete()
*/






/* Departments
-- Create
create()

-- Read
getAll()
getById()
getByName()
getByCode()

-- Update
update()
?updateImage()

-- Delete
delete()
deleteList()
*/






/* Cities -- Testing
-- Create
create()

-- Read
getAll()
getNear()
getById()
getByName()
getByInsee()
getByPostal()

-- Update
update()
?updateImage()

-- Delete
delete()
deleteList()
*/