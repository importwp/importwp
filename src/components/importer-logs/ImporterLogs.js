import React, { useState } from 'react';
import { withRouter } from 'react-router';
import PropTypes from 'prop-types';

import qs from 'qs';
import ImporterLogArchive from '../importer-log-archive/ImporterLogArchive';
import ImporterLogSingle from '../importer-log-single/ImporterLogSingle';

const AJAX_BASE = window.iwp.admin_base;

function ImporterLogs(props) {
  const [log, setLogState] = useState(() => {
    const { log: logParam } = qs.parse(props.location.search);
    return logParam ? logParam : null;
  });

  const setLog = (nextLog) => {
    const { id } = props;

    if (nextLog) {
      props.history.push(
        AJAX_BASE + '&edit=' + id + '&step=' + 5 + '&log=' + nextLog
      );
    } else {
      props.history.push(AJAX_BASE + '&edit=' + id + '&step=' + 5);
    }

    setLogState(nextLog ? nextLog : null);
  };

  const { id } = props;

  return (
    <React.Fragment>
      {log === null ? (
        <ImporterLogArchive id={id} onSetLog={setLog} />
      ) : (
        <ImporterLogSingle id={id} log={log} onSetLog={setLog} />
      )}
    </React.Fragment>
  );
}

ImporterLogs.propTypes = {
  id: PropTypes.number,
  location: PropTypes.object,
  history: PropTypes.object,
};

export default withRouter(ImporterLogs);
