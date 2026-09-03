import React, { useCallback, useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import { importer } from '../../services/importer.service';

const colStyles = (index) => {
  let width = '60%';
  switch (index) {
    case 0:
      width = '20%';
      break;
    case 2:
      width = '20%';
      break;
  }
  return {
    width: width,
  };
};

function ImporterLogArchive(props) {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);

  const displayDate = (timestamp) => {
    var date = new Date(timestamp * 1000);
    return (
      date.getDate() +
      '/' +
      (date.getMonth() + 1) +
      '/' +
      date.getFullYear() +
      ' at ' +
      date.getHours() +
      ':' +
      (date.getMinutes() > 9 ? date.getMinutes() : '0' + date.getMinutes())
    );
  };

  const getLogs = useCallback(() => {
    setLoading(true);

    const { id } = props;
    importer.logs(id).then((data) => {
      setLogs(data);
      setLoading(false);
    });
  }, [props.id]);

  useEffect(() => {
    getLogs();

    return () => {
      importer.abort('logs');
    };
  }, [getLogs]);

  return (
    <React.Fragment>
      <div className="iwp-form">
        <p className="iwp-heading iwp-heading--has-tooltip">Importer History. <a href="https://www.importwp.com/docs/import-history/?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=importer" target='_blank' className='iwp-label__tooltip'>?</a></p>

        <div className="iwp-table__wrapper">
          <table className="iwp-table iwp-table--fixed iwp-table--logs">
            <thead>
              <tr>
                <th style={colStyles(0)}>Date</th>
                <th style={colStyles(1)}>Stats</th>
                <th style={colStyles(2)}>Action</th>
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr>
                  <td colSpan={2}>Loading...</td>
                </tr>
              )}
              {loading === false && logs.length == 0 && (
                <tr>
                  <td colSpan={2}>No history logs found.</td>
                </tr>
              )}
              {logs
                .filter((log) => log !== null)
                .map((log) => (
                  <tr key={log?.id ? log.id : log.session}>
                    <td style={colStyles(0)}>
                      {displayDate(log.timestamp)}
                    </td>
                    <td style={colStyles(1)}>
                      {log?.status && (
                        <React.Fragment>
                          <strong>Status:</strong> {log.status}{' '}
                        </React.Fragment>
                      )}
                      {log.hasOwnProperty('stats') && log.stats.inserts > 0 && (
                        <React.Fragment>
                          , <strong>Inserts:</strong> {log.stats.inserts}
                        </React.Fragment>
                      )}
                      {log.hasOwnProperty('stats') && log.stats.updates > 0 && (
                        <React.Fragment>
                          , <strong>Updates:</strong> {log.stats.updates}
                        </React.Fragment>
                      )}
                      {log.hasOwnProperty('stats') && log.stats.deletes > 0 && (
                        <React.Fragment>
                          , <strong>Deletes:</strong> {log.stats.deletes}
                        </React.Fragment>
                      )}
                      {log.hasOwnProperty('stats') && log.stats.errors > 0 && (
                        <React.Fragment>
                          , <strong>Errors:</strong> {log.stats.errors}
                        </React.Fragment>
                      )}
                    </td>
                    <td style={colStyles(2)}>
                      <a
                        onClick={() => props.onSetLog(log?.id ? log.id : log.session)}
                        className="button button-secondary button-small"
                      >
                        View
                      </a>
                    </td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>
      </div>
    </React.Fragment>
  );
}

ImporterLogArchive.propTypes = {
  id: PropTypes.number,
  onSetLog: PropTypes.func,
};

export default ImporterLogArchive;
