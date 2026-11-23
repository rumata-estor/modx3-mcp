#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Финальная проверка прав/ACL/xattrs на одноразовых файлах, не на сайтах.
# EN: Final metadata preservation/refusal gate on disposable filesystem only.
"""Linux descriptor backend metadata release gate; no production paths."""
import os
from pathlib import Path
import shutil
import stat
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
HELPER = ROOT / "core/components/modxmcp/src/Security/static_io_guard.py"


def check(ok, message):
    if not ok:
        raise AssertionError("STATIC_METADATA_GATE_FAIL " + message)


def request(core, action, payload):
    proc = subprocess.run(
        [sys.executable, str(HELPER), str(core),
         "elements/chunks/existing.tpl", action,
         "-" if action == "read" else str(len(payload))],
        input=payload, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        timeout=8, check=False,
    )
    return proc


with tempfile.TemporaryDirectory(prefix="modxmcp-static-metadata-") as tmp:
    core = Path(tmp) / "core"
    folder = core / "elements" / "chunks"
    folder.mkdir(parents=True)
    target = folder / "existing.tpl"
    target.write_bytes(b"ORIGINAL")
    target.chmod(0o640)
    before = target.stat()
    response = request(core, "write", b"NEW_NORMAL")
    check(response.returncode == 0 and response.stdout == b"10",
          "ordinary strict replacement")
    now = target.stat()
    check(target.read_bytes() == b"NEW_NORMAL", "ordinary bytes")
    check(stat.S_IMODE(now.st_mode) == 0o640, "mode preserved")
    check(now.st_uid == before.st_uid and now.st_gid == before.st_gid,
          "owner and group preserved")

    # RU: Нестандартные user.xattrs до атомарной замены -> отказ.
    # EN: User xattrs would be lost by rename: refuse without modifying data.
    os.setxattr(target, b"user.modxmcp_metadata_gate", b"DO_NOT_DROP")
    original = target.read_bytes()
    with_xattr = target.stat()
    response = request(core, "write", b"SHOULD_NOT_APPEAR")
    check(response.returncode != 0, "xattr-bearing target must refuse")
    check(target.read_bytes() == original, "xattr refusal preserved bytes")
    check(os.getxattr(target, b"user.modxmcp_metadata_gate") == b"DO_NOT_DROP",
          "xattr retained")
    check(target.stat().st_ino == with_xattr.st_ino,
          "xattr refusal preserved existing inode")
    os.removexattr(target, b"user.modxmcp_metadata_gate")

    # RU: Тест POSIX ACL выполняется на disposable runner через system acl.
    # EN: A named-user POSIX ACL must not silently vanish on replacement.
    check(shutil.which("setfacl") is not None and shutil.which("getfacl") is not None,
          "acl utilities required for CI release gate")
    subprocess.run(["setfacl", "-m", "u:nobody:r--", str(target)],
                   check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    acl_before = subprocess.run(
        ["getfacl", "-cp", str(target)], check=True, capture_output=True,
    ).stdout
    check(b"user:nobody:r--" in acl_before,
          "named-user ACL was not created in fixture")
    response = request(core, "write", b"ACL_SHOULD_NOT_DISAPPEAR")
    check(response.returncode != 0, "ACL-bearing target must refuse")
    check(target.read_bytes() == original, "ACL refusal preserved bytes")
    acl_after = subprocess.run(
        ["getfacl", "-cp", str(target)], check=True, capture_output=True,
    ).stdout
    check(acl_after == acl_before, "ACL changed after refused write")
    subprocess.run(["setfacl", "-b", str(target)], check=True)

    # RU: Чужой владелец/группа с доступной записью — только отказ.
    # EN: A writable file owned by another principal is refused.
    current_uid, current_gid = os.geteuid(), os.getegid()
    check(shutil.which("sudo") is not None,
          "sudo required only for isolated ownership fixture")
    subprocess.run(["sudo", "-n", "chown", "root:root", str(target)], check=True)
    target.chmod(0o666)
    owner_before = target.stat()
    try:
        response = request(core, "write", b"WRONG_OWNER")
        check(response.returncode != 0, "foreign owner must refuse")
        check(target.read_bytes() == original, "foreign owner bytes intact")
        check(target.stat().st_uid == owner_before.st_uid
              and target.stat().st_gid == owner_before.st_gid,
              "foreign owner remains unchanged")
    finally:
        subprocess.run(["sudo", "-n", "chown",
                        f"{current_uid}:{current_gid}", str(target)], check=True)
    check(not list(folder.glob(".modxmcp-*.tmp")),
          "no temporary files left on any refused write")

print("STATIC_METADATA_INTEGRITY_GATE_OK: mode uid gid xattr POSIX_ACL foreign_owner")
