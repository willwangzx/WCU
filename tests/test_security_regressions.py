from __future__ import annotations

import hashlib
import io
import os
from pathlib import Path
import unittest

from server import python_backend


REPO_ROOT = Path(__file__).resolve().parents[1]


def make_test_scrypt_hash(prefix: str, secret: bytes) -> str:
    salt = b"wcu-test-salt"
    digest = hashlib.scrypt(secret, salt=salt, n=2, r=1, p=1, dklen=16, maxmem=1024 * 1024)
    return f"{prefix}$2$1$1${salt.hex()}${digest.hex()}"


class SecurityRegressionTests(unittest.TestCase):
    def test_python_admin_password_hashes_use_slow_formats(self) -> None:
        self.assertTrue(
            python_backend.verify_password("correct horse", make_test_scrypt_hash("scrypt", b"correct horse"))
        )

        legacy_digest = hashlib.sha256(b"correct horse").hexdigest().encode("ascii")
        self.assertTrue(
            python_backend.verify_password("correct horse", make_test_scrypt_hash("scrypt-sha256", legacy_digest))
        )

        legacy_sha256 = "sha256$" + hashlib.sha256(b"correct horse").hexdigest()
        self.assertFalse(python_backend.verify_password("correct horse", legacy_sha256))

    def test_python_backend_email_validation_matches_expected_domains(self) -> None:
        self.assertTrue(python_backend.is_valid_email("student@example.edu"))
        self.assertFalse(python_backend.is_valid_email("student@example"))
        self.assertFalse(python_backend.is_valid_email("student@@example.edu"))

    def test_python_backend_rejects_unsupported_content_type(self) -> None:
        handler = object.__new__(python_backend.Handler)
        handler.headers = {"Content-Length": "3", "Content-Type": "text/plain"}
        handler.rfile = io.BytesIO(b"a=b")

        with self.assertRaises(python_backend.UnsupportedMediaTypeError):
            handler.read_body()

    def test_python_admin_csrf_tokens_validate(self) -> None:
        token = python_backend.make_admin_csrf_token()

        self.assertTrue(python_backend.validate_admin_csrf_token(token))
        self.assertFalse(python_backend.validate_admin_csrf_token("not-a-token"))

    def test_python_recaptcha_requires_token_when_enabled(self) -> None:
        original_config = python_backend.CONFIG.get("recaptcha")
        original_env_secret = os.environ.pop("WCU_RECAPTCHA_SECRET_KEY", None)

        try:
            python_backend.CONFIG["recaptcha"] = {
                "enabled": True,
                "secret_key": "test-secret",
                "secret_key_env": "WCU_RECAPTCHA_SECRET_KEY",
            }

            self.assertEqual(
                python_backend.recaptcha_verification_errors({}, "127.0.0.1"),
                ["Please complete the reCAPTCHA challenge."],
            )
        finally:
            if original_config is None:
                python_backend.CONFIG.pop("recaptcha", None)
            else:
                python_backend.CONFIG["recaptcha"] = original_config
            if original_env_secret is not None:
                os.environ["WCU_RECAPTCHA_SECRET_KEY"] = original_env_secret

    def test_split_application_payload_keeps_honeypot_and_hidden_fields(self) -> None:
        script = (REPO_ROOT / "assets" / "js" / "script.js").read_text(encoding="utf-8")

        self.assertIn("function syncSplitHiddenFields", script)
        self.assertIn('splitFirstName: "firstName"', script)
        self.assertIn('website: ""', script)
        self.assertIn("recaptcha_token", script)

    def test_markdown_headings_keep_declared_level(self) -> None:
        formatter = (REPO_ROOT / "assets" / "js" / "content-format.js").read_text(encoding="utf-8")

        self.assertIn("#{1,6}", formatter)
        self.assertNotIn("level + 1", formatter)
        self.assertIn('rel="noopener noreferrer"', formatter)

    def test_php_admin_export_and_links_are_csrf_protected(self) -> None:
        admin_index = (REPO_ROOT / "server" / "public" / "admin" / "index.php").read_text(encoding="utf-8")
        admin_helpers = (REPO_ROOT / "server" / "src" / "admin.php").read_text(encoding="utf-8")

        self.assertIn("validate_csrf_token($_GET['csrf_token'] ?? null)", admin_index)
        self.assertIn("'csrf_token' => $csrfToken", admin_index)
        self.assertIn('rel="noopener noreferrer"', admin_index)
        self.assertIn("function admin_login_is_locked", admin_helpers)

    def test_php_confirmation_email_omits_empty_from_mailbox(self) -> None:
        application_php = (REPO_ROOT / "server" / "src" / "application.php").read_text(encoding="utf-8")

        self.assertIn("function format_mailbox_header", application_php)
        self.assertNotIn(" <>", application_php)

    def test_php_application_path_has_recaptcha_verification(self) -> None:
        api_php = (REPO_ROOT / "server" / "public" / "api" / "application.php").read_text(encoding="utf-8")
        application_php = (REPO_ROOT / "server" / "src" / "application.php").read_text(encoding="utf-8")

        self.assertIn("recaptcha_verification_errors($payload)", api_php)
        self.assertIn("https://www.google.com/recaptcha/api/siteverify", application_php)


if __name__ == "__main__":
    unittest.main()
