#!/usr/bin/env python3
from __future__ import annotations

import base64
import csv
import hashlib
import hmac
import html
import io
import json
import mimetypes
import os
import re
import sqlite3
import sys
import time
from datetime import datetime, timezone
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib import error as urlerror, request as urlrequest
from urllib.parse import parse_qs, urlencode, urlparse


BASE_DIR = Path(__file__).resolve().parent
CONFIG_PATH = BASE_DIR / "config.python.json"
SERVER_HOST = os.environ.get("WCU_BIND_HOST", "0.0.0.0")
SERVER_PORT = int(os.environ.get("WCU_BIND_PORT", "80"))
STATIC_ROOT = Path(os.environ.get("WCU_STATIC_ROOT", "/srv/wcu-site")).resolve()
DEFAULT_CONFIG = {
    "cors": {"allowed_origins": []},
    "database": {"path": "/var/lib/wcu-data/wcu.sqlite"},
    "admin": {"username": "admin", "password_hash": ""},
    "recaptcha": {
        "enabled": False,
        "secret_key": "",
        "secret_key_env": "WCU_RECAPTCHA_SECRET_KEY",
        "verify_url": "https://www.google.com/recaptcha/api/siteverify",
        "timeout_seconds": 5,
        "allowed_hostnames": [],
        "minimum_score": None,
        "expected_action": "",
    },
}


class RequestParseError(ValueError):
    pass


class UnsupportedMediaTypeError(RequestParseError):
    pass


EMAIL_PATTERN = re.compile(
    r"^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+@"
    r"[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?"
    r"(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+$"
)
SUPPORTED_FORM_CONTENT_TYPES = {"application/x-www-form-urlencoded"}
SCRYPT_MAXMEM = 64 * 1024 * 1024
ADMIN_CSRF_TTL_SECONDS = 12 * 60 * 60
ADMIN_LOGIN_MAX_FAILURES = 5
ADMIN_LOGIN_WINDOW_SECONDS = 15 * 60
ADMIN_LOGIN_FAILURES: dict[str, list[float]] = {}

VALID_TERMS = ["Fall 2026", "Spring 2027", "Fall 2027"]
VALID_PROGRAMS = [
    "School of Mathematics and Computer Science",
    "School of Engineering and Natural Science",
    "School of Business and Management",
    "School of Art and Literature",
    "School of Humanities and Social Science",
    "School of Interdisciplinary Studies",
]
VALID_GENDERS = ["Female", "Male", "Non-binary", "Prefer to self-describe", "Prefer not to say"]
VALID_BIRTH_MONTHS = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December",
]


def merge_dicts(base: dict, override: dict) -> dict:
    merged = dict(base)
    for key, value in override.items():
        if isinstance(value, dict) and isinstance(merged.get(key), dict):
            merged[key] = merge_dicts(merged[key], value)
        else:
            merged[key] = value
    return merged


def load_config() -> dict:
    loaded = {}
    if CONFIG_PATH.is_file():
        loaded = json.loads(CONFIG_PATH.read_text(encoding="utf-8"))
    return merge_dicts(DEFAULT_CONFIG, loaded)


CONFIG = load_config()


def db() -> sqlite3.Connection:
    database_path = Path(CONFIG["database"]["path"])
    database_path.parent.mkdir(parents=True, exist_ok=True)
    connection = sqlite3.connect(database_path)
    connection.row_factory = sqlite3.Row
    connection.execute("PRAGMA journal_mode = WAL")
    connection.execute("PRAGMA busy_timeout = 5000")
    return connection


def ensure_schema() -> None:
    schema = (BASE_DIR / "sql" / "schema.sqlite.sql").read_text(encoding="utf-8")
    with db() as connection:
        connection.executescript(schema)


def first(payload: dict[str, str], *keys: str, default: str = "") -> str:
    for key in keys:
        if key in payload:
            return payload[key]
    return default


