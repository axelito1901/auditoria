<?php
// Conexión PDO para XAMPP (MySQL local).
// Ajustá $user/$pass/$host/$db si hiciera falta.
function db() {
  static $pdo;
  if ($pdo) return $pdo;

  $host = '127.0.0.1';
  $db   = 'auditoria_or';
  $user = 'root';
  $pass = '';
  $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

  $opts = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ];

  $pdo = new PDO($dsn, $user, $pass, $opts);
  return $pdo;
}
