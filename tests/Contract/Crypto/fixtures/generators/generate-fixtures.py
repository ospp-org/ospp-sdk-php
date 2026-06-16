#!/usr/bin/env python3
"""
Regenerate the cross-language signing parity fixtures (run from EITHER SDK repo — output is byte-identical).

Writes, into the sibling fixtures/ directory (the parent of this script's directory):
  - hmac-golden-vectors.json      golden HMAC vectors (real spec payloads, external oracle)
  - signing-classification.json   the machine-readable spec §5.6 classification

Both files MUST stay byte-identical with the copies in the other SDK repo; running this script in both
repos against the same spec ref reproduces identical bytes.

External oracle (NOT either SDK):
  - canonical form : OSPP Canonical Form per spec §4.8 (here: json.dumps sort_keys/compact/unescaped)
  - expected_mac   : openssl dgst -sha256 -mac HMAC -macopt hexkey:... (battle-tested external tool)
A python-hmac cross-check guards against a botched openssl invocation.

Spec source: $OSPP_SPEC_DIR, else the first ancestor directory that contains a sibling `spec` repo
(…/spec/spec/06-security.md). Payloads are loaded VERBATIM from real spec files (no transcription).
"""
import base64
import hashlib
import hmac
import json
import os
import subprocess
import sys

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
FIXTURES_DIR = os.path.dirname(SCRIPT_DIR)  # generators/ -> fixtures/


def find_spec_dir():
    env = os.environ.get("OSPP_SPEC_DIR")
    if env:
        if not os.path.isfile(os.path.join(env, "spec", "06-security.md")):
            sys.exit(f"OSPP_SPEC_DIR={env} does not look like the spec repo (missing spec/06-security.md)")
        return env
    d = SCRIPT_DIR
    for _ in range(16):
        cand = os.path.join(d, "spec")
        if os.path.isfile(os.path.join(cand, "spec", "06-security.md")):
            return cand
        parent = os.path.dirname(d)
        if parent == d:
            break
        d = parent
    sys.exit("spec repo not found; set OSPP_SPEC_DIR to the ospp-org/spec checkout")


SPEC = find_spec_dir()


def load(rel):
    with open(os.path.join(SPEC, rel), "r", encoding="utf-8") as f:
        return json.load(f)


def write_fixture(name, obj):
    path = os.path.join(FIXTURES_DIR, name)
    with open(path, "w", encoding="utf-8") as f:
        json.dump(obj, f, indent=2, ensure_ascii=False)
        f.write("\n")
    return path


# ───────────────────────── HMAC golden vectors ─────────────────────────
RAW_KEY = hashlib.sha256(b"OSPP_TEST_SESSION_KEY_V1").digest()  # 32 bytes; spec conformance/test-keys/session-test-key.bin
SESSION_KEY_B64 = base64.b64encode(RAW_KEY).decode("ascii")


def envelope(message_id, message_type, action, timestamp, source, payload):
    return {
        "messageId": message_id,
        "messageType": message_type,
        "action": action,
        "timestamp": timestamp,
        "source": source,
        "protocolVersion": "1.0.0",
        "payload": payload,
    }


def canonical(msg):
    stripped = {k: v for k, v in msg.items() if k != "mac"}  # top-level mac strip (matches SDK)
    return json.dumps(stripped, sort_keys=True, separators=(",", ":"), ensure_ascii=False)


def openssl_mac(canon_str):
    p = subprocess.run(
        ["openssl", "dgst", "-sha256", "-mac", "HMAC", "-macopt", "hexkey:" + RAW_KEY.hex(), "-binary"],
        input=canon_str.encode("utf-8"), capture_output=True, check=True,
    )
    return base64.b64encode(p.stdout).decode("ascii")


def python_mac(canon_str):
    return base64.b64encode(hmac.new(RAW_KEY, canon_str.encode("utf-8"), hashlib.sha256).digest()).decode("ascii")


