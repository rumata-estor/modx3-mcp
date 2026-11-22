#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Изолированная проверка HTTP без подключения к рабочим сайтам.
# EN: Isolated HTTP verification without connecting to production sites.
"""Local HTTP integration of real endpoint/api.common.php with mock MODX 2/3 bootstrap."""
import json
import os
from pathlib import Path
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

REPO = Path(__file__).resolve().parent.parent
ENDPOINT = REPO / "core/components/modxmcp/endpoint/api.common.php"

def execute(variant):
    with tempfile.TemporaryDirectory(prefix="modxmcp-http-redaction-") as temp:
        root=Path(temp)
        model=root/"core/components/modxmcp/model"
        model.mkdir(parents=True)
        (model/"modxmcp.class.php").write_text("""<?php
class modxMCP {
 public function __construct($modx) {}
 public function processRequest($action,$type,$data) {
   if ($action === 'client_error') throw new ModxMCPClientException('password=FAKE_SECRET_CLIENT');
   if ($action === 'exception') throw new Exception('Authorization: Bearer FAKE_SECRET_EXCEPTION');
   if ($action === 'throwable') throw new Error('credentials=FAKE_SECRET_THROWABLE');
   return array('status'=>'ok');
 }
}
""")
        bootstrap=root/"bootstrap.php"
        php_class="FakeModx2" if variant==2 else "FakeModx3"
        bootstrap.write_text("""<?php
class FakeModx2 {
 public function setLogLevel($level) {}
 public function getOption($key,$unused=null,$default=null) {
   if ($key==='core_path') return __DIR__.'/core/';
   if ($key==='modxmcp.core_path') return __DIR__.'/core/components/modxmcp/';
   if ($key==='modxmcp.enabled') return true;
   if ($key==='modxmcp.require_https') return false;
   if ($key==='modxmcp.api_token') return 'TEST_ONLY_HTTP_TOKEN';
   if ($key==='modxmcp.debug') return true;
   return $default;
 }
 public function log($level,$message) {
   file_put_contents(__DIR__.'/modx-error.log', $message."\\n",FILE_APPEND);
 }
}
class FakeModx3 extends FakeModx2 {}
$modx = new """+php_class+"""();
$modxmcpVariant = 'modx"""+str(variant)+"""';
require '"""+str(ENDPOINT).replace("'", "\\'")+"""';
""")
        with socket.socket() as sock:
            sock.bind(("127.0.0.1",0))
            port=sock.getsockname()[1]
        process=subprocess.Popen(["php","-S",f"127.0.0.1:{port}","-t",str(root)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        try:
            for attempt in range(30):
                try:
                    urllib.request.urlopen(f"http://127.0.0.1:{port}/bootstrap.php",timeout=1)
                    break
                except Exception:
                    time.sleep(.1)
            def request(action,token="TEST_ONLY_HTTP_TOKEN"):
                req=urllib.request.Request(f"http://127.0.0.1:{port}/bootstrap.php",
                    data=json.dumps({"action":action,"type":"test","data":{}}).encode(),
                    headers={"Content-Type":"application/json","X-MCP-Token":token})
                try:
                    with urllib.request.urlopen(req,timeout=3) as resp:
                        return resp.status,json.loads(resp.read())
                except urllib.error.HTTPError as err:
                    return err.code,json.loads(err.read())
            for action,expected in [("client_error",400),("exception",500),("throwable",500),("healthy",200)]:
                code,data=request(action)
                assert code==expected,(variant,action,code,data)
                assert "FAKE_SECRET_" not in json.dumps(data),(variant,action,"secret in response")
                if code==500:
                    assert data.get("error_id") and "details" not in data,(variant,action,data)
                if code==200:
                    assert data.get("data",{}).get("status")=="ok"
            code,data=request("healthy","wrong")
            assert code==401 and data.get("error")=="Unauthorized",(variant,"authorization",code,data)
            logs=root/"modx-error.log"
            assert logs.exists(),(variant,"missing diagnostics")
            logdata=logs.read_text()
            assert "FAKE_SECRET_" not in logdata,(variant,"secret in log")
            assert "exception_class=" in logdata and "FAKE_SECRET_" not in logdata,(variant,"unsafe exception log")
            print(f"SECRET_REDACTION_HTTP_INTEGRATION_MODX{variant}_OK")
        finally:
            process.terminate()
            try: process.wait(timeout=3)
            except subprocess.TimeoutExpired: process.kill()

for version in (2,3):
    execute(version)
