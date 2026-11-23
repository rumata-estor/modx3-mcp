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
    if len(sys.argv) != 5:
        raise UnsafePath("incorrect arguments")
    core, rel, action, expected_bytes = sys.argv[1:]
    if action in ("read", "inspect"):
        if expected_bytes != "-":
            raise UnsafePath("incorrect read/inspect length")
    elif (not expected_bytes.isascii() or not expected_bytes.isdecimal()
          or (len(expected_bytes)>1 and expected_bytes[0]=="0")
          or len(expected_bytes)>9 or int(expected_bytes)>MAX_CONTENT):
        raise UnsafePath("incorrect write length")
    if action not in ("read", "write", "create", "inspect"):
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
                nxt = directory(parent, part, action in ("write", "create"))
                os.close(parent)
                parent = nxt
            try:
                leaf = parts[-1]
                if action in ("read", "inspect"):
                    handle = os.open(leaf, os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC | os.O_NONBLOCK, dir_fd=parent)
                    try:
                        guard_file(handle)
                        if action == "inspect":
                            # Portable PHP may publish a new inode only when
                            # doing so cannot silently discard ACL/xattrs.
                            # Inspect by fd so traversing a substituted
                            # symlink never escapes the permitted tree.
                            if os.listxattr(handle):
                                raise UnsafePath("existing ACL/xattrs detected")
                            sys.stdout.write("CLEAN")
                        else:
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
                    if len(payload) != int(expected_bytes):
                        raise UnsafePath("incomplete payload before publish")
                    # RU: Путь цели держится через dirfd. Не truncate до
                    # RU: завершения временной записи. Небезопасного fallback нет.
                    # EN: Use a private same-directory inode, fsync then atomic
                    # EN: dirfd-anchored rename/link. No truncate-first loss.
                    import secrets

                    try:
                        existing = os.stat(leaf, dir_fd=parent, follow_symlinks=False)
                    except FileNotFoundError:
                        existing = None
                    if existing is not None:
                        if not stat.S_ISREG(existing.st_mode) or existing.st_nlink != 1:
                            raise UnsafePath("target is not a singly-linked regular file")
                        if action == "create":
                            raise UnsafePath("exclusive target already exists")
                        if not os.access(leaf, os.W_OK, dir_fd=parent,
                                         follow_symlinks=False):
                            raise UnsafePath("target is read-only")
                    # A rename of a file owned by another account would
                    # silently change ownership. Refuse instead of weakening
                    # the descriptor boundary with in-place fallback.
                    if existing is not None and (
                        existing.st_uid != os.geteuid()
                        or existing.st_gid != os.getegid()
                    ):
                        raise UnsafePath("cannot preserve owner of existing file")
                    if existing is not None:
                        # RU: Замена inode не сохраняет POSIX ACL/xattrs.
                        # RU: Отказываем до создания temp, не удаляя атрибуты.
                        # EN: Replacing an inode drops POSIX ACLs/xattrs.
                        # EN: Fail closed rather than silently remove them.
                        prior_fd = os.open(
                            leaf, os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC
                            | os.O_NONBLOCK, dir_fd=parent,
                        )
                        try:
                            guard_file(prior_fd)
                            prior = os.fstat(prior_fd)
                            if (
                                prior.st_dev != existing.st_dev
                                or prior.st_ino != existing.st_ino
                                or prior.st_ctime_ns != existing.st_ctime_ns
                            ):
                                raise UnsafePath("target changed before metadata check")
                            if os.listxattr(prior_fd):
                                raise UnsafePath(
                                    "existing ACL/xattrs cannot be preserved by atomic replacement"
                                )
                        finally:
                            os.close(prior_fd)
                    temp_name = ".modxmcp-" + secrets.token_hex(12) + ".tmp"
                    temp = os.open(
                        temp_name,
                        os.O_WRONLY | os.O_CREAT | os.O_EXCL
                        | os.O_NOFOLLOW | os.O_CLOEXEC,
                        0o600, dir_fd=parent,
                    )
                    published = False
                    try:
                        try:
                            guard_file(temp)
                            remaining = memoryview(payload)
                            while remaining:
                                n = os.write(temp, remaining)
                                if n < 1:
                                    raise OSError("short temporary write")
                                remaining = remaining[n:]
                            # Preserve the inherited umask for a new file.
                            current_umask = os.umask(0)
                            os.umask(current_umask)
                            target_mode = (
                                stat.S_IMODE(existing.st_mode) if existing else
                                (0o666 & ~current_umask)
                            )
                            os.fchmod(temp, target_mode)
                            os.fsync(temp)
                        finally:
                            os.close(temp)
                        # Check target metadata again before the atomic
                        # replacement. A concurrent rename after this check
                        # can still change the *target name* but cannot redirect
                        # file I/O outside the anchored directory.
                        try:
                            now = os.stat(leaf, dir_fd=parent, follow_symlinks=False)
                        except FileNotFoundError:
                            now = None
                        if existing is None and now is not None:
                            raise UnsafePath("target appeared during write")
                        if existing is not None and (
                            now is None
                            or not stat.S_ISREG(now.st_mode) or now.st_nlink != 1
                            or (now.st_dev, now.st_ino, now.st_size, now.st_mtime_ns, now.st_ctime_ns)
                               != (existing.st_dev, existing.st_ino,
                                   existing.st_size, existing.st_mtime_ns, existing.st_ctime_ns)
                        ):
                            raise UnsafePath("target changed during write")
                        if action == "create" or existing is None:
                            # Exclusive publication for all new paths, even
                            # a normal write that races with a new file.
                            # Cannot overwrite another writer's new target.
                            os.link(temp_name, leaf, src_dir_fd=parent,
                                    dst_dir_fd=parent, follow_symlinks=False)
                        else:
                            # Atomic publish relative to an *open parent fd*.
                            os.replace(temp_name, leaf, src_dir_fd=parent,
                                       dst_dir_fd=parent)
                        published = True
                        os.fsync(parent)
                        sys.stdout.write(str(len(payload)))
                    finally:
                        try:
                            os.unlink(temp_name, dir_fd=parent)
                        except FileNotFoundError:
                            pass
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
