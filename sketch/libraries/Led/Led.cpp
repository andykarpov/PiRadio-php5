/**
 * @file Led.cpp
 * @version 1.0
 * @author Andrey Karpov <andy.karpov@gmail.com>
 */

//include the class definition
#include "Led.h"

/**
 * <<constructor>>
 * @parameter ledPin sets the pin that this led anode is connected to
 */
Led::Led(uint8_t ledPin) {
    this->pin = ledPin;
    pinMode(pin,OUTPUT);
    digitalWrite(pin, LOW);
    state = LOW;
}

/**
 * Set led ON
 */
void Led::on(void) {
    state = HIGH;
    digitalWrite(pin, HIGH);
}

/**
 * Set pin LOW as default
 */
void Led::off(void) {
    state = LOW;
    digitalWrite(pin, LOW);
}

void Led::toggle(void) {
    state = (state == HIGH) ? LOW : HIGH;
    digitalWrite(pin, state);
}

uint8_t Led::getState() {
    return state;
}