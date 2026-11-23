#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Обрыв записи до rename не должен усекать старый static_file.
# EN: Inject a short write into the real descriptor helper, preserve target.
import importlib.util
import io
import os
from pathlib import Path
import sys
import tempfile
from types import SimpleNamespace

ROOT=Path(__file__).resolve().parents[1]
path=ROOT/"core/components/modxmcp/src/Security/static_io_guard.py"
spec=importlib.util.spec_from_file_location("static_guard",path)
guard=importlib.util.module_from_spec(spec)
spec.loader.exec_module(guard)

def check(value,why):
    if not value:raise RuntimeError("STATIC_DESCRIPTOR_ATOMIC_FAULT_FAIL "+why)

with tempfile.TemporaryDirectory(prefix="modxmcp-static-atomic-") as tmp:
    core=Path(tmp)/"core"
    folder=core/"elements"/"chunks"
    folder.mkdir(parents=True)
    target=folder/"content.tpl"
    target.write_bytes(b"ORIGINAL_NEVER_TRUNCATED")
    target.chmod(0o640)
    old_argv,old_stdin=sys.argv,sys.stdin
    original_write=guard.os.write

    def partial_then_fail(fd,data):
        original_write(fd,bytes(data[:2]))
        raise OSError("simulated partial temp write")

    for action,filename in (("write","content.tpl"),("create","new.tpl")):
        try:
            guard.os.write=partial_then_fail
            sys.argv=[str(path),str(core),"elements/chunks/"+filename,action,"11"]
            sys.stdin=SimpleNamespace(buffer=io.BytesIO(b"NEW_CONTENT"))
            failed=False
            try:guard.main()
            except OSError:failed=True
            check(failed,action+" failed write did not raise")
            check(target.read_bytes()==b"ORIGINAL_NEVER_TRUNCATED",
                  action+" truncated previous content")
            check(not (folder/"new.tpl").exists(),
                  "failed create published partial new file")
            check(not list(folder.glob(".modxmcp-*.tmp")),
                  "temporary file not cleaned on failure")
        finally:
            guard.os.write=original_write
            sys.argv,sys.stdin=old_argv,old_stdin
    # PHP-to-helper pipe sent fewer bytes than promised: reject before
    # touching the target. This is a real failure of the framing boundary.
    try:
        sys.argv=[str(path),str(core),"elements/chunks/content.tpl","write","11"]
        sys.stdin=SimpleNamespace(buffer=io.BytesIO(b"NE"))
        failed=False
        try:guard.main()
        except guard.UnsafePath:failed=True
        check(failed,"truncated PHP pipe was accepted")
        check(target.read_bytes()==b"ORIGINAL_NEVER_TRUNCATED",
              "truncated PHP pipe replaced original")
        check(not list(folder.glob(".modxmcp-*.tmp")),
              "payload length refusal leaked temp file")
    finally:
        sys.argv,sys.stdin=old_argv,old_stdin
    # The success path must still replace the old file atomically and
    # keep its mode rather than silently weakening permissions.
    try:
        sys.argv=[str(path),str(core),"elements/chunks/content.tpl","write","7"]
        sys.stdin=SimpleNamespace(buffer=io.BytesIO(b"SUCCESS"))
        guard.main()
    finally:
        sys.argv,sys.stdin=old_argv,old_stdin
    check(target.read_bytes()==b"SUCCESS","successful replacement content")
    check((target.stat().st_mode & 0o777)==0o640,"permissions not retained")
    check(not list(folder.glob(".modxmcp-*.tmp")),"temporary file leaked")
print("STATIC_DESCRIPTOR_ATOMIC_PRE_RENAME_FAILURE_OK checks=12")
