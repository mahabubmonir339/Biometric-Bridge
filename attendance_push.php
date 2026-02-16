<?php
// ডিবাগিং অন (যাতে কোনো সমস্যা হলে দেখা যায়)
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");

// ডাটাবেস ক্রেডেনশিয়াল
$db_host = "localhost";
$db_user = "mahabub_1_mahabub"; 
$db_pass = "@Mahabub12345";
$db_name = "mahabub_1_hrm"; 

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

/* ========= DB CONNECTION ========= */
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(["status" => "db_error", "error" => $conn->connect_error]);
    exit;
}

/* ========= READ JSON DATA ========= */
$input = file_get_contents("php://input");
$rows = json_decode($input, true);

if (!is_array($rows)) {
    echo json_encode(["status" => "invalid_json"]);
    exit;
}

$inserted = 0;
$skipped  = 0;
$db_errors = [];

foreach ($rows as $r) {
    $empId = (int)$r['user_id'];
    $date = $conn->real_escape_string($r['date']);
    
    // টাইম ফরম্যাটিং
    $punchInTime = $date . ' ' . $r['check_in'];
    $punchOutTime = $date . ' ' . $r['check_out'];

    // ১. এমপ্লয়ী চেক
    $empCheck = $conn->query("SELECT emp_number FROM hs_hr_employee WHERE emp_number = $empId");
    if (!$empCheck || $empCheck->num_rows == 0) {
        $skipped++;
        continue;
    }

    // ২. ডুপ্লিকেট চেক (একই দিনে একই ইউজারের এন্ট্রি আছে কি না)
    $dupCheck = $conn->query("SELECT id FROM ohrm_attendance_record WHERE employee_id = $empId AND DATE(punch_in_utc_time) = '$date'");
    if ($dupCheck && $dupCheck->num_rows > 0) {
        $skipped++;
        continue; 
    }

    // ৩. ইনসার্ট কোয়েরি
    // এখানে punch_in_user_time এবং punch_out_user_time দুটিই রাখা হয়েছে
    $sql = "INSERT INTO ohrm_attendance_record 
            (employee_id, punch_in_utc_time, punch_in_time_offset, punch_in_user_time, 
             punch_out_utc_time, punch_out_time_offset, punch_out_user_time, state) 
            VALUES 
            ($empId, '$punchInTime', '6', '$punchInTime', 
             '$punchOutTime', '6', '$punchOutTime', 'PUNCHED OUT')";

    if ($conn->query($sql)) {
        $inserted++;
    } else {
        $db_errors[] = "Emp $empId: " . $conn->error;
    }
}

echo json_encode([
    "status"    => "completed",
    "received"  => count($rows),
    "inserted"  => $inserted,
    "skipped"   => $skipped,
    "db_errors" => $db_errors
]);

$conn->close();
?>