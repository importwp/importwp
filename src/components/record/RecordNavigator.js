import React, { useEffect, useState } from 'react';
import PropTypes from 'prop-types';

/**
 * Record navigation controls for file preview panels.
 * Displays a 1-based record number; value/onChange use 0-based indexes.
 */
const RecordNavigator = ({
  record = 0,
  total = 0,
  onChange = () => {},
  disabled = false,
}) => {
  const [inputValue, setInputValue] = useState(String(record + 1));

  useEffect(() => {
    setInputValue(String(record + 1));
  }, [record]);

  const maxIndex = Math.max(0, total - 1);
  const atStart = disabled || total <= 0 || record <= 0;
  const atEnd = disabled || total <= 0 || record >= maxIndex;

  const commitInput = () => {
    const parsed = parseInt(inputValue, 10);
    if (Number.isNaN(parsed) || total <= 0) {
      setInputValue(String(record + 1));
      return;
    }
    const next = Math.max(0, Math.min(parsed - 1, maxIndex));
    setInputValue(String(next + 1));
    if (next !== record) {
      onChange(next);
    }
  };

  return (
    <div className="iwp-preview__nav">
      <button
        type="button"
        className="button button-secondary"
        onClick={() => onChange(0)}
        disabled={atStart}
        title="First record"
        aria-label="First record"
      >
        «
      </button>
      <button
        type="button"
        className="button button-secondary"
        onClick={() => onChange(Math.max(0, record - 1))}
        disabled={atStart}
        title="Previous record"
        aria-label="Previous record"
      >
        ‹
      </button>
      <label className="iwp-preview__nav-label">
        <span className="screen-reader-text">Record number</span>
        <input
          type="number"
          className="iwp-preview__nav-input"
          min={1}
          max={Math.max(1, total)}
          value={inputValue}
          disabled={disabled || total <= 0}
          onChange={(e) => setInputValue(e.target.value)}
          onBlur={commitInput}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              commitInput();
            }
          }}
        />
        <span className="iwp-preview__nav-total">of {total || 0}</span>
      </label>
      <button
        type="button"
        className="button button-secondary"
        onClick={() => onChange(Math.min(maxIndex, record + 1))}
        disabled={atEnd}
        title="Next record"
        aria-label="Next record"
      >
        ›
      </button>
      <button
        type="button"
        className="button button-secondary"
        onClick={() => onChange(maxIndex)}
        disabled={atEnd}
        title="Last record"
        aria-label="Last record"
      >
        »
      </button>
    </div>
  );
};

RecordNavigator.propTypes = {
  record: PropTypes.number,
  total: PropTypes.number,
  onChange: PropTypes.func,
  disabled: PropTypes.bool,
};

export default RecordNavigator;
