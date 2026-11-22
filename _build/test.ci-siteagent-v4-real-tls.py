#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Закрытый E2E Controller v4 и MODX через TLS на одноразовом CI.
# EN: Private Controller v4 to disposable MODX TLS end-to-end fixture.
"""Disposable full Controller -> Executor -> HTTPS -> installed MODX MCP v4.

Only GitHub Actions' one-use MODX2/3 databases are eligible. The Controller
uses a simulated, explicitly trusted human Relay and a disposable key; no
real Telegram consent, real Site Agent process, production secrets or sites.
All BUSINESS resource operations are sent exclusively through installed
MODX MCP's HTTP endpoint; MODX Manager is used only to configure the CI
fixture's security settings, not for content mutations.

TLS frontend is a real localhost HTTPS socket terminating at Python; its
trusted loopback reverse-proxy path forwards to the actual PHP MODX endpoint.
No bypass of authentication/CAS or privileged business SQL is implemented.
"""
from __future__ import annotations

import base64
from contextlib import ExitStack
from http.client import HTTPConnection
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import hashlib
import json
import os
from pathlib import Path
import secrets
import socket
import ssl
import subprocess
import sys
import tempfile
from threading import Thread
import time
from unittest.mock import patch
from urllib import error,request

CI_ROOT = Path(__file__).resolve().parents[1]
RUNNER_TEMP = Path(os.environ.get("RUNNER_TEMP",""))
CORE_CONFIG = Path(os.environ.get("MODX_CONFIG_CORE",""))
DOC_ROOT = Path(os.environ.get("MODX_DOCUMENT_ROOT",""))
SITEAGENT_ROOT = Path(os.environ.get("SITEAGENT_V4_ROOT",""))
PLATFORM = os.environ.get("BUILD_PLATFORM")
# Fail closed even with python -O: assert statements are stripped under optimization.
# Only disposable GitHub runner fixtures are permitted; no production endpoints.
if not (
    os.environ.get("MODXMCP_DISPOSABLE_CI") == "1"
    and PLATFORM in ("modx2", "modx3")
    and str(CORE_CONFIG).startswith("/home/runner/") and CORE_CONFIG.is_file()
    and str(RUNNER_TEMP).startswith("/home/runner/") and RUNNER_TEMP.is_dir()
    and str(DOC_ROOT).startswith("/home/runner/") and DOC_ROOT.is_dir()
    and str(SITEAGENT_ROOT).startswith("/home/runner/") and SITEAGENT_ROOT.is_dir()
    and (DOC_ROOT / "assets/components/modxmcp/api.php").is_file()
):
    raise SystemExit("Refusing Controller v4 E2E outside disposable CI.")
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey

sys.path.insert(0, str(SITEAGENT_ROOT / "runtime"))

from siteagent.task_control import ControlStore, ControlError
from siteagent import modxmcp_executor_v4 as executor_v4
from siteagent.modxmcp_executor_v4 import http_read, http_send
from siteagent.remote_executor import RemoteExecutionError

SITE = "modx2-alex-palochkin" if PLATFORM == "modx2" else "modx3-alex-palochkin"
ENDPOINT = "/assets/components/modxmcp/api.php"
PREFIX = "CI V4 TLS " + secrets.token_hex(4)
PLATFORM_TOOL = CI_ROOT / "_build/test.ci-ed25519-controller.cjs"
SETTINGS = CI_ROOT / "_build/test.ci-protected-http-settings.php"
PROXY_SETTINGS = CI_ROOT / "_build/test.ci-protected-v4-proxy-settings.php"
checks = 0
requests_sent = []
server_events = []
proxy_drop_after_update = False


def check(value:bool,label:str) -> None:
    global checks
    if not value:
        raise AssertionError("CONTROLLER_V4_REAL_MODX_TLS_FAILURE " + label)
    checks += 1


def run(command:list[str],input_data:str|None=None,timeout:int=35) -> str:
    p=subprocess.run(command,input=input_data,text=True,capture_output=True,
                     timeout=timeout,check=False)
    if p.returncode:
        raise RuntimeError(
            "Disposable fixture command failed (exit=" + str(p.returncode)
            + ") " + p.stderr[-300:]
        )
    return p.stdout.strip()


def json_bytes(obj) -> bytes:
    return json.dumps(obj,separators=(",",":"),ensure_ascii=False).encode("utf-8")