def normalize_payload(payload: dict[str, str]) -> dict[str, object]:
    def as_int(value: str) -> int:
        try:
            return int(value.strip() or "0")
        except ValueError:
            return 0

    def as_bool(value: str) -> bool:
        return value.strip().lower() in {"1", "true", "on", "yes"}

    return {
        "first_name": first(payload, "first_name", "first-name", "firstName").strip(),
        "last_name": first(payload, "last_name", "last-name", "lastName").strip(),
        "email": first(payload, "email").strip(),
        "phone": first(payload, "phone").strip(),
        "birth_month": first(payload, "birth_month", "birth-month", "birthMonth").strip(),
        "birth_day": as_int(first(payload, "birth_day", "birth-day", "birthDay")),
        "birth_year": as_int(first(payload, "birth_year", "birth-year", "birthYear")),
        "gender": first(payload, "gender").strip(),
        "citizenship": first(payload, "citizenship", "Nationality", "nationality").strip(),
        "entry_term": first(payload, "entry_term", "entry-term", "entryTerm").strip(),
        "program": first(payload, "program").strip(),
        "school_name": first(payload, "school_name", "school-name", "schoolName").strip(),
        "personal_statement": first(payload, "personal_statement", "personal-statement", "statement").strip(),
        "portfolio_url": first(payload, "portfolio_url", "portfolio-url", "portfolio").strip(),
        "additional_notes": first(payload, "additional_notes", "additional-notes", "notes").strip(),
        "application_confirmation": as_bool(first(payload, "application_confirmation", "application-confirmation")),
        "honeypot": first(payload, "website").strip(),
    }


def is_valid_email(email: str) -> bool:
    if len(email) > 254 or email.count("@") != 1:
        return False

    local_part = email.split("@", 1)[0]
    if not local_part or local_part.startswith(".") or local_part.endswith(".") or ".." in local_part:
        return False

    return EMAIL_PATTERN.fullmatch(email) is not None


def validate_application(app: dict[str, object]) -> list[str]:
    errors: list[str] = []
    if len(str(app["first_name"])) < 2:
        errors.append("First name must be at least 2 characters.")
    if len(str(app["last_name"])) < 2:
        errors.append("Last name must be at least 2 characters.")
    email = str(app["email"])
    if not is_valid_email(email):
        errors.append("Please provide a valid email address.")
    if len(str(app["phone"])) < 5:
        errors.append("Phone number must be at least 5 characters.")
    if str(app["birth_month"]) not in VALID_BIRTH_MONTHS:
        errors.append("Please select a valid birth month.")
    if not 1 <= int(app["birth_day"]) <= 31:
        errors.append("Birth day must be between 1 and 31.")
    current_year = datetime.now(timezone.utc).year
    if not 1900 <= int(app["birth_year"]) <= current_year:
        errors.append("Birth year must be a valid year.")
    if str(app["gender"]) not in VALID_GENDERS:
        errors.append("Please select a valid gender.")
    if len(str(app["citizenship"])) < 2:
        errors.append("Please enter a valid citizenship country or region.")
    if str(app["entry_term"]) not in VALID_TERMS:
        errors.append("Invalid entry term selected.")
    if str(app["program"]) not in VALID_PROGRAMS:
        errors.append("Invalid program selection.")
    if len(str(app["school_name"])) < 2:
        errors.append("Please enter your current or most recent school name.")
    statement = str(app["personal_statement"])
    if len(statement) < 30:
        errors.append("Personal statement must be at least 30 characters.")
    elif len(statement) > 5000:
        errors.append("Personal statement cannot exceed 5000 characters.")
    if not str(app["portfolio_url"]).startswith(("http://", "https://")):
        errors.append("Portfolio or sample link must start with http:// or https://")
    if len(str(app["additional_notes"])) > 2000:
        errors.append("Additional context cannot exceed 2000 characters.")
    if app["application_confirmation"] is not True:
        errors.append("You must confirm that the information provided is accurate.")
    return errors


def recaptcha_config() -> dict:
    config = CONFIG.get("recaptcha", {})
    return config if isinstance(config, dict) else {}


