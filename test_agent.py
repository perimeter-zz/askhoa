#!/usr/bin/env python3
"""
AskHOA Testing Agent
Runs 3 iterations of: test -> debug (Hostinger log) -> propose fix
Then exits.

Required env vars for SSH log access:
  HOSTINGER_SSH_HOST  - e.g. ssh.hostinger.com or IP
  HOSTINGER_SSH_USER  - SSH username
  HOSTINGER_SSH_PASS  - SSH password (if no key)
  HOSTINGER_SSH_KEY   - path to private key (optional)
  HOSTINGER_LOG_PATH  - full path to askhoa_error.log on server
                        (default: ~/domains/askhoa.org/public_html/askhoa_error.log)
"""

import os
import sys
import json
import time
import struct
import subprocess
import tempfile
from datetime import datetime
from pathlib import Path

import requests

# ── Config ────────────────────────────────────────────────────────────────────
BASE_URL      = "https://askhoa.org/askhoa_api.php"
MAX_ITER      = 3
ITER_PAUSE    = 5   # seconds between iterations

SSH_HOST      = os.environ.get("HOSTINGER_SSH_HOST", "")
SSH_USER      = os.environ.get("HOSTINGER_SSH_USER", "")
SSH_PASS      = os.environ.get("HOSTINGER_SSH_PASS", "")
SSH_KEY       = os.environ.get("HOSTINGER_SSH_KEY", "")
LOG_PATH      = os.environ.get(
    "HOSTINGER_LOG_PATH",
    "~/domains/askhoa.org/public_html/askhoa_error.log"
)
LOCAL_LOG     = Path(__file__).parent / "askhoa_error.log"

# ── Test file factories ───────────────────────────────────────────────────────

def make_pdf(body_text: str) -> bytes:
    """Build a minimal but parseable PDF containing body_text."""
    stream = (
        "BT\n"
        "/F1 12 Tf\n"
        "50 700 Td\n"
    )
    for i, line in enumerate(body_text.split("\n")):
        escaped = line.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")
        stream += f"({escaped}) Tj\n"
        stream += "0 -16 Td\n"
    stream += "ET\n"
    stream_bytes = stream.encode()
    stream_len   = len(stream_bytes)

    objects = [
        b"",   # placeholder for offset 0
        b"1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        b"2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        (
            b"3 0 obj\n"
            b"<< /Type /Page /Parent 2 0 R\n"
            b"   /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >>\n"
            b"   /MediaBox [0 0 612 792]\n"
            b"   /Contents 4 0 R >>\n"
            b"endobj\n"
        ),
        (
            f"4 0 obj\n<< /Length {stream_len} >>\nstream\n".encode()
            + stream_bytes
            + b"\nendstream\nendobj\n"
        ),
    ]

    body  = b"%PDF-1.4\n"
    offsets = [0] * len(objects)
    for i in range(1, len(objects)):
        offsets[i] = len(body)
        body += objects[i]

    xref_offset = len(body)
    xref  = b"xref\n"
    xref += f"0 {len(objects)}\n".encode()
    xref += b"0000000000 65535 f \n"
    for i in range(1, len(objects)):
        xref += f"{offsets[i]:010d} 00000 n \n".encode()

    trailer = (
        f"trailer\n<< /Size {len(objects)} /Root 1 0 R >>\n"
        f"startxref\n{xref_offset}\n%%EOF\n"
    ).encode()

    return body + xref + trailer


GOOD_PDF_TEXT = """\
AskHOA Test Document - Maple Creek HOA Bylaws

Section 1: General Provisions
All homeowners within Maple Creek subdivision are automatic members of the Association.
Membership runs with ownership and transfers automatically upon sale of the property.

Section 2: Annual Dues and Assessments
Annual dues are $600 per household, payable by February 1st each year.
Late payments incur a $50 per month penalty after the due date.
Special assessments require a two-thirds majority vote at the annual meeting.

Section 3: Architectural Standards
All exterior modifications require prior written approval from the Architectural Review Committee.
Approved paint colors must be selected from the Association approved color palette.
Fences may not exceed six feet in height without a special variance approval.

Section 4: Common Area Rules
Common areas are for exclusive use of Association members and invited guests only.
Pool hours are 7am to 10pm daily from Memorial Day through Labor Day weekend.
Children under 14 must be supervised by a responsible adult while at the pool.

Section 5: Enforcement and Fines
Violations will be addressed with a written notice and a reasonable opportunity to cure.
Fines may be levied at $100 per day for continuing violations after the cure period.
Members may appeal fines to the Board of Directors within 30 days of receiving notice.
"""

