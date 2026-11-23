#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Конкурентная подмена родителя симлинком не должна изменять внешние данные.
# EN: Racing a parent symlink must never modify external data.
"""Run without MODX on an isolated temporary tree; no production files."""
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import threading
import time

ROOT = Path(__file__).resolve().parents[1]
HELPER = ROOT / "core/components/modxmcp/src/Security/static_io_guard.py"


def check(value, message):
    if not value:
        raise AssertionError(message)


with tempfile.TemporaryDirectory(prefix="modxmcp-toctou-") as temp:
    base = Path(temp)
    core = base / "core"
    elements = core / "elements"
    elements.mkdir(parents=True)
    private = base / "private"
    private.mkdir()
    (private / "target.tpl").write_bytes(b"OUTSIDE-UNCHANGED")
    (elements / "chunks").mkdir()
    (elements / "chunks" / "target.tpl").write_bytes(b"LOCAL")
    (elements / "chunks" / "file.tpl").write_bytes(b"LOCAL")
    external_secret = private / "target.tpl"
    attacks = {"count": 0}
    stop = threading.Event()
    benign = elements / "chunks"
    backup = elements / "chunks-original"

    def attacker():
        while not stop.is_set():
            try:
                benign.rename(backup)
                benign.symlink_to(private, target_is_directory=True)
                attacks["count"] += 1
                time.sleep(0.0002)
                benign.unlink()
                backup.rename(benign)
            except (OSError, FileNotFoundError):
                try:
                    if benign.is_symlink():
                        benign.unlink()
                    if backup.is_dir() and not benign.exists():
                        backup.rename(benign)
                except OSError:
                    pass

    thread = threading.Thread(target=attacker, daemon=True)
    thread.start()
    failures = 0
    for _ in range(250):
        for action, payload in (("write", b"INSIDE"), ("read", b"")):
            p = subprocess.run(
                [sys.executable, str(HELPER), str(core), "elements/chunks/target.tpl", action,
                 "-" if action == "read" else str(len(payload))],
                input=payload, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                timeout=6, check=False,
            )
            if p.returncode != 0:
                failures += 1
            check(external_secret.read_bytes() == b"OUTSIDE-UNCHANGED",
                  "outside secret changed during symlink race")
    stop.set()
    thread.join(timeout=5)
    if benign.is_symlink():
        benign.unlink()
    if backup.is_dir() and not benign.exists():
        backup.rename(benign)
    check(attacks["count"] > 1, "no concurrent directory substitutions occurred")
    check(failures > 0, "race never exercised helper refusal path")
    check(external_secret.read_bytes() == b"OUTSIDE-UNCHANGED",
          "outside secret changed after race")

    # A same-inode hardlink would escape lexical realpath restrictions.
    hardlink = elements / "chunks" / "linked.tpl"
    os.link(external_secret, hardlink)
    for action, payload in (("read", b""), ("write", b"DAMAGE")):
        p = subprocess.run(
            [sys.executable, str(HELPER), str(core), "elements/chunks/linked.tpl", action,
             "-" if action == "read" else str(len(payload))],
            input=payload, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=5
        )
        check(p.returncode != 0, "hard link accepted " + action)
    check(external_secret.read_bytes() == b"OUTSIDE-UNCHANGED",
          "hardlink changed outside secret")
    print("STATIC_DESCRIPTOR_TOCTOU_RACE_OK swaps=%d attempts=500 denied=%d" %
          (attacks["count"], failures))
