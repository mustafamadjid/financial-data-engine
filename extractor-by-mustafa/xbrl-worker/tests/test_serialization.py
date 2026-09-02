import json

from hissa_xbrl_worker.serialization import serialize_payload, sha256_file


def test_serialize_payload_is_compact_sorted_and_json_decodable():
    serialized = serialize_payload({"z": 1, "a": "nilai"})

    assert serialized == '{"a":"nilai","z":1}'
    assert json.loads(serialized) == {"a": "nilai", "z": 1}


def test_serialize_payload_pretty_prints_without_changing_data():
    compact = serialize_payload({"b": 2, "a": 1})
    pretty = serialize_payload({"b": 2, "a": 1}, pretty=True)

    assert "\n" in pretty
    assert json.loads(compact) == json.loads(pretty)


def test_sha256_file_hashes_binary_content(tmp_path):
    source = tmp_path / "source.xbrl"
    source.write_bytes(b"hello")

    assert sha256_file(source) == "2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824"

