<?php

/**
 * locations_import.php
 *
 * Imports all French regions, departments and cities into PocketBase using the API Découpage Administratif (geo.api.gouv.fr).
 *
 * Run once from the command line: php locations_import.php
 *
 * Requirements:
 *   - PHP 8.1+
 *   - cURL extension enabled
 *   - cacert.pem in the same directory as PocketBase.php
 */

//==============
// Imports
//==============
require_once '../core/PocketBase.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'RegionManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'DepartmentManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'CityManager.php';

//==============
// Config
//==============
const GEO_API       = 'https://geo.api.gouv.fr';
const RATE_LIMIT_MS = 50_000; // 0.05s delay
set_time_limit(0);
ini_set('memory_limit', '512M');

$pocketbaseEmail    = 'charly@solidevs.tech';
$pocketbasePassword = 'ipsyvml-LKJHGJfhdtyryfgh';

try {
    $pocketbase  = new PocketBase($pocketbaseEmail, $pocketbasePassword);
    $regions     = new RegionManager($pocketbase);
    $departments = new DepartmentManager($pocketbase);
    $cities      = new CityManager($pocketbase);
} catch (Exception $e) {
    die("Could not connect to PocketBase: {$e->getMessage()}" . PHP_EOL);
}

//==============
// Helpers
//==============
function geoFetch(string $url): array {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_CAINFO         => '../cacert.pem',
    ]);
    $data   = curl_exec($curl);
    $error  = curl_error($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($error) throw new RuntimeException("cURL error: {$error}");
    if ($status >= 400) throw new RuntimeException("Geo API error {$status} on {$url}.");

    return json_decode($data, true);
}

function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = str_replace(
        ["à","â","ä","é","è","ê","ë","î","ï","ô","ö","ù","û","ü","ç","æ","œ","ñ","ÿ", "'"],
        ["a","a","a","e","e","e","e","i","i","o","o","u","u","u","c","ae","oe","n","y", "-"],
        $text,
    );
    $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function console_log(string $message): void {
    echo '[' . date('H:i:s') . '] ' . $message . PHP_EOL;
}

//==============
// Regions
//==============
$regionsJSON  = geoFetch(GEO_API . '/regions?fields=nom,code&format=json');
$regionsArray = [];

foreach ($regionsJSON as $region) {
    $slug = slugify($region['nom']);
    try {
        $record = $regions->create($region['code'], $region['nom'], $slug);
        $regionsArray[$region['code']] = $record['id'];
        console_log("Added region {$region['nom']}.");
    } catch (RuntimeException $e) {
        console_log("Could not add region {$region['nom']}: {$e->getMessage()}");
    }
    usleep(RATE_LIMIT_MS);
}

//==============
// Departments
//==============
$departmentsJSON  = geoFetch(GEO_API . '/departements?fields=nom,code,codeRegion&format=json');
$departmentsArray = [];

foreach ($departmentsJSON as $department) {
    $regionId = $regionsArray[$department['codeRegion']] ?? null;

    if (!$regionId) {
        console_log("Could not find region {$department['codeRegion']} for department {$department['nom']}, skipping.");
        continue;
    }

    $slug = slugify($department['nom']);

    try {
        $record = $departments->create($regionId, $department['code'], $department['nom'], $slug);
        $departmentsArray[$department['code']] = $record['id'];
        console_log("Added department {$department['nom']}.");
    } catch (RuntimeException $e) {
        console_log("Could not add department {$department['nom']}: {$e->getMessage()}");
    }

    usleep(RATE_LIMIT_MS / 2);
}

//==============
// Cities
// (Fetched per department)
//==============
$cityCount = 0;
$citySlugs = [];