GOOD_TXT = """\
Maple Creek HOA - Community Guidelines

Welcome to Maple Creek. These guidelines ensure our neighborhood remains attractive and safe.

Lawn Care: Grass must be mowed weekly during growing season (April through October).
Parking: Vehicles must be parked in driveways or garages overnight, not on the street.
Noise: Quiet hours are 10pm to 7am Sunday through Thursday, 11pm to 8am Friday and Saturday.
Trash: Bins must be retrieved from the curb within 24 hours of collection day.
Pets: Dogs must be leashed at all times in common areas. Owners are responsible for waste cleanup.
Decorations: Holiday decorations may be displayed no earlier than 30 days before the holiday.
Rentals: Owners must notify the HOA in writing before renting their property to tenants.
Complaints: Submit concerns via the online portal at askhoa.org or call the management office.
Violations: First offense receives a written warning. Second offense incurs a $50 fine.
Appeals: Fines may be appealed in writing to the Board within 14 days of issuance.
"""

BAD_DOCX = b"PK\x03\x04\x14\x00\x00\x00\x08\x00" + b"FAKE DOCX CONTENT - not a valid open xml document"
BAD_IMAGE = b"\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01" + b"\x00" * 200 + b"fake jpeg payload"
BAD_EMPTY_PDF = (
    b"%PDF-1.4\n"
    b"1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    b"2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    b"3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
    b"xref\n0 4\n0000000000 65535 f \n"
    b"0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n"
    b"trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n190\n%%EOF\n"
)
BAD_SHORT_TXT  = b"Too short."
BAD_LARGE_FILE = b"X" * (11 * 1024 * 1024)   # 11 MB


# ── Test cases ────────────────────────────────────────────────────────────────

def build_test_cases():
    return [
        dict(name="Valid PDF",
             file=("bylaws.pdf",   make_pdf(GOOD_PDF_TEXT), "application/pdf"),
             expect_success=True,
             desc="Standard multi-section HOA bylaws PDF"),
        dict(name="Valid TXT",
             file=("guidelines.txt", GOOD_TXT.encode(), "text/plain"),
             expect_success=True,
             desc="Plain-text community guidelines"),
        dict(name="DOCX (rejected)",
             file=("document.docx", BAD_DOCX,
                   "application/vnd.openxmlformats-officedocument.wordprocessingml.document"),
             expect_success=False,
             desc="DOCX must be rejected (not PDF/TXT)"),
        dict(name="JPEG disguised as TXT",
             file=("sneaky.txt",  BAD_IMAGE, "text/plain"),
             expect_success=False,
             desc="Binary image bytes sent with text/plain MIME"),
        dict(name="Empty PDF (no text)",
             file=("empty.pdf",   BAD_EMPTY_PDF, "application/pdf"),
             expect_success=False,
             desc="Valid PDF structure but zero text content"),
        dict(name="TXT too short (<100 chars)",
             file=("tiny.txt",    BAD_SHORT_TXT, "text/plain"),
             expect_success=False,
             desc="Text under minimum 100-character threshold"),
        dict(name="Oversized file (>10 MB)",
             file=("huge.txt",    BAD_LARGE_FILE, "text/plain"),
             expect_success=False,
             desc="File exceeds 10 MB upload limit"),
    ]


# ── Upload helper ─────────────────────────────────────────────────────────────

def upload(name, content, mime) -> dict:
    try:
        r = requests.post(
            f"{BASE_URL}?action=upload",
            files={"file": (name, content, mime)},
            timeout=30,
        )
        ct = r.headers.get("content-type", "")
        try:
            body = r.json()
        except Exception:
            body = r.text[:500]
        return {"ok": True, "status": r.status_code, "body": body}
    except Exception as e:
        return {"ok": False, "error": str(e)}


# ── Test runner ───────────────────────────────────────────────────────────────

def run_tests() -> list:
    test_cases = build_test_cases()
    print(f"\n  Running {len(test_cases)} tests against {BASE_URL}\n")
    results = []

    for tc in test_cases:
        fname, content, mime = tc["file"]
        r = upload(fname, content, mime)

        if not r["ok"]:
            passed = False
            note   = f"request error: {r['error']}"
        else:
            body   = r["body"]
            got_ok = (
                isinstance(body, dict) and ("docId" in body or body.get("success"))
            ) or ("docId" in str(body))
            passed = (got_ok == tc["expect_success"])
            note   = json.dumps(body)[:120] if isinstance(body, dict) else str(body)[:120]

        tag = "PASS" if passed else "FAIL"
        print(f"  [{tag}] {tc['name']}")
        print(f"         {tc['desc']}")
        print(f"         HTTP {r.get('status','?')} | {note}")
        print()

        results.append({**tc, "passed": passed, "tag": tag, "response": r})
        time.sleep(0.4)

    return results


# ── Hostinger log fetch ───────────────────────────────────────────────────────

