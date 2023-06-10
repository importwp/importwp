import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { importer } from '../../../services/importer.service';
import FieldLabel from '../../field-label/FieldLabel';

class DatasourceUpload extends Component {
  constructor(props) {
    super(props);

    this.state = {
      file: null,
    };

    this.onChange = this.onChange.bind(this);
    // this.uploadFile = this.uploadFile.bind(this);
    this.run = this.run.bind(this);
  }

  onChange(event) {
    this.setState({ file: event.target.files[0] });
  }

  run(callback = () => {}) {
    const title = 'Uploading';
    this.props.showModal(<progress className="iwp-progress-bar" />, title);
    const { file } = this.state;

    const file_data = file;
    let form_data = new FormData();
    form_data.append('file', file_data);
    form_data.append('action', 'file_upload');
    importer.upload(this.props.id, form_data).then(
      () => {
        this.props.showModal(
          <progress className="iwp-progress-bar" value="100" max="100" />,
          title
        );
        this.props.closeModal();
        callback();
      },
      (error) => {
        this.props.onError(error);
        this.props.closeModal();
      }
    );
  }

  render() {
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
            onChange={this.onChange}
          />
        </div>
      </div>
    );
  }
}

DatasourceUpload.propTypes = {
  complete: PropTypes.func,
  id: PropTypes.number,
  showModal: PropTypes.func,
  closeModal: PropTypes.func,
  onError: PropTypes.func,
};

DatasourceUpload.defaultProps = {
  showModal: () => {},
  closeModal: () => {},
  onError: () => {},
};

export default DatasourceUpload;
