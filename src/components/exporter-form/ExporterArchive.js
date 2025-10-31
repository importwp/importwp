import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { exporter } from '../../services/exporter.service';
import GlobalNotice from '../global-notice/GlobalNotice';
import NoticeList from '../notice-list/NoticeList';
import StatusMessage from '../status-message/StatusMessage';

const AJAX_BASE = window.iwp.admin_base;

const ExporterArchive = () => {
  const [exporters, setExporters] = useState([]);
  const [init, setInit] = useState(false);
  const [notices, setNotices] = useState([]);
  const [statuses, setStatus] = useState([]);
  const statusXHR = useRef(null);

  const getExporters = () => {
    exporter
      .exporters()
      .then((data) => {
        setInit(true);
        setExporters(data);
      })
      .catch((e) => {
        console.error(e);
      });
  };

  useEffect(() => {
    getExporters();

    statusXHR.current = exporter.status();
    statusXHR.current.request.subscribe(
      (response) => {
        setStatus(response);
      },
      (e) => {
        // ignore abort errors
        if (e.status > 0) {
          logError(e);
        }
      }
    );

    return () => {
      if (statusXHR.current !== null) {
        statusXHR.current.abort();
      }
    };
  }, []);

  const logError = (error) => {
    let message = error;
    if (error.hasOwnProperty('statusText') && error.hasOwnProperty('status')) {
      message = `The following error has occured: ${error.statusText}, Code: ${error.status}`;
    }

    setNotices([
      ...notices,
      { message: message, type: 'error', dismissible: true },
    ]);
  };

  if (init === false) {
    return <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />;
  }

  return (
    <>
      <GlobalNotice />
      <NoticeList
        notices={notices}
        onDismiss={(i) => {
          setNotices(
            notices.map((item, item_i) =>
              item_i === i ? { ...item, dismissed: true } : item
            )
          );
        }}
      />
      <div className="iwp-archive-header">
        <Link to={AJAX_BASE + '&new-exporter'} className="iwp-add-new">
          Add Exporter +
        </Link>
      </div>
      {exporters.length > 0 &&
        exporters.map((item) => {
          const status = statuses.find((tmp) => tmp.exporter == item.id);
          const msg = <StatusMessage status={{ msg: status?.message }} />;
          return (
            <div className="iwp-importer-list__item" key={`eli-${item.id}`}>
              <div className="iwp-item">
                <div className="iwp-item__left">
                  <h2 className="iwp-heading">{item.name}</h2>
                  <p>
                    Exporting <strong>{item.type}</strong>
                    {item.file_type && (
                      <>
                        {' to '}
                        <strong>{item.file_type}</strong>.
                      </>
                    )}
                  </p>
                </div>
                <div className="iwp-item__right">
                  <div className="iwp-buttons">
                    <Link
                      to={AJAX_BASE + '&edit-exporter=' + item.id}
                      className="button button-primary button-small"
                    >
                      View
                    </Link>
                    <button
                      type="button"
                      onClick={() => {
                        var result = confirm(
                          'Are you sure you want to delete Exporter #' +
                          item.id +
                          ' ' +
                          item.name
                        );
                        if (result) {
                          // TODO: Move this into the archive list component.
                          exporter.remove(item.id).then(() => {
                            setExporters([
                              exporters.filter((data) => data.id !== item.id),
                            ]);

                            getExporters();
                          });
                        }
                      }}
                      className="button button-link-delete button-small"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              </div>
              <div className="iwp-item__progress">
                <p>{msg}</p>
                {status?.status == 'running' && (
                  <div
                    className="iwp-item__progress-bar"
                    style={
                      status && {
                        width: 100 - status.progress + '%',
                      }
                    }
                  ></div>
                )}
              </div>
            </div>
          );
        })}
      {exporters.length == 0 && (
        <NoticeList
          notices={[
            {
              message:
                'No Exporters have been created, click add exporter to create one.',
              type: 'info',
            },
          ]}
        />
      )}
    </>
  );
};

export default ExporterArchive;
