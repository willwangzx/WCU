#!/usr/bin/env python3
from __future__ import annotations

import base64
import csv
import hashlib
import html
import io
import json
import sqlite3
from datetime import datetime, timezone
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlencode, urlparse


BASE_DIR = Path(__file__).resolve().parent
CONFIG_PATH = BASE_DIR / "config.python.json"
DEFAULT_CONFIG = {
    "cors": {"allowed_origins": []},
    "database": {"path": "/var/lib/wcu-data/wcu.sqlite"},
    "admin": {"username": "admin", "password_hash": ""},
}


class RequestParseError(ValueError):
    pass

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


def validate_application(app: dict[str, object]) -> list[str]:
    errors: list[str] = []
    if len(str(app["first_name"])) < 2:
        errors.append("First name must be at least 2 characters.")
    if len(str(app["last_name"])) < 2:
        errors.append("Last name must be at least 2 characters.")
    email = str(app["email"])
    if "@" not in email or "." not in email.split("@")[-1]:
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


def verify_password(password: str, stored_hash: str) -> bool:
    if not stored_hash.startswith("sha256$"):
        return False
    digest = hashlib.sha256(password.encode("utf-8")).hexdigest()
    return digest == stored_hash[len("sha256$"):]


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
        ctype = (self.headers.get("Content-Type") or "").lower()
        if "application/json" in ctype:
            try:
                parsed = json.loads(raw.decode("utf-8") or "{}")
            except (UnicodeDecodeError, json.JSONDecodeError) as exc:
                raise RequestParseError("Request body is not valid JSON.") from exc
            if not isinstance(parsed, dict):
                raise RequestParseError("Request body must be a JSON object.")
            return {str(k): "" if v is None else str(v) for k, v in parsed.items()}
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
            self.send_error(HTTPStatus.UNAUTHORIZED)
            return False
        if username != CONFIG["admin"]["username"] or not verify_password(password, CONFIG["admin"]["password_hash"]):
            self.send_response(HTTPStatus.UNAUTHORIZED)
            self.send_header("WWW-Authenticate", 'Basic realm="WCU Admin"')
            self.end_headers()
            return False
        return True

    def send_json(self, status: HTTPStatus, payload: dict) -> None:
        self.send_response(status)
        self.write_cors()
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.end_headers()
        self.wfile.write(json.dumps(payload, ensure_ascii=False).encode("utf-8"))

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
                self.export_csv(parsed)
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
                except (RequestParseError, ValueError):
                    self.send_error(HTTPStatus.BAD_REQUEST)
                    return
                with db() as connection:
                    connection.execute("DELETE FROM applications WHERE id = ?", [application_id])
                    connection.commit()
                self.send_response(HTTPStatus.SEE_OTHER)
                self.send_header("Location", "/admin/")
                self.end_headers()
            return
        self.send_error(HTTPStatus.NOT_FOUND)

    def handle_api_post(self) -> None:
        if not self.allowed_origin():
            self.send_json(HTTPStatus.FORBIDDEN, {"ok": False, "errors": ["This origin is not allowed to submit applications."]})
            return
        try:
            app = normalize_payload(self.read_body())
        except RequestParseError as exc:
            self.send_json(HTTPStatus.BAD_REQUEST, {"ok": False, "errors": [str(exc)]})
            return
        if str(app["honeypot"]):
            self.send_json(HTTPStatus.BAD_REQUEST, {"ok": False, "errors": ["Spam detected."]})
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
            <form method="post" action="/admin/delete"><input type="hidden" name="id" value="{selected['id']}"><button type="submit">Delete application</button></form>
            """
        body = f"""<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>WCU Admin</title>
<style>body{{font-family:Arial,sans-serif;background:#f7f3ea;margin:0;padding:24px}}a{{color:#0f5a7a}}.grid{{display:grid;grid-template-columns:minmax(280px,360px) 1fr;gap:16px}}.card{{background:#fff;border:1px solid #d8d1c4;border-radius:12px;padding:16px}}label{{display:block;margin-bottom:10px}}input,select,button{{font:inherit;padding:8px 10px}}ul{{padding-left:18px}}</style></head>
<body><h1>WCU Admissions Admin</h1><p>Total applications: {count} · <a href="/admin/export.csv">Export CSV</a></p>
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


def main() -> None:
    ensure_schema()
    server = ThreadingHTTPServer(("0.0.0.0", 80), Handler)
    print("WCU backend listening on :80", flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
