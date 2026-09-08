import React, { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import debounce from 'lodash.debounce';
import { useDispatch, useSelector } from 'react-redux';

import { importer } from '../../../services/importer.service';
import {
  selectPreviewRecord,
  setPreviewRecord,
} from '../../../features/importer/importerSlice';
import RecordNavigator from '../RecordNavigator';

const RecordCsv = ({
  id,
  onSelect = () => {},
  show_headings = true,
  delimiter,
  enclosure,
  escape,
  onError = () => {},
  file_encoding = '',
  onRecordChange = () => {},
}) => {
  const dispatch = useDispatch();
  const storedRecord = useSelector(selectPreviewRecord);
  const [loading, setLoading] = useState(true);
  const [headings, setHeadings] = useState([]);
  const [row, setRow] = useState([]);
  const [error, setError] = useState(false);
  const [record, setRecord] = useState(storedRecord);
  const [total, setTotal] = useState(0);

  const propsRef = useRef();
  propsRef.current = {
    id,
    delimiter,
    enclosure,
    escape,
    show_headings,
    file_encoding,
    onError,
    onRecordChange,
    record,
  };

  const settingsKey = [
    id,
    delimiter,
    enclosure,
    escape,
    show_headings,
    file_encoding,
  ].join('|');
  const prevSettingsKeyRef = useRef();

  const fetchPreview = () => {
    const current = propsRef.current;
    if (!(current.id && current.delimiter && current.enclosure)) {
      setLoading(false);
      return;
    }

    const data = {
      delimiter: current.delimiter,
      enclosure: current.enclosure,
      escape: current.escape,
      show_headings: current.show_headings,
      file_encoding: current.file_encoding,
      record: current.record,
    };
    setError(false);
    importer
      .filePreview(current.id, data)
      .then((response) => {
        if (response.headings.length == response.row.length) {
          setHeadings(response.headings);
          setRow(response.row);
          const nextRecord =
            typeof response.record === 'number'
              ? response.record
              : current.record;
          const nextTotal =
            typeof response.total === 'number' ? response.total : 0;
          setRecord(nextRecord);
          setTotal(nextTotal);
          if (nextRecord !== current.record) {
            dispatch(setPreviewRecord(nextRecord));
            current.onRecordChange(nextRecord);
          }
        } else {
          setHeadings([]);
          setRow([]);
          setError(
            `Inconsistent num of fields, header: ${response.headings.length}, this line: ${response.row.length} `
          );
        }
      })
      .catch((e) => {
        setHeadings([]);
        setRow([]);
        setError(e);
        current.onError(e);
      })
      .finally(() => {
        setLoading(false);
      });
  };

  const debouncedFetchRef = useRef();
  if (!debouncedFetchRef.current) {
    debouncedFetchRef.current = debounce(fetchPreview, 300);
  }

  const displayNodeClick = (content, xpath = '') => {
    return (
      <span title={xpath} onClick={() => onSelect(xpath)}>
        {content.length > 0 ? content : <>&nbsp;</>}
      </span>
    );
  };

  const display = () => {
    if (error) {
      return (
        <tbody>
          <tr>
            <td colSpan="2">
              <span>Error displaying record: {error}</span>
            </td>
          </tr>
        </tbody>
      );
    }

    return (
      <tbody>
        {headings.map((heading, index) => (
          <tr key={index}>
            <th>
              {displayNodeClick(
                false === show_headings ? index : heading,
                '{' + index + '}'
              )}
            </th>
            <td>{displayNodeClick(row[index], '{' + index + '}')}</td>
          </tr>
        ))}
      </tbody>
    );
  };

  useEffect(() => {
    setLoading(true);
    const settingsChanged =
      prevSettingsKeyRef.current !== undefined &&
      prevSettingsKeyRef.current !== settingsKey;
    prevSettingsKeyRef.current = settingsKey;

    if (settingsChanged) {
      debouncedFetchRef.current();
    } else {
      fetchPreview();
    }

    return () => {
      debouncedFetchRef.current.cancel();
      importer.abort('filePreview');
    };
  }, [settingsKey, record]);

  const onNavigate = (nextRecord) => {
    if (nextRecord === record) {
      return;
    }
    setLoading(true);
    setRecord(nextRecord);
    dispatch(setPreviewRecord(nextRecord));
    onRecordChange(nextRecord);
  };

  return (
    <div className="iwp-preview iwp-preview--csv">
      <RecordNavigator
        record={record}
        total={total}
        onChange={onNavigate}
        disabled={loading}
      />
      <div className="iwp-preview__body">
        {loading ? (
          'Loading'
        ) : (
          <table border="1" cellPadding="0" cellSpacing="0">
            <thead>
              <tr>
                <th>
                  <span>
                    {false === show_headings ? 'Column Number' : 'Heading'}
                  </span>
                </th>
                <th>
                  <span>Value</span>
                </th>
              </tr>
            </thead>
            {display()}
          </table>
        )}
      </div>
    </div>
  );
};

RecordCsv.propTypes = {
  id: PropTypes.number,
  onSelect: PropTypes.func,
  show_headings: PropTypes.bool,
  delimiter: PropTypes.string,
  enclosure: PropTypes.string,
  escape: PropTypes.string,
  onError: PropTypes.func,
  file_encoding: PropTypes.string,
  onRecordChange: PropTypes.func,
};

export default RecordCsv;
