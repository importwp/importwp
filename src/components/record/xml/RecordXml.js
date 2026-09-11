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

const RecordXml = ({
  id,
  onSelect = () => {},
  base_path,
  onError = () => {},
  onRecordChange = () => {},
}) => {
  const dispatch = useDispatch();
  const storedRecord = useSelector(selectPreviewRecord);
  const initialCache =
    id && base_path
      ? importer.getCachedFilePreview(id, {
          base_path,
          record: storedRecord,
        })
      : undefined;
  const [loading, setLoading] = useState(() => !initialCache);
  const [recordData, setRecordData] = useState(() => {
    if (!initialCache) {
      return null;
    }
    return initialCache.data ? initialCache.data : initialCache;
  });
  const [record, setRecord] = useState(() =>
    typeof initialCache?.record === 'number'
      ? initialCache.record
      : storedRecord
  );
  const [total, setTotal] = useState(() =>
    typeof initialCache?.total === 'number' ? initialCache.total : 0
  );

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

  const displayNodeAttributes = useCallback((attributes) => {
    return (
      <>
        {typeof attributes === 'object' &&
          attributes.map((attribute) => (
            <Fragment key={attribute.name}>
              {' '}
              {displayNodeClick(
                attribute.name + '="' + attribute.value + '"',
                '{' + attribute.xpath + '}'
              )}
            </Fragment>
          ))}
      </>
    );
  }, [displayNodeClick]);

  const displayNode = useCallback((currentNode) => {
    const node_name = currentNode.node;
    const node_xpath = currentNode.xpath ? '{' + currentNode.xpath + '}' : '';

    if (currentNode.type === 'text') {
      return <li>{displayNodeClick(currentNode.value, node_xpath)}</li>;
    }

    return (
      <li>
        {displayNodeClick('&lt;' + node_name, node_xpath)}
        {displayNodeAttributes(currentNode.attr)}
        {displayNodeClick('&gt;', node_xpath)}
        {typeof currentNode.value === 'object' ? (
          <ul
            className={
              Object.keys(currentNode.value).length === 1 &&
              currentNode.value['0'] &&
              currentNode.value['0'].type
                ? 'iwp-preview__' + currentNode.value['0'].type
                : ''
            }
          >
            {currentNode.value.map((node, i) => (
              <Fragment key={i}>{displayNode(node)}</Fragment>
            ))}
          </ul>
        ) : (
          displayNodeClick(currentNode.value, node_xpath)
        )}
        {displayNodeClick('&lt;/' + node_name + '&gt;</li>', node_xpath)}
      </li>
    );
  }, [displayNodeAttributes, displayNodeClick]);

  const applyResponse = (response, current) => {
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
  };

  const fetchPreview = () => {
    const current = propsRef.current;
    if (!(current.id && current.base_path)) {
      setLoading(false);
      return;
    }

    const data = {
      base_path: current.base_path,
      record: current.record,
    };
    const cached = importer.getCachedFilePreview(current.id, data);
    if (cached) {
      applyResponse(cached, current);
      setLoading(false);
      return;
    }

    setLoading(true);
    importer
      .filePreview(current.id, data)
      .then((response) => {
        applyResponse(response, current);
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
    const settingsChanged =
      prevSettingsKeyRef.current !== undefined &&
      prevSettingsKeyRef.current !== settingsKey;
    prevSettingsKeyRef.current = settingsKey;

    if (settingsChanged) {
      setLoading(true);
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
    <div className="iwp-preview iwp-preview--xml">
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

RecordXml.propTypes = {
  id: PropTypes.number,
  onSelect: PropTypes.func,
  base_path: PropTypes.string,
  onError: PropTypes.func,
  onRecordChange: PropTypes.func,
};

export default RecordXml;
