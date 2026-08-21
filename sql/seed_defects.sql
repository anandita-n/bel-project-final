
-- Sample defects for a few existing projects, for demo/testing of the Defects tab.
INSERT INTO defects (project_id, code, title, description, severity, status, assigned_to, reported_by, created_at, updated_at) VALUES
(1, 'DEF-101', 'Radar returns intermittent ghost targets at long range', 'False-positive detections appear sporadically above 40km range during live tracking.', 'critical', 'open', 10, 2, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 'DEF-102', 'Signal processing latency spikes under high clutter', 'Processing delay exceeds 200ms threshold when clutter density is high.', 'major', 'in_progress', 14, 2, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, 'DEF-103', 'Calibration screen labels overlap on smaller displays', 'Minor cosmetic issue on the calibration UI at 1024x768.', 'minor', 'resolved', 37, 2, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 'DEF-104', 'Sonar signal drops during integration testing', 'Intermittent signal loss observed during the joint integration test with the naval platform.', 'critical', 'open', 6, 2, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'DEF-105', 'Incorrect target distance displayed', 'Distance readout is off by roughly 3% compared to the reference system.', 'major', 'in_progress', 10, 2, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'DEF-106', 'UI freezes when loading large dataset', 'Application becomes unresponsive for ~10s when opening logs over 500MB.', 'minor', 'resolved', 9, 2, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'DEF-107', 'Avionics display flickers during power transitions', 'Brief flicker observed when switching between primary and backup power.', 'major', 'open', 8, 4, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'DEF-108', 'Altitude readout not updating in simulator mode', 'Value freezes after entering simulator test mode until app restart.', 'critical', 'in_progress', 16, 4, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));
