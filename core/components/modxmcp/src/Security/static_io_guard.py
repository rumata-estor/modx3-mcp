#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Дескрипторная файловая граница для чтения/записи статических элементов.
# EN: Descriptor-anchored file boundary for static element reads and writes.
"""Internal helper; PHP invokes with a verified core root and relative path.

No shell, no arbitrary root chosen by the MCP client. All path components are
traversed relative to open directory FDs with O_NOFOLLOW; symlinks and hard
links are denied. This prevents changing a directory path between a realpath()
check and the actual file open from redirecting I/O outside core/elements.
"""
import os
import stat
import sys

MAX_CONTENT = 32 * 1024 * 1024


class UnsafePath(Exception):
    pass


def directory(fd, name, create):
    flags = os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC
    try:
        return os.open(name, flags, dir_fd=fd)
    except FileNotFoundError:
        if not create:
            raise
        os.mkdir(name, 0o755, dir_fd=fd)
        return os.open(name, flags, dir_fd=fd)


def guard_file(fd):
    info = os.fstat(fd)
    if not stat.S_ISREG(info.st_mode) or info.st_nlink != 1:
        raise UnsafePath("not a regular singly linked file")


def main():
    if len(sys.argv) != 4:
        raise UnsafePath("incorrect arguments")
    core, rel, action = sys.argv[1:]
    if action not in ("read", "write", "create"):
        raise UnsafePath("unsupported action")
    if not os.path.isabs(core) or os.path.realpath(core) != core:
        raise UnsafePath("core root is not canonical")
    parts = rel.split("/")
    if len(parts) < 2 or parts[0] != "elements":
        raise UnsafePath("outside elements")
    if any(p in ("", ".", "..") or "\x00" in p or "\\" in p for p in parts):
        raise UnsafePath("invalid component")
    if len(rel) > 4096 or len(parts) > 32:
        raise UnsafePath("path too large")

    root = os.open(core, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC)
    try:
        # Never create the elements root from an untrusted request.
        parent = directory(root, "elements", False)
        try:
            for part in parts[1:-1]:
                nxt = directory(parent, part, action != "read")
                os.close(parent)
                parent = nxt
            try:
                leaf = parts[-1]
                if action == "read":
                    handle = os.open(leaf, os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC | os.O_NONBLOCK, dir_fd=parent)
                    try:
                        guard_file(handle)
                        result = bytearray()
                        while True:
                            chunk = os.read(handle, 65536)
                            if not chunk:
                                break
                            if len(result) + len(chunk) > MAX_CONTENT:
                                raise UnsafePath("read size limit")
                            result.extend(chunk)
                        sys.stdout.buffer.write(result)
                    finally:
                        os.close(handle)
                else:
                    payload = sys.stdin.buffer.read(MAX_CONTENT + 1)
                    if len(payload) > MAX_CONTENT:
                        raise UnsafePath("write size limit")
                    if action == "create":
                        flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW | os.O_CLOEXEC
                    else:
                        flags = os.O_WRONLY | os.O_CREAT | os.O_NOFOLLOW | os.O_CLOEXEC | os.O_NONBLOCK
                    handle = os.open(leaf, flags, 0o644, dir_fd=parent)
                    try:
                        guard_file(handle)
                        os.ftruncate(handle, 0)
                        remaining = memoryview(payload)
                        while remaining:
                            n = os.write(handle, remaining)
                            if n < 1:
                                raise OSError("short write")
                            remaining = remaining[n:]
                        os.fsync(handle)
                        sys.stdout.write(str(len(payload)))
                    finally:
                        os.close(handle)
            finally:
                os.close(parent)
        except Exception:
            raise
    finally:
        os.close(root)


if __name__ == "__main__":
    try:
        main()
    except (OSError, UnsafePath, ValueError):
        # No paths, credentials or untrusted content in diagnostic output.
        sys.stderr.write("STATIC_IO_REFUSED\n")
        sys.exit(41)
