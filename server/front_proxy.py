#!/usr/bin/env python3
from __future__ import annotations

import http.client
import os
import ssl
import threading
from functools import partial
from http import HTTPStatus
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlsplit


STATIC_ROOT = Path(os.environ.get("WCU_STATIC_ROOT", "/var/www/wcu-site")).resolve()
BACKEND_HOST = os.environ.get("WCU_BACKEND_HOST", "161.153.87.137")
BACKEND_PORT = int(os.environ.get("WCU_BACKEND_PORT", "80"))
TLS_CERT = os.environ.get("WCU_TLS_CERT", "/opt/wcu-front/certs/wcuedu-origin.crt")
TLS_KEY = os.environ.get("WCU_TLS_KEY", "/opt/wcu-front/certs/wcuedu-origin.key")
PROXY_PREFIXES = ("/api/", "/admin/")
HOP_BY_HOP_HEADERS = {
    "connection",
    "keep-alive",
    "proxy-authenticate",
    "proxy-authorization",
    "te",
    "trailers",
    "transfer-encoding",
    "upgrade",
}


class FrontProxyHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, directory: str | None = None, **kwargs):
        super().__init__(*args, directory=directory or str(STATIC_ROOT), **kwargs)

    def log_message(self, format: str, *args) -> None:
        return

    def do_OPTIONS(self) -> None:
        if self.path.startswith(PROXY_PREFIXES):
            self.forward_to_backend()
            return

        self.send_response(HTTPStatus.NO_CONTENT)
        self.end_headers()

    def do_GET(self) -> None:
        if self.path.startswith(PROXY_PREFIXES):
            self.forward_to_backend()
            return
        super().do_GET()

    def do_HEAD(self) -> None:
        if self.path.startswith(PROXY_PREFIXES):
            self.forward_to_backend()
            return
        super().do_HEAD()

    def do_POST(self) -> None:
        if self.path.startswith(PROXY_PREFIXES):
            self.forward_to_backend()
            return
        self.send_error(HTTPStatus.METHOD_NOT_ALLOWED)

    def end_headers(self) -> None:
        if self.path.startswith(PROXY_PREFIXES):
            self.send_header("X-Forwarded-Proto", "https")
            self.send_header("X-Forwarded-Host", self.headers.get("Host", ""))
        super().end_headers()

    def send_head(self):
        parsed = urlsplit(self.path)
        self.path = parsed.path if parsed.query == "" else self.path
        return super().send_head()

    def respond_upstream_error(self) -> None:
        if self.path.startswith("/api/"):
            payload = b'{"ok": false, "errors": ["The application service is temporarily unavailable. Please try again."]}'
            content_type = "application/json; charset=utf-8"
        else:
            payload = b"Upstream service is temporarily unavailable."
            content_type = "text/plain; charset=utf-8"

        self.send_response(HTTPStatus.BAD_GATEWAY)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        if self.command != "HEAD":
            self.wfile.write(payload)

    def forward_to_backend(self) -> None:
        body = None
        content_length = int(self.headers.get("Content-Length") or "0")
        if content_length > 0:
            body = self.rfile.read(content_length)

        headers = {}
        for key, value in self.headers.items():
            if key.lower() in HOP_BY_HOP_HEADERS:
                continue
            if key.lower() == "host":
                headers[key] = BACKEND_HOST
                continue
            headers[key] = value

        headers["X-Forwarded-For"] = self.client_address[0]
        headers["X-Forwarded-Proto"] = "https"
        headers["X-Forwarded-Host"] = self.headers.get("Host", "")

        connection = http.client.HTTPConnection(BACKEND_HOST, BACKEND_PORT, timeout=30)
        try:
            try:
                connection.request(self.command, self.path, body=body, headers=headers)
                response = connection.getresponse()
                payload = response.read()
            except (OSError, http.client.HTTPException):
                self.respond_upstream_error()
                return

            self.send_response(response.status, response.reason)
            for key, value in response.getheaders():
                if key.lower() in HOP_BY_HOP_HEADERS:
                    continue
                self.send_header(key, value)
            self.end_headers()
            if self.command != "HEAD":
                self.wfile.write(payload)
        finally:
            connection.close()


class RedirectHandler(SimpleHTTPRequestHandler):
    def log_message(self, format: str, *args) -> None:
        return

    def do_GET(self) -> None:
        self.redirect()

    def do_HEAD(self) -> None:
        self.redirect()

    def do_POST(self) -> None:
        self.redirect()

    def do_OPTIONS(self) -> None:
        self.redirect()

    def redirect(self) -> None:
        host = (self.headers.get("Host") or "wcuedu.net").split(":", 1)[0]
        self.send_response(HTTPStatus.MOVED_PERMANENTLY)
        self.send_header("Location", f"https://{host}{self.path}")
        self.end_headers()


def serve_http_redirect() -> None:
    server = ThreadingHTTPServer(("0.0.0.0", 80), RedirectHandler)
    server.serve_forever()


def serve_https_front() -> None:
    handler = partial(FrontProxyHandler, directory=str(STATIC_ROOT))
    server = ThreadingHTTPServer(("0.0.0.0", 443), handler)
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    context.load_cert_chain(TLS_CERT, TLS_KEY)
    server.socket = context.wrap_socket(server.socket, server_side=True)
    server.serve_forever()


def main() -> None:
    http_thread = threading.Thread(target=serve_http_redirect, daemon=True)
    http_thread.start()
    serve_https_front()


if __name__ == "__main__":
    main()
