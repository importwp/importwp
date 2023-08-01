import React from 'react';
import PropTypes from 'prop-types';
import AsyncSelect from 'react-select/async';
import { connect } from 'react-redux';
import {
  getFieldMap,
  getPreview,
  getValue,
  setTemplate,
  fetchFieldPreview,
  setPreview,
} from '../../features/importer/importerSlice';
import debounce from 'lodash.debounce';

import './Field.scss';
import FieldLabel from '../field-label/FieldLabel';
import { importer } from '../../services/importer.service';
import FieldMapped from './FieldMapped';
import FieldSerialized from './FieldSerialized';
import FieldMap from '../field-map/FieldMap';
import NoticeList from '../notice-list/NoticeList';

const customReactSelectStyles = {
  control: (provided, state) => ({
    ...provided,
    background: '#fff',
    borderColor: '#7e8993',
    minHeight: '30px',
    height: '30px',
    boxShadow: state.isFocused ? null : null,
    borderRadius: 0,
  }),

  valueContainer: (provided, state) => ({
    ...provided,
    height: '30px',
    padding: '0 6px',
  }),

  input: (provided, state) => ({
    ...provided,
    margin: '0px',
  }),
  indicatorSeparator: (state) => ({
    display: 'none',
  }),
  indicatorsContainer: (provided, state) => ({
    ...provided,
    height: '30px',
  }),
};

class Field extends React.PureComponent {
  constructor(props) {
    super(props);

    this.state = {
      enable_text:
        props.map.hasOwnProperty(
          props.name + '.' + props.field.id + '._enable_text'
        ) &&
          props.map[props.name + '.' + props.field.id + '._enable_text'] === 'yes'
          ? true
          : false,
      options: [],
      show_map: false,
    };

    this.toggleTextField = this.toggleTextField.bind(this);
    this.selectOptions = this.selectOptions.bind(this);
    this.onSelectChange = this.onSelectChange.bind(this);

    this.getPreview = this.getPreview.bind(this);
    this.fetchPreview = debounce(this.fetchPreview, 500);
  }

  getPreview(data) {
    let tmp = {};
    Object.keys(data.fields).forEach((element) => {
      tmp = {
        ...tmp,
        [element]: 'Loading',
      };
    });

    this.props.dispatch(setPreview(tmp));
    this.fetchPreview(data);
  }

  fetchPreview(data) {
    this.props.dispatch(fetchFieldPreview(data));
  }

  toggleTextField(event) {
    const target = event.target;
    const value = target.type === 'checkbox' ? target.checked : target.value;
    const name = target.name;

    this.setState({
      [name]: value,
    });

    this.props.dispatch(
      setTemplate({
        [this.props.name + '.' + this.props.field.id + '._enable_text']:
          value === true ? 'yes' : 'no',
      })
    );
  }

  selectOptions() {
    const options = this.props.field.options;

    return new Promise((resolve, reject) => {
      if (options !== 'callback') {
        this.setState({ options: options });
        resolve(options);
      } else {
        // how to we get the cache key?

        const field = `${this.props.name}.${this.props.field.id}`;

        importer
          .fieldOptions(
            this.props.importer_id,
            field,
            field.replace(/\.\d+\./gm, '')
          )
          .then((data) => {
            this.setState({ options: data });
            resolve(data);
          })
          .catch((e) => reject(e));
      }
    });
  }

  onSelectChange(data) {
    this.props.dispatch(
      setTemplate({
        [this.props.name + '.' + this.props.field.id]:
          data && data.value ? data.value : '',
      })
    );
  }

