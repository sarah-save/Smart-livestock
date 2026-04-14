<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "livestock");
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

$uid = $_GET['tagId'] ?? '';
$uid = strtoupper(str_replace([' ', '-', ':'], '', $uid));

if(empty($uid)){
    echo json_encode(["error" => "No UID provided"]);
     exit();
}

$sql = "SELECT * FROM animals WHERE tagId='$uid'";

$result = $conn->query($sql);

if($result && $result->num_rows > 0){

     $row = $result->fetch_assoc(); 

    echo json_encode([
    "animalType" => $row['animalType'],
    "name" => $row['name'],
    //"isSick" => (bool)$row['isSick'],
     //"isPregnant" => (bool)$row['isPregnant']
]);

} else {
    http_response_code(404);
   echo json_encode(["error" => "Animal not found"]);
}

$conn->close();
?>