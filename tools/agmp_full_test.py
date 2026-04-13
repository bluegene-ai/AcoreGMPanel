from __future__ import annotations

import argparse
import contextlib
import dataclasses
import json
import os
import re
import shutil
import subprocess
import sys
import time
import traceback
from pathlib import Path
from typing import Any
from urllib.parse import quote, urlencode, urljoin


def ensure_package(module_name: str, package_name: str, auto_install: bool) -> Any:
    try:
        return __import__(module_name)
    except ImportError:
        if not auto_install:
            raise
        subprocess.check_call([sys.executable, "-m", "pip", "install", package_name])
        return __import__(module_name)


@dataclasses.dataclass
class TestResult:
    name: str
    status: str
    message: str
    details: dict[str, Any] = dataclasses.field(default_factory=dict)
    artifacts: list[str] = dataclasses.field(default_factory=list)


class Reporter:
    def __init__(self, output_dir: Path) -> None:
        self.output_dir = output_dir
        self.output_dir.mkdir(parents=True, exist_ok=True)
        self.results: list[TestResult] = []
        self.started_at = time.strftime("%Y-%m-%d %H:%M:%S")

    def add(self, result: TestResult) -> None:
        self.results.append(result)

    def artifact_path(self, relative_name: str) -> Path:
        target = self.output_dir / relative_name
        target.parent.mkdir(parents=True, exist_ok=True)
        return target

    def write_text(self, relative_name: str, content: str) -> str:
        path = self.artifact_path(relative_name)
        path.write_text(content, encoding="utf-8")
        return str(path)

    def write_bytes(self, relative_name: str, content: bytes) -> str:
        path = self.artifact_path(relative_name)
        path.write_bytes(content)
        return str(path)

    def finalize(self, args: argparse.Namespace) -> tuple[Path, Path]:
        ended_at = time.strftime("%Y-%m-%d %H:%M:%S")
        summary = {
            "started_at": self.started_at,
            "ended_at": ended_at,
            "base_url": args.base_url,
            "deployment_root": str(args.deployment_root),
            "auth_db": args.auth_db,
            "world_dbs": args.world_dbs,
            "characters_dbs": args.characters_dbs,
            "results": [dataclasses.asdict(item) for item in self.results],
        }
        json_path = self.artifact_path("report.json")
        json_path.write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding="utf-8")

        lines = [
            "# AGMP 全功能测试报告",
            "",
            f"- 开始时间: {self.started_at}",
            f"- 结束时间: {ended_at}",
            f"- 基础地址: {args.base_url}",
            f"- 部署目录: {args.deployment_root}",
            "",
            "## 结果汇总",
            "",
        ]
        for item in self.results:
            lines.append(f"- [{item.status}] {item.name}: {item.message}")
            for artifact in item.artifacts:
                lines.append(f"  - artifact: {artifact}")
        md_path = self.artifact_path("report.md")
        md_path.write_text("\n".join(lines) + "\n", encoding="utf-8")
        return json_path, md_path


