import React, { useCallback, useRef, useState } from 'react';
import PropTypes from 'prop-types';

import DatasourceLocal from '../datasource/local/DatasourceLocal';
import DatasourceRemote from '../datasource/remote/DatasourceRemote';
import DatasourceUpload from '../datasource/upload/DatasourceUpload';
import { importer } from '../../services/importer.service';
import Modal from '../modal/Modal';
import ExistingDatasource from '../datasource/existing/ExistingDatasource';

function DatasourceForm({
  id,
  complete,
  parser,
  file: fileProp,
  files,
  datasource: datasourceProp = 'upload',
  settings = {},
  onError = () => { }
}) {
  const [datasource, setDatasource] = useState(datasourceProp);
  const [remote_url, setRemoteUrl] = useState(settings.remote_url ? settings.remote_url : '');
  const [local_url, setLocalUrl] = useState(settings.local_url ? settings.local_url : '');
  const [file, setFile] = useState(fileProp);
  const [prevFileProp, setPrevFileProp] = useState(fileProp);
  const [prevDatasourceProp, setPrevDatasourceProp] = useState(datasourceProp);
  const [saving, setSaving] = useState(false);
  const [showModalState, setShowModalState] = useState(false);
  const [modalTitle, setModalTitle] = useState('');
  const [modalMessage, setModalMessage] = useState('');

  const datasourceRef = useRef(null);

  const onChange = (event) => {
    const { name, value } = event.target;
    if (name === 'datasource') {
      setDatasource(value);
    } else if (name === 'remote_url') {
      setRemoteUrl(value);
    } else if (name === 'local_url') {
      setLocalUrl(value);
    }
  };

  const showModal = useCallback((content = '', title = '') => {
    setShowModalState(true);
    setModalMessage(content);
    setModalTitle(title);
  }, []);

  const closeModal = useCallback(() => {
    setShowModalState(false);
  }, []);

  const processFile = useCallback((callback = () => { }) => {
    const title = 'Processing File';
    showModal(<progress className="iwp-progress-bar" />, title);
    importer
      .process(id)
      .promise.then(() => {
        showModal(
          <progress className="iwp-progress-bar" value="100" max="100" />,
          title
        );
        callback();
      })
      .catch(e => onError(e));
  }, [id, onError, showModal]);

  const save = useCallback((callback = () => { }) => {
    setSaving(true);

    importer
      .save({
        id: id,
        datasource: datasource,
        remote_url: remote_url,
        local_url: local_url
      })
      .then(() => {
        setSaving(false);
        callback();
      })
      .catch(error => {
        onError(error);
        setSaving(false);
      });
  }, [id, datasource, remote_url, local_url, onError]);

  const onSave = () => {
    if (datasourceRef && datasourceRef.current) {
      datasourceRef.current.run(() => {
        onError('New File added.');
        processFile(() => {
          save(() => {
            closeModal();
          });
        });
      });
    } else {
      save(() => {
        closeModal();
      });
    }
  };

  const onSubmit = () => {
    if (datasourceRef && datasourceRef.current) {
      datasourceRef.current.run(() => {
        save(() => {
          processFile(() => {
            complete();
          });
        });
      });
    } else {
      save(() => {
        complete();
      });
    }
  };

  if (fileProp !== prevFileProp) {
    setPrevFileProp(fileProp);
    if (fileProp !== null) {
      setFile(fileProp);
    }
  }

  if (datasourceProp !== prevDatasourceProp) {
    setPrevDatasourceProp(datasourceProp);
    setDatasource(datasourceProp);
    setRemoteUrl(settings.remote_url ? settings.remote_url : '');
    setLocalUrl(settings.local_url ? settings.local_url : '');
  }

  let btn_action_text = 'Save';
  switch (datasource) {
    case 'upload':
      btn_action_text = 'Upload';
      break;
    case 'remote':
    case 'local':
      btn_action_text = 'Download';
      break;
  }

  return (
    <React.Fragment>
      <Modal
        title={modalTitle}
        onClose={closeModal}
        show={showModalState}
        closable={false}
      >
        {modalMessage}
      </Modal>
      <div className="iwp-form">
        <p className="iwp-heading iwp-heading--has-tooltip">Datasource. <a href="https://www.importwp.com/docs/selecting-a-file-to-import/?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=importer" target='_blank' className='iwp-label__tooltip'>?</a></p>
        <p>
          Select from the options below, the method to be used to retrieve
          your data file.
        </p>
        <form encType="multipart/form-data">
          <div className="iwp-accordion__block iwp-accordion__block--first">
            <div className="iwp-block__handle">
              <input
                id="datasource_upload"
                type="radio"
                name="datasource"
                value="upload"
                onChange={onChange}
                checked={datasource === 'upload'}
              />
              <label htmlFor="datasource_upload">
                <strong>Uploaded File</strong> - Upload a file from your
                computer.
              </label>
            </div>
            {datasource === 'upload' && (
              <div className="iwp-block__content">
                <DatasourceUpload
                  id={id}
                  complete={processFile}
                  showModal={showModal}
                  closeModal={closeModal}
                  onError={onError}
                  ref={datasourceRef}
                />
              </div>
            )}
          </div>

          <div className="iwp-accordion__block">
            <div className="iwp-block__handle">
              <input
                id="datasource_remote"
                type="radio"
                name="datasource"
                value="remote"
                onChange={onChange}
                checked={datasource === 'remote'}
              />
              <label htmlFor="datasource_remote">
                <strong>Remote File</strong> - Download your file from a
                website or url.
              </label>
            </div>
            {datasource === 'remote' && (
              <div className="iwp-block__content">
                <DatasourceRemote
                  id={id}
                  complete={processFile}
                  remote_url={remote_url}
                  filetype={parser}
                  onChange={onChange}
                  showModal={showModal}
                  closeModal={closeModal}
                  onError={onError}
                  ref={datasourceRef}
                />
              </div>
            )}
          </div>

          <div className="iwp-accordion__block">
            <div className="iwp-block__handle">
              <input
                id="datasource_local"
                type="radio"
                name="datasource"
                value="local"
                onChange={onChange}
                checked={datasource === 'local'}
              />
              <label htmlFor="datasource_local">
                <strong>Local File</strong> - Get file from within a local
                folder.
              </label>
            </div>
            {datasource === 'local' && (
              <div className="iwp-block__content">
                <DatasourceLocal
                  id={id}
                  complete={processFile}
                  local_url={local_url}
                  filetype={parser}
                  onChange={onChange}
                  showModal={showModal}
                  closeModal={closeModal}
                  onError={onError}
                  ref={datasourceRef}
                />
              </div>
            )}
          </div>

          <div className="iwp-accordion__block">
            <div className="iwp-block__handle">
              <input
                id="datasource_attached"
                type="radio"
                name="datasource"
                value="existing"
                onChange={onChange}
                checked={datasource === 'existing'}
              />
              <label htmlFor="datasource_attached">
                <strong>Existing Files</strong> - Choose from a list of
                previously attached files.
              </label>
            </div>
            {datasource === 'existing' && (
              <div className="iwp-block__content">
                <ExistingDatasource
                  id={id}
                  onError={onError}
                  files={files}
                  file={file}
                  ref={datasourceRef}
                />
              </div>
            )}
          </div>
        </form>
      </div>

      <div className="iwp-form__actions">
        <div className="iwp-buttons">
          <button
            className="button button-secondary"
            type="button"
            onClick={onSave}
            disabled={saving}
          >
            {saving && <span className="spinner is-active"></span>}
            {saving ? 'Saving' : btn_action_text}
          </button>{' '}
          <button
            className="button button-primary"
            type="button"
            onClick={onSubmit}
            disabled={saving}
          >
            {saving && <span className="spinner is-active"></span>}
            {saving ? 'Saving' : btn_action_text + ' & continue'}
          </button>
        </div>
      </div>
    </React.Fragment>
  );
}

DatasourceForm.propTypes = {
  id: PropTypes.number.isRequired,
  complete: PropTypes.func,
  parser: PropTypes.string,
  file: PropTypes.number,
  files: PropTypes.object,
  datasource: PropTypes.string,
  settings: PropTypes.object,
  onError: PropTypes.func
};

export default DatasourceForm;
