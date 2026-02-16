<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");

/* ========= DATABASE ========= */
$db_host = "localhost";
$db_user = "mahabub_1_mahabub"; 
$db_pass = "@Mahabub12345";
$db_name = "mahabub_1_hrm"; 

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(["status" => "db_error", "error" => $conn->connect_error]);
    exit;
}

/* ========= AUTH CHECK ========= */
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader !== 'Bearer MY_SECRET_TOKEN') {
    http_response_code(401);
    echo json_encode(["status" => "unauthorized"]);
    exit;
}

/* ========= CUSTOM USER MAPPING ========= */
$userMapping = [
    "1"  => "RS0001",
    "2"  => "RS0002",
    "3"  => "RS0003",
    "4"  => "RS0004",
    "5"  => "RS0005",
    "6"  => "RS0006",
    "7"  => "RS0007",
    "8"  => "RS0008",
    "9"  => "RS0009",
    "10" => "RS0010"
];

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

    $jsonUserId = $r['user_id'] ?? null;
    $date       = $r['date'] ?? null;
    $checkIn    = $r['check_in'] ?? null;
    $checkOut   = $r['check_out'] ?? null;

    if (!$jsonUserId || !$date) {
        $skipped++;
        continue;
    }

    if (!isset($userMapping[$jsonUserId])) {
        $skipped++;
        continue;
    }

    $customEmployeeId = $userMapping[$jsonUserId];

    /* ===== GET emp_number ===== */
    $empQuery = $conn->query("
        SELECT emp_number 
        FROM hs_hr_employee 
        WHERE employee_id = '$customEmployeeId'
        LIMIT 1
    ");

    if (!$empQuery || $empQuery->num_rows == 0) {
        $skipped++;
        continue;
    }

    $empNumber = $empQuery->fetch_assoc()['emp_number'];

    $startDateTime = $date . " 00:00:00";
    $endDateTime   = $date . " 23:59:59";

    /* ===== CHECK EXISTING RECORD ===== */
    $existing = $conn->query("
        SELECT id, punch_out_user_time
        FROM ohrm_attendance_record
        WHERE employee_id = $empNumber
        AND punch_in_user_time BETWEEN '$startDateTime' AND '$endDateTime'
        LIMIT 1
    ");

    $checkInDateTime  = $checkIn  ? $date . " " . $checkIn  : null;
    $checkOutDateTime = $checkOut ? $date . " " . $checkOut : null;

    /* ===============================
       IF RECORD EXISTS → UPDATE
    ================================*/
    if ($existing && $existing->num_rows > 0) {

        $row = $existing->fetch_assoc();

        if ($checkOutDateTime) {

            $updateSql = "
                UPDATE ohrm_attendance_record
                SET punch_out_user_time = '$checkOutDateTime',
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

    }
    /* ===============================
       NO RECORD → INSERT
    ================================*/
    else {

        if ($checkInDateTime) {

            $insertSql = "
                INSERT INTO ohrm_attendance_record
                (employee_id,
                 punch_in_user_time,
                 punch_in_time_offset,
                 punch_in_timezone_name,
                 state)
                VALUES
                ($empNumber,
                 '$checkInDateTime',
                 '6',
                 'Asia/Dhaka',
                 'PUNCHED IN')
            ";

            if ($conn->query($insertSql)) {
                $inserted++;
            } else {
                $db_errors[] = $conn->error;
            }

        } else {
            $skipped++;
        }
    }
}

/* ========= RESPONSE ========= */
echo json_encode([
    "status"   => "completed",
    "received" => count($rows),
    "inserted" => $inserted,
    "updated"  => $updated,
    "skipped"  => $skipped,
    "errors"   => $db_errors
]);

$conn->close();
?>



<!-- orangehrm file folder a bosbe ei code -->