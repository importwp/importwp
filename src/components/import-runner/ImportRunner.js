import React, { useCallback, useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';

import { importer } from '../../services/importer.service';
import Modal from '../modal/Modal';
import ProgressBar from '../progress-bar/ProgressBar';
import StatusMessage from '../status-message/StatusMessage';
import ImporterLogTable from '../importer-log-table/ImporterLogTable';

const ImportRunner = ({
  id,
  session,
  status,
  onComplete = () => {},
}) => {
  const [modalTitle, setModalTitle] = useState('Initialising Import.');
  const [modalContent, setModalContent] = useState('');
  const [modalClosable, setModalClosable] = useState(false);
  const [modalLoading, setModalLoading] = useState(true);
  const [progress, setProgress] = useState(0);
  const [complete, setComplete] = useState(false);
  const [showModal, setShowModal] = useState(true);
  const [error, setError] = useState(false);

  const runnerRef = useRef(null);
  const documentTitleRef = useRef(document.title);

  const closeModal = useCallback(() => {
    setShowModal(false);
  }, []);

  const updateStatus = useCallback((response) => {
    const nextModalContent = (
      <p className="iwp-import__stats">
        <StatusMessage status={response} />
      </p>
    );

    if (response.status === 'error') {
      document.title = documentTitleRef.current;
      setModalTitle('A fatal error has occurred.');
      setModalLoading(false);
      setModalContent(<p>{response.message}</p>);
      setError(true);
      return;
    }

    if (response.status === 'complete') {
      document.title = documentTitleRef.current;
      setModalTitle('Complete.');
      setModalContent(nextModalContent);
      setProgress(100);
      setModalClosable(true);
      setModalLoading(false);
      setComplete(true);
      return;
    }

    if (response.section === 'import') {
      if (response.status === 'init') {
        const counter = +response.process;
        document.title = 'Processing File: ' + counter + '%';
        setModalTitle('Processing File.');
        setProgress(counter);
        setModalContent(nextModalContent);
      } else {
        const counter = response.progress.import.current_row;
        const total = response.progress.import.end - response.progress.import.start;
        document.title = 'Importing: (' + counter + '/' + total + ')';
        setModalTitle('Importing.');
        setProgress(Math.round((counter / total) * 100));
        setModalContent(nextModalContent);
      }
    } else if (response.section === 'delete') {
      const counter = response.progress.delete.current_row;
      const total = response.progress.delete.end - response.progress.delete.start;
      document.title = 'Deleting: (' + counter + '/' + total + ')';
      setModalTitle('Deleting.');
      setProgress(Math.round((counter / total) * 100));
      setModalContent(nextModalContent);
    }
  }, []);

  const run = useCallback(() => {
    runnerRef.current = importer.run(id, session);
    documentTitleRef.current = document.title;
    setComplete(false);

    runnerRef.current.request.subscribe(
      (response) => {
        if (response.status !== 'S' || response.data.status === 'error') {
          setModalTitle('A fatal error has occurred.');
          setModalLoading(false);
          setModalContent(<p>{response.data.message}</p>);
          setError(true);
          runnerRef.current.abort();
          document.title = documentTitleRef.current;
          return;
        }

        if (response.data.status === 'complete') {
          runnerRef.current.abort();
        }

        updateStatus(response.data);
      },
      (err) => {
        runnerRef.current.abort();
        if (err.status > 0) {
          let error_msg = err.statusText;
          if (
            err.responseJSON &&
            err.responseJSON.code &&
            err.responseJSON.message &&
            err.responseJSON.code === 'IWP_ERR'
          ) {
            error_msg = err.responseJSON.message;
          }

          setModalTitle('A fatal error has occurred.');
          setModalLoading(false);
          setModalContent(<p>{error_msg}</p>);
          setError(true);
        }
      }
    );
  }, [id, session, updateStatus]);

  const stop = useCallback(() => {
    if (runnerRef.current) {
      runnerRef.current.abort();
    }
    importer.stop(id, session).then(
      () => {
        setShowModal(false);
        onComplete();
        document.title = documentTitleRef.current;
      },
      (error_msg) => {
        setModalTitle('A fatal error has occurred.');
        setModalLoading(false);
        setModalContent(<p>{error_msg}</p>);
        setError(true);
      }
    );
  }, [id, onComplete, session]);

  useEffect(() => {
    run();

    return () => {
      if (runnerRef.current) {
        runnerRef.current.abort();
      }
    };
  }, [run]);

  useEffect(() => {
    if (status != null) {
      updateStatus(status);
    }
  }, [status, updateStatus]);

  return (
    <Modal
      title={modalTitle}
      onClose={() => {
        closeModal();
        onComplete();
      }}
      show={showModal}
      closable={modalClosable}
      loading={modalLoading}
    >
      <ProgressBar progress={progress} text={progress + '%'} />

      {modalContent}

      {!error ? (
        <>
          {!complete ? (
            <>
              <button
                type="button"
                className="button button-link-delete"
                style={{ marginBottom: '20px' }}
                onClick={stop}
              >
                Cancel
              </button>
            </>
          ) : (
            <>
              <button
                type="button"
                onClick={() => {
                  closeModal();
                  onComplete();
                }}
                className="button button-secondary"
                style={{ marginBottom: '20px' }}
              >
                Close
              </button>
              <ImporterLogTable id={id} log={session} />
            </>
          )}
        </>
      ) : (
        <button
          type="button"
          onClick={() => {
            closeModal();
            onComplete();
          }}
          className="button button-link-delete"
          style={{ marginBottom: '20px' }}
        >
          Close
        </button>
      )}
    </Modal>
  );
};

ImportRunner.propTypes = {
  id: PropTypes.number.isRequired,
  session: PropTypes.string.isRequired,
  onComplete: PropTypes.func,
};

export default ImportRunner;
