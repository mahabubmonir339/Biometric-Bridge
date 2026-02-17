<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
date_default_timezone_set('Asia/Dhaka');

/* ========= AUTH CHECK ========= */
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($authHeader) && function_exists('getallheaders')) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
}

if ($authHeader !== 'Bearer MY_SECRET_TOKEN') {
    http_response_code(401);
    echo json_encode(["status" => "unauthorized"]);
    exit;
}

/* ========= CUSTOM USER MAPPING ========= */
$userMapping = [
    "1"  => "RS0003",
    "2"  => "RS0001",
    "3"  => "RS0002",
    "4"  => "RS0009",
    "5"  => "RS0004",
    "6"  => "RS0010",
    "7"  => "RS0005",
    "8"  => "RS0007",
    "9"  => "RS0011",
    "10" => "RS0008"
];

/* ========= DATABASE ========= */
$db_host = "localhost";
$db_user = "hrm_reta1_hrm"; 
$db_pass = "@Mahabub12345";
$db_name = "hrm_reta1_hrm"; 

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(["status" => "db_error", "error" => $conn->connect_error]);
    exit;
}

/* ========= READ JSON ========= */
$input = file_get_contents("php://input");
$rows = json_decode($input, true);

if (!is_array($rows)) {
    echo json_encode(["status" => "invalid_json"]);
    exit;
}

$inserted = 0;
$updated  = 0;
$skipped  = 0;
$db_errors = [];

foreach ($rows as $r) {

    $deviceUserId = $r['user_id'];
    $date         = $conn->real_escape_string($r['date']);
    $checkIn      = $r['check_in']  ?? null;
    $checkOut     = $r['check_out'] ?? null;

    if (!isset($userMapping[$deviceUserId])) {
        $skipped++;
        continue;
    }

    $customEmployeeId = $userMapping[$deviceUserId];

    $empResult = $conn->query("SELECT emp_number FROM hs_hr_employee WHERE employee_id = '$customEmployeeId'");
    if (!$empResult || $empResult->num_rows == 0) {
        $skipped++;
        continue;
    }

    $empRow = $empResult->fetch_assoc();
    $empNumber = $empRow['emp_number'];

    /* ========= BUILD DATETIME ========= */

    $checkInDateTime  = !empty($checkIn)  ? "$date $checkIn"  : null;
    $checkOutDateTime = !empty($checkOut) ? "$date $checkOut" : null;

    $checkInUTC  = null;
    $checkOutUTC = null;

    if ($checkInDateTime) {
        $dt = new DateTime($checkInDateTime, new DateTimeZone('Asia/Dhaka'));
        $dt->setTimezone(new DateTimeZone('UTC'));
        $checkInUTC = $dt->format('Y-m-d H:i:s');
    }

    if ($checkOutDateTime) {
        $dt2 = new DateTime($checkOutDateTime, new DateTimeZone('Asia/Dhaka'));
        $dt2->setTimezone(new DateTimeZone('UTC'));
        $checkOutUTC = $dt2->format('Y-m-d H:i:s');
    }

    // validation: punch_out must be later than punch_in
    if ($checkInUTC && $checkOutUTC) {
        if (strtotime($checkOutUTC) <= strtotime($checkInUTC)) {
            $db_errors[] = "Punch out earlier than punch in for $customEmployeeId ($date)";
            continue;
        }
    }

    $checkInUserSQL   = $checkInDateTime  ? "'$checkInDateTime'"  : "NULL";
    $checkOutUserSQL  = $checkOutDateTime ? "'$checkOutDateTime'" : "NULL";
    $checkInUTCSQL    = $checkInUTC  ? "'$checkInUTC'"  : "NULL";
    $checkOutUTCSQL   = $checkOutUTC ? "'$checkOutUTC'" : "NULL";

    /* ========= EXISTING CHECK ========= */

    $existing = $conn->query("
        SELECT id FROM ohrm_attendance_record
        WHERE employee_id = $empNumber
        AND DATE(punch_in_user_time) = '$date'
    ");

    if ($existing && $existing->num_rows > 0) {

        $row = $existing->fetch_assoc();

        if (!empty($checkOutUTC)) {

            $updateSql = "
                UPDATE ohrm_attendance_record
                SET 
                    punch_out_user_time = $checkOutUserSQL,
                    punch_out_utc_time  = $checkOutUTCSQL,
                    punch_out_time_offset = '6',
                    punch_out_timezone_name = 'Asia/Dhaka',
                    state = 'PUNCHED OUT'
                WHERE id = {$row['id']}
            ";

            if ($conn->query($updateSql)) {
                $updated++;
            } else {
                $db_errors[] = $conn->error;
            }

        } else {
            $skipped++;
        }

    } else {

        if (empty($checkInUTC)) {
            $skipped++;
            continue;
        }

        $stateValue = $checkOutUTC ? 'PUNCHED OUT' : 'PUNCHED IN';

        $insertSql = "
            INSERT INTO ohrm_attendance_record
            (employee_id,
             punch_in_user_time,
             punch_in_utc_time,
             punch_in_time_offset,
             punch_in_timezone_name,
             punch_out_user_time,
             punch_out_utc_time,
             punch_out_time_offset,
             punch_out_timezone_name,
             state)
            VALUES
            ($empNumber,
             $checkInUserSQL,
             $checkInUTCSQL,
             '6',
             'Asia/Dhaka',
             $checkOutUserSQL,
             $checkOutUTCSQL,
             '6',
             'Asia/Dhaka',
             '$stateValue')
        ";

        if ($conn->query($insertSql)) {
            $inserted++;
        } else {
            $db_errors[] = $conn->error;
        }
    }
}

echo json_encode([
    "status"    => "completed",
    "received"  => count($rows),
    "inserted"  => $inserted,
    "updated"   => $updated,
    "skipped"   => $skipped,
    "db_errors" => $db_errors
]);

$conn->close();
?>
