<?php
header('Content-Type: text/plain');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_path'])) {
    $file_path = $_POST['file_path'];
    
    if (file_exists($file_path)) {
        echo 'true';
    } else {
        echo 'false';
    }
} else {
    echo 'false';
}
?>
