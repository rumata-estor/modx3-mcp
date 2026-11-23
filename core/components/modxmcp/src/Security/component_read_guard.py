#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Чтение файла компонента только через закреплённые дескрипторы каталогов.
# EN: Read a component file through pinned directory descriptors only.
"""Read-only, fail-closed component code helper (POSIX, Python 3).

The returned bytes come from a regular file opened via chained dirfd and
O_NOFOLLOW, not from a path checked separately from its use. No writes.
"""
import base64
import json
import mimetypes
import os
import stat
import sys

MAX_READ = 4 * 1024 * 1024


def fail():
    # No filesystem path or original exception in stdout/HTTP diagnostics.
    raise RuntimeError('SECURE_COMPONENT_READ_UNAVAILABLE')


def components(value):
    if not value or '\x00' in value:
        fail()
    parts = value.split('/')
    if any(p in ('', '.', '..') for p in parts):
        fail()
    return parts


def anchored_open(root, relative):
    if not root.startswith('/') or root == '/' or '\\' in root:
        fail()
    if not hasattr(os, 'O_NOFOLLOW') or not hasattr(os, 'O_DIRECTORY'):
        fail()
    folders = components(root[1:].rstrip('/'))
    leaf = components(relative)
    if not leaf:
        fail()
    parent_fd = os.open('/', os.O_RDONLY | os.O_DIRECTORY | os.O_CLOEXEC)
    try:
        all_parts = folders + leaf
        for idx, part in enumerate(all_parts):
            last = idx == len(all_parts) - 1
            flags = os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC
            if last:
                flags |= getattr(os, 'O_NONBLOCK', 0)
            else:
                flags |= os.O_DIRECTORY
            child_fd = os.open(part, flags, dir_fd=parent_fd)
            os.close(parent_fd)
            parent_fd = child_fd
        return parent_fd
    except BaseException:
        os.close(parent_fd)
        raise


def read(argv):
    if len(argv) != 4:
        fail()
    root, relative, offset_raw, size_raw = argv
    try:
        offset = int(offset_raw)
        count = int(size_raw)
    except ValueError:
        fail()
    if offset < 0 or count < 1 or count > MAX_READ:
        fail()
    fd = anchored_open(root, relative)
    try:
        before = os.fstat(fd)
        if not stat.S_ISREG(before.st_mode):
            fail()
        size = before.st_size
        offset = min(size, offset)
        os.lseek(fd, offset, os.SEEK_SET)
        remaining = min(count, size - offset)
        data = bytearray()
        while remaining:
            chunk = os.read(fd, remaining)
            if not chunk:
                fail()
            data.extend(chunk)
            remaining -= len(chunk)
        after = os.fstat(fd)
        # Detect modifications observed during reading. Cannot serialize other
        # writers, but never fetch the payload through a second pathname.
        if (before.st_dev, before.st_ino, before.st_size, before.st_mtime_ns,
                before.st_ctime_ns) != (after.st_dev, after.st_ino, after.st_size,
                                        after.st_mtime_ns, after.st_ctime_ns):
            fail()
    finally:
        os.close(fd)
    mime = mimetypes.guess_type(relative)[0] or 'application/octet-stream'
    if relative.lower().endswith(('.php', '.inc')):
        mime = 'application/x-httpd-php'
    is_text = (mime.startswith('text/') or any(
        item in mime for item in ('json', 'xml', 'javascript', 'svg', 'x-httpd-php')))
    if is_text:
        try:
            payload = data.decode('utf-8')
        except UnicodeDecodeError:
            is_text = False
    if not is_text:
        payload = base64.b64encode(data).decode('ascii')
    return dict(mime=mime, size=size, offset=offset,
                returned_bytes=len(data), truncated=offset + len(data) < size,
                encoding='utf-8' if is_text else 'base64', content=payload)


if __name__ == '__main__':
    try:
        result = read(sys.argv[1:])
        sys.stdout.write(json.dumps(result, ensure_ascii=False, separators=(',', ':')))
    except BaseException:
        # Deliberately avoid disclosing host paths or Python tracebacks.
        sys.stderr.write('SECURE_COMPONENT_READ_UNAVAILABLE\n')
        sys.exit(3)
