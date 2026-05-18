from __future__ import annotations

from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit
import unittest


REPO_ROOT = Path(__file__).resolve().parents[1]

KEY_PAGES = [
    REPO_ROOT / "index.html",
    REPO_ROOT / "pages" / "about.html",
    REPO_ROOT / "pages" / "admissions.html",
    REPO_ROOT / "pages" / "apply.html",
    REPO_ROOT / "pages" / "apply-basic.html",
    REPO_ROOT / "pages" / "apply-writing.html",
    REPO_ROOT / "pages" / "application-success.html",
]

KEY_ASSETS = [
    REPO_ROOT / "assets" / "favicon.ico",
    REPO_ROOT / "assets" / "css" / "styles.css",
    REPO_ROOT / "assets" / "js" / "script.js",
    REPO_ROOT / "assets" / "js" / "site-config.js",
]

EXPECTED_BASIC_FIELDS = [
    'name="firstName"',
    'name="lastName"',
    'name="email"',
    'name="phone"',
    'name="Nationality"',
    'name="entryTerm"',
    'name="program"',
    'name="schoolName"',
]

EXPECTED_WRITING_FIELDS = [
    'name="website"',
    'name="first-name"',
    'name="last-name"',
    'name="email"',
    'name="phone"',
    'name="birth-month"',
    'name="birth-day"',
    'name="birth-year"',
    'name="gender"',
    'name="citizenship"',
    'name="entry-term"',
    'name="program"',
    'name="school-name"',
    'name="statement"',
    'name="portfolio"',
    'name="notes"',
    'name="application-confirmation"',
]


class LocalReferenceParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.references: list[tuple[str, str, str]] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        tracked_attributes = {"href", "src", "action"}
        for attribute, value in attrs:
            if attribute in tracked_attributes and value:
                self.references.append((tag, attribute, value.strip()))


def is_external_reference(reference: str) -> bool:
    lowered = reference.lower()
    return lowered.startswith(
        ("http://", "https://", "//", "mailto:", "tel:", "javascript:", "data:")
    )


def normalize_reference(reference: str) -> str:
    split_result = urlsplit(reference)
    return split_result.path.strip()


def resolve_local_reference(source_file: Path, reference: str) -> Path | None:
    normalized = normalize_reference(reference)

    if not normalized or normalized.startswith("#") or is_external_reference(reference):
        return None

    alias_reference = normalized.lstrip("./")
    if alias_reference.startswith("api/") or alias_reference.startswith("../api/"):
        mapped = alias_reference.removeprefix("../")
        return (REPO_ROOT / "server" / "public" / mapped).resolve(strict=False)

    if alias_reference.startswith("admin/") or alias_reference.startswith("../admin/"):
        mapped = alias_reference.removeprefix("../")
        return (REPO_ROOT / "server" / "public" / mapped).resolve(strict=False)

    if normalized.startswith("/"):
        candidate = REPO_ROOT / normalized.lstrip("/")
    else:
        candidate = source_file.parent / normalized

    if candidate.is_dir():
        candidate = candidate / "index.html"

    return candidate.resolve(strict=False)


class SiteSmokeTests(unittest.TestCase):
    def test_key_pages_exist(self) -> None:
        for page in KEY_PAGES:
            with self.subTest(page=page.relative_to(REPO_ROOT).as_posix()):
                self.assertTrue(page.exists(), f"Missing expected page: {page}")

    def test_shared_assets_exist(self) -> None:
        for asset in KEY_ASSETS:
            with self.subTest(asset=asset.relative_to(REPO_ROOT).as_posix()):
                self.assertTrue(asset.exists(), f"Missing shared asset: {asset}")

    def test_local_html_references_resolve(self) -> None:
        html_files = sorted(REPO_ROOT.rglob("*.html"))

        for html_file in html_files:
            parser = LocalReferenceParser()
            parser.feed(html_file.read_text(encoding="utf-8"))

            for tag, attribute, reference in parser.references:
                target = resolve_local_reference(html_file, reference)
                if target is None:
                    continue

                with self.subTest(
                    source=html_file.relative_to(REPO_ROOT).as_posix(),
                    tag=tag,
                    attribute=attribute,
                    reference=reference,
                ):
                    self.assertTrue(
                        target.exists(),
                        (
                            f"{html_file.relative_to(REPO_ROOT)} uses {attribute}={reference!r} "
                            f"but the target does not exist at {target}"
                        ),
                    )

    def test_application_entry_page_points_to_split_flow(self) -> None:
        apply_page = (REPO_ROOT / "pages" / "apply.html").read_text(encoding="utf-8")
        self.assertIn('href="apply-basic.html"', apply_page)
        self.assertIn("Start Application", apply_page)

    def test_basic_information_page_has_expected_fields(self) -> None:
        basic_page = (REPO_ROOT / "pages" / "apply-basic.html").read_text(encoding="utf-8")
        self.assertIn('id="basicInformationForm"', basic_page)

        for field in EXPECTED_BASIC_FIELDS:
            with self.subTest(field=field):
                self.assertIn(field, basic_page)

    def test_writing_materials_page_has_expected_fields(self) -> None:
        writing_page = (REPO_ROOT / "pages" / "apply-writing.html").read_text(encoding="utf-8")
        self.assertIn('id="writingMaterialsForm"', writing_page)
        self.assertIn('action="../api/application.php"', writing_page)
        self.assertIn('id="applicationMessage"', writing_page)
        self.assertIn('src="../assets/js/site-config.js"', writing_page)

        for field in EXPECTED_WRITING_FIELDS:
            with self.subTest(field=field):
                self.assertIn(field, writing_page)

    def test_application_api_file_exists(self) -> None:
        api_file = REPO_ROOT / "server" / "public" / "api" / "application.php"
        self.assertTrue(api_file.exists(), "Expected application endpoint server/public/api/application.php")

    def test_python_backend_files_exist(self) -> None:
        backend_file = REPO_ROOT / "server" / "python_backend.py"
        config_file = REPO_ROOT / "server" / "config.python.example.json"
        self.assertTrue(backend_file.exists(), "Expected Python backend entrypoint")
        self.assertTrue(config_file.exists(), "Expected Python backend config template")

    def test_front_proxy_file_exists(self) -> None:
        front_proxy_file = REPO_ROOT / "server" / "front_proxy.py"
        self.assertTrue(front_proxy_file.exists(), "Expected front proxy entrypoint")


if __name__ == "__main__":
    unittest.main()