def direct_raw_request(port:int,raw:bytes) -> tuple[int,bytes]:
    http=HTTPConnection("127.0.0.1",port,timeout=10)
    try:
        http.request("POST",ENDPOINT,body=raw,headers={
            "Content-Type":"application/json; charset=utf-8"})
        result=http.getresponse()
        return result.status,result.read()
    finally:
        http.close()


def authorize_ci_create_or_delete(key_file:Path,action:str,body:bytes) -> dict:
    # A privileged one-use fixture sets up/cleans up a test resource. The v4
    # mutation itself is NEVER signed here: it must come from human-reviewed
    # Controller v4 code.
    data=json.loads(body)
    scope=data["type"] or data["data"].get("type","none")
    signed=run([
        "node",str(PLATFORM_TOOL),"sign",str(key_file),
        SITE,action,scope,
    ],body.decode("utf-8"))
    permit=json.loads(signed)
    check(permit["claims"]["site_id"]==SITE,"CI fixture bound to test site")
    return permit


def new_controller(root:Path,signing_key:Path):
    uid=os.geteuid()
    agent=uid+5101
    relay=uid+5102
    exec_uid=uid+5103
    policy={"sites":{SITE:{
        "audience":"ci-protected",
        "contours":["test"],
        "ed25519_allowed_actions":["update_element"],
        "actors":["ci-reviewer"],
        "channels":[{"channel":"telegram",
                     "conversation_id":"ci-chat","thread_id":"test-17"}],
    }}}
    store=ControlStore(root,policy,
                       agent_uid=agent,relay_uid=relay,executor_uid=exec_uid,
                       ed25519_key_paths={SITE:signing_key},
                       _test_allow_ed25519_v1=True)
    return store,agent,relay,exec_uid


def consent(site_store:ControlStore,agent:int,relay:int,exec_uid:int,
            task_id:str,snapshot:dict[str,object],next_content:str,
            event_offset:int=0):
    """Simulate exact human-delivered, independently approved review."""
    def q(action:str,**fields):
        return {"action":action,"site_id":SITE,"task_id":task_id,**fields}
    def person(n:int):
        return {"actor_id":"ci-reviewer","channel":"telegram",
                "conversation_id":"ci-chat","thread_id":"test-17",
                "message_id":str(n+event_offset)}
    recorded=site_store.handle(exec_uid,q(
        "register_snapshot_v4",snapshot=snapshot))
    check(recorded["snapshot_kind"]=="modxmcp-v4",
          "Executor registered real MCP read snapshot")
    proposed=site_store.handle(agent,q(
        "propose",protocol_version=4,contour="test",
        summary="Тестовый скрытый ресурс: утверждённое изменение содержимого",
        new_content=next_content))
    check(proposed["review_protocol_version"]==4,"Controller froze reviewed plan")
    reviewed=site_store.handle(relay,q(
        "review_operation",operation_index=0,review_page=1,human=person(101)))
    pages=int(reviewed["review_pages"])
    check(pages>=1,"human review has pages")
    try:
        site_store.handle(relay,q(
            "approve",plan_digest=proposed["plan_digest"],human=person(103)))
    except ControlError:
        checks_increment()  # meaningful negative test in one place
    else:
        raise AssertionError("Controller approved undisplayed plan")
    for page_num in range(1,pages+1):
        display=site_store.handle(relay,q(
            "review_operation",operation_index=0,
            review_page=page_num,human=person(101)))
        ack=site_store.handle(relay,q(
            "review_delivered",plan_digest=proposed["plan_digest"],
            operation_index=0,review_page=page_num,
            review_page_digest=display["review_page_digest"],
            human=person(102)))
        check(ack["delivery_recorded"] is True,"verified review receipt")
    approved=site_store.handle(relay,q(
        "approve",plan_digest=proposed["plan_digest"],human=person(103)))
    check(approved["state"]=="approved_blocked",
          "approved Controller task blocked until one-time permit")
    return q


def checks_increment():
    global checks
    checks+=1


