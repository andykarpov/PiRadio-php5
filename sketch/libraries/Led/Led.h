/**
 * @file Led.h
 * @version 1.0
 * @author Andrey Karpov <andy.karpov@gmail.com>
 */

#ifndef LED_H
#define LED_H

#include "Arduino.h"

class Led {
  public:
    Led(uint8_t ledPin);
    void on();
    void off();
    void toggle();
    uint8_t getState();
  private: 
    uint8_t pin;
    uint8_t state;
};

#endif
