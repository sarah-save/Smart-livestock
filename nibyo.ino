#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
// WIFI & Server
const char* ssid = "ngoga";
const char* password = "12345678";
const char* serverUrl = "http://192.168.137.1/Fridaus";
// Pins
#define RST_PIN 27
#define SS_PIN 5
#define GREEN_LED 14
#define RED_LED 13
#define BUTTON_PIN  25// button to reset system
// OLED
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_RESET -1
#define SCREEN_ADDRESS 0x3C
// Objects
MFRC522 mfrc522(SS_PIN, RST_PIN);
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);
HTTPClient http;
// Variables
String currentTagId = "";
unsigned long lastScanTime = 0;
void setup() {
  Serial.begin(115200);
  Wire.begin(5,22);
  // LEDs & Button
  pinMode(GREEN_LED, OUTPUT);
  pinMode(RED_LED, OUTPUT);
  pinMode(BUTTON_PIN, INPUT_PULLUP); // button connected to GND
  digitalWrite(GREEN_LED, LOW);
  digitalWrite(RED_LED, LOW);
  // RFID
  SPI.begin(18,19,23);
  mfrc522.PCD_Init();
  // OLED
  display.begin(SSD1306_SWITCHCAPVCC, SCREEN_ADDRESS);
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0,0);
  display.println("Smart Livestock");
  display.println("Starting...");
  display.display();
  // WiFi
  WiFi.begin(ssid, password);
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    attempts++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    showMessage("WiFi Connected!", "Ready to scan");
  } else {
    showMessage("WiFi Failed", "Offline Mode");
  }
  delay(2000);
  showReadyScreen();
}
void loop() {
  // Button to reset
  if(digitalRead(BUTTON_PIN) == LOW) {
    resetSystem();
  }
  // RFID Scan
  if (mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {
    if (millis() - lastScanTime > 2000) { // 2s debounce
      lastScanTime = millis();
      currentTagId = getTagId();
      Serial.println("Scanned: " + currentTagId);
      handleScan();
    }
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
  }
  delay(50);
}
// Get RFID tag
String getTagId() {
  String tagId = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) tagId += "0";
    tagId += String(mfrc522.uid.uidByte[i], HEX);
  }
  tagId.toUpperCase();
  return tagId;
}
// Scan animal and show on OLED + LED
void handleScan() {
  displayMessage("Scanning...", currentTagId);
  if (WiFi.status() == WL_CONNECTED) {
    String url = String(serverUrl) + "/animal.php?esp32=animal&tagId=" + currentTagId;
    http.begin(url);
    int httpCode = http.GET();
    if (httpCode == 200) {
      String response = http.getString();
      DynamicJsonDocument doc(1024);
      deserializeJson(doc, response);
      String name = doc["name"] | "Unknown";
      bool isSick = doc["isSick"] | false;
      display.clearDisplay();
      display.setCursor(0,0);
      display.println("Animal: " + name);
      display.println("Tag: " + currentTagId);
      if (isSick) {
        display.println("STATUS: SICK");
        digitalWrite(GREEN_LED, LOW);
        digitalWrite(RED_LED, HIGH);
      } else {
        display.println("STATUS: HEALTHY");
        digitalWrite(RED_LED, LOW);
        digitalWrite(GREEN_LED, HIGH);
      }
      display.display();
    } else {
      displayMessage("Animal not found", currentTagId);
      digitalWrite(GREEN_LED, LOW);
      digitalWrite(RED_LED, HIGH);
    }
    http.end();
  }
  delay(2000);
  resetOutputs();
}
// Display helper
void displayMessage(String line1, String line2) {
  display.clearDisplay();
  display.setCursor(0,0);
  display.println(line1);
  display.println(line2);
  display.display();
}
// Ready screen
void showReadyScreen() {
  display.clearDisplay();
  display.setCursor(0,0);
  display.println("Smart Livestock");
  display.println("Ready to scan");
  display.display();
}
// Show temporary messages
void showMessage(String line1, String line2) {
  display.clearDisplay();
  display.setCursor(0,0);
  display.println(line1);
  display.println(line2);
  display.display();
  delay(1500);
}
// Reset LEDs & OLED
  void resetOutputs(){
  digitalWrite(GREEN_LED, LOW);
  digitalWrite(RED_LED, LOW);
  showReadyScreen();
}
// Reset system (button)
void resetSystem(){
  Serial.println("System Reset!");
  resetOutputs();
}