def fetch_log() -> str | None:
    # Try local log first (useful when running locally against prod)
    if LOCAL_LOG.exists():
        text = LOCAL_LOG.read_text()
        print(f"  Using local log ({len(text)} bytes): {LOCAL_LOG}")
        return text

    if not (SSH_HOST and SSH_USER):
        print("  SSH not configured (set HOSTINGER_SSH_HOST / SSH_USER). Skipping log fetch.")
        return None

    print(f"  SSH → {SSH_USER}@{SSH_HOST}  log: {LOG_PATH}")
    cmd = ["ssh", "-o", "StrictHostKeyChecking=no", "-o", "ConnectTimeout=10"]
    if SSH_KEY:
        cmd += ["-i", SSH_KEY]
    cmd += [f"{SSH_USER}@{SSH_HOST}", f"tail -200 {LOG_PATH} 2>/dev/null || echo '__LOG_NOT_FOUND__'"]

    if SSH_PASS:
        # Use sshpass if available, otherwise prompt falls through
        sshpass = subprocess.run(["which", "sshpass"], capture_output=True).returncode == 0
        if sshpass:
            cmd = ["sshpass", "-p", SSH_PASS] + cmd

    try:
        out = subprocess.run(cmd, capture_output=True, text=True, timeout=20)
        if "__LOG_NOT_FOUND__" in out.stdout:
            print(f"  Log file not found at {LOG_PATH} on server.")
            return None
        print(f"  Fetched {len(out.stdout)} bytes from server log.")
        return out.stdout
    except subprocess.TimeoutExpired:
        print("  SSH timed out.")
        return None
    except Exception as e:
        print(f"  SSH error: {e}")
        return None


# ── Analysis + proposed fixes ─────────────────────────────────────────────────

def analyze(results: list, log: str | None, iteration: int) -> str:
    failures  = [r for r in results if not r["passed"]]
    passes    = [r for r in results if r["passed"]]
    lines     = []

    lines.append(f"=== Iteration {iteration} Analysis ===")
    lines.append(f"Passed: {len(passes)}/{len(results)}  |  Failed: {len(failures)}/{len(results)}")

    if not failures:
        lines.append("All tests passed. No fixes required.")
        return "\n".join(lines)

    lines.append("\nFailed tests:")
    for f in failures:
        body = f["response"].get("body", {})
        lines.append(f"  ✗ {f['name']}")
        lines.append(f"    expected_success={f['expect_success']}  "
                     f"HTTP {f['response'].get('status','?')}  "
                     f"body={str(body)[:100]}")

    # Server log excerpt
    if log:
        err_lines = [l for l in log.splitlines()
                     if any(k in l.lower() for k in ("error", "warning", "fatal", "parse", "notice"))]
        if err_lines:
            lines.append(f"\nServer log errors (last 10 of {len(err_lines)} found):")
            for el in err_lines[-10:]:
                lines.append(f"  {el.strip()}")
    else:
        lines.append("\n(No server log available for this iteration.)")

    # Targeted fix proposals
    lines.append("\n── Proposed Fixes ──────────────────────────────────────────")

    for f in failures:
        name = f["name"]
        expect_ok = f["expect_success"]
        body = f["response"].get("body", {})
        status = f["response"].get("status")

        if name == "Valid PDF" and expect_ok:
            lines.append("""
[FIX] Valid PDF upload failing
  1. Confirm storage/ directory exists and is world-writable on Hostinger:
       ssh> ls -la ~/domains/askhoa.org/public_html/storage/
       ssh> chmod 755 ~/domains/askhoa.org/public_html/storage/
  2. Confirm autoload_pdfparser.php is deployed:
       ssh> ls ~/domains/askhoa.org/public_html/autoload_pdfparser.php
  3. Increase PHP memory if parsing fails silently:
       In .htaccess: php_value memory_limit 256M
  4. Add verbose logging around PDF parse block in askhoa_api.php:
       error_log("PDF parse start: " . $filePath);
       try { ... } catch (Exception $e) { error_log("PDF parse error: " . $e->getMessage()); }
""")

        if name == "Valid TXT" and expect_ok:
            lines.append("""
[FIX] Valid TXT upload failing
  1. Check PHP upload settings on Hostinger (.htaccess or php.ini):
       php_value upload_max_filesize 10M
       php_value post_max_size 12M
  2. Verify file_get_contents() can access temp upload path:
       error_log("TXT read start: " . $_FILES['file']['tmp_name']);
  3. Ensure DocumentProcessor::chunkText() is available (include path correct).
""")

        if name == "DOCX (rejected)" and not expect_ok:
            lines.append("""
[FIX] DOCX file is not being rejected (MIME check too permissive)
  In askhoa_api.php, add a secondary extension guard after the MIME check:

    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'txt'])) {
        echo json_encode(['error' => 'Only PDF or TXT files are accepted.']);
        exit;
    }

  Also tighten the MIME whitelist: some servers report docx as 'application/octet-stream'.
""")

        if name == "JPEG disguised as TXT" and not expect_ok:
            lines.append("""
[FIX] Binary file accepted when sent with text/plain MIME
  Add a magic-byte check in askhoa_api.php for TXT uploads:

    if ($fileType === 'text/plain') {
        $chunk = file_get_contents($_FILES['file']['tmp_name'], false, null, 0, 12);
        $magic = ["\xFF\xD8\xFF", "\x89PNG", "GIF8", "\x25PDF", "PK\x03\x04"];
        foreach ($magic as $sig) {
            if (str_starts_with($chunk, $sig)) {
                echo json_encode(['error' => 'File content does not match declared type.']);
                exit;
            }
        }
    }
""")

        if name == "Empty PDF (no text)" and not expect_ok:
            lines.append("""
[FIX] Image-only PDF not being caught
  The 100-char minimum check should handle this, but verify it exists in askhoa_api.php:

    $text = $pdf->getText();
    if (strlen(trim($text)) < 100) {
        echo json_encode(['error' => 'PDF contains insufficient readable text (image-only?).']);
        exit;
    }
""")

        if name == "TXT too short (<100 chars)" and not expect_ok:
            lines.append("""
[FIX] Short TXT files accepted without error
  Ensure the minimum-length check applies to TXT as well as PDF in askhoa_api.php:

    // After reading TXT content:
    if (strlen(trim($content)) < 100) {
        echo json_encode(['error' => 'Document is too short (minimum 100 characters).']);
        exit;
    }
""")

        if name == "Oversized file (>10 MB)" and not expect_ok:
            lines.append("""
[FIX] Oversized file not rejected (PHP may swallow it before our check)
  In askhoa_api.php, check for PHP's own upload error codes FIRST:

    $err = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        echo json_encode(['error' => 'File exceeds maximum allowed size (10 MB).']);
        exit;
    }

  Also ensure Hostinger php.ini / .htaccess has:
    upload_max_filesize = 10M
    post_max_size = 11M
""")

    return "\n".join(lines)


