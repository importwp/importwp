import React, { useImperativeHandle, useState } from 'react';
import PropTypes from 'prop-types';

import { importer } from '../../../services/importer.service';
import FieldLabel from '../../field-label/FieldLabel';

const DatasourceRemote = React.forwardRef(({
  id,
  remote_url,
  onChange,
  complete,
  showModal = () => { },
  closeModal = () => { },
  filetype: filetypeProp = '',
  onError = () => { }
}, ref) => {
  const [filetype, setFiletype] = useState(filetypeProp);
  const [filetype_enabled] = useState(!filetypeProp);

  const onFiletypeChange = (event) => {
    setFiletype(event.target.value);
  };

  const run = (callback = () => { }) => {
    const title = 'Downloading File.';

    showModal(<progress className="iwp-progress-bar" />, title);

    let form_data = new FormData();
    form_data.append('remote_url', remote_url);
    form_data.append('filetype', filetype);
    form_data.append('action', 'file_remote');

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
  };

  useImperativeHandle(ref, () => ({
    run
  }));

  const extra_file_types = window.iwp.hooks.applyFilters('iwp_allowed_file_types', []);

  return (
    <React.Fragment>
      <div className="iwp-field">
        <div className="iwp-field__left">
          <FieldLabel
            id="remote_url"
            field="remote_url"
            label="Remote Url"
            tooltip="Enter the  url of the file, this should begin with http/https."
          />
        </div>
        <div className="iwp-field__right">
          <input
            className="iwp-form__input"
            id="remote_url"
            name="remote_url"
            value={remote_url}
            onChange={onChange}
            type="text"
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

DatasourceRemote.propTypes = {
  id: PropTypes.number,
  remote_url: PropTypes.string,
  onChange: PropTypes.func,
  complete: PropTypes.func,
  showModal: PropTypes.func,
  closeModal: PropTypes.func,
  filetype: PropTypes.string,
  onError: PropTypes.func
};

export default DatasourceRemote;
