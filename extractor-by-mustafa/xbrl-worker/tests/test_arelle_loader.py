from unittest.mock import Mock, patch

import pytest

from hissa_xbrl_worker.arelle_loader import load_model
from hissa_xbrl_worker.errors import WorkerError


class FakeController:
    def __init__(self, *args, **kwargs):
        self.webCache = Mock(cacheDir=None, workOffline=False)
        self.close = Mock()


def _fake_arelle(model):
    controller = FakeController()
    file_source = Mock()
    manager = Mock()
    manager.load.return_value = model
    manager.close = Mock()
    return controller, file_source, manager


def test_load_model_yields_model_and_closes_all_arelle_resources(tmp_path):
    source = tmp_path / "instance.xbrl"
    source.write_text("xbrl", encoding="utf-8")
    model = Mock(contexts={"c": object()}, facts=[])
    controller, file_source, manager = _fake_arelle(model)

    with patch("arelle.Cntlr.Cntlr", return_value=controller), patch("arelle.FileSource.openFileSource", return_value=file_source), patch("arelle.ModelManager.initialize", return_value=manager):
        with load_model(source) as actual:
            assert actual is model

    file_source.close.assert_called_once_with()
    manager.close.assert_called_once_with()
    controller.close.assert_called_once_with()
    assert controller.webCache.workOffline is True


def test_load_model_rejects_model_without_contexts_or_facts(tmp_path):
    source = tmp_path / "not-xbrl.xml"
    source.write_text("xml", encoding="utf-8")
    controller, file_source, manager = _fake_arelle(Mock(contexts={}, facts=[]))

    with patch("arelle.Cntlr.Cntlr", return_value=controller), patch("arelle.FileSource.openFileSource", return_value=file_source), patch("arelle.ModelManager.initialize", return_value=manager):
        with pytest.raises(WorkerError) as exc:
            with load_model(source):
                pass

    assert exc.value.code == "UNSUPPORTED_DOCUMENT"
    assert exc.value.exit_code == 11


def test_load_model_converts_arelle_exception_to_load_error(tmp_path):
    source = tmp_path / "broken.xbrl"
    source.write_text("broken", encoding="utf-8")
    controller, file_source, manager = _fake_arelle(None)
    manager.load.side_effect = RuntimeError("internal Arelle failure")

    with patch("arelle.Cntlr.Cntlr", return_value=controller), patch("arelle.FileSource.openFileSource", return_value=file_source), patch("arelle.ModelManager.initialize", return_value=manager):
        with pytest.raises(WorkerError) as exc:
            with load_model(source):
                pass

    assert exc.value.code == "XBRL_LOAD_ERROR"
    assert exc.value.exit_code == 12


def test_load_model_rejects_missing_file_before_initializing_arelle(tmp_path):
    missing = tmp_path / "missing.xbrl"

    with pytest.raises(WorkerError) as exc:
        with load_model(missing):
            pass

    assert exc.value.code == "INPUT_FILE_ERROR"
    assert exc.value.exit_code == 10


@pytest.mark.parametrize("resource", ["file_source", "manager", "controller"])
def test_load_model_survives_resource_close_failure(tmp_path, resource):
    source = tmp_path / "instance.xbrl"
    source.write_text("xbrl", encoding="utf-8")
    model = Mock(contexts={"c": object()}, facts=[])
    controller, file_source, manager = _fake_arelle(model)
    target = {"file_source": file_source, "manager": manager, "controller": controller}[resource]
    target.close.side_effect = RuntimeError("close failure")

    with patch("arelle.Cntlr.Cntlr", return_value=controller), patch("arelle.FileSource.openFileSource", return_value=file_source), patch("arelle.ModelManager.initialize", return_value=manager):
        with load_model(source) as actual:
            assert actual is model
