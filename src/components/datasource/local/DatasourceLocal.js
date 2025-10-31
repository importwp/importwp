import React, { Component } from 'react';
import PropTypes from 'prop-types';

import { importer } from '../../../services/importer.service';
import FieldLabel from '../../field-label/FieldLabel';

class DatasourceLocal extends Component {
  constructor(props) {
    super(props);

    let filetype_enabled = true;
    if (props.filetype) {
      filetype_enabled = false;
    }

    this.state = {
      filetype: props.filetype,
      filetype_enabled: filetype_enabled
    };

    this.onChange = this.onChange.bind(this);
    this.run = this.run.bind(this);
  }

  onChange(event) {
    this.setState({ [event.target.name]: event.target.value });
  }

  run(callback = () => { }) {
    this.setState({ showModal: true, modalMessage: 'Uploading.' });
    const title = 'Uploading';
    this.props.showModal(<progress className="iwp-progress-bar" />, title);

    const { local_url, id } = this.props;
    const { filetype } = this.state;

    let form_data = new FormData();
    form_data.append('local_url', local_url);
    form_data.append('filetype', filetype);
    form_data.append('action', 'file_local');

    importer.upload(id, form_data).then(
      () => {
        this.props.showModal(
          <progress className="iwp-progress-bar" value="100" max="100" />,
          title
        );
        this.props.closeModal();
        callback();
      },
      error => {
        this.props.onError(error);
        this.props.closeModal();
      }
    );
  }

  render() {
    const { local_url } = this.props;
    const { filetype, filetype_enabled } = this.state;

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
              onChange={this.props.onChange}
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
              <select name="filetype" value={filetype} onChange={this.onChange}>
                <option value="">Choose file type</option>
                <option value="csv">CSV File</option>
                <option value="xml">XML File</option>
                {extra_file_types && extra_file_types.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
              </select>
            </div>
          </div>
        )}
      </React.Fragment>
    );
  }
}

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

DatasourceLocal.defaultProps = {
  showModal: () => { },
  closeModal: () => { },
  onError: () => { }
};

export default DatasourceLocal;
