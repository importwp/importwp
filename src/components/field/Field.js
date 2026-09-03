import React, { useMemo, useState } from 'react';
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
    alignItems: 'center',
  }),

  valueContainer: (provided) => ({
    ...provided,
    height: '30px',
    padding: '0 8px',
  }),

  input: (provided) => ({
    ...provided,
    margin: 0,
    padding: 0,
  }),

  singleValue: (provided) => ({
    ...provided,
    margin: 0,
  }),

  placeholder: (provided) => ({
    ...provided,
    margin: 0,
  }),

  indicatorSeparator: () => ({
    display: 'none',
  }),

  indicatorsContainer: (provided) => ({
    ...provided,
    height: '30px',
  }),

  clearIndicator: (provided) => ({
    ...provided,
    padding: '4px',
  }),

  dropdownIndicator: (provided) => ({
    ...provided,
    padding: '4px',
  }),
};

const Field = (props) => {
  const {
    field,
    map,
    value,
    name,
    showSelectModal,
    preview,
    importer_id,
    dispatch,
  } = props;

  const [enable_text, setEnableText] = useState(
    map.hasOwnProperty(name + '.' + field.id + '._enable_text') &&
      map[name + '.' + field.id + '._enable_text'] === 'yes'
  );
  const [options, setOptions] = useState([]);
  const [show_map, setShowMap] = useState(false);

  const fetchPreview = useMemo(
    () =>
      debounce((data) => {
        dispatch(fetchFieldPreview(data));
      }, 500),
    [dispatch]
  );

  const getPreview = (data) => {
    let tmp = {};
    Object.keys(data.fields).forEach((element) => {
      tmp = {
        ...tmp,
        [element]: 'Loading',
      };
    });

    dispatch(setPreview(tmp));
    fetchPreview(data);
  };

  const toggleTextField = (event) => {
    const target = event.target;
    const nextValue = target.type === 'checkbox' ? target.checked : target.value;

    setEnableText(nextValue);
    dispatch(
      setTemplate({
        [name + '.' + field.id + '._enable_text']:
          nextValue === true ? 'yes' : 'no',
      })
    );
  };

  const selectOptions = () => {
    const fieldOptions = field.options;

    return new Promise((resolve, reject) => {
      if (fieldOptions !== 'callback') {
        setOptions(fieldOptions);
        resolve(fieldOptions);
      } else {
        const fieldKey = `${name}.${field.id}`;

        importer
          .fieldOptions(
            importer_id,
            fieldKey,
            fieldKey.replace(/\.\d+\./gm, '')
          )
          .then((data) => {
            setOptions(data);
            resolve(data);
          })
          .catch((e) => reject(e));
      }
    });
  };

  const onSelectChange = (data) => {
    dispatch(
      setTemplate({
        [name + '.' + field.id]:
          data && data.value ? data.value : '',
      })
    );
  };

  const { type, label, id, tooltip } = field;
  const fieldOptions = field.options;

  if (type === 'mapped' && (!map.hasOwnProperty(name + '.' + id + '._index') || map[name + '.' + id + '._index'] == 0)) {
    return <NoticeList notices={[{ message: 'Mapped field type has been deprecated, please use the generic field mapper found beside the select data button.', type: 'info' }]} />;
  }

  const preview_text = preview ? preview.replace(/<\/?[^>]+(>|$)/g, '') : '';

  switch (type) {
    case 'serialized':
      return (
        <>
          <div className="iwp-field__left">
            <FieldLabel
              label={label}
              tooltip={tooltip}
              id={name + '.' + id}
              field={name + '.' + id}
            />
          </div>
          <div className="iwp-field__right">
            <FieldSerialized {...props} />
          </div>
        </>
      );
    case 'mapped':
      return (
        <>
          <div className="iwp-field__left">
            <FieldLabel
              label={label}
              tooltip={tooltip}
              id={name + '.' + id}
              field={name + '.' + id}
            />
          </div>
          <div className="iwp-field__right">
            <FieldMapped {...props} />
          </div>
        </>
      );
    case 'select':
      return (
        <>
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
              loadOptions={selectOptions}
              value={
                fieldOptions === 'callback'
                  ? options.find((item) => item.value === value)
                  : fieldOptions.find((item) => item.value === value)
              }
              name={name + '.' + id}
              id={name + '.' + id}
              onChange={onSelectChange}
              isSearchable={true}
              className="iwp-field__react-select"
              classNamePrefix="iwp-select"
              styles={customReactSelectStyles}
            />
          </div>
        </>
      );
    default:
      return (
        <>
          <div className="iwp-field__left">
            <FieldLabel
              label={label}
              tooltip={tooltip}
              id={name + '.' + id}
              field={name + '.' + id}
            />
          </div>
          <div className="iwp-field__right">
            {typeof fieldOptions !== 'undefined' &&
              enable_text === false ? (
              <>
                <AsyncSelect
                  isClearable
                  defaultOptions
                  loadOptions={selectOptions}
                  value={
                    fieldOptions === 'callback'
                      ? options?.find(
                        (item) => item.value === value
                      )
                      : fieldOptions.find((item) => item.value === value)
                  }
                  name={name + '.' + id}
                  id={name + '.' + id}
                  onChange={onSelectChange}
                  isSearchable={true}
                  className="iwp-field__react-select"
                  classNamePrefix="iwp-select"
                  styles={customReactSelectStyles}
                />
              </>
            ) : (
              <>
                <FieldMap
                  show={show_map}
                  onClose={() => setShowMap(false)}
                  name={name + '.' + id}
                  field={field}
                  delimiter={map.hasOwnProperty(`${name}.settings._delimiter`) ? (map[`${name}.settings._delimiter`]?.length ? map[`${name}.settings._delimiter`] : ',') : false}
                />
                <div className="iwp-field__input-wrapper">
                  <input
                    type="text"
                    name={name + '.' + id}
                    id={name + '.' + id}
                    className="iwp-field__input"
                    value={value}
                    onChange={(event) => {
                      const target = event.target;
                      let nextValue = target.value;
                      dispatch(
                        setTemplate({ [target.name]: nextValue })
                      );
                      getPreview({
                        id: importer_id,
                        fields: { [target.name]: nextValue },
                      });
                    }}
                  />
                  <button
                    className="iwp-field__select"
                    type="button"
                    onClick={() =>
                      showSelectModal(
                        name + '.' + id,
                        map.hasOwnProperty(`${name}.row_base`)
                          ? map[`${name}.row_base`]
                          : ''
                      )
                    }
                  >
                    Select Data
                  </button>
                  <button
                    className={`iwp-field__settings dashicons-before dashicons-editor-table ${map.hasOwnProperty(name + '.' + id + '._mapped._index') && +map[name + '.' + id + '._mapped._index'] > 0 ? 'iwp-field__settings--active' : ''}`}
                    type="button"
                    onClick={() => setShowMap(true)}
                  >
                    Settings
                  </button>
                </div>
                <p className="iwp-preview--text" title={preview}>
                  Preview: {preview_text}
                </p>
              </>
            )}
            {typeof fieldOptions !== 'undefined' && (
              <label className="iwp-field__enable-text">
                <input
                  type="checkbox"
                  name="enable_text"
                  onChange={toggleTextField}
                  value="yes"
                  checked={enable_text}
                />{' '}
                Enable Text Field
              </label>
            )}
          </div>
        </>
      );
  }
};

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
