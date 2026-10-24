# Watering valve

A tile for a **Sonoff SWV** watering valve paired in Zigbee2MQTT. **One tile = one equipment of the plugin**,
placed on the object of your choice.

Shows: state, open / close, duration, “Water N min” button, watered time and volume, battery, fault, **schedule**.

## Create a tile

1. Plugins › Nature › Vanne d'arrosage › **Add**, give it a name.
2. Choose the **parent object**, tick **Enable** and **Visible**.
3. Choose the **source equipment** (the valve in Zigbee2MQTT) and the settings offered.
4. Save. The tile shows on its object's dashboard.

## Settings

- Durations offered, “Water N min” button (0 = none), flow meter reading.
- Plugin configuration: Zigbee2MQTT base topic (`zigbee2mqtt` by default), for the flow meter.

## How it works

- **Open** opens the valve for the set duration; it closes by itself.
- **Schedule**: every hour within a range, every day or some days, for a duration, during a period.
  The schedule is kept in the equipment and run by the plugin every minute (nothing to create). It does not restart a valve that is already open.
- **Read the flow meter**: the Sonoff SWV does not push the watered time and volume. The plugin asks Zigbee2MQTT for them every minute while
  watering, then once after (MQTT Manager plugin required).
- Firmware 1.0.7: Zigbee2MQTT reads the time and volume **byte-reversed** (1 min → 16777216). Fix to put in the *calculation* of these
  commands, in the Zigbee2MQTT equipment:
  `min(#value#, (#value# % 256) * 16777216 + floor(#value# / 256) % 256 * 65536 + floor(#value# / 65536) % 256 * 256 + floor(#value# / 16777216))`
- Valve values update live. The plugin creates no command: the tile reads the Zigbee2MQTT equipment.

## Double check (opening and above all closing)

- Open / Close from the tile and the schedule go through the plugin, which records the order sent. The next minute, it checks the state
  reported by the valve: order not followed → sent again (3 sends in all), then an **alert** (on the tile, in the message center and the log).
  For a closing, the plugin then keeps trying every minute.
- **Safety closing**: a valve open longer than the set duration + 2 min (60 min if the duration is unknown) is closed by the plugin,
  whatever opened it (valve button, another scenario, Zigbee2MQTT).
- The check relies on the state reported by the valve: an out-of-range valve that keeps its last known state "open" triggers
  the resends then the alert.

## Known limits

- The schedule runs at the exact minute: if Jeedom's cron skips that minute (heavily loaded machine), the watering is lost.
- “Next watering” uses the browser's time, the schedule uses Jeedom's time: both must be in the same time zone.
