import React, { useCallback, useImperativeHandle, useState, forwardRef } from 'react';
import PropTypes from 'prop-types';

import { importer } from '../../../services/importer.service';
import FieldLabel from '../../field-label/FieldLabel';

const DatasourceLocal = forwardRef(function DatasourceLocal({
  id,
  local_url,
  filetype: filetypeProp,
  onChange,
  complete,
  showModal = () => { },
  closeModal = () => { },
  onError = () => { }
}, ref) {
  const [filetype, setFiletype] = useState(filetypeProp);
  const [filetype_enabled] = useState(() => !filetypeProp);

  const onFiletypeChange = (event) => {
    setFiletype(event.target.value);
  };

  const run = useCallback((callback = () => { }) => {
    const title = 'Uploading';
    showModal(<progress className="iwp-progress-bar" />, title);

    let form_data = new FormData();
    form_data.append('local_url', local_url);
    form_data.append('filetype', filetype);
    form_data.append('action', 'file_local');

    importer.upload(id, form_data).then(
      () => {
        showModal(
          <progress className="iwp-progress-bar" value="100" max="100" />,
          title
        );
        closeModal();
        callback();
      },
      error => {
        onError(error);
        closeModal();
      }
    );
  }, [id, local_url, filetype, showModal, closeModal, onError]);

  useImperativeHandle(ref, () => ({
    run
  }), [run]);

  const extra_file_types = window.iwp.hooks.applyFilters('iwp_allowed_file_types', []);

  return (
    <React.Fragment>
      <div className="iwp-field">
        <div className="iwp-field__left">
          <FieldLabel
            id="local_url"
            field="local_url"
            label="Local Path"
            tooltip="Enter the path to the file from the servers root."
          />
        </div>
        <div className="iwp-field__right">
          <input
            className="iwp-form__input"
            id="local_url"
            name="local_url"
            type="text"
            value={local_url}
            onChange={onChange}
          />
        </div>
      </div>
      {filetype_enabled && (
        <div className="iwp-field iwp-pb--0 iwp-pt--0">
          <div className="iwp-field__left">
            <label className="iwp-form__label" htmlFor="remote_url">
              File Type:
            </label>
          </div>
          <div className="iwp-field__right">
            <select name="filetype" value={filetype} onChange={onFiletypeChange}>
              <option value="">Choose file type</option>
              <option value="csv">CSV File</option>
              <option value="xml">XML File</option>
              <option value="json">JSON File</option>
              {extra_file_types && extra_file_types.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
            </select>
          </div>
        </div>
      )}
    </React.Fragment>
  );
});

DatasourceLocal.propTypes = {
  id: PropTypes.number,
  local_url: PropTypes.string,
  filetype: PropTypes.string,
  onChange: PropTypes.func,
  complete: PropTypes.func,
  showModal: PropTypes.func,
  closeModal: PropTypes.func,
  onError: PropTypes.func
};

export default DatasourceLocal;
