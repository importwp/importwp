import React, { Component } from 'react';
import PropTypes from 'prop-types';

import RecordCsv from '../record/csv/RecordCsv';
import { importer } from '../../services/importer.service';
import NoticeList from '../notice-list/NoticeList';
import FieldLabel from '../field-label/FieldLabel';
import FormRow from '../FormRow/FormRow';
import FormField from '../FormField/FormField';
import InputField from '../InputField/InputField';

const ENCODINGS = window.iwp.encodings;

class PreviewCsvForm extends Component {
  constructor(props) {
    super(props);

    this.state = {
      delimiter: props.settings.delimiter,
      enclosure: props.settings.enclosure,
      escape: props.settings.escape ?? '\\',
      show_headings: props.settings.show_headings,
      file_encoding: props.settings.file_encoding,
      processing: false,
      saving: false,
      disabled: false,
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

    this.setState({
      [name]: value,
    }, () => {
      this.isDisabled();
    });
  }

  save(callback = () => { }) {
    this.setState({ saving: true });

    const { id } = this.props;
    const { delimiter, enclosure, escape, show_headings, file_encoding } = this.state;

    importer
      .save({
        id: id,
        file_settings_delimiter: delimiter,
        file_settings_enclosure: enclosure,
        file_settings_escape: escape,
        file_settings_show_headings: show_headings,
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
    if (this.state.delimiter !== '' && this.state.enclosure !== '') {
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
        (error) => {
          this.setState({ processing: false });
          this.props.onError(error);
        }
      );
    }
  }

  componentDidMount() {
    this.isDisabled();
    this.isFileProcessed();
  }

  render() {
    const {
      delimiter,
      enclosure,
      escape,
      show_headings,
      saving,
      disabled,
      processing,
      file_encoding,
    } = this.state;

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
            <p className="iwp-heading iwp-heading--has-tooltip">File Settings. <a href="https://www.importwp.com/docs/importer-file-settings/?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=importer" target='_blank' className='iwp-label__tooltip'>?</a></p>
            <p>
              Configure how the importer reads a record from your file, a
              preview showing the first record is available at the bottom of the
              page.
            </p>

            <FormRow>
              <FormField>
                <FieldLabel
                  label="Delimiter Character"
                  id="delimiter"
                  field="delimiter"
                  tooltip="The character which separates the CSV record elements."
                  display="inline-block"
                />
                <InputField
                  type="text"
                  id="delimiter"
                  className="iwp-form__input"
                  name="delimiter"
                  maxLength={1}
                  onChange={(value) => this.onChange({
                    target: {
                      name: 'delimiter',
                      value: value
                    }
                  })}
                  value={delimiter}
                />
              </FormField>

              <FormField>
                <FieldLabel
                  label="Enclosure Character"
                  id="enclosure"
                  field="enclosure"
                  tooltip="The character which is wrapper around the CSV record elements."
                  display="inline-block"
                />
                <InputField
                  type="text"
                  id="enclosure"
                  className="iwp-form__input"
                  name="enclosure"
                  maxLength={1}
                  onChange={(value) => this.onChange({
                    target: {
                      name: 'enclosure',
                      value: value
                    }
                  })}
                  value={enclosure}
                />
              </FormField>

              <FormField>
                <FieldLabel
                  label="Escape Character"
                  id="escape"
                  field="escape"
                  tooltip="The escape used to escape enclosure characters within a cells content."
                  display="inline-block"
                />
                <InputField
                  type="text"
                  id="escape"
                  className="iwp-form__input"
                  maxLength={1}
                  name="escape"
                  onChange={(value) => this.onChange({
                    target: {
                      name: 'escape',
                      value: value
                    }
                  })}
                  value={escape}
                />
              </FormField>
            </FormRow>

            <div className="iwp-form__grid">
              <div className="iwp-form__row iwp-form__row--left">
                <FieldLabel
                  label="Column Headings"
                  id="show_headings"
                  field="show_headings"
                  tooltip="Display column headings as numeric index or first row of csv file."
                  display="inline-block"
                />
                <div>
                  <input
                    id="show_headings"
                    type="checkbox"
                    name="show_headings"
                    onChange={this.onChange}
                    checked={show_headings}
                  />
                  <label htmlFor="show_headings">
                    First record is column headings?
                  </label>
                </div>
              </div>
              <div className="iwp-form__row iwp-form__row--right">
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
            </div>

            <div className="iwp-form__row">
              <FieldLabel label="Record CSV Preview" />
              <RecordCsv
                id={id}
                file_encoding={file_encoding}
                show_headings={show_headings}
                delimiter={delimiter}
                enclosure={enclosure}
                escape={escape}
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

PreviewCsvForm.propTypes = {
  complete: PropTypes.func,
  id: PropTypes.number,
  settings: PropTypes.object,
  onError: PropTypes.func,
};

PreviewCsvForm.defaultProps = {
  settings: {
    show_headings: true,
    delimiter: ',',
    enclosure: '"',
    file_encoding: '',
  },
  onError: () => { },
};

export default PreviewCsvForm;
