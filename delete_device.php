<?php
session_start();
require_once 'db_connect.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id'])) {
    $device_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    // Проверка прав доступа
    $stmt = $pdo->prepare("SELECT user_id FROM devices WHERE id_device = ?");
    $stmt->execute([$device_id]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($device && $device['user_id'] == $_SESSION['user_id']) {
        try {
            // Удаление связанных событий
            $stmt = $pdo->prepare("DELETE FROM events WHERE device_id = ?");
            $stmt->execute([$device_id]);
            
            // Удаление устройства
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id_device = ?");
            $stmt->execute([$device_id]);
            
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            die("Ошибка при удалении устройства: " . $e->getMessage());
        }
    } else {
        die("Недостаточно прав или устройство не найдено");
    }
} else {
    header('Location: index.php');
    exit;
}
?>