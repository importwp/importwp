import React, { useCallback, useEffect, useRef, useState } from 'react';
import ImporterListItem from '../importer-list-item/ImporterListItem';
import { importer } from '../../services/importer.service';
import NoticeList from '../notice-list/NoticeList';
import { Link } from 'react-router-dom';
import GlobalNotice from '../global-notice/GlobalNotice';

const AJAX_BASE = window.iwp.admin_base;

function ArchivePage() {
  const [loaded, setLoaded] = useState(false);
  const [errors, setErrors] = useState([]);
  const [importers, setImporters] = useState([]);
  const [status, setStatus] = useState([]);
  const [init, setInit] = useState(false);

  const statusXHRRef = useRef(null);

  const getStatus = useCallback(() => {
    statusXHRRef.current = importer.status();
    statusXHRRef.current.request.subscribe(
      (response) => {
        setStatus(response);
      },
      () => { }
    );
  }, []);

  const getImporters = useCallback(() => {
    importer
      .importers()
      .then((data) => {
        setImporters(data);
        setLoaded(true);
        setInit(true);

        if (statusXHRRef.current !== null) {
          statusXHRRef.current.abort();
        }
        getStatus();
      })
      .catch((data) => {
        if (data.statusText === 'abort') {
          return;
        }

        setErrors((prevErrors) => [
          ...prevErrors,
          {
            section: 'archive',
            message: data.responseJSON.message,
          },
        ]);
        setLoaded(true);
        setInit(true);
      });
  }, [getStatus]);

  const onDelete = useCallback(
    (id) => {
      setImporters((prevImporters) =>
        prevImporters.filter((data) => data.id !== id)
      );
      getImporters();
    },
    [getImporters]
  );

  useEffect(() => {
    getImporters();

    return () => {
      importer.abort('importers');

      if (statusXHRRef.current) {
        statusXHRRef.current.abort();
      }
    };
  }, [getImporters]);

  if (init === false) {
    return <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />;
  }

  return (
    <React.Fragment>

      <GlobalNotice />

      <div className="iwp-archive-header">
        <Link to={AJAX_BASE + '&new'} className="iwp-add-new">
          Add Importer +
        </Link>
      </div>
      {importers.length > 0 &&
        importers.map((importerItem) => (
          <ImporterListItem
            key={importerItem.id}
            importer={importerItem}
            status={
              Array.isArray(status)
                ? status.find((item) => {
                  return item?.version == 2 ? item.importer == importerItem.id : item.id === importerItem.id;
                })
                : {}
            }
            onDelete={onDelete}
          />
        ))}
      {importers.length === 0 && (
        <NoticeList
          notices={[
            {
              message:
                'No Importers have been created, click add importer to create one.',
              type: 'info',
            },
          ]}
        />
      )}
    </React.Fragment>
  );
}

export default ArchivePage;
