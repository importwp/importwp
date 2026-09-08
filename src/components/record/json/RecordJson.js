import { useCallback, useEffect, useRef, useState, Fragment } from 'react';
import PropTypes from 'prop-types';
import debounce from 'lodash.debounce';
import { useDispatch, useSelector } from 'react-redux';

import { importer } from '../../../services/importer.service';
import {
  selectPreviewRecord,
  setPreviewRecord,
} from '../../../features/importer/importerSlice';
import RecordNavigator from '../RecordNavigator';

const RecordJson = ({
  id,
  onSelect = () => {},
  base_path,
  onError = () => {},
  onRecordChange = () => {},
}) => {
  const dispatch = useDispatch();
  const storedRecord = useSelector(selectPreviewRecord);
  const [loading, setLoading] = useState(true);
  const [recordData, setRecordData] = useState(null);
  const [record, setRecord] = useState(storedRecord);
  const [total, setTotal] = useState(0);

  const propsRef = useRef();
  propsRef.current = {
    id,
    base_path,
    onError,
    onRecordChange,
    record,
  };

  const settingsKey = [id, base_path].join('|');
  const prevSettingsKeyRef = useRef();

  const displayNodeClick = useCallback((content, xpath = '') => {
    return (
      <span
        title={xpath}
        onClick={() => onSelect(xpath)}
        dangerouslySetInnerHTML={{
          __html: content,
        }}
      ></span>
    );
  }, [onSelect]);

  const displayNode = useCallback((currentNode) => {
    const node_name = currentNode.node;
    const node_xpath = currentNode.xpath ? '{' + currentNode.xpath + '}' : '';

    if (currentNode.type === 'text') {
      return <li>{displayNodeClick(currentNode.value, node_xpath)}</li>;
    }

    const hasChildren = Array.isArray(currentNode.value);

    return (
      <li>
        {displayNodeClick('"' + node_name + '"', node_xpath)}
        {hasChildren ? (
          <>
            {displayNodeClick(': {', node_xpath)}
            {currentNode.value.length > 0 ? (
              <ul>
                {currentNode.value.map((node, i) => (
                  <Fragment key={i}>{displayNode(node)}</Fragment>
                ))}
              </ul>
            ) : null}
            {displayNodeClick('}', node_xpath)}
          </>
        ) : (
          <>
            {displayNodeClick(': ', node_xpath)}
            {displayNodeClick(
              typeof currentNode.value === 'string'
                ? '"' + currentNode.value + '"'
                : String(currentNode.value),
              node_xpath
            )}
          </>
        )}
      </li>
    );
  }, [displayNodeClick]);

  const fetchPreview = () => {
    const current = propsRef.current;
    if (!(current.id && current.base_path)) {
      setLoading(false);
      setRecordData(null);
      return;
    }

    importer
      .filePreview(current.id, {
        base_path: current.base_path,
        record: current.record,
      })
      .then((response) => {
        const nextRecordData = response && response.data ? response.data : response;
        setRecordData(nextRecordData);
        if (typeof response?.record === 'number') {
          setRecord(response.record);
          if (response.record !== current.record) {
            dispatch(setPreviewRecord(response.record));
            current.onRecordChange(response.record);
          }
        }
        if (typeof response?.total === 'number') {
          setTotal(response.total);
        }
      })
      .catch((e) => current.onError(e))
      .finally(() => {
        setLoading(false);
      });
  };

  const debouncedFetchRef = useRef();
  if (!debouncedFetchRef.current) {
    debouncedFetchRef.current = debounce(fetchPreview, 300);
  }

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

  const output = recordData
    ? displayNode(recordData)
    : 'No data to preview, please try changing the base_path.';

  return (
    <div className="iwp-preview iwp-preview--json">
      <RecordNavigator
        record={record}
        total={total}
        onChange={onNavigate}
        disabled={loading}
      />
      <div className="iwp-preview__body">
        {loading ? 'Loading' : <ul>{output}</ul>}
      </div>
    </div>
  );
};

RecordJson.propTypes = {
  id: PropTypes.number,
  onSelect: PropTypes.func,
  base_path: PropTypes.string,
  onError: PropTypes.func,
  onRecordChange: PropTypes.func,
};

export default RecordJson;