foreach ($departmentsArray as $departmentCode => $departmentId) {
    $url      = GEO_API . '/departements/' . $departmentCode . '/communes?fields=nom,code,codesPostaux,centre&format=json';
    $cityList = geoFetch($url);

    foreach ($cityList as $city) {
        $gpsLatitude  = $city['centre']['coordinates'][1] ?? null;
        $gpsLongitude = $city['centre']['coordinates'][0] ?? null;
        $postalCode   = $city['codesPostaux'][0] ?? '';

        $baseSlug  = slugify($city['nom']);
        $slug      = isset($cityitySlugs[$baseSlug]) ? $baseSlug . '-' . strtolower($city['code']) : $baseSlug;
        $citySlugs[$slug] = true;

        try {
            $cities->create(
                departmentId: $departmentId,
                postalCode:   $postalCode,
                inseeCode:    $city['code'],
                name:         $city['nom'],
                slug:         $slug,
                gpsLatitude:  $gpsLatitude,
                gpsLongitude: $gpsLongitude,
            );
            $cityCount++;
            console_log("Added {$city['nom']} to $departmentCode.");
        } catch (RuntimeException $e) {
            console_log("City {$city['nom']} ({$city['code']}): " . $e->getMessage());
        }
    }
    usleep(RATE_LIMIT_MS);
}

//==============
// Prefectures
// (Source: Official INSEE prefectures list)
//==============
$prefectures = [
    // Mainland
    '01' => '01053', '02' => '02408', '03' => '03190', '04' => '04070', '05' => '05061',
    '06' => '06088', '07' => '07186', '08' => '08105', '09' => '09122', '10' => '10387',
    '11' => '11069', '12' => '12202', '13' => '13055', '14' => '14118', '15' => '15014',
    '16' => '16015', '17' => '17300', '18' => '18033', '19' => '19272',
    '21' => '21231', '22' => '22278', '23' => '23096', '24' => '24322', '25' => '25056',
    '26' => '26362', '27' => '27229', '28' => '28085', '29' => '29232', '30' => '30189',
    '31' => '31555', '32' => '32013', '33' => '33063', '34' => '34172', '35' => '35238',
    '36' => '36044', '37' => '37261', '38' => '38185', '39' => '39300', '40' => '40192',
    '41' => '41018', '42' => '42218', '43' => '43157', '44' => '44109', '45' => '45234',
    '46' => '46042', '47' => '47091', '48' => '48095', '49' => '49328', '50' => '50502',
    '51' => '51108', '52' => '52121', '53' => '53130', '54' => '54395', '55' => '55029',
    '56' => '56260', '57' => '57463', '58' => '58194', '59' => '59350', '60' => '60057',
    '61' => '61001', '62' => '62041', '63' => '63113', '64' => '64422', '65' => '65440',
    '66' => '66136', '67' => '67482', '68' => '68224', '69' => '69123', '70' => '70550',
    '71' => '71270', '72' => '72181', '73' => '73065', '74' => '74010', '75' => '75056',
    '76' => '76540', '77' => '77288', '78' => '78646', '79' => '79191', '80' => '80021',
    '81' => '81004', '82' => '82121', '83' => '83137', '84' => '84007', '85' => '85191',
    '86' => '86194', '87' => '87085', '88' => '88160', '89' => '89024', '90' => '90010',
    '91' => '91228', '92' => '92012', '93' => '93008', '94' => '94028', '95' => '95500',
    // Corsica
    '2A' => '2A004', '2B' => '2B033',
    // Overseas
    '971' => '97105', '972' => '97209', '973' => '97302', '974' => '97411', '976' => '97608',
];

foreach ($prefectures as $departmentCode => $inseeCode) {
    $departmentId = $departmentsArray[(string) $departmentCode] ?? null;
    if (!$departmentId) continue;

    $city = $cities->getByInsee($inseeCode);

    if (!$city) {
        console_log("Prefecture city $inseeCode not found for department $departmentCode.");
        continue;
    }

    try {
        $departments->update($departmentId, prefectureId: $city['id']);
    } catch (RuntimeException $e) {
        console_log("Department $departmentCode prefecture update: " . $e->getMessage());
    }
    usleep(RATE_LIMIT_MS);
}

// Done :)
console_log("Import complete. $cityCount cities added.");