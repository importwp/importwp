import { useCallback, useEffect, useMemo, useRef, useState, Fragment } from 'react';
import PropTypes from 'prop-types';
import debounce from 'lodash.debounce';

import { importer } from '../../../services/importer.service';

const RecordXml = ({
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
    <div className="iwp-preview iwp-preview--xml">
      {loading ? 'Loading' : <ul>{output}</ul>}
    </div>
  );
};

RecordXml.propTypes = {
  id: PropTypes.number,
  onSelect: PropTypes.func,
  base_path: PropTypes.string,
  onError: PropTypes.func,
};

export default RecordXml;
