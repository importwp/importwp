import React, { Component } from 'react';
import PropTypes from 'prop-types';
import RecordCsv from '../record/csv/RecordCsv';
import RecordXml from '../record/xml/RecordXml';
import { importer } from '../../services/importer.service';

import './DataSelector.scss';
import PreviewRecord from '../preview-form/PreviewRecord';
import InputButton from '../InputButton/InputButton';
import InputField from '../InputField/InputField';

class DataSelector extends Component {
  constructor(props) {
    super(props);

    this.state = {
      selection: props.selection,
      preview: props.preview,
    };

    this.onChange = this.onChange.bind(this);
    this.onSelect = this.onSelect.bind(this);
    this.onSubmit = this.onSubmit.bind(this);
    this.refreshPreview = this.refreshPreview.bind(this);
  }

  onChange(event) {
    const target = event.target;
    this.setState(
      {
        [target.name]: target.value,
      },
      this.refreshPreview
    );
  }

  onSelect(selection) {
    this.setState(
      {
        selection: this.state.selection + selection,
      },
      this.refreshPreview
    );
  }

  onSubmit() {
    const { selection } = this.state;
    this.props.onSelect(selection);
  }

  refreshPreview() {
    this.setState({ preview: 'Loading.' });
    importer
      .recordPreview(this.props.id, {
        selection: this.state.selection,
      })
      .then((response) => {
        this.setState({ preview: response.selection });
      })
      .catch(() => {
        this.setState({ preview: '' });
      });
  }

  componentDidMount() {
    this.refreshPreview();
  }

  render() {
    const { id, parser, settings } = this.props;
    const { selection, preview } = this.state;

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
                onSelect={this.onSelect}
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
                onSelect={this.onSelect}
                base_path={settings.base_path + this.props.subPath}
              />
            </React.Fragment>
          )}
          {parser !== 'xml' && parser !== 'csv' && <>
            <p>
              Click on a value to be used as the value in your previously selected field.
            </p>
            <PreviewRecord id={id} onSelect={this.onSelect} parser={parser} />
          </>}
        </div>
        <div className="iwp-data-selector__tool">

          <InputField
            name="selection"
            value={selection}
            onChange={val => this.onChange({
              target: {
                name: 'selection',
                value: val
              }
            })}
          >
            <InputButton onClick={this.onSubmit}>
              Select and Close
            </InputButton>
          </InputField>
          <p className="iwp-preview--text" title={preview}>
            Preview: {preview}
          </p>
        </div>
      </div>
    );
  }
}

DataSelector.propTypes = {
  id: PropTypes.number,
  parser: PropTypes.string,
  selection: PropTypes.string,
  settings: PropTypes.object,
  onSelect: PropTypes.func,
  preview: PropTypes.string,
  subPath: PropTypes.string,
};

DataSelector.defaultProps = {
  settings: {},
  selection: '',
  onSelect: () => { },
  preview: '',
  subPath: '',
};

export default DataSelector;
