<?php

include "classes.php";

if (!extension_loaded('dio') && !extension_loaded('socket')) {
    die("Расширение dio или socket не загружено");
}

/*function DB_Send($id,$eventName,$value){
	try {
                    $stmt = $pdo->prepare("INSERT INTO events (device_id, event_type, status, value, datatime) VALUES (?, ?, 'on', ?, NOW())");
                    $stmt->execute([$id, "$eventName", "$value"]);
                    
                    
                    
                } catch (PDOException $e) {
                   echo ("Ошибка базы данных: " . $e->getMessage());
                }
}
*/
function ProcessData($text,$db){
	$dataJSON = [];
	if ($text !== false && !empty($text)) {
        $text = trim($text);
		
		$lines = explode(",", $text);
		$matched = false;
		
		foreach ($lines as $line) {
            if (empty($line)) continue;
			
            // Разбор ответа датчиков от Arduino
            if (preg_match('/^(\d+):([a-zA-Z_]+):([^:]+)$/', $line, $matches)) {
                $id = $matches[1];         // Первая группа - ID
                $eventName = $matches[2];   // Вторая группа - название события
                $value = $matches[3];      // Третья группа - значение
				
				if($eventName == "Condition"){
				$db->Insert('events', 
				[
				'device_id' => $id,
				'event_type' => $eventName,
				'status' => $value,
				'value' => '0',
				'datatime' => date("Y-m-d H:i:s")
				]
				);	
				} else{
				$db->Insert('events', 
				[
				'device_id' => $id,
				'event_type' => $eventName,
				'status' => 'on',
				'value' => $value,
				'datatime' => date("Y-m-d H:i:s")
				]
				);
				}
				
				array_push($dataJSON, [
                        'id' => (int)$id,
                        'event_type' => $eventName,
                        'value' => $value
                    ]);
                $matched = true;
			}
		}
	$dataJSON = json_encode($dataJSON);
	}
	return $dataJSON;
};


$Ard = new ArduinoCOM;
$ws = new WebSocket;
$db = new DB;

$ws->Create();
$ws->Bind();
$ws->Listen();

$db->Connect('home_bd',"127.0.0.1","root","");

while(!$client = $ws->GetNewConnection()){
	sleep(1);
};

$Ard->Setup("COM2");
if($Ard->Open("COM2")){
	echo "Arduino ком порт открыт!" . PHP_EOL;
} else{
	echo "Error open Arduino com port" . PHP_EOL;
}

$command = '';
while($command = $ws->Receive($client,1024)){
	$Ard->Send($command);
	$data = $Ard->ReadUntil(";");
	if($command == "read")
		$data = ProcessData($data,$db);
	$ws->Send($client,$data);
}

$Ard->Close();

?>