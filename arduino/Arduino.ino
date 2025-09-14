String  serialInput;

int ledPin = D0; 
unsigned char ledPin_state = HIGH;


void setup() {
  pinMode(ledPin, OUTPUT);
  digitalWrite(ledPin,ledPin_state);
  Serial.begin(9600);
  Serial.setTimeout(50);
}

void sendInfo() {
  if(Serial.availableForWrite() > 0){
    Serial.write("94:Data_collection:");
    Serial.write("30");
    Serial.write(",");
    Serial.write("93:Data_collection:");
    Serial.write("70");
    Serial.write(",");
    Serial.write("92:Data_collection:");
    Serial.write("0");
    Serial.write(",");
    Serial.write("89:Condition:");
    if(ledPin_state == HIGH){
      Serial.write("off");
    } else{
      Serial.write("on");
    }
    Serial.write(";");
    Serial.flush();
  };
}


void loop() {
if (Serial.available() != 0) {
  serialInput = Serial.readStringUntil('\n');
  if (serialInput == "read") {
    sendInfo();
  }
  else if (serialInput.indexOf("control") >= 0) {
    int firstSpace = serialInput.indexOf(' ');  // Находим первый пробел

    if (firstSpace != -1) {  // Если найдены оба пробела
      String command = serialInput.substring(0, firstSpace);  // "control"
      String device = serialInput.substring(firstSpace + 1, 128);  // "device_id" 
      if (command == "control") {
        if (device == "89") {
            if(ledPin_state == LOW){
              digitalWrite(ledPin,HIGH);
              ledPin_state = HIGH;
              Serial.write("89:Condition:off;");
            } else {
              digitalWrite(ledPin,LOW);
              Serial.write("89:Condition:on;");
              ledPin_state = LOW;
            }
        }
      }  
      }
    } 
  } 
  delay(50); 
}