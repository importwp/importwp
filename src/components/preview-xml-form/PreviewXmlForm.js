import React, { Component } from 'react';
import PropTypes from 'prop-types';

import RecordXml from '../record/xml/RecordXml';
import { importer } from '../../services/importer.service';
import NoticeList from '../notice-list/NoticeList';
import FieldLabel from '../field-label/FieldLabel';

const ENCODINGS = window.iwp.encodings;

class PreviewXmlForm extends Component {
  constructor(props) {
    super(props);

    this.state = {
      base_path:
        props.settings.base_path !== null ? props.settings.base_path : '',
      nodes: props.settings.nodes,
      file_encoding: props.settings.file_encoding,
      processing: false,
      saving: false,
      disabled: true,
    };

    this.onChange = this.onChange.bind(this);
    this.save = this.save.bind(this);
    this.onSave = this.onSave.bind(this);
    this.onSubmit = this.onSubmit.bind(this);
    this.isDisabled = this.isDisabled.bind(this);
    this.isFileProcessed = this.isFileProcessed.bind(this);
  }
  onChange(event) {
    const target = event.target;
    const value = target.type === 'checkbox' ? target.checked : target.value;
    const name = target.name;

    this.setState(
      {
        [name]: value,
      },
      () => {
        if (this.state.base_path.length > 0) {
          this.setState({ disabled: false });
        }
      }
    );
  }

  save(callback = () => {}) {
    this.setState({ saving: true });
    const { id } = this.props;
    const { base_path, file_encoding } = this.state;

    importer
      .save({
        id: id,
        file_settings_base_path: base_path,
        file_settings_setup: true,
        file_settings_encoding: file_encoding,
      })
      .then(() => {
        this.setState({ saving: false });
        callback();
      })
      .catch((error) => {
        this.props.onError(error);
        this.setState({
          saving: false,
        });
      });
  }

  onSave() {
    this.save();
  }

  onSubmit() {
    this.save(() => {
      this.props.complete();
    });
  }

  isDisabled() {
    if (this.state.base_path) {
      this.setState({ disabled: false });
    } else {
      this.setState({ disabled: true });
    }
  }

  isFileProcessed() {
    if (this.props.settings.processed === false) {
      this.setState({ processing: true });
      importer.process(this.props.id).promise.then(
        () => {
          this.setState({ processing: false });
        },
        (error) => this.props.onError(error)
      );
    }
  }

  componentDidMount() {
    this.isDisabled();
    this.isFileProcessed();
  }

  componentDidUpdate(prevProps) {
    if (this.props.settings.processed !== prevProps.settings.processed) {
      this.setState({
        base_path: this.props.settings.base_path,
        nodes: this.props.settings.nodes,
      });
    }
  }

  render() {
    const { disabled, saving, base_path, nodes, processing, file_encoding } =
      this.state;
    const { id } = this.props;
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
            <p className="iwp-heading">File Settings</p>
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
                tooltip="This Record Base is the path to the XML records that you want to import."
                display="inline-block"
              />
              <select
                className="iwp-form__input"
                onChange={this.onChange}
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
                onChange={this.onChange}
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
              <label className="iwp-form__label">Record XML Preview:</label>
              <RecordXml
                id={id}
                base_path={base_path}
                onError={this.props.onError}
              />
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
              {saving ? 'Saving' : 'Save'}
            </button>{' '}
            <button
              className="button button-primary"
              type="button"
              onClick={this.onSubmit}
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
}

PreviewXmlForm.propTypes = {
  complete: PropTypes.func,
  id: PropTypes.number,
  settings: PropTypes.object,
  onError: PropTypes.func,
};

PreviewXmlForm.defaultProps = {
  id: null,
  settings: {
    base_path: '',
    nodes: {},
  },
  onError: () => {},
};

export default PreviewXmlForm;
