import React, { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import RecordCsv from '../record/csv/RecordCsv';
import RecordXml from '../record/xml/RecordXml';
import RecordJson from '../record/json/RecordJson';
import { importer } from '../../services/importer.service';

import './DataSelector.scss';
import PreviewRecord from '../preview-form/PreviewRecord';
import InputButton from '../InputButton/InputButton';
import InputField from '../InputField/InputField';

const DataSelector = ({
  id,
  parser,
  selection: selectionProp = '',
  settings = {},
  onSelect: onSelectProp = () => { },
  onError = () => { },
  preview: previewProp = '',
  subPath = '',
}) => {
  const [selection, setSelection] = useState(selectionProp);
  const [preview, setPreview] = useState(previewProp);

  const refreshPreview = (nextSelection = selection) => {
    setPreview('Loading.');
    importer
      .recordPreview(id, {
        selection: nextSelection,
      })
      .then((response) => {
        setPreview(response.selection);
      })
      .catch((error) => {
        setPreview('');
        onError(error);
      });
  };

  const onChange = (event) => {
    const target = event.target;
    const nextSelection = target.value;
    setSelection(nextSelection);
    refreshPreview(nextSelection);
  };

  const onSelect = (next) => {
    const nextSelection = selection + next;
    setSelection(nextSelection);
    refreshPreview(nextSelection);
  };

  const onSubmit = () => {
    onSelectProp(selection);
  };

  useEffect(() => {
    refreshPreview();
    // Match class componentDidMount: preview once on mount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className="iwp-data-selector">
      <div className="iwp-data-selector__tool">
        {parser === 'csv' && (
          <React.Fragment>
            <p>
              Click on a row in the table below, to be used as the value in
              your previously selected field, each row represents a column in
              your CSV file.
            </p>
            <RecordCsv
              id={id}
              onSelect={onSelect}
              show_headings={settings.show_headings}
              enclosure={settings.enclosure}
              delimiter={settings.delimiter}
              escape={settings.escape ?? '\\'}
              file_encoding={settings.file_encoding}
            />
          </React.Fragment>
        )}
        {parser === 'xml' && (
          <React.Fragment>
            <p>
              Click on a node/attribute/text in the record below, to be used
              as the value in your previously selected field.
            </p>
            <RecordXml
              id={id}
              onSelect={onSelect}
              base_path={settings.base_path + subPath}
            />
          </React.Fragment>
        )}
        {parser === 'json' && (
          <React.Fragment>
            <p>
              Click on a key or value in the record below, to be used as the
              value in your previously selected field.
            </p>
            <RecordJson
              id={id}
              onSelect={onSelect}
              base_path={settings.base_path + subPath}
            />
          </React.Fragment>
        )}
        {parser !== 'xml' && parser !== 'csv' && parser !== 'json' && <>
          <p>
            Click on a value to be used as the value in your previously selected field.
          </p>
          <PreviewRecord id={id} onSelect={onSelect} onError={onError} parser={parser} />
        </>}
      </div>
      <div className="iwp-data-selector__tool">

        <InputField
          name="selection"
          value={selection}
          onChange={val => onChange({
            target: {
              name: 'selection',
              value: val
            }
          })}
        >
          <InputButton onClick={onSubmit}>
            Select and Close
          </InputButton>
        </InputField>
        <p className="iwp-preview--text" title={preview}>
          Preview: {preview}
        </p>
      </div>
    </div>
  );
};

DataSelector.propTypes = {
  id: PropTypes.number,
  parser: PropTypes.string,
  selection: PropTypes.string,
  settings: PropTypes.object,
  onSelect: PropTypes.func,
  onError: PropTypes.func,
  preview: PropTypes.string,
  subPath: PropTypes.string,
};

export default DataSelector;
