<?php
session_start();
require_once 'db_connect.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type_device = filter_input(INPUT_POST, 'type_device', FILTER_SANITIZE_STRING);
    $name_device = filter_input(INPUT_POST, 'name_device', FILTER_SANITIZE_STRING);
    $room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $user_id = $_SESSION['user_id'];

    if (in_array($type_device, ['sensor', 'device']) && $name_device && $room_id) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO devices (type_device, name_device, room_id, user_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$type_device, $name_device, $room_id, $user_id]);
            
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            die("Ошибка при добавлении устройства: " . $e->getMessage());
        }
    } else {
        die("Неверные данные");
    }
} else {
    header('Location: index.php');
    exit;
}
?>