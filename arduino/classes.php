<?php

//------------------------------Подключение по COM порту к Arduino-----------------
class ArduinoCOM {
	private $serial = NULL;

	public function Setup($ComPortNumber){
		exec("mode $ComPortNumber baud=9600 data=8 stop=1 parity=n xon=on");
	}
	
	public function Open($ComPortNumber){
		if($this->serial = dio_open($ComPortNumber, O_RDWR)){
				return true;
		} else {
			return false;
		}
	}
	
	public function isConnected(){
		if(isset($serial)) return true;
		return false;
	}
	
	public function Send($text){
		if($this->serial){
			dio_write($this->serial,$text);
			return true;
		}else{
			return false;
		}
	}
	public function ReadUntil($EscapeSymbol){
		if(!$this->serial) return false;
		$readed = '';
		do{
        $byte = dio_read($this->serial, 1);
		if($byte == ";")
			continue;
        $readed .= $byte;
		} while ($byte != $EscapeSymbol);
		return $readed;
	}
	public function Close(){
		if($this->serial){
		dio_close($this->serial);
		echo "Arduino ком порт закрыт" . PHP_EOL;
		}
		return false;
	}
}

//------------------------------Запуск WebSocket сервера---------------------------
class WebSocket{
	private $socket;
	private $connections = [];
	public $host = "127.0.0.1";
	public $port = "8080";
	
	private function Handshake($header,$connection,$host,$port){
		$headers = array();
		$lines = preg_split("/\r\n/", $header);
		foreach($lines as $line)
		{
			$line = chop($line);
			if(preg_match('/\A(\S+): (.*)\z/', $line, $matches)){
				$headers[$matches[1]] = $matches[2];
			}
		}

		$sec_key = $headers['Sec-WebSocket-Key'];
		$sec_accept = base64_encode(pack('H*', sha1($sec_key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
		$response_header  = "HTTP/1.1 101 Switching Protocols\r\n" .
		"Upgrade: websocket\r\n" .
		"Connection: Upgrade\r\n" .
		"Sec-WebSocket-Accept:$sec_accept\r\n\r\n";
		socket_write($connection,$response_header,strlen($response_header));
		echo 'handshake done!'  . PHP_EOL ;
	}
	
	private function RemoveClient($client){
		$key = array_search($client, $this->connections);
		if($key !== false){
		unset($this->connections[$key]);
		socket_close($client);
		echo "client disconnected.\n";
		}
	}
	
	//Decode from client
	private function unmask($text){
		$length = ord($text[1]) & 127;
		if($length == 126) {
			$masks = substr($text, 4, 4);
			$data = substr($text, 8);
		}
		elseif($length == 127) {
			$masks = substr($text, 10, 4);
			$data = substr($text, 14);
		}
		else {
			$masks = substr($text, 2, 4);
			$data = substr($text, 6);
		}
		$text = "";
		for ($i = 0; $i < strlen($data); ++$i) {
			$text .= $data[$i] ^ $masks[$i%4];
		}
		return $text;
	}
	
	//Encode data to client
	private function pack_data($text) {
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($text);

		if($length <= 125) {
			$header = pack('CC', $b1, $length);
		}
			
		elseif($length > 125 && $length < 65536) {
			$header = pack('CCn', $b1, 126, $length);
		}
			
		elseif($length >= 65536) {
			$header = pack('CCNN', $b1, 127, $length);
		}
			
		return $header.$text;
	}
	
	public function Create(){
		if(!$this->socket){
			$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
		}
	}
	public function Bind($host = "127.0.0.1", $port = "8080"){
		$this->host = $host;
		$this->port = $port;
		socket_bind($this->socket,$this->host, $this->port);
	}
	public function Listen(){
		socket_listen($this->socket);
		$this->connections[] = $this->socket; // 0 в массиве, это слушающий сокет
		echo "WebSocket server started on $this->host:$this->port" . PHP_EOL;
	}
	
	public function Close(){
		if($this->socket)
			socket_close($this->socket);
		return false;
	}
	
	public function GetNewConnection(){
		$read = $this->connections;
		$write = NULL;
		$except = NULL;
		
		if(socket_select($read,$write ,$except , 0) >= 1){
			if (in_array($this->socket, $read)) { //check for new sockets
				$this->connections[] = $new_conn = socket_accept($this->socket);
				$header = socket_read($new_conn,1024);
				$this->Handshake($header,$new_conn,$this->host,$this->port);
				$found_socket = array_search($this->socket,$read);
				unset($read[$found_socket]);
				echo 'Client connected!' . PHP_EOL;
				return $new_conn;
			}
		}
		return false;
	}
	
	public function Send($client,$text){
		socket_write($client, $this->pack_data($text));
		echo "data sent to client:" . $text . PHP_EOL;
	}
	
	public function Receive($client,$buffer_size){
            $buffer = @socket_read($client,$buffer_size);
            if ($buffer === false || $buffer === '') {
               $this->RemoveClient($client);
			   return false;
            }
			
			if (ord($buffer[0]) == '\x03\xE9') {
				$this->RemoveClient($client);
				return false;
			}
			
			$buffer = $this->unmask($buffer);
			$buffer = trim($buffer);
			echo "Recvd:" . $buffer . PHP_EOL;
			return $buffer;
	}

	
}


//------------------------------Работа с БД----------------------------------------

class DB{
	private $db_connection;
	private $host;
	private $port;
	private $user;
	private $pass;
	private $db_name;
	
	public function Connect($db_name,$host,$user,$pass){
		try{
			$this->db_connection = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
			$this->db_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->db_connection->exec("SET NAMES 'utf8mb4'");
		}
		catch (PDOException $e){
			die("Ошибка подключения к базе данных: " . $e->getMessage());
		}
	}
	
	public function Query($sql, $params = []) {
        $stmt = $this->db_connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
	
	public function Fetch($sql, $params = []) {
        $stmt = $this->Query($sql, $params);
        return $stmt->fetch();
    }
	
	public function FetchAll($sql, $params = []) {
        $stmt = $this->Query($sql, $params);
        return $stmt->fetchAll();
    }
	
	public function Insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->Query($sql, array_values($data));
        return $this->db_connection->lastInsertId();
    }
	
	public function Update($table, $data, $condition, $params = []) {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE $table SET $set WHERE $condition";
        $mergedParams = array_merge(array_values($data), $params);
        $stmt = $this->Query($sql, $mergedParams);
        return $stmt->rowCount();
    }
	
	public function Delete($table, $condition, $params = []) {
        $sql = "DELETE FROM $table WHERE $condition";
        $stmt = $this->Query($sql, $params);
        return $stmt->rowCount();
    }
	
	public function BeginTransaction() {
        $this->db_connection->beginTransaction();
    }
	
	public function Commit() {
        $this->db_connection->commit();
    }
	
	public function RollBack() {
        $this->db_connection->rollBack();
    }
}

?>