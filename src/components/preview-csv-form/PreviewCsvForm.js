import React, { useCallback, useEffect, useState } from 'react';
import PropTypes from 'prop-types';

import RecordCsv from '../record/csv/RecordCsv';
import { importer } from '../../services/importer.service';
import NoticeList from '../notice-list/NoticeList';
import FieldLabel from '../field-label/FieldLabel';
import FormRow from '../FormRow/FormRow';
import FormField from '../FormField/FormField';
import InputField from '../InputField/InputField';

const ENCODINGS = window.iwp.encodings;

function PreviewCsvForm({
  complete,
  id,
  settings = {
    show_headings: true,
    delimiter: ',',
    enclosure: '"',
    file_encoding: '',
  },
  onError = () => { },
}) {
  const [delimiter, setDelimiter] = useState(settings.delimiter);
  const [enclosure, setEnclosure] = useState(settings.enclosure);
  const [escape, setEscape] = useState(settings.escape ?? '\\');
  const [show_headings, setShowHeadings] = useState(settings.show_headings);
  const [file_encoding, setFileEncoding] = useState(settings.file_encoding);
  const [processing, setProcessing] = useState(false);
  const [saving, setSaving] = useState(false);
  const disabled = delimiter === '' || enclosure === '';

  const onChange = useCallback((event) => {
    const target = event.target;
    const value = target.type === 'checkbox' ? target.checked : target.value;
    const fieldName = target.name;

    if (fieldName === 'delimiter') {
      setDelimiter(value);
    } else if (fieldName === 'enclosure') {
      setEnclosure(value);
    } else if (fieldName === 'escape') {
      setEscape(value);
    } else if (fieldName === 'show_headings') {
      setShowHeadings(value);
    } else if (fieldName === 'file_encoding') {
      setFileEncoding(value);
    }
  }, []);

  const save = useCallback((callback = () => { }) => {
    setSaving(true);

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
        setSaving(false);
        callback();
      })
      .catch((error) => {
        onError(error);
        setSaving(false);
      });
  }, [delimiter, enclosure, escape, file_encoding, id, onError, show_headings]);

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
        (error) => {
          setProcessing(false);
          onError(error);
        }
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
                onChange={(value) => onChange({
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
                onChange={(value) => onChange({
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
                tooltip="Allow for the use of an extra escape character, alongside the escaping of the enclosure character by doubling it, leave empty to disable."
                display="inline-block"
              />
              <InputField
                type="text"
                id="escape"
                className="iwp-form__input"
                maxLength={1}
                name="escape"
                onChange={(value) => onChange({
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
                  onChange={onChange}
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

PreviewCsvForm.propTypes = {
  complete: PropTypes.func,
  id: PropTypes.number,
  settings: PropTypes.object,
  onError: PropTypes.func,
};

export default PreviewCsvForm;
