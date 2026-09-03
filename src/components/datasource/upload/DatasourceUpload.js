import React, { useImperativeHandle, useState } from 'react';
import PropTypes from 'prop-types';
import { importer } from '../../../services/importer.service';
import FieldLabel from '../../field-label/FieldLabel';

const DatasourceUpload = React.forwardRef(({
  complete,
  id,
  showModal = () => {},
  closeModal = () => {},
  onError = () => {},
}, ref) => {
  const [file, setFile] = useState(null);

  const onChange = (event) => {
    setFile(event.target.files[0]);
  };

  const run = (callback = () => {}) => {
    const title = 'Uploading';
    showModal(<progress className="iwp-progress-bar" />, title);

    const file_data = file;
    let form_data = new FormData();
    form_data.append('file', file_data);
    form_data.append('action', 'file_upload');
    importer.upload(id, form_data).then(
      () => {
        showModal(
          <progress className="iwp-progress-bar" value="100" max="100" />,
          title
        );
        closeModal();
        callback();
      },
      (error) => {
        onError(error);
        closeModal();
      }
    );
  };

  useImperativeHandle(ref, () => ({
    run
  }));

  return (
    <div className="iwp-field">
      <div className="iwp-field__left">
        <FieldLabel
          field="upload_file"
          id="upload_file"
          label="Upload File"
          tooltip="Select the file you wish to import via the file upload input."
        />
      </div>
      <div className="iwp-field__right">
        <input
          className="iwp-form__input"
          id="upload_file"
          name="file"
          type="file"
          onChange={onChange}
        />
      </div>
    </div>
  );
});

DatasourceUpload.propTypes = {
  complete: PropTypes.func,
  id: PropTypes.number,
  showModal: PropTypes.func,
  closeModal: PropTypes.func,
  onError: PropTypes.func,
};

export default DatasourceUpload;
