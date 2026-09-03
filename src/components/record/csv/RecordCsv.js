import React, { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import debounce from 'lodash.debounce';

import { importer } from '../../../services/importer.service';

const RecordCsv = ({
  id,
  onSelect = () => { },
  show_headings = true,
  delimiter,
  enclosure,
  escape,
  onError = () => { },
  file_encoding = '',
}) => {
  const [loading, setLoading] = useState(true);
  const [headings, setHeadings] = useState([]);
  const [row, setRow] = useState([]);
  const [error, setError] = useState(false);

  const propsRef = useRef();
  propsRef.current = {
    id,
    delimiter,
    enclosure,
    escape,
    show_headings,
    file_encoding,
    onError,
  };

  const getPreviewRef = useRef();
  if (!getPreviewRef.current) {
    getPreviewRef.current = debounce(() => {
      const current = propsRef.current;
      if (current.id && current.delimiter && current.enclosure) {
        const data = {
          delimiter: current.delimiter,
          enclosure: current.enclosure,
          escape: current.escape,
          show_headings: current.show_headings,
          file_encoding: current.file_encoding,
        };
        setError(false);
        importer
          .filePreview(current.id, data)
          .then((record) => {
            if (record.headings.length == record.row.length) {
              setHeadings(record.headings);
              setRow(record.row);
            } else {
              setHeadings([]);
              setRow([]);
              setError(`Inconsistent num of fields, header: ${record.headings.length}, this line: ${record.row.length} `);
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
      }
    }, 300);
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
            <td colspan="2">
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
    getPreviewRef.current();

    return () => {
      getPreviewRef.current.cancel();
      importer.abort();
    };
  }, [delimiter, enclosure, escape, file_encoding, show_headings, id]);

  const record = display();
  return (
    <div className="iwp-preview iwp-preview--csv">
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
          {record}
        </table>
      )}
    </div>
  );
};

RecordCsv.propTypes = {
  id: PropTypes.number,
  onSelect: PropTypes.func,
  show_headings: PropTypes.bool,
  delimiter: PropTypes.string,
  enclosure: PropTypes.string,
  onError: PropTypes.func,
  file_encoding: PropTypes.string,
};

export default RecordCsv;
