Anomalies
=========

Sample `anomalies.ini`:

```ini
[Modem frozen for a week]
host = "modem-*"
service = "Antenna gain"
filter = "stdev_value>-0.001&stdev_value<0.001&max_value<113&min_value>0"
start = "-7 days"
; end = "now" (default)
```