# ── Report saver ──────────────────────────────────────────────────────────────

def save_report(iteration: int, results: list, log: str | None, analysis: str):
    path = Path(__file__).parent / f"test_report_{iteration}.txt"
    with open(path, "w") as fh:
        fh.write(f"AskHOA Test Report — Iteration {iteration}/{MAX_ITER}\n")
        fh.write(f"Timestamp : {datetime.now().isoformat()}\n")
        fh.write(f"Target    : {BASE_URL}\n\n")
        fh.write("TEST RESULTS\n" + "-" * 40 + "\n")
        for r in results:
            fh.write(f"[{r['tag']}] {r['name']}\n")
            fh.write(f"      expect_success={r['expect_success']}  "
                     f"HTTP {r['response'].get('status','?')}\n")
            fh.write(f"      {str(r['response'].get('body',''))[:200]}\n\n")
        if log:
            fh.write("SERVER LOG (last 2 KB)\n" + "-" * 40 + "\n")
            fh.write(log[-2000:] + "\n\n")
        fh.write("ANALYSIS & PROPOSED FIXES\n" + "-" * 40 + "\n")
        fh.write(analysis + "\n")
    print(f"  Report saved → {path}")


# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    print()
    print("=" * 60)
    print(f"  AskHOA Testing Agent")
    print(f"  {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"  Target   : {BASE_URL}")
    print(f"  Max iters: {MAX_ITER}")
    print("=" * 60)

    for i in range(1, MAX_ITER + 1):
        print(f"\n{'─'*60}")
        print(f"  ITERATION {i}/{MAX_ITER}")
        print(f"{'─'*60}")

        # Phase 1: Test
        print("\n[1/3] Running upload tests…")
        results = run_tests()
        passed  = sum(1 for r in results if r["passed"])
        print(f"  → {passed}/{len(results)} passed")

        # Phase 2: Logs
        print("\n[2/3] Fetching Hostinger error log…")
        log = fetch_log()

        # Phase 3: Analysis
        print("\n[3/3] Analyzing and proposing fixes…")
        analysis = analyze(results, log, i)
        print(analysis)

        save_report(i, results, log, analysis)

        if i < MAX_ITER:
            print(f"\n  Pausing {ITER_PAUSE}s before iteration {i + 1}…")
            time.sleep(ITER_PAUSE)

    print()
    print("=" * 60)
    print(f"  All {MAX_ITER} iterations complete.")
    print("  Agent signing off. Goodbye.")
    print("=" * 60)
    print()
    sys.exit(0)


if __name__ == "__main__":
    main()