def recaptcha_secret_key() -> str:
    config = recaptcha_config()
    env_name = str(config.get("secret_key_env") or "WCU_RECAPTCHA_SECRET_KEY").strip()
    env_secret = os.environ.get(env_name, "") if env_name else ""
    return str(env_secret or config.get("secret_key") or "").strip()


def recaptcha_is_enabled() -> bool:
    config = recaptcha_config()
    return config.get("enabled") is True or recaptcha_secret_key() != ""


def recaptcha_token_from_payload(payload: dict[str, str]) -> str:
    return first(payload, "recaptcha_token", "g-recaptcha-response", "g_recaptcha_response").strip()


def recaptcha_verification_errors(payload: dict[str, str], client_ip: str) -> list[str]:
    if not recaptcha_is_enabled():
        return []

    secret_key = recaptcha_secret_key()
    if not secret_key:
        return ["reCAPTCHA protection is not configured."]

    token = recaptcha_token_from_payload(payload)
    if not token:
        return ["Please complete the reCAPTCHA challenge."]

    config = recaptcha_config()
    verify_url = str(config.get("verify_url") or DEFAULT_CONFIG["recaptcha"]["verify_url"])
    request_payload = {
        "secret": secret_key,
        "response": token,
    }
    if client_ip:
        request_payload["remoteip"] = client_ip

    try:
        timeout_seconds = float(config.get("timeout_seconds") or DEFAULT_CONFIG["recaptcha"]["timeout_seconds"])
    except (TypeError, ValueError):
        timeout_seconds = float(DEFAULT_CONFIG["recaptcha"]["timeout_seconds"])
    if timeout_seconds <= 0:
        timeout_seconds = float(DEFAULT_CONFIG["recaptcha"]["timeout_seconds"])
    request_body = urlencode(request_payload).encode("utf-8")
    request = urlrequest.Request(
        verify_url,
        data=request_body,
        headers={"Content-Type": "application/x-www-form-urlencoded"},
        method="POST",
    )

    try:
        with urlrequest.urlopen(request, timeout=timeout_seconds) as response:
            result = json.loads(response.read().decode("utf-8"))
    except (OSError, TimeoutError, ValueError, urlerror.URLError, json.JSONDecodeError):
        return ["Unable to verify reCAPTCHA right now. Please try again."]

    if not isinstance(result, dict) or result.get("success") is not True:
        return ["reCAPTCHA verification failed. Please try again."]

    configured_hostnames = config.get("allowed_hostnames", []) or []
    if isinstance(configured_hostnames, str):
        configured_hostnames = [configured_hostnames]
    allowed_hostnames = [
        str(hostname).strip().lower()
        for hostname in configured_hostnames
        if str(hostname).strip()
    ]
    response_hostname = str(result.get("hostname") or "").strip().lower()
    if allowed_hostnames and response_hostname not in allowed_hostnames:
        return ["reCAPTCHA verification failed for this site."]

    expected_action = str(config.get("expected_action") or "").strip()
    if expected_action and str(result.get("action") or "").strip() != expected_action:
        return ["reCAPTCHA verification failed for this action."]

    minimum_score = config.get("minimum_score")
    if minimum_score is not None and "score" in result:
        try:
            score = float(result.get("score"))
            required_score = float(minimum_score)
        except (TypeError, ValueError):
            return ["reCAPTCHA verification returned an invalid score."]
        if score < required_score:
            return ["reCAPTCHA verification score was too low."]

    return []


