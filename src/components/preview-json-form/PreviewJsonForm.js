import React, { useCallback, useEffect, useState } from 'react';
import PropTypes from 'prop-types';

import RecordJson from '../record/json/RecordJson';
import { importer } from '../../services/importer.service';
import NoticeList from '../notice-list/NoticeList';
import FieldLabel from '../field-label/FieldLabel';

const ENCODINGS = window.iwp.encodings;

function PreviewJsonForm({
  complete,
  id = null,
  settings = {
    base_path: '',
    nodes: {},
  },
  onError = () => {},
}) {
  const [base_path, setBasePath] = useState(
    settings.base_path !== null ? settings.base_path : ''
  );
  const [nodes, setNodes] = useState(settings.nodes);
  const [file_encoding, setFileEncoding] = useState(settings.file_encoding);
  const [processing, setProcessing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [prevSettings, setPrevSettings] = useState(settings);

  if (settings !== prevSettings) {
    setPrevSettings(settings);
    setBasePath(settings.base_path !== null ? settings.base_path : '');
    setNodes(settings.nodes);
    setFileEncoding(settings.file_encoding);
  }

  const disabled = !base_path;

  const onChange = useCallback((event) => {
    const target = event.target;
    const value = target.type === 'checkbox' ? target.checked : target.value;
    const fieldName = target.name;

    if (fieldName === 'base_path') {
      setBasePath(value);
    } else if (fieldName === 'file_encoding') {
      setFileEncoding(value);
    }
  }, []);

  const save = useCallback((callback = () => {}) => {
    setSaving(true);

    importer
      .save({
        id: id,
        file_settings_base_path: base_path,
        file_settings_setup: true,
        file_settings_encoding: file_encoding,
      })
      .then(() => {
        setSaving(false);
        callback();
      })
      .catch((error) => {
        onError(error);
        setSaving(false);
      });
  }, [base_path, file_encoding, id, onError]);

  const onSave = useCallback(() => {
    save();
  }, [save]);

  const onSubmit = useCallback(() => {
    save(() => {
      complete();
    });
  }, [complete, save]);

  useEffect(() => {
    if (settings.processed === false) {
      setProcessing(true);
      importer.process(id).promise.then(
        () => {
          setProcessing(false);
        },
        (error) => onError(error)
      );
    }
  }, [id, onError, settings.processed]);

  return (
    <React.Fragment>
      {processing && (
        <NoticeList
          notices={[
            {
              message: (
                <React.Fragment>We are Processing your file.</React.Fragment>
              ),
              type: 'warn',
            },
          ]}
        />
      )}

      <div className="iwp-form">
        <form>
          <p className="iwp-heading iwp-heading--has-tooltip">
            File Settings.{' '}
            <a
              href="https://www.importwp.com/docs/importer-file-settings/?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=importer"
              target="_blank"
              rel="noreferrer"
              className="iwp-label__tooltip"
            >
              ?
            </a>
          </p>
          <p>
            Configure how the importer reads a record from your file, a
            preview showing the first record is available at the bottom of the
            page.
          </p>
          <div className="iwp-form__row">
            <FieldLabel
              label="Base Path"
              id="base_path"
              field="base_path"
              tooltip="This Record Base is the path to the JSON array of records that you want to import. Use / for a root array."
              display="inline-block"
            />
            <select
              className="iwp-form__input"
              onChange={onChange}
              id="base_path"
              name="base_path"
              value={base_path}
            >
              <option value="">Choose a record base.</option>
              {nodes &&
                Object.keys(nodes).map((key) => (
                  <option key={key} value={key}>
                    {key}
                  </option>
                ))}
            </select>
          </div>
          <div className="iwp-form__row">
            <FieldLabel
              label="Encoding"
              id="file_encoding"
              field="file_encoding"
              tooltip="Set the file encoding, check this if you see unexpected ? in the preview text"
              display="inline-block"
            />
            <select
              className="iwp-form__input"
              onChange={onChange}
              id="file_encoding"
              name="file_encoding"
              value={file_encoding}
            >
              <option value="">Default Encoding</option>
              {Object.keys(ENCODINGS).map((key) => (
                <option key={key} value={key}>
                  {ENCODINGS[key]}
                </option>
              ))}
            </select>
          </div>
          <div className="iwp-form__row">
            <label className="iwp-form__label">Record JSON Preview:</label>
            <RecordJson
              id={id}
              base_path={base_path}
              onError={onError}
            />
          </div>
        </form>
      </div>
      <div className="iwp-form__actions">
        <div className="iwp-buttons">
          <button
            className="button button-secondary"
            type="button"
            onClick={onSave}
            disabled={disabled}
          >
            {saving && <span className="spinner is-active"></span>}
            {saving ? 'Saving' : 'Save'}
          </button>{' '}
          <button
            className="button button-primary"
            type="button"
            onClick={onSubmit}
            disabled={disabled}
          >
            {saving && <span className="spinner is-active"></span>}
            {saving ? 'Saving' : 'Save & Continue'}
          </button>
        </div>
      </div>
    </React.Fragment>
  );
}

PreviewJsonForm.propTypes = {
  complete: PropTypes.func,
  id: PropTypes.number,
  settings: PropTypes.object,
  onError: PropTypes.func,
};

export default PreviewJsonForm;
