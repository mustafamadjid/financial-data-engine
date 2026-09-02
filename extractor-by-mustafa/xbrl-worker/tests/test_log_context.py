from hissa_xbrl_worker.log_context import (
    LogContext,
    clear_log_context,
    get_log_context,
    set_log_context,
)


def test_log_context_can_be_set_and_cleared():
    context = LogContext(correlation_id="corr-01", filing_id="filing_01")

    set_log_context(context)
    assert get_log_context() == context

    clear_log_context()
    assert get_log_context().filing_id is None

