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
 
// display configuration
#define ROWS 4 // number of rows
#define COLS 20 // number of columns

 LiquidCrystal lcd(12, 11, 10, 9, 8, 7); // lcd connected to D12, D11, D10, D9, D8, D7 
 Encoder enc(2, 3); // encoder pins A and B connected to D2 and D3 
 Button btn(4, PULLUP); // encoder's button connected to GND and D4
 Led led_red(5); // encoder's red led connected to GND and D5 via 470 Ohm resistor
 Led led_green(6); // encoder's green led connected to GND and D6 via 470 Ohm resistor
 Led backlight(13); // lcd backlight (lcd pins 15 and 16) connected to D13 and GND
 
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
 
 /**
  * Setup routines
  *
  * Perform Serial init, LCD init, set the initial states of leds and backlight
  *
  * @return void
  */
 void setup() {
   Serial.begin(9600);
   Serial.flush();
   noInterrupts();
   lcd.createChar(1, p1);
   lcd.createChar(2, p2);
   lcd.createChar(3, p3);
   lcd.createChar(4, p4);
   lcd.createChar(5, p5);
   lcd.begin(ROWS, COLS);
   lcd.clear();
   interrupts();
   lcd.print("Loading...");
   led_green.off();
   led_red.off();
   backlight.on();
 }
 
 /**
  * Main loop
  *
  * @return void
  */
 void loop() {
   readLine();
   if (!buffering) {
     processInput();
     index = 0;
     buf[index] = '\0';
     buffering = true;
   }
 }
 
 /**
  * Fill internal buffer with a single line from the serial port 
  *
  * @return void
  */
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
 
 /**
  * Routine to compare input line from the serial port and perform a response, if required
  *
  * @return void
  */
 void processInput() {
     String content = String(buf);
  
     int pos = content.indexOf(sep);
     if (content.length() == 0 || pos < 0) return;
  
     String cmd = content.substring(0, pos);
     String arg = content.substring(pos+1);

     // command CMD: will answer DISPLAY:X:Y, where X is number of columns and Y is number of rows  
     if (cmd.compareTo("CFG") == 0) {
         Serial.print("DISPLAY:");
         Serial.print(COLS);
         Serial.print(":");
         Serial.println(ROWS);
     }

     // command TEXT1:<some text> will print text on the first line of the lcd
     if (cmd.compareTo("TEXT1") == 0) {
         lcd.setCursor(0,0);
         lcd.print(arg);
     } 

     // command TEXT2:<some text> will print text on the second line of the lcd, if allowed    
     if (cmd.compareTo("TEXT2") == 0 && COLS >=2) {
         lcd.setCursor(0,1);
         lcd.print(arg);
     }

     // command TEXT3:<some text> will print text on the third line of the lcd, if allowed
     if (cmd.compareTo("TEXT3") == 0 && COLS >=3) {
         lcd.setCursor(0,2);
         lcd.print(arg);
     }

     // command TEXT4:<some text> will print text on the fourth line of the lcd, if allowed
     if (cmd.compareTo("TEXT4") == 0 && COLS >=4) {
         lcd.setCursor(0,3);
         lcd.print(arg);
     }
     
     // command BAR1:<integer between 0 and 100> will draw a progress bar on the first line of the lcd, if allowed
     if (cmd.compareTo("BAR1") == 0) {
         int bar_int = stringToInt(arg);
         lcd.setCursor(0,0);
         printBar(bar_int);
     }

     // command BAR2:<integer between 0 and 100> will draw a progress bar on the second line of the lcd, if allowed
     if (cmd.compareTo("BAR2") == 0  && COLS >=2) {
         int bar_int = stringToInt(arg);
         lcd.setCursor(0,1);
         printBar(bar_int);
     }

     // command BAR3:<integer between 0 and 100> will draw a progress bar on the third line of the lcd, if allowed
     if (cmd.compareTo("BAR3") == 0  && COLS >=3) {
         int bar_int = stringToInt(arg);
         lcd.setCursor(0,2);
         printBar(bar_int);
     }

     // command BAR4:<integer between 0 and 100> will draw a progress bar on the fourth line of the lcd, if allowed
     if (cmd.compareTo("BAR4") == 0  && COLS >=4) {
         int bar_int = stringToInt(arg);
         lcd.setCursor(0,3);
         printBar(bar_int);
     }
     
     // command GET_ENC: will return ENCODER:X, where X is the actual value of the encoder
     if (cmd.compareTo("GET_ENC") == 0) {
         Serial.print("ENCODER:");
         Serial.println(enc.read());
     } 

     // command SET_ENC:<some integer> will set internal encoder value to the specified one     
     if (cmd.compareTo("SET_ENC") == 0) {
         int32_t enc_int = stringToInt(arg);
         enc.write(enc_int);
     } 
     
     // command LED_RED:X will swith on a red led if X=1, otherwise switch off 
     if (cmd.compareTo("LED_RED") == 0) {
         if (arg == "1") {
           led_red.on();
         } else {
           led_red.off();
         }
     } 

     // command LED_GREEN:X will swith on a green led if X=1, otherwise switch off      
     if (cmd.compareTo("LED_GREEN") == 0) {
         if (arg == "1") {
           led_green.on();
         } else {
           led_green.off();
         }   
     }    
  
     // command BACKLIGHT:X will swith on lcd backlight if X=1, otherwise switch off 
     if (cmd.compareTo("BACKLIGHT") == 0) {
         if (arg == "1") {
           backlight.on();
         } else {
           backlight.off();
         }
     }

     // command BTN: will return a button state with response BUTTON:X, where X=1 if button is pressed, X=0 - otherwise     
     if (cmd.compareTo("BTN") == 0) {
       Serial.print("BUTTON:");
       Serial.println(btn.isPressed() ? "1" : "0");
     }
     
     // command READ: will return a response VALUES:X:Y, where X is an encoder value, and Y is a button state
     if (cmd.compareTo("READ") == 0) {
        Serial.print("VALUES:");
        Serial.print(enc.read());
        Serial.print(":");
        Serial.println((btn.isPressed()) ? "1" : "0");
     }
 }
 
 /**
  * Conver string object into signed integer value
  *
  * @param String s
  * @return int
  */
 int stringToInt(String s) {
     char this_char[s.length() + 1];
     s.toCharArray(this_char, sizeof(this_char));
     int result = atoi(this_char);     
     return result;
 }
 
 /** 
  * Pring a progress bar on the current cursor position
  *
  * @param int percent
  */
 void printBar(int percent) {
   
   double lenght = COLS + 0.0;
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
