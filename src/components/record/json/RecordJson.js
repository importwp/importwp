import { useCallback, useEffect, useMemo, useRef, useState, Fragment } from 'react';
import PropTypes from 'prop-types';
import debounce from 'lodash.debounce';

import { importer } from '../../../services/importer.service';

const RecordJson = ({
  id,
  onSelect = () => {},
  base_path,
  onError = () => {},
}) => {
  const [loading, setLoading] = useState(true);
  const [record, setRecord] = useState(null);

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

  const getPreview = useMemo(
    () =>
      debounce(() => {
        if (id && base_path) {
          setLoading(true);

          importer
            .filePreview(id, {
              base_path,
            })
            .then((nextRecord) => {
              setRecord(nextRecord);
            })
            .catch((e) => onError(e))
            .finally(() => {
              setLoading(false);
            });
        } else {
          setLoading(false);
          setRecord(null);
        }
      }, 300),
    [id, base_path, onError]
  );

  const getPreviewRef = useRef(getPreview);
  getPreviewRef.current = getPreview;

  useEffect(() => {
    getPreviewRef.current();

    return () => {
      getPreviewRef.current.cancel();
    };
  }, [id, base_path]);

  const output = record
    ? displayNode(record)
    : 'No data to preview, please try changing the base_path.';

  return (
    <div className="iwp-preview iwp-preview--json">
      {loading ? 'Loading' : <ul>{output}</ul>}
    </div>
  );
};

RecordJson.propTypes = {
  id: PropTypes.number,
  onSelect: PropTypes.func,
  base_path: PropTypes.string,
  onError: PropTypes.func,
};

export default RecordJson;
