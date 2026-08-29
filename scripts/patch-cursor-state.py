#!/usr/bin/env python3
"""Patch Cursor globalStorage/state.vscdb paths after workspace rename."""

from __future__ import annotations

import shutil
import sqlite3
import sys
from pathlib import Path


def replace_bytes(data: bytes, replacements: list[tuple[bytes, bytes]]) -> bytes:
    out = data
    for old, new in replacements:
        if old in out:
            out = out.replace(old, new)
    return out


def main() -> int:
    if len(sys.argv) < 10:
        print(
            "Usage: patch-cursor-state.py <state.vscdb> <old_path> <new_path> "
            "<old_slug> <new_slug> <old_ws_id> <new_ws_id> <old_uri> <new_uri> [--dry-run]",
            file=sys.stderr,
        )
        return 1

    db_path = Path(sys.argv[1])
    dry_run = "--dry-run" in sys.argv

    replacements_str = [
        (sys.argv[2], sys.argv[3]),  # Windows paths (escaped backslashes in arg)
        (sys.argv[2].replace("\\\\", "\\"), sys.argv[3].replace("\\\\", "\\")),
        (sys.argv[4], sys.argv[5]),  # folder slug
        (sys.argv[6], sys.argv[7]),  # workspace ids
        (sys.argv[8], sys.argv[9]),  # file URIs
        ("wp-country-search", "ship-to-rules"),
    ]

    replacements_bytes = [(a.encode("utf-8"), b.encode("utf-8")) for a, b in replacements_str]

    if not db_path.exists():
        print(f"Skip: {db_path} not found", file=sys.stderr)
        return 0

    if dry_run:
        print(f"Dry run: would patch {db_path}")
        return 0

    backup = db_path.with_suffix(db_path.suffix + ".bak")
    shutil.copy2(db_path, backup)

    conn = sqlite3.connect(db_path)
    cur = conn.cursor()

    patched = 0
    for table in ("ItemTable", "cursorDiskKV"):
        try:
            cur.execute(f"SELECT key, value FROM {table}")
        except sqlite3.Error:
            continue
        rows = cur.fetchall()
        for key, value in rows:
            if not isinstance(value, (bytes, bytearray)):
                continue
            new_value = replace_bytes(bytes(value), replacements_bytes)
            if new_value != value:
                cur.execute(f"UPDATE {table} SET value = ? WHERE key = ?", (new_value, key))
                patched += 1

    conn.commit()
    conn.close()
    print(f"Patched {patched} rows in {db_path} (backup: {backup})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
