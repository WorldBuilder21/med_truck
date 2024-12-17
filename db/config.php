<?php

// for local host connection
// $servername = "localhost";
// $username = "root";
// $password = "";
// $dbname = "medtrak";

$servername = "localhost";
$username = "denis.demitrus";
$password = "helloworld123";
$dbname = "webtech_fall2024_denis_demitrus";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
