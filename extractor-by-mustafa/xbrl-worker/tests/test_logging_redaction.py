from hissa_xbrl_worker.logging_config import sanitize_log_value, sanitize_mapping


def test_sensitive_mapping_values_are_redacted():
    payload = sanitize_mapping({"password": "super-secret", "token": "abc123", "safe": "ok"})

    assert payload == {"password": "[REDACTED]", "token": "[REDACTED]", "safe": "ok"}
    assert "super-secret" not in repr(payload)
    assert "abc123" not in repr(payload)


def test_nested_sensitive_values_are_redacted():
    payload = sanitize_log_value({"Authorization": "Bearer secret", "nested": [{"api_key": "key"}]})

    assert payload["Authorization"] == "[REDACTED]"
    assert payload["nested"][0]["api_key"] == "[REDACTED]"

