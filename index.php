<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'PocketBase.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'PocketBaseHelpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'UserManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'establishment' . DIRECTORY_SEPARATOR . 'RegionManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'establishment' . DIRECTORY_SEPARATOR . 'DepartmentManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'establishment' . DIRECTORY_SEPARATOR . 'CityManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'location' . DIRECTORY_SEPARATOR . 'CategoryManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'location' . DIRECTORY_SEPARATOR . 'EstablishmentManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'location' . DIRECTORY_SEPARATOR . 'ScheduleManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'location' . DIRECTORY_SEPARATOR . 'ReviewManager.php';

try {
    $pocketbase = new PocketBase('charly@solidevs.tech', 'ipsyvml-LKJHGJfhdtyryfgh');
    $dbUsers = new UserManager($pocketbase);
    $dbRegions = new RegionManager($pocketbase);
    $dbDepartments = new DepartmentManager($pocketbase);
    $dbCities = new CityManager($pocketbase);
    $dbCategories = new CategoryManager($pocketbase);
    $dbEstablishments = new EstablishmentManager($pocketbase);
    $dbSchedules = new ScheduleManager($pocketbase);
    $dbReviews = new ReviewManager($pocketbase);
} catch (Exception $e) {
    die("Could not connect to PocketBase: {$e->getMessage()}" . PHP_EOL);
}



/*
RegionManager
    getAll()
    getById()
    getByName()
    getByCode()
    create()
    update()
    updateByName()
    updateByCode()
    delete()
    deleteByName()
    deleteByCode()
DepartmentManager
    getAll()
    getById()
    getByName()
    getByCode()
    getBySlug()
    create()
    update()
    updateByName()
    updateByCode()
    delete()
    deleteByName()
    deleteByCode()
CityManager
    getAll()
    getById()
    getByInsee()
    getBySlug()
    getByPostal()
    getByName()
    getNear()
    create()
    updateById()
    updateByInsee()
    updateByPostal()
    updateByName()
    delete()
    deleteByInsee()
EstablishmentManager
    getAll()
    getById()
    getBySlug()
    getByCity()
    getByUser()
    getPublished()
    getPublishedByCity()
    getNearby()
    create()
    update()
    setPublish()
    setUnpublish()
    setDraft()
    setVerify()
    setPremium()
    updateLogo()
    updateImage()
    delete()
ReviewManager
    getForEstablishment()
    getForUser()
    getAverageRating()
    create()
    update()
    delete()
ScheduleManager
    getByEstablishment()
    create()
    update()
    delete()
    deleteAllFromEstablishment()
CategoryManager
    getAll()
    get()
    getForEstablishment()
    create()
    update()
    updateIcon()
    delete()
    link()
    unlink()
*/