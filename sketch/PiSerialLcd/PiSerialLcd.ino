/*
 * Raspberry Pi internet radio project
 * Arduino based front-end using usb serial communication, LCD and quad encoder
 *
 * @author Andrey Karpov <andy.karpov@gmail.com>
 * @copyright 2012 Andrey Karpov
 */
 
 #include <LiquidCrystal.h>
 #include <Encoder.h>
 #include <Button.h>
 #include <Led.h>
 
 LiquidCrystal lcd(12,11,10,9,8,7); 
 Encoder enc(2,3);
 Button btn(4, PULLUP);
 Led led_red(5);
 Led led_green(6);
 Led backlight(13);
 
 const int buf_len = 128; 
 char buf[buf_len];
 byte index = 0;
 char sep = ':';
 bool buffering = true;
 
 // custom LCD characters (bars)
 byte p1[8] = {
  0b10000,
  0b10000,
  0b10000,
  0b10000,
  0b10000,
  0b10000,
  0b10000,
  0b10000
};
 
byte p2[8] = {
  0b11000,
  0b11000,
  0b11000,
  0b11000,
  0b11000,
  0b11000,
  0b11000,
  0b11000
};
 
byte p3[8] = {
  0b11100,
  0b11100,
  0b11100,
  0b11100,
  0b11100,
  0b11100,
  0b11100,
  0b11100
};
 
byte p4[8] = {
  0b11110,
  0b11110,
  0b11110,
  0b11110,
  0b11110,
  0b11110,
  0b11110,
  0b11110
};
 
byte p5[8] = {
  0b11111,
  0b11111,
  0b11111,
  0b11111,
  0b11111,
  0b11111,
  0b11111,
  0b11111
};
 
 void setup() {
   Serial.begin(9600);
   Serial.flush();
   noInterrupts();
   lcd.createChar(1, p1);
   lcd.createChar(2, p2);
   lcd.createChar(3, p3);
   lcd.createChar(4, p4);
   lcd.createChar(5, p5);
   lcd.begin(16,2);
   lcd.clear();
   interrupts();
//   lcd.print("RpiSerialLCD");
   led_green.off();
   led_red.off();
   backlight.on();
 }
 
 void loop() {
   readLine();
   if (!buffering) {
     processInput();
     index = 0;
     buf[index] = '\0';
     buffering = true;
   }
 }
 
 void readLine() {
   if (Serial.available())  {
     while (Serial.available()) {
         char c = Serial.read();
         if (c == '\n' || c == '\r' || index >= buf_len) {
           buffering = false;
         } else {
           buffering = true;
           buf[index] = c;
           index++;
           buf[index] = '\0';
         }
     }
   }
 }
 
 void processInput() {
     String content = String(buf);
  
     //content.trim();
     int pos = content.indexOf(sep);
     if (content.length() == 0 || pos < 0) return;
  
     String cmd = content.substring(0, pos);
     String arg = content.substring(pos+1);
  
     if (cmd.compareTo("TEXT1") == 0) {
         lcd.setCursor(0,0);
         lcd.print(arg);
     } 
     
     if (cmd.compareTo("TEXT2") == 0) {
         lcd.setCursor(0,1);
         lcd.print(arg);
     }
     
     if (cmd.compareTo("BAR1") == 0) {
         int bar_int = stringToInt(arg);
         lcd.setCursor(0,0);
         printBar(bar_int);
     }

     if (cmd.compareTo("BAR2") == 0) {
         int bar_int = stringToInt(arg);
         lcd.setCursor(0,1);
         printBar(bar_int);
     }
     
     if (cmd.compareTo("GET_ENC") == 0) {
         Serial.print("ENCODER:");
         Serial.println(enc.read());
     } 
     
     if (cmd.compareTo("SET_ENC") == 0) {
         int32_t enc_int = stringToInt(arg);
         enc.write(enc_int);
     } 
     
     if (cmd.compareTo("LED_RED") == 0) {
         if (arg == "1") {
           led_red.on();
         } else {
           led_red.off();
         }
     } 
     
     if (cmd.compareTo("LED_GREEN") == 0) {
         if (arg == "1") {
           led_green.on();
         } else {
           led_green.off();
         }   
     }    
  
     if (cmd.compareTo("BACKLIGHT") == 0) {
         if (arg == "1") {
           backlight.on();
         } else {
           backlight.off();
         }
     }
     
     if (cmd.compareTo("BTN") == 0) {
       Serial.print("BUTTON:");
       Serial.println(btn.isPressed());
     }
     
     if (cmd.compareTo("READ") == 0) {
        Serial.print("VALUES:");
        Serial.print(enc.read());
        Serial.print(":");
        Serial.println((btn.isPressed()) ? "1" : "0");
     }
 }
 
 int stringToInt(String s) {
     char this_char[s.length() + 1];
     s.toCharArray(this_char, sizeof(this_char));
     int result = atoi(this_char);     
     return result;
 }
 
 void printBar(int percent) {
   
   double lenght = 16.0;
   double value = lenght/100*percent;
   unsigned int num_full;
   double value_half;
   unsigned int peace;
   
   // fill full parts of progress
   if (value>=1) {
    for (int i=1;i<value;i++) {
      lcd.write(5); 
      num_full=i;
    }
    value_half=value-num_full;
  }
  
  // fill partial part of progress
  peace=value_half*5;
  
  if (peace > 0 && peace <=5) { 
    lcd.write(peace);
  }
  
  // fill spaces
  for (int i =0;i<(lenght-num_full);i++) { 
    lcd.print(" ");
  }  
 }
