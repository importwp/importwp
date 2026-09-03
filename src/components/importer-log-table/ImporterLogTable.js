import React, { useCallback, useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import debounce from 'lodash.debounce';

import { importer } from '../../services/importer.service';

const colStyles = (index) => {
  let width = '60%';
  switch (index) {
    case 0:
      width = '20%';
      break;
    case 1:
      width = '80%';
      break;
  }
  return {
    width: width,
  };
};

function ImporterLogTable(props) {
  const [logs, setLogs] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [hasMore, setHasMore] = useState(true);
  const [page, setPage] = useState(0);

  const scrollBoxRef = useRef(null);
  const isLoadingRef = useRef(isLoading);
  const hasMoreRef = useRef(hasMore);
  const pageRef = useRef(page);

  isLoadingRef.current = isLoading;
  hasMoreRef.current = hasMore;
  pageRef.current = page;

  const getLog = useCallback(() => {
    const nextPage = pageRef.current + 1;
    setIsLoading(true);
    setPage(nextPage);
    const { id, log } = props;
    importer.log(id, log, nextPage).then((data) => {
      setLogs((prevLogs) => prevLogs.concat(data.logs));
      setIsLoading(false);
      setHasMore(data.logs.length > 0);
    });
  }, [props.id, props.log]);

  useEffect(() => {
    setPage(0);
    pageRef.current = 0;
    getLog();

    const node = scrollBoxRef.current;
    if (node) {
      const handleScroll = debounce(() => {
        window.requestAnimationFrame(() => {
          if (
            node.scrollTop > node.scrollHeight - node.clientHeight * 2 &&
            isLoadingRef.current === false &&
            hasMoreRef.current
          ) {
            getLog();
          }
        });
      }, 100);

      node.addEventListener('scroll', handleScroll);

      return () => {
        node.removeEventListener('scroll', handleScroll);
        handleScroll.cancel();
        importer.abort('log');
      };
    }

    return () => {
      importer.abort('log');
    };
  }, [getLog]);

  return (
    <div className="iwp-table__wrapper">
      <table className="iwp-table iwp-table--fixed iwp-table--logs">
        <thead>
          <tr>
            <th style={colStyles(0)}>Record</th>
            <th style={colStyles(1)}>Message</th>
          </tr>
        </thead>
        <tbody ref={scrollBoxRef}>
          {logs.map((log) => (
            <tr key={log[0] + log[1]}>
              <td className="iwp-table-row">{log[0]}</td>
              <td className="iwp-table-content">{log[2]}</td>
            </tr>
          ))}
          {isLoading && (
            <tr>
              <td colSpan={2}>Loading...</td>
            </tr>
          )}
          {logs.length === 0 && !isLoading && (
            <tr>
              <td colSpan={2}>No Logs found.</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}

ImporterLogTable.propTypes = {
  id: PropTypes.number.isRequired,
  log: PropTypes.string.isRequired,
};

export default ImporterLogTable;