class AgmpTester:
    def __init__(self, args: argparse.Namespace, reporter: Reporter, requests_mod: Any, pymysql_mod: Any) -> None:
        self.args = args
        self.reporter = reporter
        self.requests = requests_mod
        self.pymysql = pymysql_mod
        self.session = self.requests.Session()
        self.session.headers.update({"User-Agent": "AGMP-Full-Test/1.0", "Accept-Language": "zh-CN,zh;q=0.9,en;q=0.8"})
        self.base_url = args.base_url.rstrip("/") + "/"
        self.csrf_token: str | None = None
        self.created_account_id: int | None = None
        self.created_account_username: str | None = None
        self.generated_backup_dir = self.reporter.output_dir / "backup_generated"
        self.created_mail_ids: list[tuple[int, int]] = []
        self.generated_redeem_code_ids: list[int] = []

    def soap_port_for_realm(self, index: int) -> int:
        ports = getattr(self.args, "soap_ports", None) or []
        if index < len(ports):
            return int(ports[index])
        return int(self.args.soap_port)

    def absolute_url(self, path: str) -> str:
        return urljoin(self.base_url, path.lstrip("/"))

    def record(self, name: str, status: str, message: str, details: dict[str, Any] | None = None, artifacts: list[str] | None = None) -> None:
        self.reporter.add(TestResult(name=name, status=status, message=message, details=details or {}, artifacts=artifacts or []))

    def snapshot_response(self, name: str, response: Any) -> list[str]:
        artifacts: list[str] = []
        suffix = name.replace("/", "_").replace(" ", "_")
        try:
            content_type = response.headers.get("content-type", "")
            if "text" in content_type or "json" in content_type or content_type == "":
                artifacts.append(self.reporter.write_text(f"responses/{suffix}.txt", response.text))
            else:
                artifacts.append(self.reporter.write_bytes(f"responses/{suffix}.bin", response.content))
        except Exception:
            pass
        return artifacts

    def request(self, method: str, path: str, *, expected: int | tuple[int, ...] | None = None, retries: int = 2, allow_redirects: bool = True, **kwargs: Any) -> Any:
        url = self.absolute_url(path)
        expected_set: tuple[int, ...] | None
        if expected is None:
            expected_set = None
        elif isinstance(expected, int):
            expected_set = (expected,)
        else:
            expected_set = expected

        last_error: Exception | None = None
        for attempt in range(retries + 1):
            try:
                response = self.session.request(method, url, timeout=self.args.timeout, allow_redirects=allow_redirects, **kwargs)
                self.refresh_csrf(response.text)
                if expected_set is not None and response.status_code not in expected_set:
                    raise RuntimeError(f"Unexpected status {response.status_code} for {method} {url}")
                return response
            except Exception as exc:
                last_error = exc
                if attempt >= retries:
                    raise
                time.sleep(1.0 + attempt)
        raise last_error if last_error is not None else RuntimeError("request failed")

    def refresh_csrf(self, html: str) -> None:
        token = self.extract_csrf(html)
        if token:
            self.csrf_token = token

    @staticmethod
    def extract_csrf(html: str) -> str | None:
        if not html:
            return None
        patterns = [
            r'name="_csrf"\s+value="([a-f0-9]{32,128})"',
            r'window\.__CSRF_TOKEN\s*=\s*"([a-f0-9]{32,128})"',
            r'"__CSRF_TOKEN"\s*:\s*"([a-f0-9]{32,128})"',
        ]
        for pattern in patterns:
            match = re.search(pattern, html, flags=re.IGNORECASE)
            if match:
                return match.group(1)
        return None

    @staticmethod
    def extract_panel_json(html: str, key: str) -> Any | None:
        pattern = rf'data-global="{re.escape(key)}"[^>]*>(.*?)</script>'
        match = re.search(pattern, html, flags=re.IGNORECASE | re.DOTALL)
        if not match:
            return None
        try:
            return json.loads(match.group(1))
        except Exception:
            return None

    def post_form(self, path: str, data: dict[str, Any], *, expected: int | tuple[int, ...] | None = None) -> Any:
        payload = dict(data)
        if self.csrf_token:
            payload.setdefault("_csrf", self.csrf_token)
            payload.setdefault("_token", self.csrf_token)
        return self.request("POST", path, data=payload, expected=expected)

    def flatten_php_nested(self, prefix: str, value: Any) -> list[tuple[str, str]]:
        pairs: list[tuple[str, str]] = []
        if isinstance(value, dict):
            for key, child in value.items():
                child_prefix = f"{prefix}[{key}]" if prefix else str(key)
                pairs.extend(self.flatten_php_nested(child_prefix, child))
            return pairs
        if isinstance(value, list):
            for index, child in enumerate(value):
                child_prefix = f"{prefix}[{index}]" if prefix else str(index)
                pairs.extend(self.flatten_php_nested(child_prefix, child))
            return pairs
        pairs.append((prefix, "" if value is None else str(value)))
        return pairs

    def mysql_connection(self, database: str):
        return self.pymysql.connect(
            host=self.args.db_host,
            port=self.args.db_port,
            user=self.args.db_user,
            password=self.args.db_password,
            database=database,
            charset="utf8mb4",
            autocommit=True,
            cursorclass=self.pymysql.cursors.DictCursor,
        )

    def mysql_one(self, database: str, sql: str, params: tuple[Any, ...] = ()) -> dict[str, Any] | None:
        with self.mysql_connection(database) as conn:
            with conn.cursor() as cursor:
                cursor.execute(sql, params)
                return cursor.fetchone()

    def mysql_all(self, database: str, sql: str, params: tuple[Any, ...] = ()) -> list[dict[str, Any]]:
        with self.mysql_connection(database) as conn:
            with conn.cursor() as cursor:
                cursor.execute(sql, params)
                return list(cursor.fetchall())

    def mysql_one_safe(self, database: str, sql: str, params: tuple[Any, ...] = ()) -> dict[str, Any] | None:
        try:
            return self.mysql_one(database, sql, params)
        except Exception:
            return None

    def mysql_all_safe(self, database: str, sql: str, params: tuple[Any, ...] = ()) -> list[dict[str, Any]]:
        try:
            return self.mysql_all(database, sql, params)
        except Exception:
            return []

    def server_count(self) -> int:
        return max(1, min(len(self.args.world_dbs), len(self.args.characters_dbs)))

    def server_ids(self) -> list[int]:
        return list(range(self.server_count()))

    def path_with_server(self, path: str, server_id: int) -> str:
        separator = '&' if '?' in path else '?'
        return f"{path}{separator}server={server_id}"

    def characters_db_for_server(self, server_id: int) -> str:
        return self.args.characters_dbs[min(server_id, len(self.args.characters_dbs) - 1)]

    def world_db_for_server(self, server_id: int) -> str:
        return self.args.world_dbs[min(server_id, len(self.args.world_dbs) - 1)]

    def cleanup_generated_dir(self) -> None:
        generated_dir = self.args.deployment_root / "config" / "generated"
        if generated_dir.exists():
            shutil.rmtree(generated_dir)
        generated_dir.mkdir(parents=True, exist_ok=True)
        (generated_dir / ".gitkeep").write_text("", encoding="utf-8")

    def backup_generated_dir(self) -> None:
        generated_dir = self.args.deployment_root / "config" / "generated"
        if self.generated_backup_dir.exists():
            shutil.rmtree(self.generated_backup_dir)
        if generated_dir.exists():
            shutil.copytree(generated_dir, self.generated_backup_dir)

    def restore_generated_dir(self) -> None:
        if not self.args.restore_generated_after_run:
            return
        generated_dir = self.args.deployment_root / "config" / "generated"
        if generated_dir.exists():
            shutil.rmtree(generated_dir)
        if self.generated_backup_dir.exists():
            shutil.copytree(self.generated_backup_dir, generated_dir)

    def build_single_group(self, world_db: str, characters_db: str, realm_index: int = 0, realm_id: int = 1, name: str = "Single Realm") -> dict[str, Any]:
        soap_port = self.soap_port_for_realm(realm_index)
        return {
            "name": name,
            "realm_id": realm_id,
            "port": soap_port,
            "auth": {
                "host": self.args.db_host,
                "port": self.args.db_port,
                "database": self.args.auth_db,
                "username": self.args.db_user,
                "password": self.args.db_password,
            },
            "characters": {
                "host": self.args.db_host,
                "port": self.args.db_port,
                "database": characters_db,
                "username": self.args.db_user,
                "password": self.args.db_password,
            },
            "world": {
                "host": self.args.db_host,
                "port": self.args.db_port,
                "database": world_db,
                "username": self.args.db_user,
                "password": self.args.db_password,
            },
            "soap": {
                "host": self.args.soap_host,
                "port": soap_port,
                "username": self.args.soap_user,
                "password": self.args.soap_password,
                "uri": self.args.soap_uri,
            },
        }

    def ensure_ok(self, name: str, response: Any, expected_codes: tuple[int, ...] = (200,), extra: dict[str, Any] | None = None) -> bool:
        if response.status_code in expected_codes:
            self.record(name, "passed", f"HTTP {response.status_code}", extra or {})
            return True
        artifacts = self.snapshot_response(name, response)
        self.record(name, "failed", f"HTTP {response.status_code}", extra or {}, artifacts)
        return False

    def run_setup_flow(self) -> None:
        self.backup_generated_dir()
        self.cleanup_generated_dir()

        step1 = self.request("GET", "/setup?step=1", expected=200)
        self.record("setup.step1.env", "passed", "setup env page loaded", {"has_csrf": bool(self.csrf_token)})

        lang_resp = self.post_form("/setup/post", {"action": "lang_save", "locale": self.args.locale}, expected=(200, 302))
        try:
            payload = lang_resp.json()
        except Exception:
            payload = {}
        if (payload.get("success") is True) or lang_resp.status_code in (200, 302):
            self.record("setup.step1.locale", "passed", "locale saved", payload if isinstance(payload, dict) else {})
        else:
            self.record("setup.step1.locale", "failed", "locale save failed", payload if isinstance(payload, dict) else {}, self.snapshot_response("setup_step1_locale", lang_resp))

        step2 = self.request("GET", "/setup?step=2", expected=200)
        self.record("setup.step2.mode_page", "passed", "setup mode page loaded", {"has_verify_endpoint": "setup/api/realms" in step2.text})

        single_realms = [self.build_single_group(self.args.world_dbs[0], self.args.characters_dbs[0], 0, 1, "Single")]
        single_payload = {"action": "mode_save", "mode": "single", "realms": single_realms}
        single_resp = self.request("POST", "/setup/post", data=self.flatten_php_nested("", {**single_payload, "_csrf": self.csrf_token}), expected=200)
        single_json = single_resp.json()
        self.record("setup.step2.single_mode", "passed" if single_json.get("success") else "failed", "single mode save", single_json, [] if single_json.get("success") else self.snapshot_response("setup_step2_single", single_resp))

        test_single = self.request("GET", "/setup?step=3", expected=200)
        self.record("setup.step3.single_test", "passed", "single mode test page loaded", {"contains_ok": "OK" in test_single.text})

        step2_again = self.request("GET", "/setup?step=2", expected=200)
        multi_full_realms = []
        realm_count = min(len(self.args.world_dbs), len(self.args.characters_dbs))
        for index in range(realm_count):
            multi_full_realms.append(self.build_single_group(self.args.world_dbs[index], self.args.characters_dbs[index], index, index + 1, f"Server {index + 1}"))
        multi_payload = {"action": "mode_save", "mode": "multi-full", "realms": multi_full_realms}
        multi_full_resp = self.request("POST", "/setup/post", data=self.flatten_php_nested("", {**multi_payload, "_csrf": self.csrf_token}), expected=200)
        multi_full_json = multi_full_resp.json()
        self.record("setup.step2.multi_full_mode", "passed" if multi_full_json.get("success") else "failed", "multi-full mode save", multi_full_json, [] if multi_full_json.get("success") else self.snapshot_response("setup_step2_multi_full", multi_full_resp))

        test_multi_full = self.request("GET", "/setup?step=3", expected=200)
        self.record("setup.step3.multi_full_test", "passed", "multi-full mode test page loaded", {"contains_ok": "OK" in test_multi_full.text})

        step2_multi = self.request("GET", "/setup?step=2", expected=200)
        realms_resp = self.request(
            "GET",
            "/setup/api/realms?" + urlencode({
                "mode": "multi",
                "auth_host": self.args.db_host,
                "auth_port": self.args.db_port,
                "auth_db": self.args.auth_db,
                "auth_user": self.args.db_user,
                "auth_pass": self.args.db_password,
            }),
            expected=(200, 422),
        )
        try:
            realms_json = realms_resp.json()
        except Exception:
            realms_json = {"success": False, "message": "invalid json"}
        realm_entries = realms_json.get("realms") if isinstance(realms_json.get("realms"), list) else []
        if realms_json.get("success") and realm_entries:
            self.record("setup.step2.multi_verify_realms", "passed", "realms detected from auth db", {"count": len(realm_entries)})
        else:
            artifacts = self.snapshot_response("setup_step2_multi_verify_realms", realms_resp)
            self.record("setup.step2.multi_verify_realms", "failed", "realm autodiscovery failed", realms_json if isinstance(realms_json, dict) else {}, artifacts)

        effective_realms: list[dict[str, Any]] = []
        for index in range(max(1, min(len(self.args.world_dbs), len(self.args.characters_dbs), len(realm_entries) if realm_entries else len(self.args.world_dbs)))):
            seed = realm_entries[index] if index < len(realm_entries) else {}
            effective_realms.append({
                "name": seed.get("name") or f"Realm {index + 1}",
                "realm_id": seed.get("realm_id") or (index + 1),
                "port": seed.get("port") or self.soap_port_for_realm(index),
                "characters": {
                    "host": self.args.db_host,
                    "port": self.args.db_port,
                    "database": self.args.characters_dbs[index],
                    "username": self.args.db_user,
                    "password": self.args.db_password,
                },
                "world": {
                    "host": self.args.db_host,
                    "port": self.args.db_port,
                    "database": self.args.world_dbs[index],
                    "username": self.args.db_user,
                    "password": self.args.db_password,
                },
                "soap": {
                    "host": self.args.soap_host,
                    "port": self.soap_port_for_realm(index),
                    "username": self.args.soap_user,
                    "password": self.args.soap_password,
                    "uri": self.args.soap_uri,
                },
            })

        shared_payload = {
            "action": "mode_save",
            "mode": "multi",
            "auth_host": self.args.db_host,
            "auth_port": self.args.db_port,
            "auth_db": self.args.auth_db,
            "auth_user": self.args.db_user,
            "auth_pass": self.args.db_password,
            "realms": effective_realms,
        }
        shared_resp = self.request("POST", "/setup/post", data=self.flatten_php_nested("", {**shared_payload, "_csrf": self.csrf_token}), expected=200)
        shared_json = shared_resp.json()
        self.record("setup.step2.multi_mode", "passed" if shared_json.get("success") else "failed", "multi mode save", shared_json, [] if shared_json.get("success") else self.snapshot_response("setup_step2_multi", shared_resp))

        test_multi = self.request("GET", "/setup?step=3", expected=200)
        self.record("setup.step3.multi_test", "passed", "multi mode test page loaded", {"contains_ok": "OK" in test_multi.text})

        step4 = self.request("GET", "/setup?step=4", expected=200)
        self.record("setup.step4.admin_page", "passed", "admin page loaded", {"has_csrf": bool(self.csrf_token)})
        admin_resp = self.post_form("/setup/post", {
            "action": "admin_save",
            "admin_user": self.args.admin_user,
            "admin_pass": self.args.admin_password,
            "admin_pass2": self.args.admin_password,
        }, expected=200)
        admin_json = admin_resp.json()
        self.record("setup.step4.admin_save", "passed" if admin_json.get("success") else "failed", "admin save", admin_json, [] if admin_json.get("success") else self.snapshot_response("setup_step4_admin_save", admin_resp))

        finish = self.request("GET", "/setup?step=5", expected=(200, 302), allow_redirects=False)
        if finish.status_code == 302:
            details = {"location": finish.headers.get("Location", "")}
            self.record("setup.step5.finish", "passed", "setup finished with redirect", details)
        else:
            self.record("setup.step5.finish", "passed", "setup finish page rendered", {"length": len(finish.text)})

    def login(self) -> bool:
        page = self.request("GET", "/account/login", expected=200)
        response = self.request(
            "POST",
            "/account/login",
            data={"username": self.args.admin_user, "password": self.args.admin_password},
            expected=(200, 302),
            allow_redirects=False,
        )
        if response.status_code == 302 and "/account" in response.headers.get("Location", ""):
            self.record("auth.login", "passed", "login redirect received", {"location": response.headers.get("Location")})
            landing = self.request("GET", response.headers.get("Location", "/account"), expected=200)
            self.refresh_csrf(landing.text)
            return True
        if "/account" in response.text:
            self.record("auth.login", "passed", "login page followed redirect")
            return True
        self.record("auth.login", "failed", "login failed", {}, self.snapshot_response("auth_login", response))
        return False

    def smoke_get(self, name: str, path: str, marker: str | None = None) -> None:
        response = self.request("GET", path, expected=200)
        ok = marker is None or marker in response.text
        artifacts = [] if ok else self.snapshot_response(name, response)
        self.record(name, "passed" if ok else "failed", f"GET {path}", {"marker": marker, "length": len(response.text)}, artifacts)

    def post_json_api(self, name: str, path: str, data: dict[str, Any], expected_codes: tuple[int, ...] = (200,)) -> dict[str, Any] | None:
        response = self.post_form(path, data, expected=expected_codes)
        try:
            payload = response.json()
        except Exception:
            payload = None
        if response.status_code in expected_codes:
            self.record(name, "passed", f"POST {path}", payload if isinstance(payload, dict) else {"status": response.status_code})
        else:
            self.record(name, "failed", f"POST {path} failed", payload if isinstance(payload, dict) else {}, self.snapshot_response(name, response))
        return payload if isinstance(payload, dict) else None

    def find_sample_character(self, server_id: int | None = None) -> dict[str, Any] | None:
        databases = [self.characters_db_for_server(server_id)] if server_id is not None else self.args.characters_dbs
        for index, database in enumerate(databases):
            row = self.mysql_one(database, "SELECT guid, name, account FROM characters ORDER BY guid ASC LIMIT 1")
            if row:
                row["database"] = database
                row["server_id"] = server_id if server_id is not None else index
                return row
        return None

    def find_ascii_sample_character(self, server_id: int) -> dict[str, Any] | None:
        row = self.mysql_one_safe(
            self.characters_db_for_server(server_id),
            "SELECT guid, name, account FROM characters WHERE name REGEXP '^[A-Za-z][A-Za-z0-9_]*$' ORDER BY guid ASC LIMIT 1",
        )
        if row:
            row["database"] = self.characters_db_for_server(server_id)
            row["server_id"] = server_id
        return row

    def find_sample_mail(self, server_id: int | None = None) -> dict[str, Any] | None:
        databases = [self.characters_db_for_server(server_id)] if server_id is not None else self.args.characters_dbs
        for index, database in enumerate(databases):
            row = self.mysql_one(database, "SELECT id, receiver FROM mail ORDER BY id ASC LIMIT 1")
            if row:
                row["database"] = database
                row["server_id"] = server_id if server_id is not None else index
                return row
        return None

    def find_sample_world_entry(self, server_id: int, table: str, field: str = "entry") -> int | None:
        row = self.mysql_one(self.world_db_for_server(server_id), f"SELECT {field} FROM {table} ORDER BY {field} ASC LIMIT 1")
        if not row:
            return None
        value = row.get(field)
        return int(value) if value is not None else None

    def find_sample_account_with_ip(self) -> dict[str, Any] | None:
        return self.mysql_one(self.args.auth_db, "SELECT id, username, last_ip FROM account WHERE last_ip <> '' ORDER BY id ASC LIMIT 1")

    def find_boost_template(self, realm_id: int) -> dict[str, Any] | None:
        return self.mysql_one_safe(
            self.args.auth_db,
            "SELECT id, realm_id, name FROM character_boost_templates WHERE realm_id=%s ORDER BY id ASC LIMIT 1",
            (realm_id,),
        )

    def latest_mass_mail_log_id(self, server_id: int) -> int:
        row = self.mysql_one_safe(self.characters_db_for_server(server_id), "SELECT MAX(id) AS id FROM panel_massmail_log")
        return int(row.get("id") or 0) if row else 0

    def latest_mail_id(self, server_id: int) -> int:
        row = self.mysql_one_safe(self.characters_db_for_server(server_id), "SELECT MAX(id) AS id FROM mail")
        return int(row.get("id") or 0) if row else 0

    def find_sent_mail(self, server_id: int, minimum_id: int, receiver_guid: int, subject: str) -> dict[str, Any] | None:
        return self.mysql_one_safe(
            self.characters_db_for_server(server_id),
            "SELECT id, sender, receiver, subject, checked FROM mail WHERE id > %s AND receiver=%s AND subject=%s ORDER BY id DESC LIMIT 1",
            (minimum_id, receiver_guid, subject),
        )

    def find_latest_mass_mail_log(self, server_id: int, minimum_id: int, action: str, subject: str) -> dict[str, Any] | None:
        return self.mysql_one_safe(
            self.characters_db_for_server(server_id),
            "SELECT id, action, subject, success_count, fail_count, success FROM panel_massmail_log WHERE id > %s AND action=%s AND subject=%s ORDER BY id DESC LIMIT 1",
            (minimum_id, action, subject),
        )

    def find_redeem_codes(self, codes: list[str]) -> list[dict[str, Any]]:
        if not codes:
            return []
        placeholders = ",".join(["%s"] * len(codes))
        sql = (
            "SELECT id, template_id, code, used_at FROM character_boost_redeem_codes "
            f"WHERE code IN ({placeholders}) ORDER BY id ASC"
        )
        return self.mysql_all_safe(self.args.auth_db, sql, tuple(codes))

    def record_json_result(self, name: str, response: Any, accepted_codes: tuple[int, ...] = (200,), success_codes: tuple[int, ...] | None = None) -> dict[str, Any] | None:
        try:
            payload = response.json()
        except Exception:
            payload = None

        effective_success = success_codes or accepted_codes
        ok = response.status_code in effective_success
        details = payload if isinstance(payload, dict) else {"status": response.status_code}
        artifacts = [] if ok else self.snapshot_response(name, response)
        self.record(name, "passed" if ok else "failed", f"{response.request.method} {response.request.path_url}", details, artifacts)
        return payload if isinstance(payload, dict) else None

    def run_authenticated_checks(self) -> None:
        self.smoke_get("page.home", "/", marker="Acore GM Panel")
        self.smoke_get("page.public_boost", "/public/character-boost", marker="character")
        self.exercise_public_boost_flow()

        self.exercise_account_flow()
        for server_id in self.server_ids():
            self.exercise_server_pages(server_id)
            self.exercise_character_flow(server_id)
            self.exercise_mail_flow(server_id)
            self.exercise_aegis_flow(server_id)
            self.exercise_logs_flow(server_id)
            self.exercise_soap_flow(server_id)
            self.exercise_misc_reads(server_id)
            self.exercise_content_tools(server_id)
            self.exercise_boost_admin_flow(server_id)
            self.exercise_smart_ai_flow(server_id)
            self.exercise_mass_mail_flow(server_id)
            self.exercise_real_write_flow(server_id)
            self.exercise_validation_paths(server_id)

    def exercise_server_pages(self, server_id: int) -> None:
        for name, path, marker in [
            ("page.account", "/account", "account-search"),
            ("page.character", "/character", "character"),
            ("page.aegis", "/aegis", "aegis-layout"),
            ("page.mail", "/mail", "mailTable"),
            ("page.logs", "/logs", "logsForm"),
            ("page.soap", "/soap", "soap-wizard"),
            ("page.smart_ai", "/smart-ai", "smart"),
            ("page.bag", "/bag", "bag"),
            ("page.item_ownership", "/item-ownership", "item_owner"),
            ("page.creature", "/creature", "creature"),
            ("page.item", "/item", "item"),
            ("page.quest", "/quest", "quest"),
            ("page.mass_mail", "/mass-mail", "mass"),
            ("page.boost_templates", "/character-boost/templates", "character"),
            ("page.boost_redeem_codes", "/character-boost/redeem-codes", "character"),
        ]:
            self.smoke_get(f"{name}.server{server_id}", self.path_with_server(path, server_id), marker=marker)

    def exercise_account_flow(self) -> None:
        username = f"agmp_test_{int(time.time())}"
        password = self.args.admin_password
        create_response = self.post_form("/account/api/create", {
            "username": username,
            "password": password,
            "password_confirm": password,
            "email": f"{username}@example.test",
            "gmlevel": 0,
        }, expected=(200, 422, 500))
        try:
            create_payload = create_response.json()
        except Exception:
            create_payload = {}
        self.record(
            "account.create",
            "passed" if create_response.status_code == 200 and create_payload.get("success") else "failed",
            "POST /account/api/create",
            create_payload if isinstance(create_payload, dict) else {"status": create_response.status_code},
            [] if create_response.status_code == 200 and create_payload.get("success") else self.snapshot_response("account_create", create_response),
        )
        if not create_payload or not create_payload.get("success"):
            return
        self.created_account_id = int(create_payload.get("id", 0))
        self.created_account_username = username
        row = self.mysql_one(self.args.auth_db, "SELECT id, username FROM account WHERE id=%s", (self.created_account_id,))
        if row and row.get("username") == username:
            self.record("account.create.db_check", "passed", "created account exists in auth db", row)
        else:
            self.record("account.create.db_check", "failed", "created account missing in auth db", row or {})

        renamed = username + "_renamed"
        rename_payload = self.post_json_api("account.rename", "/account/api/update-username", {
            "id": self.created_account_id,
            "username": renamed,
            "password": password,
        }, expected_codes=(200, 422))
        if rename_payload and rename_payload.get("success"):
            self.created_account_username = renamed
            row = self.mysql_one(self.args.auth_db, "SELECT username FROM account WHERE id=%s", (self.created_account_id,))
            if row and row.get("username") == renamed:
                self.record("account.rename.db_check", "passed", "renamed account persisted", row)
            else:
                self.record("account.rename.db_check", "failed", "rename not persisted", row or {})

        ban_payload = self.post_json_api("account.ban", "/account/api/ban", {
            "id": self.created_account_id,
            "hours": 1,
            "reason": "agmp automated test",
        }, expected_codes=(200, 422))
        if ban_payload and ban_payload.get("success"):
            row = self.mysql_one(self.args.auth_db, "SELECT id, active FROM account_banned WHERE id=%s ORDER BY bandate DESC LIMIT 1", (self.created_account_id,))
            self.record("account.ban.db_check", "passed" if row else "failed", "ban row check", row or {})

        unban_payload = self.post_json_api("account.unban", "/account/api/unban", {"id": self.created_account_id}, expected_codes=(200, 422))
        if unban_payload and unban_payload.get("success"):
            row = self.mysql_one(self.args.auth_db, "SELECT COUNT(*) AS cnt FROM account_banned WHERE id=%s AND active=1", (self.created_account_id,))
            self.record("account.unban.db_check", "passed" if row and int(row.get("cnt", 0)) == 0 else "failed", "active ban row cleared", row or {})

        ip_sample = self.find_sample_account_with_ip()
        if ip_sample:
            response = self.request("GET", "/account/api/ip-location?ip=" + quote(str(ip_sample["last_ip"])), expected=(200, 422))
            self.record("account.ip_location", "passed" if response.status_code == 200 else "failed", "ip location request", response.json() if "json" in response.headers.get("content-type", "") else {})
            response2 = self.request("GET", "/account/api/ip-accounts?" + urlencode({"ip": ip_sample["last_ip"], "exclude": ip_sample["id"], "limit": 10}), expected=(200, 422))
            self.record("account.ip_accounts", "passed" if response2.status_code == 200 else "failed", "ip accounts request", response2.json() if "json" in response2.headers.get("content-type", "") else {})
        else:
            self.record("account.ip_tools", "skipped", "no sample account with last_ip found")

    def exercise_character_flow(self, server_id: int) -> None:
        response = self.request("GET", self.path_with_server("/character/api/list?load_all=1", server_id), expected=200)
        try:
            payload = response.json()
        except Exception:
            payload = {}
        self.record(f"character.list.server{server_id}", "passed" if payload.get("success") else "failed", "character list api", payload if isinstance(payload, dict) else {}, [] if payload.get("success") else self.snapshot_response(f"character_list_server{server_id}", response))
        sample = self.find_sample_character(server_id)
        if not sample:
            self.record(f"character.sample.server{server_id}", "skipped", "no character rows found in configured character database")
            return
        show = self.request("GET", self.path_with_server("/character/api/show?guid=" + quote(str(sample["guid"])), server_id), expected=(200, 404, 422))
        self.record(f"character.show.server{server_id}", "passed" if show.status_code == 200 else "failed", "character detail api", show.json() if "json" in show.headers.get("content-type", "") else {}, [] if show.status_code == 200 else self.snapshot_response(f"character_show_server{server_id}", show))
        names = self.request("GET", self.path_with_server("/character/api/names?type=quest&ids=1,2,3", server_id), expected=(200, 422))
        self.record(f"character.names.server{server_id}", "passed" if names.status_code == 200 else "failed", "name resolution api", names.json() if "json" in names.headers.get("content-type", "") else {})
        account_resp = self.request("GET", self.path_with_server("/account/api/characters?id=" + quote(str(sample["account"])), server_id), expected=(200, 422, 500))
        self.record(f"account.characters.server{server_id}", "passed" if account_resp.status_code == 200 else "failed", "account characters api", account_resp.json() if "json" in account_resp.headers.get("content-type", "") else {})
        status_resp = self.request("GET", self.path_with_server("/account/api/characters-status?id=" + quote(str(sample["account"])), server_id), expected=(200, 422, 500))
        self.record(f"account.characters_status.server{server_id}", "passed" if status_resp.status_code == 200 else "failed", "account characters status api", status_resp.json() if "json" in status_resp.headers.get("content-type", "") else {})

    def exercise_mail_flow(self, server_id: int) -> None:
        self.post_json_api(f"mail.list.server{server_id}", self.path_with_server("/mail/api/list", server_id), {"limit": 10}, expected_codes=(200, 422, 500))
        self.post_json_api(f"mail.stats.server{server_id}", self.path_with_server("/mail/api/stats", server_id), {}, expected_codes=(200, 422, 500))
        self.post_json_api(f"mail.logs.server{server_id}", self.path_with_server("/mail/api/logs", server_id), {"type": "sql", "limit": 10}, expected_codes=(200, 422, 500))
        sample = self.find_sample_mail(server_id)
        if not sample:
            self.record(f"mail.sample.server{server_id}", "skipped", "no mail rows found in configured character database")
            return
        self.post_json_api(f"mail.view.server{server_id}", self.path_with_server("/mail/api/view", server_id), {"mail_id": sample["id"]}, expected_codes=(200, 404, 422, 500))

    def exercise_aegis_flow(self, server_id: int) -> None:
        for name, path in [
            ("aegis.overview", "/aegis/api/overview?days=7"),
            ("aegis.offenses", "/aegis/api/offenses?page=1&per_page=10"),
            ("aegis.events", "/aegis/api/events?page=1&per_page=10"),
            ("aegis.log", "/aegis/api/log?limit=20"),
        ]:
            server_path = self.path_with_server(path, server_id)
            response = self.request("GET", server_path, expected=(200, 422, 500))
            self.record(f"{name}.server{server_id}", "passed" if response.status_code == 200 else "failed", f"GET {server_path}", response.json() if "json" in response.headers.get("content-type", "") else {}, [] if response.status_code == 200 else self.snapshot_response(f"{name}.server{server_id}", response))

        sample = self.find_sample_character(server_id)
        player_query = urlencode({"guid": sample["guid"]}) if sample else urlencode({"name": ""})
        player = self.request("GET", self.path_with_server("/aegis/api/player?" + player_query, server_id), expected=(200, 404, 422, 500))
        self.record(f"aegis.player.server{server_id}", "passed" if player.status_code == 200 else "failed", "aegis player lookup", player.json() if "json" in player.headers.get("content-type", "") else {}, [] if player.status_code == 200 else self.snapshot_response(f"aegis_player_server{server_id}", player))

        action = self.post_json_api(f"aegis.action.server{server_id}", self.path_with_server("/aegis/api/action", server_id), {"action": "reload", "target": ""}, expected_codes=(200, 422, 500))
        if action and action.get("success"):
            self.record(f"aegis.action.result.server{server_id}", "passed", "aegis reload action succeeded", action)

    def exercise_logs_flow(self, server_id: int) -> None:
        page = self.request("GET", self.path_with_server("/logs", server_id), expected=200)
        logs_data = self.extract_panel_json(page.text, "LOGS_DATA") or {}
        defaults = logs_data.get("defaults", {}) if isinstance(logs_data, dict) else {}
        payload = {
            "module": defaults.get("module") or "audit",
            "type": defaults.get("type") or "main",
            "limit": int(defaults.get("limit") or 50),
        }
        self.post_json_api(f"logs.list.server{server_id}", self.path_with_server("/logs/api/list", server_id), payload, expected_codes=(200, 422, 500))
        self.post_json_api(f"audit.list.server{server_id}", self.path_with_server("/audit/api/list", server_id), {"limit": 20}, expected_codes=(200, 422, 500))

    def exercise_soap_flow(self, server_id: int) -> None:
        page = self.request("GET", self.path_with_server("/soap", server_id), expected=200)
        catalog = self.extract_panel_json(page.text, "SOAP_WIZARD_DATA") or {}
        command_key = None
        categories = catalog.get("categories", []) if isinstance(catalog, dict) else []
        for category in categories:
            for command in category.get("commands", []):
                fields = command.get("fields") or []
                requires_target = bool(command.get("requires_target"))
                has_required_field = any(field.get("required") for field in fields if isinstance(field, dict))
                if not requires_target and not has_required_field:
                    command_key = command.get("key")
                    break
            if command_key:
                break
        if not command_key:
            self.record(f"soap.execute.server{server_id}", "skipped", "no parameterless soap command found in catalog")
            return
        self.post_json_api(f"soap.execute.server{server_id}", self.path_with_server("/soap/api/execute", server_id), {"command_key": command_key, "arguments": json.dumps({}), "server_id": server_id}, expected_codes=(200, 422, 500))

    def exercise_misc_reads(self, server_id: int) -> None:
        sample = self.find_sample_character(server_id)
        if sample:
            bag_characters = self.request("GET", self.path_with_server("/bag/api/characters?" + urlencode({"type": "character_name", "value": sample["name"], "limit": 5}), server_id), expected=(200, 422, 500))
            self.record_json_result(f"bag.characters.server{server_id}", bag_characters, accepted_codes=(200, 422, 500), success_codes=(200,))
            bag_items = self.request("GET", self.path_with_server("/bag/api/items?guid=" + quote(str(sample["guid"])), server_id), expected=(200, 422, 500))
            self.record_json_result(f"bag.items.server{server_id}", bag_items, accepted_codes=(200, 422, 500), success_codes=(200,))
        subclasses = self.request("GET", self.path_with_server("/item/api/subclasses?class=2", server_id), expected=(200, 422, 500))
        self.record_json_result(f"item.subclasses.server{server_id}", subclasses, accepted_codes=(200, 422, 500), success_codes=(200,))
        ownership_search = self.request("GET", self.path_with_server("/item-ownership/api/search-items?keyword=sword&limit=5", server_id), expected=(200, 422, 500))
        self.record_json_result(f"item_ownership.search.server{server_id}", ownership_search, accepted_codes=(200, 422, 500), success_codes=(200,))
        self.post_json_api(f"mass_mail.logs.server{server_id}", self.path_with_server("/mass-mail/api/logs", server_id), {"limit": 5}, expected_codes=(200, 422, 500))

    def exercise_content_tools(self, server_id: int) -> None:
        creature_entry = self.find_sample_world_entry(server_id, "creature_template")
        item_entry = self.find_sample_world_entry(server_id, "item_template")
        quest_id = self.find_sample_world_entry(server_id, "quest_template", "ID")

        if creature_entry:
            response = self.post_form(self.path_with_server("/creature/api/fetch-row", server_id), {"entry": creature_entry}, expected=(200, 404, 422, 500))
            self.record_json_result(f"creature.fetch_row.server{server_id}", response, accepted_codes=(200, 404, 422, 500), success_codes=(200,))
        creature_logs = self.post_form(self.path_with_server("/creature/api/logs", server_id), {"type": "sql", "limit": 5}, expected=(200, 422, 500))
        self.record_json_result(f"creature.logs.server{server_id}", creature_logs, accepted_codes=(200, 422, 500), success_codes=(200,))

        if item_entry:
            check = self.post_form(self.path_with_server("/item/api/check", server_id), {"entry": item_entry}, expected=(200, 422, 500))
            self.record_json_result(f"item.check.server{server_id}", check, accepted_codes=(200, 422, 500), success_codes=(200,))
            fetch = self.post_form(self.path_with_server("/item/api/fetch", server_id), {"entry": item_entry}, expected=(200, 404, 422, 500))
            self.record_json_result(f"item.fetch.server{server_id}", fetch, accepted_codes=(200, 404, 422, 500), success_codes=(200,))
            ownership = self.request("GET", self.path_with_server(f"/item-ownership/api/ownership?entry={item_entry}", server_id), expected=(200, 422, 500))
            self.record_json_result(f"item_ownership.ownership.server{server_id}", ownership, accepted_codes=(200, 422, 500), success_codes=(200,))
        item_logs = self.post_form(self.path_with_server("/item/api/logs", server_id), {"type": "sql", "limit": 5}, expected=(200, 422, 500))
        self.record_json_result(f"item.logs.server{server_id}", item_logs, accepted_codes=(200, 422, 500), success_codes=(200,))

        if quest_id:
            quest_fetch = self.post_form(self.path_with_server("/quest/api/fetch", server_id), {"id": quest_id}, expected=(200, 404, 422, 500))
            self.record_json_result(f"quest.fetch.server{server_id}", quest_fetch, accepted_codes=(200, 404, 422, 500), success_codes=(200,))
            editor_load = self.request("GET", self.path_with_server(f"/quest/api/editor/load?id={quest_id}", server_id), expected=(200, 422, 500))
            self.record_json_result(f"quest.editor_load.server{server_id}", editor_load, accepted_codes=(200, 422, 500), success_codes=(200,))
            editor_preview = self.post_form(self.path_with_server("/quest/api/editor/preview", server_id), {"id": quest_id, "payload": json.dumps({})}, expected=(200, 422, 500))
            self.record_json_result(f"quest.editor_preview.server{server_id}", editor_preview, accepted_codes=(200, 422, 500), success_codes=(200, 422))
        quest_logs = self.post_form(self.path_with_server("/quest/api/logs", server_id), {"type": "sql", "limit": 5}, expected=(200, 422, 500))
        self.record_json_result(f"quest.logs.server{server_id}", quest_logs, accepted_codes=(200, 422, 500), success_codes=(200,))

    def exercise_boost_admin_flow(self, server_id: int) -> None:
        stats = self.post_form(self.path_with_server("/character-boost/api/redeem-codes/stats", server_id), {}, expected=(200, 422, 500))
        self.record_json_result(f"boost.codes.stats.server{server_id}", stats, accepted_codes=(200, 422, 500), success_codes=(200,))
        listing = self.post_form(self.path_with_server("/character-boost/api/redeem-codes/list", server_id), {"per_page": 10}, expected=(200, 422, 500))
        self.record_json_result(f"boost.codes.list.server{server_id}", listing, accepted_codes=(200, 422, 500), success_codes=(200,))
        generate = self.post_form(self.path_with_server("/character-boost/api/redeem-codes/generate", server_id), {"template_id": "all", "count": 0}, expected=(200, 422, 500))
        self.record_json_result(f"boost.codes.generate_validation.server{server_id}", generate, accepted_codes=(200, 422, 500), success_codes=(422,))
        template_invalid = self.post_form(self.path_with_server("/character-boost/api/templates/save", server_id), {"name": "", "target_level": 0, "money_gold": -1}, expected=(200, 422, 500))
        self.record_json_result(f"boost.templates.save_validation.server{server_id}", template_invalid, accepted_codes=(200, 422, 500), success_codes=(422,))

    def exercise_public_boost_flow(self) -> None:
        options = self.request("GET", "/public/character-boost/options", expected=(200, 500))
        self.record_json_result("public_boost.options", options, accepted_codes=(200, 500), success_codes=(200,))
        redeem = self.post_form("/public/character-boost/redeem", {"realm_id": 1, "character_name": "invalid", "code": "BAD"}, expected=(200, 422, 404, 409, 500))
        self.record_json_result("public_boost.redeem_validation", redeem, accepted_codes=(200, 422, 404, 409, 500), success_codes=(422, 404, 409))

    def exercise_smart_ai_flow(self, server_id: int) -> None:
        preview = self.post_form(self.path_with_server("/smart-ai/api/preview", server_id), {"payload": json.dumps({})}, expected=(200, 422, 500))
        self.record_json_result(f"smart_ai.preview.server{server_id}", preview, accepted_codes=(200, 422, 500), success_codes=(200, 422))

    def exercise_mass_mail_flow(self, server_id: int) -> None:
        announce = self.post_form(self.path_with_server("/mass-mail/api/announce", server_id), {"message": ""}, expected=(200, 422, 500))
        self.record_json_result(f"mass_mail.announce_validation.server{server_id}", announce, accepted_codes=(200, 422, 500), success_codes=(200, 422))
        send = self.post_form(self.path_with_server("/mass-mail/api/send", server_id), {"action": "send_mail", "subject": "", "body": ""}, expected=(200, 422, 500))
        self.record_json_result(f"mass_mail.send_validation.server{server_id}", send, accepted_codes=(200, 422, 500), success_codes=(200, 422))
        boost = self.post_form(self.path_with_server("/mass-mail/api/boost", server_id), {"character_name": "", "template_id": ""}, expected=(200, 422, 404, 500))
        self.record_json_result(f"mass_mail.boost_validation.server{server_id}", boost, accepted_codes=(200, 422, 404, 500), success_codes=(422, 404))

    def exercise_real_write_flow(self, server_id: int) -> None:
        self.exercise_real_mail_send(server_id)
        self.exercise_real_soap_command(server_id)
        self.exercise_real_boost_code_cycle(server_id)

    def exercise_real_mail_send(self, server_id: int) -> None:
        sample = self.find_ascii_sample_character(server_id) or self.find_sample_character(server_id)
        if not sample:
            self.record(f"mass_mail.send_real.server{server_id}", "skipped", "no character available for real mail send")
            return

        before_mail_id = self.latest_mail_id(server_id)
        before_log_id = self.latest_mass_mail_log_id(server_id)
        stamp = int(time.time())
        subject = f"AGMP_REAL_MAIL_{server_id}_{stamp}"
        body = f"AGMP automated real mail send for server {server_id}"
        response = self.post_form(
            self.path_with_server("/mass-mail/api/send", server_id),
            {
                "action": "send_mail",
                "subject": subject,
                "body": body,
                "target_type": "custom",
                "custom_char_list": sample["name"],
            },
            expected=(200, 422, 500),
        )
        try:
            payload = response.json()
        except Exception:
            payload = {}

        ok = response.status_code == 200 and payload.get("success") and int(payload.get("success_count", 0)) >= 1 and int(payload.get("fail_count", 0)) == 0
        self.record(
            f"mass_mail.send_real.server{server_id}",
            "passed" if ok else "failed",
            "POST real mass-mail send",
            payload if isinstance(payload, dict) else {"status": response.status_code},
            [] if ok else self.snapshot_response(f"mass_mail_send_real_server{server_id}", response),
        )
        if not ok:
            return

        mail_row = self.find_sent_mail(server_id, before_mail_id, int(sample["guid"]), subject)
        if mail_row:
            self.created_mail_ids.append((server_id, int(mail_row["id"])))
            self.record(f"mass_mail.send_real.db_check.server{server_id}", "passed", "real mail persisted", mail_row)
        else:
            self.record(f"mass_mail.send_real.db_check.server{server_id}", "failed", "real mail not found in mail table", {"receiver": sample["guid"], "subject": subject})

        log_row = self.find_latest_mass_mail_log(server_id, before_log_id, "send_mail", subject)
        if log_row:
            self.record(f"mass_mail.send_real.log_check.server{server_id}", "passed", "mass-mail action log created", log_row)
        else:
            self.record(f"mass_mail.send_real.log_check.server{server_id}", "failed", "mass-mail action log missing", {"subject": subject})

        if not mail_row:
            return

        cleanup = self.post_form(
            self.path_with_server("/mail/api/delete", server_id),
            {"mail_id": int(mail_row["id"])},
            expected=(200, 422, 500),
        )
        cleanup_payload = self.record_json_result(
            f"mass_mail.send_real.cleanup.server{server_id}",
            cleanup,
            accepted_codes=(200, 422, 500),
            success_codes=(200,),
        )
        if cleanup_payload and cleanup_payload.get("success"):
            self.created_mail_ids = [item for item in self.created_mail_ids if item != (server_id, int(mail_row["id"]))]
            deleted_row = self.mysql_one_safe(self.characters_db_for_server(server_id), "SELECT id FROM mail WHERE id=%s", (int(mail_row["id"]),))
            self.record(
                f"mass_mail.send_real.cleanup.db_check.server{server_id}",
                "passed" if deleted_row is None else "failed",
                "real mail cleanup verification",
                {"mail_id": int(mail_row["id"]), "deleted": deleted_row is None},
            )

    def exercise_real_soap_command(self, server_id: int) -> None:
        message = f"AGMP real SOAP announce server {server_id} at {int(time.time())}"
        response = self.post_form(
            self.path_with_server("/soap/api/execute", server_id),
            {
                "command_key": "announce-global",
                "arguments": json.dumps({"message": message}, ensure_ascii=False),
                "server_id": server_id,
            },
            expected=(200, 422, 500),
        )
        try:
            payload = response.json()
        except Exception:
            payload = {}
        ok = response.status_code == 200 and payload.get("success") is True
        self.record(
            f"soap.execute_real.server{server_id}",
            "passed" if ok else "failed",
            "POST low-risk SOAP GM command",
            payload if isinstance(payload, dict) else {"status": response.status_code},
            [] if ok else self.snapshot_response(f"soap_execute_real_server{server_id}", response),
        )

    def exercise_real_boost_code_cycle(self, server_id: int) -> None:
        stats = self.post_form(self.path_with_server("/character-boost/api/redeem-codes/stats", server_id), {}, expected=(200, 422, 500))
        try:
            stats_payload = stats.json()
        except Exception:
            stats_payload = {}
        realm_id = int((((stats_payload or {}).get("payload") or {}).get("realm_id") or 0))
        if realm_id <= 0:
            self.record(f"boost.codes.generate_real.server{server_id}", "skipped", "unable to resolve realm id for boost code generation")
            return

        template = self.find_boost_template(realm_id)
        if not template:
            self.record(f"boost.codes.generate_real.server{server_id}", "skipped", "no boost template available for current realm", {"realm_id": realm_id})
            return

        response = self.post_form(
            self.path_with_server("/character-boost/api/redeem-codes/generate", server_id),
            {"template_id": int(template["id"]), "count": 1},
            expected=(200, 422, 500),
        )
        try:
            payload = response.json()
        except Exception:
            payload = {}

        generated = (((payload or {}).get("payload") or {}).get("generated") or []) if isinstance(payload, dict) else []
        codes: list[str] = []
        for group in generated:
            codes.extend([str(code) for code in group.get("codes", []) if code])

        ok = response.status_code == 200 and payload.get("success") is True and len(codes) == 1
        self.record(
            f"boost.codes.generate_real.server{server_id}",
            "passed" if ok else "failed",
            "POST real boost redeem-code generation",
            payload if isinstance(payload, dict) else {"status": response.status_code},
            [] if ok else self.snapshot_response(f"boost_codes_generate_real_server{server_id}", response),
        )
        if not ok:
            return

        rows = self.find_redeem_codes(codes)
        matched_ids = [int(row["id"]) for row in rows if row.get("id") is not None]
        if matched_ids:
            self.generated_redeem_code_ids.extend(matched_ids)
            self.record(f"boost.codes.generate_real.db_check.server{server_id}", "passed", "generated redeem code persisted", {"codes": codes, "ids": matched_ids})
        else:
            self.record(f"boost.codes.generate_real.db_check.server{server_id}", "failed", "generated redeem code not found in auth db", {"codes": codes})
            return

        cleanup_ok = True
        for code_id in matched_ids:
            delete_response = self.post_form(
                self.path_with_server("/character-boost/api/redeem-codes/delete-unused", server_id),
                {"id": code_id},
                expected=(200, 422, 500),
            )
            delete_payload = self.record_json_result(
                f"boost.codes.generate_real.cleanup.{code_id}.server{server_id}",
                delete_response,
                accepted_codes=(200, 422, 500),
                success_codes=(200,),
            )
            cleanup_ok = cleanup_ok and bool(delete_payload and delete_payload.get("success"))

        remaining = self.find_redeem_codes(codes)
        if cleanup_ok and not remaining:
            self.generated_redeem_code_ids = [code_id for code_id in self.generated_redeem_code_ids if code_id not in matched_ids]
        self.record(
            f"boost.codes.generate_real.cleanup.db_check.server{server_id}",
            "passed" if not remaining else "failed",
            "generated redeem code cleanup verification",
            {"deleted_ids": matched_ids, "remaining": [row.get("id") for row in remaining]},
        )

    def exercise_validation_paths(self, server_id: int) -> None:
        cases = [
            (f"account.set_gm.validation.server{server_id}", "/account/api/set-gm", {"id": 0, "gmlevel": 0}),
            (f"account.update_email.validation.server{server_id}", "/account/api/update-email", {"id": 0, "email": ""}),
            (f"account.change_password.validation.server{server_id}", "/account/api/change-password", {"id": 0, "password": ""}),
            (f"account.kick.validation.server{server_id}", "/account/api/kick", {"id": 0}),
            (f"character.set_level.validation.server{server_id}", "/character/api/set-level", {"guid": 0, "level": 0}),
            (f"character.set_gold.validation.server{server_id}", "/character/api/set-gold", {"guid": 0, "gold": 0}),
            (f"character.teleport.validation.server{server_id}", "/character/api/teleport", {"guid": 0, "map": 0, "x": 0, "y": 0, "z": 0}),
            (f"character.reset_talents.validation.server{server_id}", "/character/api/reset-talents", {"guid": 0}),
            (f"character.reset_spells.validation.server{server_id}", "/character/api/reset-spells", {"guid": 0}),
            (f"character.reset_cooldowns.validation.server{server_id}", "/character/api/reset-cooldowns", {"guid": 0}),
            (f"character.rename_flag.validation.server{server_id}", "/character/api/rename-flag", {"guid": 0}),
            (f"character.boost.validation.server{server_id}", "/character/api/boost", {"guid": 0, "template_id": ""}),
            (f"bag.reduce.validation.server{server_id}", "/bag/api/reduce", {"guid": 0, "slot": 0, "count": 0}),
            (f"item_ownership.bulk.validation.server{server_id}", "/item-ownership/api/bulk", {"action": "unknown", "instances": []}),
            (f"creature.create.validation.server{server_id}", "/creature/api/create", {"new_creature_id": 0}),
            (f"creature.save.validation.server{server_id}", "/creature/api/save", {"entry": 0, "changes": json.dumps({})}),
            (f"creature.delete.validation.server{server_id}", "/creature/api/delete", {"entry": 0}),
            (f"creature.exec_sql.validation.server{server_id}", "/creature/api/exec-sql", {"sql": ""}),
            (f"creature.add_model.validation.server{server_id}", "/creature/api/add-model", {"creature_id": 0, "display_id": 0}),
            (f"item.create.validation.server{server_id}", "/item/api/create", {"new_item_id": 0}),
            (f"item.save.validation.server{server_id}", "/item/api/save", {"entry": 0, "changes": json.dumps({})}),
            (f"item.delete.validation.server{server_id}", "/item/api/delete", {"entry": 0}),
            (f"item.exec_sql.validation.server{server_id}", "/item/api/exec-sql", {"sql": ""}),
            (f"quest.create.validation.server{server_id}", "/quest/api/create", {"new_id": 0}),
            (f"quest.save.validation.server{server_id}", "/quest/api/save", {"id": 0, "changes": json.dumps({})}),
            (f"quest.delete.validation.server{server_id}", "/quest/api/delete", {"id": 0}),
            (f"quest.exec_sql.validation.server{server_id}", "/quest/api/exec-sql", {"sql": ""}),
            (f"quest.editor_save.validation.server{server_id}", "/quest/api/editor/save", {"id": 0, "payload": json.dumps({})}),
            (f"boost.codes.delete_unused.validation.server{server_id}", "/character-boost/api/redeem-codes/delete-unused", {"id": 0}),
        ]
        for name, path, payload in cases:
            response = self.post_form(self.path_with_server(path, server_id), payload, expected=(200, 422, 404, 409, 500))
            self.record_json_result(name, response, accepted_codes=(200, 422, 404, 409, 500), success_codes=(200, 422, 404, 409))

    def cleanup(self) -> None:
        for server_id, mail_id in list(self.created_mail_ids):
            with contextlib.suppress(Exception):
                payload = self.post_form(self.path_with_server("/mail/api/delete", server_id), {"mail_id": mail_id}, expected=(200, 422, 500)).json()
                if payload.get("success"):
                    self.created_mail_ids.remove((server_id, mail_id))
        for code_id in list(self.generated_redeem_code_ids):
            for server_id in self.server_ids():
                with contextlib.suppress(Exception):
                    payload = self.post_form(self.path_with_server("/character-boost/api/redeem-codes/delete-unused", server_id), {"id": code_id}, expected=(200, 422, 500)).json()
                    if payload.get("success"):
                        self.generated_redeem_code_ids.remove(code_id)
                        break
        if self.created_account_id:
            try:
                self.post_json_api("account.cleanup_delete", "/account/api/delete", {"id": self.created_account_id}, expected_codes=(200, 422, 500))
            except Exception as exc:
                self.record("account.cleanup_delete", "failed", f"cleanup delete failed: {exc}")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="AGMP 全功能模拟交互测试脚本")
    parser.add_argument("--base-url", default="http://127.0.0.1/agmp", help="AGMP 本地访问地址")
    parser.add_argument("--deployment-root", type=Path, default=Path(r"C:\web\WWW\AGMP"), help="本地部署目录")
    parser.add_argument("--db-host", default="127.0.0.1")
    parser.add_argument("--db-port", type=int, default=43306)
    parser.add_argument("--db-user", default="root")
    parser.add_argument("--db-password", default="")
    parser.add_argument("--auth-db", default="acore_auth")
    parser.add_argument("--world-dbs", nargs="+", default=["acore_world70", "acore_world80"])
    parser.add_argument("--characters-dbs", nargs="+", default=["acore_characters70", "acore_characters80"])
    parser.add_argument("--soap-host", default="127.0.0.1")
    parser.add_argument("--soap-port", type=int, default=7878)
    parser.add_argument("--soap-ports", nargs="+", type=int, default=[], help="按 realm 顺序提供多个 SOAP 端口")
    parser.add_argument("--soap-user", default="soap_user")
    parser.add_argument("--soap-password", default="soap_pass")
    parser.add_argument("--soap-uri", default="urn:AC")
    parser.add_argument("--admin-user", default="agmp_admin")
    parser.add_argument("--admin-password", default="AgmpTest@123456")
    parser.add_argument("--locale", default="zh_CN")
    parser.add_argument("--timeout", type=int, default=20)
    parser.add_argument("--output-dir", type=Path, default=Path("test_reports") / f"agmp_full_{int(time.time())}")
    parser.add_argument("--auto-install", action="store_true", help="缺少 requests/pymysql 时自动安装")
    parser.add_argument("--restore-generated-after-run", action="store_true", help="测试结束后恢复 deployment_root/config/generated 备份")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    reporter = Reporter(args.output_dir)
    try:
        requests_mod = ensure_package("requests", "requests", args.auto_install)
        pymysql_mod = ensure_package("pymysql", "pymysql", args.auto_install)
    except Exception as exc:
        reporter.add(TestResult(name="environment.dependencies", status="failed", message=str(exc), details={"traceback": traceback.format_exc()}))
        reporter.finalize(args)
        return 2

    tester = AgmpTester(args, reporter, requests_mod, pymysql_mod)
    exit_code = 0
    try:
        tester.run_setup_flow()
        if tester.login():
            tester.run_authenticated_checks()
        else:
            exit_code = 1
    except Exception as exc:
        exit_code = 1
        trace = traceback.format_exc()
        artifact = reporter.write_text("fatal_traceback.txt", trace)
        reporter.add(TestResult(name="fatal", status="failed", message=str(exc), details={"traceback": trace}, artifacts=[artifact]))
    finally:
        with contextlib.suppress(Exception):
            tester.cleanup()
        with contextlib.suppress(Exception):
            tester.restore_generated_dir()

    json_path, md_path = reporter.finalize(args)
    print(json.dumps({
        "success": exit_code == 0,
        "report_json": str(json_path),
        "report_md": str(md_path),
        "results": [dataclasses.asdict(item) for item in reporter.results],
    }, ensure_ascii=False, indent=2))
    return exit_code


if __name__ == "__main__":
    raise SystemExit(main())