def build_hmac_fixture():
    specs = [
        dict(
            name="start-service-request",
            description="StartService Request (Server->Station). Nested params object; integer durationSeconds; key-sort at depth.",
            payloadSource="examples/payloads/mqtt/start-service.request.json",
            msg=envelope("msg_ss_0001", "Request", "StartService", "2026-02-13T09:52:00.000Z", "Server",
                         load("examples/payloads/mqtt/start-service.request.json")),
        ),
        dict(
            name="transaction-event-request",
            description="TransactionEvent Request (Station->Server). Deep nesting (receipt, meterValues); integer creditsCharged (money, atomic units); base64 strings containing '/' and '+' probe slash-escaping parity.",
            payloadSource="examples/payloads/mqtt/transaction-event.request.json",
            msg=envelope("msg_tx_0002", "Request", "TransactionEvent", "2026-02-13T09:56:45.000Z", "Station",
                         load("examples/payloads/mqtt/transaction-event.request.json")),
        ),
        dict(
            name="session-ended-event",
            description="SessionEnded Event (Station->Server). messageType=Event; integer creditsCharged (sole online-billing source) + integer meterValues.",
            payloadSource="conformance/test-vectors/valid/transaction/session-ended-event-timer-expired.json",
            msg=envelope("msg_se_0003", "Event", "SessionEnded", "2026-02-13T09:56:46.000Z", "Station",
                         load("conformance/test-vectors/valid/transaction/session-ended-event-timer-expired.json")),
        ),
    ]
    v4 = envelope("msg_strip_0004", "Request", "StartService", "2026-02-13T09:52:00.000Z", "Server",
                  load("conformance/test-vectors/valid/transaction/start-service-request-minimal.json"))
    v4["mac"] = "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="
    specs.append(dict(
        name="mac-strip",
        description="StartService Request (minimal) with a bogus top-level 'mac' present in the INPUT. The mac field MUST be stripped before signing; expectedCanonicalJson has no mac and expectedMac is computed over the stripped form.",
        payloadSource="conformance/test-vectors/valid/transaction/start-service-request-minimal.json",
        msg=v4,
    ))

    vectors = []
    for s in specs:
        canon = canonical(s["msg"])
        o_mac = openssl_mac(canon)
        assert o_mac == python_mac(canon), f"openssl/python HMAC mismatch on {s['name']} — botched oracle"
        vectors.append({
            "name": s["name"],
            "description": s["description"],
            "payloadSource": s["payloadSource"],
            "message": s["msg"],
            "expectedCanonicalJson": canon,
            "expectedMac": o_mac,
        })

    return {
        "_comment": (
            "OSPP cross-language HMAC golden vectors. MUST stay BYTE-IDENTICAL with the sibling copy in the other SDK "
            "repo (ospp-sdk-php: tests/Contract/Crypto/fixtures/hmac-golden-vectors.json ; sdk-ts: "
            "tests/crypto/fixtures/hmac-golden-vectors.json). Safety net for live HMAC signing: if either SDK's canonical "
            "form or HMAC diverges, its golden test goes RED against these externally-computed values. "
            "Reproducible without either SDK: expectedCanonicalJson = the OSPP Canonical Form of "
            "the message (spec §4.8: strip top-level 'mac', recursive lexicographic key sort, compact JSON, UTF-8); "
            "expectedMac = openssl dgst -sha256 -mac HMAC -macopt hexkey:<hex(base64_decode(sessionKeyBase64))> "
            "-binary | base64, over UTF8(expectedCanonicalJson)."
        ),
        "specRef": "v0.5.0",
        "specSection": "06-security.md §4.8 (OSPP Canonical Form) + §5.3-§5.5 (HMAC)",
        "algorithm": "expectedMac = base64(HMAC-SHA256(base64_decode(sessionKeyBase64), UTF8(expectedCanonicalJson)))",
        "canonicalForm": "strip top-level 'mac' -> recursive lexicographic (UTF-8 byte) key sort, arrays preserved -> compact JSON (',' ':' separators, no whitespace) -> UTF-8 bytes",
        "sessionKeyDerivation": "sessionKeyBase64 = base64(SHA-256('OSPP_TEST_SESSION_KEY_V1')) — matches spec conformance/test-keys/session-test-key.bin",
        "sessionKeyBase64": SESSION_KEY_B64,
        "vectors": vectors,
    }