  getField() {
    const { type, label, id, options, tooltip } = this.props.field;
    const { value, name, preview } = this.props;

    // hide mapped field, unless it is populated.
    if (type === 'mapped' && (!this.props.map.hasOwnProperty(name + '.' + id + '._index') || this.props.map[name + '.' + id + '._index'] == 0)) {
      return <NoticeList notices={[{ message: 'Mapped field type has been deprecated, please use the generic field mapper found beside the select data button.', type: 'info' }]} />;
    }

    // strip html tags
    const preview_text = preview ? preview.replace(/<\/?[^>]+(>|$)/g, '') : '';

    switch (type) {
      case 'serialized':
        return (
          <React.Fragment>
            <div className="iwp-field__left">
              <FieldLabel
                label={label}
                tooltip={tooltip}
                id={name + '.' + id}
                field={name + '.' + id}
              />
            </div>
            <div className="iwp-field__right">
              <FieldSerialized {...this.props} />
            </div>
          </React.Fragment>
        );
      case 'mapped':
        return (
          <React.Fragment>
            <div className="iwp-field__left">
              <FieldLabel
                label={label}
                tooltip={tooltip}
                id={name + '.' + id}
                field={name + '.' + id}
              />
            </div>
            <div className="iwp-field__right">
              <FieldMapped {...this.props} />
            </div>
          </React.Fragment>
        );
      case 'select':
        return (
          <React.Fragment>
            <div className="iwp-field__left">
              <FieldLabel
                label={label}
                tooltip={tooltip}
                id={name + '.' + id}
                field={name + '.' + id}
              />
            </div>
            <div className="iwp-field__right">
              <AsyncSelect
                isClearable
                defaultOptions
                loadOptions={this.selectOptions}
                value={
                  options === 'callback'
                    ? this.state.options.find((item) => item.value === value)
                    : options.find((item) => item.value === value)
                }
                name={name + '.' + id}
                id={name + '.' + id}
                onChange={this.onSelectChange}
                isSearchable={true}
                className="iwp-form__input"
                styles={customReactSelectStyles}
              />
            </div>
          </React.Fragment>
        );
      default:
        return (
          <React.Fragment>
            <div className="iwp-field__left">
              <FieldLabel
                label={label}
                tooltip={tooltip}
                id={name + '.' + id}
                field={name + '.' + id}
              />
            </div>
            <div className="iwp-field__right">
              {typeof options !== 'undefined' &&
                this.state.enable_text === false ? (
                <React.Fragment>
                  <AsyncSelect
                    isClearable
                    defaultOptions
                    loadOptions={this.selectOptions}
                    value={
                      options === 'callback'
                        ? this.state.options.find(
                          (item) => item.value === value
                        )
                        : options.find((item) => item.value === value)
                    }
                    name={name + '.' + id}
                    id={name + '.' + id}
                    onChange={this.onSelectChange}
                    isSearchable={true}
                    className="iwp-form__input"
                    styles={customReactSelectStyles}
                  />
                </React.Fragment>
              ) : (
                <React.Fragment>
                  <FieldMap
                    show={this.state.show_map}
                    onClose={() => this.setState({ show_map: false })}
                    name={name + '.' + id}
                    field={this.props.field}
                    delimiter={this.props.map.hasOwnProperty(`${name}.settings._delimiter`) ? (this.props.map[`${name}.settings._delimiter`]?.length ? this.props.map[`${name}.settings._delimiter`] : ',') : false}
                  />
                  <div className="iwp-field__input-wrapper">
                    <input
                      type="text"
                      name={name + '.' + id}
                      id={name + '.' + id}
                      className="iwp-field__input-wrapper"
                      value={value}
                      onChange={(event) => {
                        const target = event.target;
                        let value = target.value;
                        this.props.dispatch(
                          setTemplate({ [target.name]: value })
                        );
                        this.getPreview({
                          id: this.props.importer_id,
                          fields: { [target.name]: value },
                        });
                      }}
                    />
                    <button
                      className="iwp-field__select"
                      type="button"
                      onClick={() =>
                        this.props.showSelectModal(
                          name + '.' + id,
                          this.props.map.hasOwnProperty(`${name}.row_base`)
                            ? this.props.map[`${name}.row_base`]
                            : ''
                        )
                      }
                    >
                      Select Data
                    </button>
                    <button
                      className={`iwp-field__settings dashicons-before dashicons-editor-table ${this.props.map.hasOwnProperty(name + '.' + id + '._mapped._index') && +this.props.map[name + '.' + id + '._mapped._index'] > 0 ? 'iwp-field__settings--active' : ''}`}
                      type="button"
                      onClick={() => this.setState({ show_map: true })}
                    >
                      Settings
                    </button>
                  </div>
                  <p className="iwp-preview--text" title={preview}>
                    Preview: {preview_text}
                  </p>
                </React.Fragment>
              )}
              {typeof options !== 'undefined' && (
                <label className="iwp-field__enable-text">
                  <input
                    type="checkbox"
                    name="enable_text"
                    onChange={this.toggleTextField}
                    value="yes"
                    checked={this.state.enable_text}
                  />{' '}
                  Enable Text Field
                </label>
              )}
            </div>
          </React.Fragment>
        );
    }
  }

  render() {
    return this.getField();
  }
}

Field.propTypes = {
  field: PropTypes.object.isRequired,
  map: PropTypes.object,
  value: PropTypes.string,
  name: PropTypes.string.isRequired,
  showSelectModal: PropTypes.func,
  preview: PropTypes.string,
  importer_id: PropTypes.number,
};

const mapStateToProps = (state, props) => ({
  map: getFieldMap(state, props.name),
  preview: getPreview(state, `${props.name}.${props.field.id}`),
  value: getValue(state, `${props.name}.${props.field.id}`),
});

export default connect(mapStateToProps)(Field);
