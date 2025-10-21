<?php
  
//Dados de conexão com servidor local
$host = "localhost";
$user = "user_copasa";
$password = "@uT0m4#*C0p@5a";
$database = "copasa"; 

try {
  $conn = new PDO("mysql:host=$host; dbname=$database; charset=utf8", $user, $password);
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
} catch (PDOException $e) {
  echo 'ERROR: ' . $e->getMessage();
}

// Configurar fuso horário para Brasília (UTC-3)
date_default_timezone_set('America/Sao_Paulo');