class TrustedTLSProxy(BaseHTTPRequestHandler):
    protocol_version="HTTP/1.1"
    php_port=0

    def do_GET(self):
        self._forward()
    def do_POST(self):
        self._forward()
    def _forward(self):
        global proxy_drop_after_update
        if self.path!=ENDPOINT:
            self.send_error(404)
            return
        try:
            length=int(self.headers.get("Content-Length","0"))
        except ValueError:
            self.send_error(400);return
        if length<0 or length>1048576:
            self.send_error(413);return
        body=self.rfile.read(length) if length else b""
        action=None
        if body:
            try:action=json.loads(body).get("action")
            except (ValueError,UnicodeError):pass
        # Drop every client supplied proxy-identity header. The trusted
        # local TLS terminator sets one constant instead. PHP verifies the
        # socket peer 127.0.0.1 against the own server allowlist.
        headers={
            "Content-Type":self.headers.get(
                "Content-Type","application/json; charset=utf-8"),
            "X-Forwarded-Proto":"https",
        }
        for label in ("X-MCP-Token","X-ModxMCP-Permit"):
            value=self.headers.get(label)
            if value is not None:headers[label]=value
        upstream=HTTPConnection("127.0.0.1",self.php_port,timeout=15)
        try:
            upstream.request(self.command,ENDPOINT,body=body,headers=headers)
            backend=upstream.getresponse()
            status=backend.status
            response=backend.read(1048577)
        finally:
            upstream.close()
        server_events.append((action,status))
        # An adversarial lost response: MODX has already committed the
        # signed update but the HTTPS client never gets an acknowledgement.
        if action=="update_element" and proxy_drop_after_update:
            proxy_drop_after_update=False
            self.close_connection=True
            try:self.connection.shutdown(socket.SHUT_RDWR)
            except (OSError,ValueError):pass
            self.connection.close()
            return
        self.send_response(status)
        self.send_header("Content-Type","application/json; charset=utf-8")
        self.send_header("Content-Length",str(len(response)))
        self.end_headers()
        try:self.wfile.write(response)
        except OSError:pass

    def log_message(self,fmt,*args):
        # Never print request headers / tokens / permits to CI logs.
        pass


def wait_for_php(proc,port:int):
    for _ in range(90):
        if proc.poll() is not None:
            raise RuntimeError("Disposable PHP server terminated")
        try:
            status,_=direct_raw_request(port,b'{}')
            if status==403:
                return
        except (OSError,TimeoutError):
            time.sleep(.15)
    raise RuntimeError("PHP server did not start or HTTPS gate disabled")


def unix_port() -> int:
    with socket.socket(socket.AF_INET,socket.SOCK_STREAM) as s:
        s.bind(("127.0.0.1",0))
        return int(s.getsockname()[1])


def endpoint_state(url,token,read_set=None):
    response=http_read(url,token,"get_site_state",
                       {"read_set":read_set} if read_set is not None else {})
    state=response["data"]
    check(state.get("cas_required") is True,"installed MODX MCP strict CAS advertised")
    return state


def get_resource(url,token,id_:int):
    return http_read(url,token,"get_element",
                     {"type":"resource","id":id_})["data"]


def setup_resource(url,token,signing_key:Path,name:str):
    revision=int(endpoint_state(url,token)["site_revision"])
    alias="ci-v4-tls-"+secrets.token_hex(7)
    data={
        "type":"resource","name":name,"content":"ORIGINAL",
        "alias":alias,"template":0,"parent":0,"published":0,"hidemenu":1,
        "_runtime_preconditions":{
            "observed_site_revision":revision,
            "strict_site_revision":True,"read_set":[],
        },
    }
    raw=json_bytes({"action":"create_element","type":"resource","data":data})
    permit=authorize_ci_create_or_delete(signing_key,"create_element",raw)
    result=http_send(url,token,raw,permit)
    rid=int(result["data"]["id"])
    check(rid>0,"MCP created disposable hidden resource")
    observed=get_resource(url,token,rid)
    check(int(observed["published"])==0 and int(observed["hidemenu"])==1,
          "CI setup resource is actually unpublished and hidden")
    check(observed["content"]=="ORIGINAL","CI setup resource readback")
    return rid


def snapshot_of(url,token,rid:int):
    state=endpoint_state(url,token,[
        {"role":"target","type":"resource","id":rid}
    ])
    row=get_resource(url,token,rid)
    check(len(state["read_set"])==1
          and len(state["read_set"][0]["fingerprint"])==64,
          "server-observed full target fingerprint")
    return {
        "site_revision":int(state["site_revision"]),
        "cas_required":True,
        "read_set":state["read_set"],
        "resource_id":rid,
        "pagetitle":str(row["pagetitle"]),
        "content":str(row["content"]),
        "published":int(row["published"]),
        "hidemenu":int(row["hidemenu"]),
    }


