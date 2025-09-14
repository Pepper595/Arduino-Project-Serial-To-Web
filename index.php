<?php
session_start();
require_once 'db_connect.php';


if (!empty($_GET['code'])) {
	// Отправляем код для получения токена (POST-запрос).
	$params = array(
		'grant_type'    => 'authorization_code',
		'code'          => $_GET['code'],
		'client_id'     => "ea121e034f834795b5b2cd271ef1db4b",
		'client_secret' => "6e21fe24ad1e4b2b8b3f0a57f3472efd",
	);
	
	$ch = curl_init('https://oauth.yandex.ru/token');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HEADER, false);
	$data = curl_exec($ch);
	curl_close($ch);	

	$data = json_decode($data, true);
	if (!empty($data['access_token'])) {
		// Токен получили, получаем данные пользователя.
		$ch = curl_init('https://login.yandex.ru/info');
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array('format' => 'json')); 
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: OAuth ' . $data['access_token']));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		$info = curl_exec($ch);
		curl_close($ch);
 
		$info = json_decode($info, true);
		print_r($info);
	}
}

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Выход из системы
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Умный дом - Управление</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

        <script> <!-- Changed -->
			let socket;
			let req;
			function connect() {
				try{
					socket = new WebSocket("ws://127.0.0.1:8080");
				}
				catch (error){
					console.log("Error: " + error);
				}
				
				socket.onopen = function(event) {
					document.getElementById("output").innerHTML = "<span style='color: green;'>Connected to server</span><br>";
					//sendMessage("sensor");
					req = setInterval(sendMessage,5000,"read");
					//console.log("ws OnOpen");
				};
			
				
				socket.onmessage = (event) => {
					try{
						const data = JSON.parse(event.data);
						data.forEach((sensor) => {
							const sensorId = sensor.id;
							const eventName = sensor.event_type;
							const sensorValue = sensor.value;
							// добавить проверку на существование датчика
							try{
								if(eventName == "Condition"){
									
									if(sensorValue == "on"){
										document.getElementById(sensorId.toString()).innerText = "Включено";
									}
									else{
										document.getElementById(sensorId.toString()).innerText = "Выключено";
									}
								} else{
								document.getElementById(sensorId.toString()).getElementsByTagName('a')[0].text = sensorValue.toString();
								}
							}
							catch(error){
								//console.log("error on device id " + sensorId);
							}
							
						});
						console.log(data);
					}
					catch (error) {
						console.log("error onmessage " + error);
					};
					
				}
				
				
				socket.onclose = function(event) {
					document.getElementById("output").innerHTML = "<span style='color: red;'>Disconnected from server</span><br>";
					connect();
					clearInterval(req);
				};
				
				socket.onerror = function(event) {
					clearInterval(req);
				};
			}

			function sendMessage(msg) {
				if(socket.readyState == 1)
					socket.send(msg);
			}
			
			window.addEventListener("beforeunload", function(e){
				socket.close();
			});
			window.addEventListener('DOMContentLoaded', function(){
					connect();
				});
			

        </script>

        <div class="user-info">
        <span>Пользователь: <?php echo $_SESSION['user_name']; ?></span>
        <a href="index.php?logout=1" class="logout">Выйти</a>
		<div id="output">Not Connected</div> <!-- Added -->
    </div>

    <h1>Управление умным домом</h1>

    <!-- Форма добавления устройства -->
    <h2>Добавить устройство</h2>
    <form class="add-form" method="POST" action="add_device.php">
        <select name="type_device" required>
            <option value="sensor">Датчик</option>
            <option value="device">Устройство</option>
        </select>
        <input type="text" name="name_device" placeholder="Название устройства" required>
        <select name="room_id" required>
            <?php
            $rooms = $pdo->query("SELECT * FROM rooms")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rooms as $room) {
                echo "<option value='{$room['id_room']}'>{$room['room_name']}</option>";
            }
            ?>
        </select>
        <button type="submit">Добавить</button>
    </form>

    <!-- Список устройств -->
    <h2>Устройства</h2>
    <?php
    $stmt = $pdo->query("
        SELECT d.*, r.room_name, u.user_name 
        FROM devices d 
        JOIN rooms r ON d.room_id = r.id_room 
        JOIN users u ON d.user_id = u.id_user
        WHERE u.id_user = {$_SESSION['user_id']}
        ORDER BY d.room_id, d.name_device
    ");
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Тип</th><th>Название</th><th>Комната</th><th>Управление</th><th>Действие</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['id_device']}</td>";
        echo "<td>" . ($row['type_device'] == 'sensor' ? 'Датчик' : 'Устройство') . "</td>";
        echo "<td>{$row['name_device']}</td>";
        echo "<td>{$row['room_name']}</td>";
        
        if ($row['type_device'] == 'device') {
            $last_event = $pdo->query("
                SELECT status 
                FROM events 
                WHERE device_id = {$row['id_device']} 
                AND event_type = 'Condition' 
                ORDER BY datatime DESC 
                LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC);
            
            $status = $last_event ? $last_event['status'] : 'off';
            
            echo "<td class='device-control'>";
            //echo "<form method='POST' action='control.php'>";
            //echo "<input type='hidden' name='device_id' value='{$row['id_device']}'>";
            //echo "<select name='status' onchange='sendMessage(\"control {$row['id_device']}\")'>";
            //echo "<option value='on'" . ($status == 'on' ? ' selected' : '') . ">Включено</option>";
            //echo "<option value='off'" . ($status == 'off' ? ' selected' : '') . ">Выключено</option>";
            //echo "</select>";
            //echo "</form>";
			echo "<button id={$row['id_device']} onclick=\"sendMessage('control {$row['id_device']}')\">Обновление</button>";
            echo "</td>";
        } else {
            $last_value = $pdo->query("
                SELECT value 
                FROM events 
                WHERE device_id = {$row['id_device']} 
                AND event_type = 'Data_collection' 
                ORDER BY datatime DESC 
                LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC);
            
            $value = $last_value ? $last_value['value'] : '-';
            echo "<td id=\"{$row['id_device']}\"><a href='SensorHistory.php?id={$row['id_device']}' class='sensor-link'>$value</a></td>";
        }
        echo "<td><a href='delete_device.php?id={$row['id_device']}' class='delete-btn' onclick='return confirm(\"Вы уверены?\")'>Удалить</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    ?>

    <!-- Последние события -->
    <h2>Последние события</h2>
    <?php
    $stmt = $pdo->query("
        SELECT e.*, d.name_device, r.room_name 
        FROM events e 
        JOIN devices d ON e.device_id = d.id_device 
        JOIN rooms r ON d.room_id = r.id_room 
        JOIN users u ON d.user_id = u.id_user
        WHERE u.id_user = {$_SESSION['user_id']}
        ORDER BY e.datatime DESC 
        LIMIT 10
    ");
    
    echo "<table>";
    echo "<tr><th>Время</th><th>Устройство</th><th>Комната</th><th>Тип события</th><th>Состояние</th><th>Значение</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['datatime']}</td>";
        echo "<td>{$row['name_device']}</td>";
        echo "<td>{$row['room_name']}</td>";
        echo "<td>" . ($row['event_type'] == 'Condition' ? 'Состояние' : 'Сбор данных') . "</td>";
        echo "<td>" . ($row['status'] == 'on' ? 'Вкл' : 'Выкл') . "</td>";
        echo "<td>" . ($row['value'] > 0 ? $row['value'] : '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    ?>

</body>
</html>