# ───────────────────────── signing classification (spec §5.6) ─────────────────────────
CRITICAL = [
    ("AuthorizeOfflinePass", "Request"),  ("AuthorizeOfflinePass", "Response"),
    ("ReserveBay", "Request"),            ("ReserveBay", "Response"),
    ("CancelReservation", "Request"),     ("CancelReservation", "Response"),
    ("StartService", "Request"),          ("StartService", "Response"),
    ("StopService", "Request"),           ("StopService", "Response"),
    ("TransactionEvent", "Request"),      ("TransactionEvent", "Response"),
    ("SessionEnded", "Event"),
    ("ChangeConfiguration", "Request"),   ("ChangeConfiguration", "Response"),
    ("Reset", "Request"),                 ("Reset", "Response"),
    ("UpdateFirmware", "Request"),        ("UpdateFirmware", "Response"),
    ("SetMaintenanceMode", "Request"),    ("SetMaintenanceMode", "Response"),
    ("UpdateServiceCatalog", "Request"),  ("UpdateServiceCatalog", "Response"),
    ("SignCertificate", "Request"),       ("SignCertificate", "Response"),
    ("CertificateInstall", "Request"),    ("CertificateInstall", "Response"),
    ("TriggerCertificateRenewal", "Request"), ("TriggerCertificateRenewal", "Response"),
    ("TriggerMessage", "Request"),        ("TriggerMessage", "Response"),
]
ALWAYS_EXEMPT = [
    ("BootNotification", "Request"),
    ("BootNotification", "Response"),
    ("ConnectionLost", "Event"),
]
PHP_API_ONLY = ["IssueOfflinePass", "RevokeOfflinePass", "WebPaymentAuthorization"]


def build_classification_fixture():
    assert len(CRITICAL) == 31 and len(set(CRITICAL)) == 31
    assert len({a for a, _ in CRITICAL}) == 16
    assert len(ALWAYS_EXEMPT) == 3 and len(PHP_API_ONLY) == 3
    assert {a for a, _ in CRITICAL}.isdisjoint({a for a, _ in ALWAYS_EXEMPT})
    return {
        "_comment": (
            "OSPP §5.6 message-signing classification, as machine-readable data. MUST stay BYTE-IDENTICAL with the "
            "sibling copy in the other SDK repo (ospp-sdk-php: tests/Contract/Crypto/fixtures/signing-classification.json ; "
            "sdk-ts: tests/crypto/fixtures/signing-classification.json). Cross-language parity safety net for live HMAC "
            "signing: sdk-ts asserts its (action,messageType) registry equals criticalInCriticalMode + alwaysExempt; "
            "ospp-sdk-php (which keys by action only) asserts its critical set MINUS phpApiOnlySuperset collapses to the "
            "16 distinct critical actions here, and runs a no-split guard proving that action-only collapse stays sound. "
            "Source of truth: spec/06-security.md §5.6 (Critical mode: 31 of 47 signed, 16 exempt, 3 always-exempt)."
        ),
        "specRef": "v0.5.0",
        "specSection": "06-security.md §5.6",
        "criticalInCriticalMode": [{"action": a, "messageType": m} for a, m in CRITICAL],
        "alwaysExempt": [{"action": a, "messageType": m} for a, m in ALWAYS_EXEMPT],
        "phpApiOnlySuperset": PHP_API_ONLY,
    }


if __name__ == "__main__":
    h = write_fixture("hmac-golden-vectors.json", build_hmac_fixture())
    c = write_fixture("signing-classification.json", build_classification_fixture())
    print(f"spec: {SPEC}")
    print(f"wrote {h}")
    print(f"wrote {c}")
