import React, { Component } from 'react';
import PropTypes from 'prop-types';

import DatasourceLocal from '../datasource/local/DatasourceLocal';
import DatasourceRemote from '../datasource/remote/DatasourceRemote';
import DatasourceUpload from '../datasource/upload/DatasourceUpload';
import { importer } from '../../services/importer.service';
import Modal from '../modal/Modal';
import ExistingDatasource from '../datasource/existing/ExistingDatasource';

class DatasourceForm extends Component {
  constructor(props) {
    super(props);

    this.state = {
      datasource: props.datasource,
      remote_url: props.settings.remote_url ? props.settings.remote_url : '',
      local_url: props.settings.local_url ? props.settings.local_url : '',
      file: props.file,
      saving: false,
      disabled: false,
      showModal: false,
      modalTitle: '',
      modalMessage: ''
    };

    this.save = this.save.bind(this);
    this.onSave = this.onSave.bind(this);
    this.onSubmit = this.onSubmit.bind(this);
    this.onChange = this.onChange.bind(this);
    this.processFile = this.processFile.bind(this);
    this.showModal = this.showModal.bind(this);
    this.closeModal = this.closeModal.bind(this);

    this.datasourceRef = React.createRef();
  }

  onChange(event) {
    this.setState({ [event.target.name]: event.target.value });
  }

  processFile(callback = () => { }) {
    const title = 'Processing File';
    this.setState({
      disabled: false
    });
    this.showModal(<progress className="iwp-progress-bar" />, title);
    importer
      .process(this.props.id)
      .promise.then(() => {
        this.showModal(
          <progress className="iwp-progress-bar" value="100" max="100" />,
          title
        );
        callback();
      })
      .catch(e => this.props.onError(e));
  }

  save(callback = () => { }) {
    this.setState({ saving: true });
    const { id } = this.props;
    const { datasource, remote_url, local_url } = this.state;

    importer
      .save({
        id: id,
        datasource: datasource,
        remote_url: remote_url,
        local_url: local_url
      })
      .then(() => {
        this.setState({ saving: false });
        callback();
      })
      .catch(error => {
        this.props.onError(error);
        this.setState({
          saving: false
        });
      });
  }

  onSave() {
    if (this.datasourceRef && this.datasourceRef.current) {
      this.datasourceRef.current.run(() => {
        this.props.onError('New File added.');
        this.processFile(() => {
          this.save(() => {
            this.closeModal();
          });
        });
      });
    } else {
      this.save(() => {
        this.closeModal();
      });
    }
  }

  onSubmit() {
    if (this.datasourceRef && this.datasourceRef.current) {
      this.datasourceRef.current.run(() => {
        this.save(() => {
          this.processFile(() => {
            this.props.complete();
          });
        });
      });
    } else {
      this.save(() => {
        this.props.complete();
      });
    }
  }

  componentDidUpdate(prevProps) {
    // update file
    const prevFile = prevProps.file;
    const file = this.props.file;
    if (prevFile !== file && file !== null) {
      this.setState({ file: file });
    }

    // disabled
    const prevParser = prevProps.parser;
    const parser = this.props.parser;
    if (parser !== prevParser) {
      this.setState({ disabled: false });
    }

    // update datasource
    const prevDatasource = prevProps.datasource;
    const datasource = this.props.datasource;
    if (datasource !== prevDatasource) {
      this.setState({
        datasource: datasource,
        remote_url: this.props.settings.remote_url
          ? this.props.settings.remote_url
          : '',
        local_url: this.props.settings.local_url
          ? this.props.settings.local_url
          : ''
      });
    }
  }

  showModal(content = '', title = '') {
    this.setState({
      showModal: true,
      modalMessage: content,
      modalTitle: title
    });
  }

  closeModal() {
    this.setState({
      showModal: false
    });
  }

  render() {
    const {
      datasource,
      disabled,
      saving,
      file,
      showModal,
      modalTitle,
      modalMessage,
      local_url,
      remote_url
    } = this.state;
    const { files, id, parser } = this.props;

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
          onClose={this.closeModal}
          show={showModal}
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
                  onChange={this.onChange}
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
                    complete={this.processFile}
                    showModal={this.showModal}
                    closeModal={this.closeModal}
                    onError={this.props.onError}
                    ref={this.datasourceRef}
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
                  onChange={this.onChange}
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
                    complete={this.processFile}
                    remote_url={remote_url}
                    filetype={parser}
                    onChange={this.onChange}
                    showModal={this.showModal}
                    closeModal={this.closeModal}
                    onError={this.props.onError}
                    ref={this.datasourceRef}
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
                  onChange={this.onChange}
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
                    complete={this.processFile}
                    local_url={local_url}
                    filetype={parser}
                    onChange={this.onChange}
                    showModal={this.showModal}
                    closeModal={this.closeModal}
                    onError={this.props.onError}
                    ref={this.datasourceRef}
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
                  onChange={this.onChange}
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
                    onError={this.props.onError}
                    files={files}
                    file={file}
                    ref={this.datasourceRef}
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
              onClick={this.onSave}
              disabled={disabled}
            >
              {saving && <span className="spinner is-active"></span>}
              {saving ? 'Saving' : btn_action_text}
            </button>{' '}
            <button
              className="button button-primary"
              type="button"
              onClick={this.onSubmit}
              disabled={disabled}
            >
              {saving && <span className="spinner is-active"></span>}
              {saving ? 'Saving' : btn_action_text + ' & continue'}
            </button>
          </div>
        </div>
      </React.Fragment>
    );
  }
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

DatasourceForm.defaultProps = {
  datasource: 'upload',
  settings: {},
  onError: () => { }
};

export default DatasourceForm;