def insert_application(app: dict[str, object], client_ip: str, user_agent: str, origin_url: str) -> int:
    created_at = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    with db() as connection:
        cursor = connection.execute(
            """
            INSERT INTO applications (
                first_name, last_name, email, phone, birth_month, birth_day, birth_year,
                gender, citizenship, entry_term, program, school_name, personal_statement,
                portfolio_url, additional_notes, ip_address, user_agent, origin_url, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            [
                app["first_name"], app["last_name"], app["email"], app["phone"], app["birth_month"],
                app["birth_day"], app["birth_year"], app["gender"], app["citizenship"], app["entry_term"],
                app["program"], app["school_name"], app["personal_statement"], app["portfolio_url"],
                app["additional_notes"] or None, client_ip or None, user_agent or None, origin_url or None, created_at,
            ],
        )
        return int(cursor.lastrowid)


def verify_scrypt_secret(secret: bytes, stored_hash: str) -> bool:
    parts = stored_hash.split("$")
    if len(parts) != 6:
        return False

    try:
        _, n_value, r_value, p_value, salt_hex, expected_hex = parts
        actual = hashlib.scrypt(
            secret,
            salt=bytes.fromhex(salt_hex),
            n=int(n_value),
            r=int(r_value),
            p=int(p_value),
            dklen=len(bytes.fromhex(expected_hex)),
            maxmem=SCRYPT_MAXMEM,
        ).hex()
    except (ValueError, TypeError):
        return False

    return hmac.compare_digest(actual, expected_hex)


def verify_password(password: str, stored_hash: str) -> bool:
    normalized_hash = stored_hash.strip()
    password_bytes = password.encode("utf-8")

    if normalized_hash.startswith("scrypt$"):
        return verify_scrypt_secret(password_bytes, normalized_hash)

    if normalized_hash.startswith("scrypt-sha256$"):
        legacy_digest = hashlib.sha256(password_bytes).hexdigest().encode("ascii")
        return verify_scrypt_secret(legacy_digest, "scrypt$" + normalized_hash.split("$", 1)[1])

    if (
        normalized_hash.startswith("sha256$")
        and os.environ.get("WCU_ALLOW_LEGACY_SHA256_ADMIN_HASH") == "1"
    ):
        expected = normalized_hash[len("sha256$"):]
        actual = hashlib.sha256(password_bytes).hexdigest()
        return hmac.compare_digest(actual, expected)

    return False


def admin_csrf_secret() -> bytes:
    configured_secret = os.environ.get("WCU_ADMIN_CSRF_SECRET")
    if configured_secret:
        return configured_secret.encode("utf-8")

    admin_config = CONFIG.get("admin", {})
    return str(admin_config.get("csrf_secret") or admin_config.get("password_hash") or "").encode("utf-8")


def make_admin_csrf_token(now: float | None = None) -> str:
    bucket = int((now if now is not None else time.time()) // ADMIN_CSRF_TTL_SECONDS)
    message = f"{CONFIG['admin']['username']}:{bucket}".encode("utf-8")
    mac = hmac.new(admin_csrf_secret(), message, hashlib.sha256).hexdigest()
    return f"{bucket}:{mac}"


def validate_admin_csrf_token(token: str) -> bool:
    try:
        bucket_text, submitted_mac = token.split(":", 1)
        bucket = int(bucket_text)
    except (ValueError, AttributeError):
        return False

    current_bucket = int(time.time() // ADMIN_CSRF_TTL_SECONDS)
    if bucket not in {current_bucket, current_bucket - 1}:
        return False

    message = f"{CONFIG['admin']['username']}:{bucket}".encode("utf-8")
    expected_mac = hmac.new(admin_csrf_secret(), message, hashlib.sha256).hexdigest()
    return hmac.compare_digest(submitted_mac, expected_mac)


def admin_login_key(client_ip: str, username: str) -> str:
    return f"{client_ip}:{username.strip().lower()}"


def recent_admin_failures(key: str) -> list[float]:
    cutoff = time.time() - ADMIN_LOGIN_WINDOW_SECONDS
    failures = [attempt for attempt in ADMIN_LOGIN_FAILURES.get(key, []) if attempt >= cutoff]
    ADMIN_LOGIN_FAILURES[key] = failures
    return failures


def admin_login_is_limited(client_ip: str, username: str) -> bool:
    return len(recent_admin_failures(admin_login_key(client_ip, username))) >= ADMIN_LOGIN_MAX_FAILURES


def record_admin_login_failure(client_ip: str, username: str) -> None:
    key = admin_login_key(client_ip, username)
    failures = recent_admin_failures(key)
    failures.append(time.time())
    ADMIN_LOGIN_FAILURES[key] = failures


def clear_admin_login_failures(client_ip: str, username: str) -> None:
    ADMIN_LOGIN_FAILURES.pop(admin_login_key(client_ip, username), None)


class Handler(BaseHTTPRequestHandler):
    server_version = "WCUBackend/1.0"

    def log_message(self, fmt: str, *args) -> None:
        return

    def allowed_origin(self) -> bool:
        origin = (self.headers.get("Origin") or "").strip()
        allowed = CONFIG["cors"]["allowed_origins"]
        return origin == "" or not allowed or origin in allowed or "*" in allowed

    def write_cors(self) -> None:
        origin = (self.headers.get("Origin") or "").strip()
        if origin and self.allowed_origin():
            self.send_header("Access-Control-Allow-Origin", origin)
            self.send_header("Vary", "Origin")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Accept, Content-Type, Authorization")

    def read_body(self) -> dict[str, str]:
        raw = self.rfile.read(int(self.headers.get("Content-Length") or "0"))
        ctype = (self.headers.get("Content-Type") or "").lower().split(";", 1)[0].strip()
        if ctype == "application/json":
            try:
                parsed = json.loads(raw.decode("utf-8") or "{}")
            except (UnicodeDecodeError, json.JSONDecodeError) as exc:
                raise RequestParseError("Request body is not valid JSON.") from exc
            if not isinstance(parsed, dict):
                raise RequestParseError("Request body must be a JSON object.")
            return {str(k): "" if v is None else str(v) for k, v in parsed.items()}
        if ctype not in SUPPORTED_FORM_CONTENT_TYPES:
            if ctype == "" and raw == b"":
                return {}
            raise UnsupportedMediaTypeError("Unsupported Content-Type. Use application/json or application/x-www-form-urlencoded.")
        try:
            parsed = parse_qs(raw.decode("utf-8"), keep_blank_values=True)
        except UnicodeDecodeError as exc:
            raise RequestParseError("Request body could not be decoded.") from exc
        return {k: v[-1] if v else "" for k, v in parsed.items()}

    def require_admin(self) -> bool:
        header = self.headers.get("Authorization") or ""
        if not header.startswith("Basic "):
            self.send_response(HTTPStatus.UNAUTHORIZED)
            self.send_header("WWW-Authenticate", 'Basic realm="WCU Admin"')
            self.end_headers()
            return False
        try:
            decoded = base64.b64decode(header.split(" ", 1)[1]).decode("utf-8")
            username, password = decoded.split(":", 1)
        except Exception:
            record_admin_login_failure(self.client_address[0], "")
            self.send_error(HTTPStatus.UNAUTHORIZED)
            return False

        if admin_login_is_limited(self.client_address[0], username):
            self.send_response(HTTPStatus.TOO_MANY_REQUESTS)
            self.send_header("Retry-After", str(ADMIN_LOGIN_WINDOW_SECONDS))
            self.end_headers()
            return False

        username_matches = hmac.compare_digest(username, CONFIG["admin"]["username"])
        password_matches = verify_password(password, CONFIG["admin"]["password_hash"])
        if not username_matches or not password_matches:
            record_admin_login_failure(self.client_address[0], username)
            self.send_response(HTTPStatus.UNAUTHORIZED)
            self.send_header("WWW-Authenticate", 'Basic realm="WCU Admin"')
            self.end_headers()
            return False
        clear_admin_login_failures(self.client_address[0], username)
        return True

    def send_json(self, status: HTTPStatus, payload: dict, head_only: bool = False) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.write_cors()
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        if not head_only:
            self.wfile.write(body)

    def do_OPTIONS(self) -> None:
        self.send_response(HTTPStatus.NO_CONTENT)
        self.write_cors()
        self.end_headers()

    def do_GET(self) -> None:
        parsed = urlparse(self.path)
        if parsed.path == "/api/application.php":
            self.send_json(HTTPStatus.OK, {"ok": True, "service": "wcu-applications-api"})
            return
        if parsed.path in {"/admin", "/admin/"}:
            if self.require_admin():
                self.render_admin(parsed)
            return
        if parsed.path == "/admin/export.csv":
            if self.require_admin():
                query = parse_qs(parsed.query, keep_blank_values=True)
                token = (query.get("csrf_token") or [""])[-1]
                if not validate_admin_csrf_token(token):
                    self.send_error(HTTPStatus.FORBIDDEN)
                    return
                self.export_csv(parsed)
            return
        if self.serve_static(parsed.path):
            return
        self.send_error(HTTPStatus.NOT_FOUND)

    def do_POST(self) -> None:
        parsed = urlparse(self.path)
        if parsed.path == "/api/application.php":
            self.handle_api_post()
            return
        if parsed.path == "/admin/delete":
            if self.require_admin():
                try:
                    data = self.read_body()
                    application_id = int(data.get("id", "0") or "0")
                except UnsupportedMediaTypeError as exc:
                    self.send_error(HTTPStatus.UNSUPPORTED_MEDIA_TYPE, str(exc))
                    return
                except (RequestParseError, ValueError):
                    self.send_error(HTTPStatus.BAD_REQUEST)
                    return
                if not validate_admin_csrf_token(data.get("csrf_token", "")):
                    self.send_error(HTTPStatus.FORBIDDEN)
                    return
                with db() as connection:
                    connection.execute("DELETE FROM applications WHERE id = ?", [application_id])
                    connection.commit()
                self.send_response(HTTPStatus.SEE_OTHER)
                self.send_header("Location", "/admin/")
                self.end_headers()
            return
        self.send_error(HTTPStatus.NOT_FOUND)

    def do_HEAD(self) -> None:
        parsed = urlparse(self.path)
        if parsed.path == "/api/application.php":
            self.send_json(HTTPStatus.OK, {"ok": True, "service": "wcu-applications-api"}, head_only=True)
            return
        if parsed.path in {"/admin", "/admin/"}:
            if self.require_admin():
                self.send_response(HTTPStatus.OK)
                self.send_header("Content-Type", "text/html; charset=utf-8")
                self.end_headers()
            return
        if parsed.path == "/admin/export.csv":
            if self.require_admin():
                self.send_response(HTTPStatus.OK)
                self.send_header("Content-Type", "text/csv; charset=utf-8")
                self.end_headers()
            return
        if self.serve_static(parsed.path, head_only=True):
            return
        self.send_error(HTTPStatus.NOT_FOUND)

    def static_path_for(self, request_path: str) -> Path | None:
        normalized = request_path or "/"
        if normalized.startswith(("/api", "/admin")):
            return None

        relative = normalized.lstrip("/")
        target = STATIC_ROOT / relative if relative else STATIC_ROOT

        if normalized.endswith("/"):
            target = target / "index.html"
        elif target.is_dir():
            target = target / "index.html"

        if not target.exists() and "." not in Path(relative).name and relative:
            html_target = STATIC_ROOT / f"{relative}.html"
            if html_target.exists():
                target = html_target

        try:
            resolved = target.resolve()
        except FileNotFoundError:
            resolved = target

        if STATIC_ROOT not in resolved.parents and resolved != STATIC_ROOT:
            return None
        return resolved

    def serve_static(self, request_path: str, head_only: bool = False) -> bool:
        target = self.static_path_for(request_path)
        if target is None or not target.is_file():
            return False

        content_type = mimetypes.guess_type(str(target))[0] or "application/octet-stream"
        payload = target.read_bytes()

        self.send_response(HTTPStatus.OK)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        if not head_only:
            self.wfile.write(payload)
        return True

    def handle_api_post(self) -> None:
        if not self.allowed_origin():
            self.send_json(HTTPStatus.FORBIDDEN, {"ok": False, "errors": ["This origin is not allowed to submit applications."]})
            return
        try:
            payload = self.read_body()
            app = normalize_payload(payload)
        except UnsupportedMediaTypeError as exc:
            self.send_json(HTTPStatus.UNSUPPORTED_MEDIA_TYPE, {"ok": False, "errors": [str(exc)]})
            return
        except RequestParseError as exc:
            self.send_json(HTTPStatus.BAD_REQUEST, {"ok": False, "errors": [str(exc)]})
            return
        if str(app["honeypot"]):
            self.send_json(HTTPStatus.BAD_REQUEST, {"ok": False, "errors": ["Spam detected."]})
            return
        recaptcha_errors = recaptcha_verification_errors(payload, self.client_address[0])
        if recaptcha_errors:
            self.send_json(HTTPStatus.BAD_REQUEST, {"ok": False, "errors": recaptcha_errors})
            return
        errors = validate_application(app)
        if errors:
            self.send_json(HTTPStatus.UNPROCESSABLE_ENTITY, {"ok": False, "errors": errors})
            return
        application_id = insert_application(app, self.client_address[0], self.headers.get("User-Agent") or "", self.headers.get("Origin") or "")
        self.send_json(HTTPStatus.CREATED, {"ok": True, "message": "Application submitted successfully.", "application_id": application_id, "email_sent": False})

    def render_admin(self, parsed) -> None:
        query = parse_qs(parsed.query, keep_blank_values=True)
        filters = {"q": (query.get("q") or [""])[-1], "entry_term": (query.get("entry_term") or [""])[-1], "program": (query.get("program") or [""])[-1]}
        selected_id = int((query.get("id") or ["0"])[-1] or "0")
        csrf_token = make_admin_csrf_token()
        export_params = {**filters, "csrf_token": csrf_token}
        clauses, params = [], []
        if filters["q"]:
            search = f"%{filters['q']}%"
            clauses.append("(first_name LIKE ? OR last_name LIKE ? OR (first_name || ' ' || last_name) LIKE ? OR email LIKE ? OR phone LIKE ?)")
            params.extend([search, search, search, search, search])
        if filters["entry_term"] in VALID_TERMS:
            clauses.append("entry_term = ?")
            params.append(filters["entry_term"])
        if filters["program"] in VALID_PROGRAMS:
            clauses.append("program = ?")
            params.append(filters["program"])
        where_sql = f" WHERE {' AND '.join(clauses)}" if clauses else ""
        with db() as connection:
            rows = connection.execute(f"SELECT id, first_name, last_name, program, entry_term, created_at FROM applications{where_sql} ORDER BY id DESC LIMIT 200", params).fetchall()
            count = connection.execute(f"SELECT COUNT(*) FROM applications{where_sql}", params).fetchone()[0]
            selected = connection.execute("SELECT * FROM applications WHERE id = ?", [selected_id]).fetchone() if selected_id > 0 else None
        items = "".join(
            f"<li><a href=\"/admin/?{urlencode({'id': row['id'], 'q': filters['q'], 'entry_term': filters['entry_term'], 'program': filters['program']})}\">#{row['id']} {html.escape(row['first_name'])} {html.escape(row['last_name'])}</a><br><small>{html.escape(row['program'])} · {html.escape(row['entry_term'])} · {html.escape(row['created_at'])}</small></li>"
            for row in rows
        ) or "<li>No applications yet.</li>"
        detail = "<p>Select an application to inspect it.</p>"
        if selected is not None:
            detail = f"""
            <h3>Application #{selected['id']}</h3>
            <p><strong>Name:</strong> {html.escape(selected['first_name'])} {html.escape(selected['last_name'])}</p>
            <p><strong>Email:</strong> {html.escape(selected['email'])}</p>
            <p><strong>Program:</strong> {html.escape(selected['program'])}</p>
            <p><strong>Entry Term:</strong> {html.escape(selected['entry_term'])}</p>
            <p><strong>Statement:</strong><br>{html.escape(selected['personal_statement'])}</p>
            <p><strong>Portfolio:</strong> <a href="{html.escape(selected['portfolio_url'])}" target="_blank" rel="noopener noreferrer">{html.escape(selected['portfolio_url'])}</a></p>
            <form method="post" action="/admin/delete"><input type="hidden" name="csrf_token" value="{html.escape(csrf_token)}"><input type="hidden" name="id" value="{selected['id']}"><button type="submit">Delete application</button></form>
            """
        body = f"""<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>WCU Admin</title>
<style>body{{font-family:Arial,sans-serif;background:#f7f3ea;margin:0;padding:24px}}a{{color:#0f5a7a}}.grid{{display:grid;grid-template-columns:minmax(280px,360px) 1fr;gap:16px}}.card{{background:#fff;border:1px solid #d8d1c4;border-radius:12px;padding:16px}}label{{display:block;margin-bottom:10px}}input,select,button{{font:inherit;padding:8px 10px}}ul{{padding-left:18px}}</style></head>
<body><h1>WCU Admissions Admin</h1><p>Total applications: {count} · <a href="/admin/export.csv?{urlencode(export_params)}">Export CSV</a></p>
<div class="card" style="margin-bottom:16px"><form method="get" action="/admin/"><label>Search <input type="text" name="q" value="{html.escape(filters['q'])}"></label><label>Entry Term <select name="entry_term"><option value="">All</option>{''.join(f'<option value="{html.escape(term)}" {"selected" if filters["entry_term"] == term else ""}>{html.escape(term)}</option>' for term in VALID_TERMS)}</select></label><label>Program <select name="program"><option value="">All</option>{''.join(f'<option value="{html.escape(program)}" {"selected" if filters["program"] == program else ""}>{html.escape(program)}</option>' for program in VALID_PROGRAMS)}</select></label><button type="submit">Apply filters</button></form></div>
<div class="grid"><div class="card"><ul>{items}</ul></div><div class="card">{detail}</div></div></body></html>"""
        self.send_response(HTTPStatus.OK)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.end_headers()
        self.wfile.write(body.encode("utf-8"))

    def export_csv(self, parsed) -> None:
        query = parse_qs(parsed.query, keep_blank_values=True)
        filters = {"q": (query.get("q") or [""])[-1], "entry_term": (query.get("entry_term") or [""])[-1], "program": (query.get("program") or [""])[-1]}
        clauses, params = [], []
        if filters["q"]:
            search = f"%{filters['q']}%"
            clauses.append("(first_name LIKE ? OR last_name LIKE ? OR (first_name || ' ' || last_name) LIKE ? OR email LIKE ? OR phone LIKE ?)")
            params.extend([search, search, search, search, search])
        if filters["entry_term"] in VALID_TERMS:
            clauses.append("entry_term = ?")
            params.append(filters["entry_term"])
        if filters["program"] in VALID_PROGRAMS:
            clauses.append("program = ?")
            params.append(filters["program"])
        where_sql = f" WHERE {' AND '.join(clauses)}" if clauses else ""
        with db() as connection:
            rows = connection.execute(f"SELECT * FROM applications{where_sql} ORDER BY id DESC", params).fetchall()
        output = io.StringIO()
        writer = csv.writer(output)
        writer.writerow(["id", "first_name", "last_name", "email", "phone", "birth_month", "birth_day", "birth_year", "gender", "citizenship", "entry_term", "program", "school_name", "personal_statement", "portfolio_url", "additional_notes", "ip_address", "user_agent", "origin_url", "created_at"])
        for row in rows:
            writer.writerow([row[key] for key in row.keys()])
        payload = output.getvalue().encode("utf-8-sig")
        self.send_response(HTTPStatus.OK)
        self.send_header("Content-Type", "text/csv; charset=utf-8")
        self.send_header("Content-Disposition", f'attachment; filename="wcu-applications-{datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")}.csv"')
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)


class WCUHTTPServer(ThreadingHTTPServer):
    daemon_threads = True

    def handle_error(self, request, client_address) -> None:  # type: ignore[override]
        _, exc, _ = sys.exc_info()
        if isinstance(exc, (BrokenPipeError, ConnectionResetError, TimeoutError)):
            return
        super().handle_error(request, client_address)


def main() -> None:
    ensure_schema()
    server = WCUHTTPServer((SERVER_HOST, SERVER_PORT), Handler)
    print(f"WCU backend listening on {SERVER_HOST}:{SERVER_PORT}", flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
