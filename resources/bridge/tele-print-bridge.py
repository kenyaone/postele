#!/usr/bin/env python3
# Tele Cafe print bridge: run "python3 tele-print-bridge.py" on a computer or phone on the cafe WiFi.
import base64, json, socket, time, urllib.request

API = '__API__'
HEAD = {'Authorization': 'Bearer __TOKEN__', 'Accept': 'application/json', 'Content-Type': 'application/json'}

def call(method, path, body=None):
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(API + path, data=data, headers=HEAD, method=method)
    with urllib.request.urlopen(req, timeout=20) as r:
        return json.loads(r.read() or b'{}')

print('Tele Cafe print bridge is running. Leave this open.')
while True:
    try:
        for j in call('GET', '/jobs/pending').get('jobs', []):
            if not j.get('ip'):
                continue
            status, err = 'sent', None
            try:
                with socket.create_connection((j['ip'], int(j.get('port') or 9100)), timeout=4) as s:
                    s.sendall(base64.b64decode(j['payload_b64']))
                print(time.strftime('%H:%M:%S'), ' printed #%s on %s' % (j['id'], j.get('printer_name')))
            except Exception as e:
                status, err = 'failed_attempt', str(e)
                print(time.strftime('%H:%M:%S'), ' #%s FAILED: %s' % (j['id'], err))
            call('PATCH', '/jobs/%s' % j['id'], {'status': status, 'error': err})
    except Exception as e:
        print(time.strftime('%H:%M:%S'), ' cannot reach the POS:', e)
        time.sleep(5)
    time.sleep(2)
