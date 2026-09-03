import { useCallback, useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';

import { importer } from '../../services/importer.service';

const ImporterDebug = ({ id, settings }) => {
  const [log, setLog] = useState('');
  const [download, setDownload] = useState('');
  const scrollBox = useRef(null);
  const pageRef = useRef(0);
  const logRef = useRef('');

  const getLog = useCallback(() => {
    const page = pageRef.current + 1;
    pageRef.current = page;

    importer.debug_log(id, page).then((data) => {
      const nextLog = logRef.current + (page > 1 ? '\n' : '') + data.log.join('\n');
      const hasMore = data.log.length > 0;

      logRef.current = nextLog;
      setLog(nextLog);
      setDownload(data.download);

      if (hasMore) {
        getLog();
      }
    });
  }, [id]);

  useEffect(() => {
    pageRef.current = 0;
    logRef.current = '';
    setLog('');
    getLog();

    return () => {
      importer.abort('debug_log');
    };
  }, [getLog]);

  return (
    <div className="iwp-form iwp-form--mb">
      <p className="iwp-heading">Debug</p>
      {settings && (
        <>
          <p>Importer Settings:</p>
          <textarea
            disabled
            className="iwp-debug__code iwp-debug__code--settings"
            defaultValue={settings}
          ></textarea>
        </>
      )}
      <>
        <p>
          Import Logs: (
          <a
            href={download}
            target="_blank"
            rel="noopener noreferrer"
          >
            download
          </a>
          )
        </p>
        <textarea
          ref={scrollBox}
          disabled
          className="iwp-debug__code iwp-debug__code--log"
          value={log}
          readOnly
        ></textarea>
      </>
    </div>
  );
};

ImporterDebug.propTypes = {
  id: PropTypes.number,
  settings: PropTypes.string,
  log: PropTypes.string
};

export default ImporterDebug;
