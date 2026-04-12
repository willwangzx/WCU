#!/usr/bin/env python3
from __future__ import annotations

import getpass
import hashlib


password = getpass.getpass("Admin password: ")
print("sha256$" + hashlib.sha256(password.encode("utf-8")).hexdigest())
