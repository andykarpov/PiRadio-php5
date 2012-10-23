PiRadio
=======

Raspberry pi based internet radio with Arduino (LCD + encoder) as USB serial front-end


**Preparing SD card for raspbian (on OSX)**

1. `diskutil list`
2. choose a right disk id, like /dev/disk1
3. `diskutil unmountDisk /dev/disk1`
4. `sudo dd if=2012-09-18-wheeze-raspbian.img of=/dev/rdisk1 bs=1m`
5. `diskutil eject /dev/disk1`

**Preparing Arduino (on OSX)**

1. connect LCD, encoder regarding schematic or fritzing diagram
2. `git clone git://github.com/andykarpov/PiRadio.git`
3. copy sketch/libraries to your arduino libraries folder (like /Users/<user>/Documents/Arduino/libraries)
4. upload sketch/PiSerialLcd/PiSerialLcd.ino
5. connect arduino via USB to the raspberry pi

**Booting Raspberry Pi**

1. configure by setup wizard - enable ssh, over clock, resize root partition, set locale, timezone, etc… then reboot
2. `ssh pi@<your-pi-ip-address>`
3. `sudo apt-get update`
4. `sudo apt-get dist-upgrade`
5. `sudo apt-get install php5-cli git mpd mpc`
6. `sudo reboot`
7. `ssh pi@<your-pi-ip-address>`
8. `cd`
9. `git clone git://github.com/andykarpov/PiRadio.git`
10. `sudo ln -s /home/pi/PiRadio/init.d/pi-radio /etc/init.d/pi-radio`
11. `cd /home/pi/PiRadio/`
12. `sudo chmod -R a+rwx state/ playlist/`
13. `cd /home/pi/PiRadio/init.d/`
14. `sudo chmod a+x pi-radio`
15. `sudo /etc/init.d/pi-radio start`
16. `sudo update-rc.d pi-radio defaults`
17. `sudo reboot` - should start internet radio automatically on boot


**BOM**

1. Raspberry Pi + case (https://www.modmypi.com/shop/raspberry-pi/raspberry-pi-and-modmypi-case) $58
2. Arduino Nano (http://imall.iteadstudio.com/development-platform/arduino/arduino-compatible-mainboard/im120411003.html) $15
3. 16x2 LCD (http://imall.iteadstudio.com/display/character-lcm/im120424015.html) $5.50
4. Rotary encoder illuminated (https://www.sparkfun.com/products/10596) $2.95
5. 2x470 Ohm resistors (ebay), around $0.1 for 10 pcs
6. 10k trimpot (https://www.sparkfun.com/products/9806) $0.95
7. Breadboard (http://imall.iteadstudio.com/prototyping/breadboard/im120530016.html) $8.50
8. male breadboard jumper wires (http://imall.iteadstudio.com/prototyping/cable-and-wires/im120530005.html) $5
9. Ethernet cable (http://imall.iteadstudio.com/prototyping/cable-and-wires/im120813001.html) $1.50
10. Usb-A to Usb-mini cable (http://imall.iteadstudio.com/prototyping/cable-and-wires/im120530007.html) $0.80
11. Usb-A to Usb-micro cable (ebay), around $1
12. Active speakers (http://hard.rozetka.com.ua/ru/products/details/35203/index.html or similar) $11
13. 5V power supply (https://www.sparkfun.com/products/11456 or similar) $3.95

*Total: around $100*


**TODO**

1. Buy WiFi adapter and set-up wireless network
2. Replace USB speakers with home-brew amplifier + speakers
3. Put it all into the semi-transparent laser-cutted acrylic case (ponoko.com)
4. Buy a big knob for encoder
5. Write a web interface to manage playlists and control the radio via web-browser