def cleanup_resource(url,token,signing_key,rid):
    observation=endpoint_state(url,token,[
        {"role":"target","type":"resource","id":rid}
    ])
    data={"type":"resource","id":rid,"dry_run":False,
          "_runtime_preconditions":{
              "observed_site_revision":int(observation["site_revision"]),
              "strict_site_revision":True,
              "read_set":observation["read_set"],
          }}
    raw=json_bytes({"action":"delete_element","type":"resource","data":data})
    permit=authorize_ci_create_or_delete(signing_key,"delete_element",raw)
    result=http_send(url,token,raw,permit)
    check(result["success"] is True,"disposable resource soft-deleted through MCP")
    check(bool(get_resource(url,token,rid)["deleted"]),
          "MCP readback confirms resource deleted")


def main():
    global proxy_drop_after_update
    # Ephemeral private files outside CI MODX docroot; never export secrets.
    with tempfile.TemporaryDirectory(
        prefix="controller-v4-real-modx-tls-",dir=str(Path.home())
    ) as temp_dir:
        home=Path(temp_dir)
        home.chmod(0o700)
        tls_key=home/"tls-frontend.key"
        tls_cert=home/"tls-frontend.pem"
        key_dir=home/"controller-private"
        key_dir.mkdir(mode=0o700)
        key_dir.chmod(0o700)
        controller_key=key_dir/"issuer.pem"
        private=Ed25519PrivateKey.generate()
        controller_key.write_bytes(private.private_bytes(
            serialization.Encoding.PEM,
            serialization.PrivateFormat.PKCS8,
            serialization.NoEncryption()
        ))
        controller_key.chmod(0o600)
        public=private.public_key().public_bytes(
            serialization.Encoding.Raw, serialization.PublicFormat.Raw
        ).hex()
        public_file=home/"controller-public.hex"
        public_file.write_text(public)
        public_file.chmod(0o600)
        token_file=home/"modxmcp-token"
        proc=subprocess.run([
            "openssl","req","-x509","-newkey","rsa:2048",
            "-keyout",str(tls_key),"-out",str(tls_cert),
            "-days","1","-nodes","-subj","/CN=localhost",
            "-addext","subjectAltName=DNS:localhost",
        ],capture_output=True,timeout=40,check=False)
        check(proc.returncode==0,"ephemeral real TLS key and cert")
        tls_key.chmod(0o600)

        logpath=home/"ci-php-http.log"
        server_process=None
        server_log=None
        proxy_server=None
        proxy_thread=None
        prior_ca=os.environ.get("SSL_CERT_FILE")
        configured=False
        fixture_enabled=False
        rid_created=[]
        url=None
        token=None
        try:
            run(["php",str(SETTINGS),"enable",
                 str(public_file),str(token_file)])
            fixture_enabled=True
            token=token_file.read_text().strip()
            check(len(token)==64 and token.isascii(),
                  "existing disposable MODX API token preserved")
            run(["php",str(PROXY_SETTINGS),"enable",SITE])
            configured=True
            php_port=unix_port()
            server_log=logpath.open("wb")
            server_process=subprocess.Popen([
                "php","-S","127.0.0.1:"+str(php_port),
                "-t",str(DOC_ROOT)
            ],cwd=str(DOC_ROOT),
                stdout=server_log,stderr=subprocess.STDOUT,
                env={**os.environ,"MODX_CONFIG_CORE":str(CORE_CONFIG),
                     "MODX_DOCUMENT_ROOT":str(DOC_ROOT)})
            wait_for_php(server_process,php_port)
            direct_status,_=direct_raw_request(
                php_port,json_bytes({
                    "action":"get_site_state","type":"","data":{}
                })
            )
            check(direct_status==403,
                  "plain HTTP rejected even with protected proxy installed")

            # The real client connects via verified TLS. The loopback
            # terminator alone sets X-Forwarded-Proto; MODX authorizes this
            # because REMOTE_ADDR is the configured trusted 127.0.0.1.
            class CIProxy(TrustedTLSProxy):
                pass
            CIProxy.php_port=php_port
            proxy_server=ThreadingHTTPServer(("127.0.0.1",0),CIProxy)
            context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
            context.load_cert_chain(str(tls_cert),str(tls_key))
            proxy_server.socket=context.wrap_socket(
                proxy_server.socket,server_side=True
            )
            proxy_thread=Thread(target=proxy_server.serve_forever,daemon=True)
            proxy_thread.start()
            url="https://localhost:"+str(proxy_server.server_port)+ENDPOINT
            os.environ["SSL_CERT_FILE"]=str(tls_cert)

            # Mandatory actual CA/host validation by the same Executor code.
            state=endpoint_state(url,token)
            check("site_revision" in state,
                  "connected over real TLS to installed MODX API")

            # CI fixture create goes through signed HTTP MCP. The test user
            # approves only the later v4 UPDATE generated by Controller.
            rid=setup_resource(url,token,controller_key,PREFIX+" success")
            rid_created.append(rid)
            observed=snapshot_of(url,token,rid)
            store,agent,relay,exec_uid=new_controller(
                home/"controller-state",controller_key
            )
            task="TASK-20261121-210001-a001"
            consent(store,agent,relay,exec_uid,task,observed,
                    "V4 HUMAN REVIEWED VIA REAL TLS")
            issued={}

            def control(_socket,payload):
                result=store.handle(exec_uid,payload)
                if payload["action"]=="begin_ed25519_v1":
                    issued["cas_operation"]=result["cas_operation"]
                return result

            target={
                "url":url,"api_token":token,
                "audience":"ci-protected",
                "ed25519_public_key":public,
            }
            check(executor_v4.PROTECTED_ED25519_V4_EXECUTION_ENABLED is False,
                  "production Executor gate starts disabled")
            # This is a CI-only simulated OS identity with actual Controller
            # actor UID guards still exercised; no privileged service started.
            with patch.object(executor_v4,"_require_executor_identity"):
                executor_v4.PROTECTED_ED25519_V4_EXECUTION_ENABLED=True
                try:
                    result=executor_v4.execute_once(
                        socket_path=home/"not-a-real-socket",
                        site_id=SITE,task_id=task,
                        trusted_target=target,control=control,
                    )
                finally:
                    executor_v4.PROTECTED_ED25519_V4_EXECUTION_ENABLED=False
            check(result["verified"] is True and result["resource_id"]==rid,
                  "human-reviewed one-use Controller signed real HTTPS update")
            check(
                get_resource(url,token,rid)["content"]
                    == "V4 HUMAN REVIEWED VIA REAL TLS",
                "separate MODX MCP HTTP read confirms persisted content"
            )
            check(store.handle(agent,{
                "action":"status","site_id":SITE,"task_id":task
            })["state"]=="cas_verified",
                "Controller durable completion verified")
            check(any(
                action=="update_element" and status==200
                for action,status in server_events
            ),"actual installed MODX handler returned successful update")
            signed=issued.get("cas_operation") or getattr(
                store, "last_granted_permit", None)
            check(isinstance(signed,dict)
                  and set(signed)=={"request_body","remote_permit"},
                  "Controller signed one-use permit available for replay denial test")
            try:
                http_send(url,token,signed["request_body"].encode(),
                          signed["remote_permit"])
            except error.HTTPError as exc:
                check(exc.code==403,"installed MODX nonce replay denied")
            else:
                raise AssertionError("MCP accepted repeated Controller signature")
            check(get_resource(url,token,rid)["content"]
                    == "V4 HUMAN REVIEWED VIA REAL TLS",
                  "replay did not overwrite resource")
            print("CONTROLLER_ED25519_REAL_TLS_MODX_SUCCESS_OK",flush=True)

            # Second resource: the signed MODX write succeeds but the trusted
            # TLS proxy intentionally drops the server reply. Controller must
            # permanently classify outcome unknown, NOT retry or mint token.
            other=setup_resource(url,token,controller_key,PREFIX+" drop")
            rid_created.append(other)
            observed_other=snapshot_of(url,token,other)
            other_task="TASK-20261121-210002-a002"
            consent(store,agent,relay,exec_uid,other_task,observed_other,
                    "COMMITTED_BUT_HTTPS_REPLY_LOST",event_offset=1000)
            lost={"captured":None}
            def lost_control(_socket,payload):
                out=store.handle(exec_uid,payload)
                if payload["action"]=="begin_ed25519_v1":
                    lost["captured"]=out["cas_operation"]
                return out
            proxy_drop_after_update=True
            before_rev=observed_other["site_revision"]
            with patch.object(executor_v4,"_require_executor_identity"):
                executor_v4.PROTECTED_ED25519_V4_EXECUTION_ENABLED=True
                try:
                    try:
                        executor_v4.execute_once(
                            socket_path=home/"not-a-real-socket",
                            site_id=SITE,task_id=other_task,
                            trusted_target=target,control=lost_control,
                        )
                    except RemoteExecutionError:
                        checks_increment()
                    else:
                        raise AssertionError(
                            "Executor incorrectly verified lost HTTPS response"
                        )
                finally:
                    executor_v4.PROTECTED_ED25519_V4_EXECUTION_ENABLED=False
            check(proxy_drop_after_update is False,
                  "TLS proxy truly discarded response AFTER MODX commit")
            check(
                get_resource(url,token,other)["content"]
                    == "COMMITTED_BUT_HTTPS_REPLY_LOST",
                "real MODX committed resource even without client reply"
            )
            check(
                endpoint_state(url,token)["site_revision"] == before_rev+1,
                "ambiguous write committed exactly once"
            )
            current=store.handle(agent,{
                "action":"status","site_id":SITE,"task_id":other_task
            })
            check(current["state"]=="needs_review",
                  "lost reply forces durable needs_review")
            try:
                store.handle(exec_uid,{
                    "action":"begin_ed25519_v1",
                    "site_id":SITE,"task_id":other_task,
                })
            except ControlError:
                checks_increment()
            else:
                raise AssertionError("Controller reissued permit after lost reply")
            try:
                used=lost.get("captured") or getattr(
                    store, "last_granted_permit", None)
                check(isinstance(used,dict),
                      "ambiguous Executor captured approved one-use permit")
                http_send(url,token,used["request_body"].encode(),
                          used["remote_permit"])
            except error.HTTPError as exc:
                check(exc.code==403,"MODX rejects duplicate nonce after lost reply")
            else:
                raise AssertionError("MODX replay succeeded after client disconnect")
            print("CONTROLLER_ED25519_REAL_TLS_UNKNOWN_OUTCOME_FAIL_CLOSED_OK",
                  flush=True)

            # Independent signer revocation: the site is the authority on
            # which Controller public key is trusted. Rotate to a different
            # key and prove that an otherwise-correct UNCONSUMED signature
            # made with the old key no longer authorizes any content write.
            rotated_dir=home/"rotated-controller-private"
            rotated_dir.mkdir(mode=0o700)
            rotated_dir.chmod(0o700)
            rotated_key=rotated_dir/"issuer.pem"
            rotated_private=Ed25519PrivateKey.generate()
            rotated_key.write_bytes(rotated_private.private_bytes(
                serialization.Encoding.PEM,
                serialization.PrivateFormat.PKCS8,
                serialization.NoEncryption(),
            ))
            rotated_key.chmod(0o600)
            rotated_public=rotated_private.public_key().public_bytes(
                serialization.Encoding.Raw,serialization.PublicFormat.Raw
            ).hex()
            check(rotated_public!=public,
                  "rotated Controller public key differs from old")
            rotate_rid=setup_resource(
                url,token,controller_key,PREFIX+" rotate"
            )
            rid_created.append(rotate_rid)
            state_before_rotate=endpoint_state(url,token,[
                {"role":"target","type":"resource","id":rotate_rid}
            ])
            intended_delete={
                "type":"resource","id":rotate_rid,"dry_run":False,
                "_runtime_preconditions":{
                    "observed_site_revision":int(state_before_rotate["site_revision"]),
                    "strict_site_revision":True,
                    "read_set":state_before_rotate["read_set"],
                },
            }
            delete_raw=json_bytes({
                "action":"delete_element","type":"resource",
                "data":intended_delete,
            })
            old_unconsumed_permit=authorize_ci_create_or_delete(
                controller_key,"delete_element",delete_raw
            )
            run(["php",str(PROXY_SETTINGS),"rotate-key",SITE,rotated_public])
            try:
                http_send(url,token,delete_raw,old_unconsumed_permit)
            except error.HTTPError as exc:
                check(exc.code==403,
                      "site rejected old but unused valid signature after key rotation")
            else:
                raise AssertionError("MCP accepted revoked Controller public key")
            check(not bool(get_resource(url,token,rotate_rid)["deleted"]),
                  "revoked signer did not delete resource")
            check(
                endpoint_state(url,token)["site_revision"]
                    ==int(state_before_rotate["site_revision"]),
                "revoked signature did not mutate connector revision"
            )
            new_permit=authorize_ci_create_or_delete(
                rotated_key,"delete_element",delete_raw
            )
            accepted=http_send(url,token,delete_raw,new_permit)
            check(accepted["success"] is True,
                  "new trusted public key permitted valid signed MCP operation")
            check(bool(get_resource(url,token,rotate_rid)["deleted"]),
                  "rotated key mutation was independently confirmed")
            rid_created.remove(rotate_rid)

            # Revocation to NO KEY must fail closed. A signed request from
            # even the current Controller is not sufficient while the site
            # refuses to trust any Controller key.
            remaining=rid_created[0]
            before_revoke=endpoint_state(url,token,[
                {"role":"target","type":"resource","id":remaining}
            ])
            pending={
                "type":"resource","id":remaining,"dry_run":False,
                "_runtime_preconditions":{
                    "observed_site_revision":int(before_revoke["site_revision"]),
                    "strict_site_revision":True,
                    "read_set":before_revoke["read_set"],
                },
            }
            raw_revoked=json_bytes({
                "action":"delete_element","type":"resource","data":pending
            })
            valid_b_permit=authorize_ci_create_or_delete(
                rotated_key,"delete_element",raw_revoked
            )
            run(["php",str(PROXY_SETTINGS),"revoke-key",SITE])
            try:
                http_send(url,token,raw_revoked,valid_b_permit)
            except error.HTTPError as exc:
                check(exc.code==403,"missing trusted public key denies all writes")
            else:
                raise AssertionError("MCP accepted signed request with revoked key")
            check(not bool(get_resource(url,token,remaining)["deleted"]),
                  "missing key did not alter resource")
            check(
                endpoint_state(url,token)["site_revision"]
                    ==int(before_revoke["site_revision"]),
                "missing public key did not alter revision"
            )
            run(["php",str(PROXY_SETTINGS),"rotate-key",SITE,rotated_public])
            print("CONTROLLER_ED25519_REAL_TLS_KEY_ROTATION_REVOCATION_OK",
                  flush=True)

            # Cleanup is also a protected, CAS-verified MCP HTTP mutation,
            # not direct deletion from the database. Use the rotated current
            # Controller key: the original key remains rejected.
            for cleanup_id in reversed(rid_created):
                cleanup_resource(url,token,rotated_key,cleanup_id)
            rid_created=[]
            print("CONTROLLER_ED25519_REAL_TLS_SOFT_DELETE_CLEANUP_OK",
                  flush=True)
            print(
                "CONTROLLER_ED25519_REAL_MODX_TLS_E2E_OK "
                f"platform={PLATFORM} checks={checks} "
                "real_https=yes controller_review=yes "
                "resource_cas=yes replay_denied=yes "
                "lost_response_needs_review=yes key_rotation=yes "
                "key_revocation=yes cleanup_via_mcp=yes",
                flush=True,
            )
        finally:
            # The entire MODX installation is disposable and discarded by CI
            # even on failure; logs never contain credentials.
            if proxy_server is not None:
                proxy_server.shutdown()
                if proxy_thread is not None:proxy_thread.join(timeout=15)
                proxy_server.server_close()
            if server_process is not None:
                server_process.terminate()
                try:server_process.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    server_process.kill()
                    server_process.wait(timeout=10)
            if server_log is not None:server_log.close()
            if configured:
                try:run(["php",str(PROXY_SETTINGS),"disable",SITE])
                except Exception:
                    print("CI_TLS_PROXY_SETTING_RESET_FAILED",flush=True)
            if fixture_enabled:
                try:run(["php",str(SETTINGS),"disable",
                         str(public_file),str(token_file)])
                except Exception:
                    print("CI_MODX_PERMIT_FIXTURE_RESET_FAILED",flush=True)
            if prior_ca is None:os.environ.pop("SSL_CERT_FILE",None)
            else:os.environ["SSL_CERT_FILE"]=prior_ca


if __name__=="__main__":
    main()
