<?php
session_start();
require_once 'db_connect.php';

// Отключаем кэширование
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$device_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Проверка прав доступа
$stmt = $pdo->prepare("
    SELECT d.*, r.room_name 
    FROM devices d 
    JOIN rooms r ON d.room_id = r.id_room 
    WHERE d.id_device = ? AND d.user_id = ? AND d.type_device = 'sensor'
");
$stmt->execute([$device_id, $_SESSION['user_id']]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    die("Датчик не найден или доступ запрещен");
}

// Получение истории показаний
$stmt = $pdo->prepare("
    SELECT datatime, value 
    FROM events 
    WHERE device_id = ? 
    ORDER BY datatime DESC 
    LIMIT 50
");
$stmt->execute([$device_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Отладка: выводим количество записей
error_log("Sensor ID: $device_id, Number of history records: " . count($history));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>История показаний - <?php echo htmlspecialchars($device['name_device']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <script src="chart.js"></script>
</head>
<body>
    <a href="index.php" class="back-link">← Назад</a>
    <h1>История показаний: <?php echo htmlspecialchars($device['name_device']); ?></h1>
    <h2>Комната: <?php echo htmlspecialchars($device['room_name']); ?></h2>

    <!-- График -->
    <canvas id="sensorChart" width="400" height="200"></canvas>
    <script>
        const ctx = document.getElementById('sensorChart').getContext('2d');
        const data = {
            labels: [<?php 
                $labels = array_map(function($entry) { return "'{$entry['datatime']}'"; }, array_reverse($history));
                echo implode(',', $labels);
            ?>],
            datasets: [{
                label: 'Показания датчика',
                data: [<?php 
                    $values = array_map(function($entry) { return $entry['value']; }, array_reverse($history));
                    echo implode(',', $values);
                ?>],
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        };
        const config = {
            type: 'line',
            data: data,
            options: {
                scales: {
                    x: { title: { display: true, text: 'Время' } },
                    y: { title: { display: true, text: 'Значение' } }
                }
            }
        };
        new Chart(ctx, config);
    </script>

    <!-- Таблица -->
    <h2>Последние 50 записей</h2>
    <table>
        <tr><th>Время</th><th>Значение</th></tr>
        <?php
        if (empty($history)) {
            echo "<tr><td colspan='2'>Нет данных</td></tr>";
        } else {
            foreach ($history as $entry) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($entry['datatime']) . "</td>";
                echo "<td>" . htmlspecialchars($entry['value']) . "</td>";
                echo "</tr>";
            }
        }
        ?>
    </table>
</body>
</html>