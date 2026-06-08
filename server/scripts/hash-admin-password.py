#!/usr/bin/env python3
from __future__ import annotations

import getpass
import hashlib
import secrets
import sys


SCRYPT_N = 32768
SCRYPT_R = 8
SCRYPT_P = 1
SCRYPT_DKLEN = 32
SCRYPT_MAXMEM = 64 * 1024 * 1024


def scrypt_hash(secret: bytes, prefix: str = "scrypt") -> str:
    salt = secrets.token_bytes(16)
    digest = hashlib.scrypt(
        secret,
        salt=salt,
        n=SCRYPT_N,
        r=SCRYPT_R,
        p=SCRYPT_P,
        dklen=SCRYPT_DKLEN,
        maxmem=SCRYPT_MAXMEM,
    )
    return f"{prefix}${SCRYPT_N}${SCRYPT_R}${SCRYPT_P}${salt.hex()}${digest.hex()}"


if len(sys.argv) == 3 and sys.argv[1] == "--from-sha256":
    legacy_hash = sys.argv[2].strip()
    if legacy_hash.startswith("sha256$"):
        legacy_hash = legacy_hash.split("$", 1)[1]
    if len(legacy_hash) != 64:
        raise SystemExit("Expected a sha256$ hash or 64-character SHA-256 hex digest.")
    print(scrypt_hash(legacy_hash.encode("ascii"), "scrypt-sha256"))
    raise SystemExit(0)

if len(sys.argv) != 1:
    raise SystemExit("Usage: hash-admin-password.py [--from-sha256 sha256$hex]")

password = getpass.getpass("Admin password: ")
print(scrypt_hash(password.encode("utf-8")))
