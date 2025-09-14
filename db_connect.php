<?php
// Параметры подключения к базе данных
$host = '127.0.0.1';
$dbname = 'home_bd';
$username = 'root';     //имя пользователя БД
$password = '';         //пароль БД

// Создание подключения через PDO
try {                                         
    // Создание подключения через PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Установка режима обработки ошибок
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Установка кодировки
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    // Вывод ошибки в случае неудачного подключения
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
?>


