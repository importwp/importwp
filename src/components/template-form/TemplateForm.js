import React, { useCallback, useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import {
  setTemplate,
  resetEnabled,
  selectMap,
  resetRepeater,
  resetTemplate,
  fetchFieldPreview,
  clearPreview,
} from '../../features/importer/importerSlice';

import FieldGroup from '../field-group/FieldGroup';
import Modal from '../modal/Modal';
import UpgradeMessage from '../upgrade-message/UpgradeMessage';

import { importer } from '../../services/importer.service';
import DataSelector from '../data-selector/DataSelector';
import NoticeList from '../notice-list/NoticeList';
import { store } from '../../store';
import { debugLog } from '../../util/debug';

const TemplateForm = ({
  id,
  complete,
  parser,
  settings,
  map = {},
  enabled = {},
  onError = () => {},
  template,
  dispatch,
}) => {
  const repeaterTemplates = useRef({});
  const defaultValues = useRef({});

  const [showSelectModal, setShowSelectModal] = useState(false);
  const [showSelectModalSubPath, setShowSelectModalSubPath] = useState('');
  const [selectModalField, setSelectModalField] = useState('');
  const [saving, setSaving] = useState(false);
  const disabled = false;
  const [loaded, setLoaded] = useState(false);
  const [groups, setGroups] = useState([]);

  const enabledRef = useRef(enabled);
  const mapRef = useRef(map);
  const parserRef = useRef(parser);
  const onErrorRef = useRef(onError);
  const templateRef = useRef(template);
  enabledRef.current = enabled;
  mapRef.current = map;
  parserRef.current = parser;
  onErrorRef.current = onError;
  templateRef.current = template;

  const generateRepeaterTemplates = (templateFields, group) => {
    const prefix = group + '.{iwpr_template}.';
    return Object.keys(templateFields)
      .filter((fieldKey) => fieldKey.startsWith(prefix))
      .reduce((obj, key) => {
        obj[key.substring(prefix.length)] = templateFields[key];
        delete templateFields[key];
        return obj;
      }, {});
  };

  const recursiveFieldSearch = (data, path = [], output = [], join = '') => {
    let result = [];
    const keys = Object.keys(data);

    let basePath = [...path];

    if (join.length > 0) {
      basePath.push(join);
      join = '';
    }

    for (let i = 0; i < keys.length; i++) {
      const record = data[keys[i]];
      let tempPath = [...basePath];
      tempPath.push(record.id);
      if (record.hasOwnProperty('type') && record.type === 'repeatable') {
        result.push(
          ...recursiveFieldSearch(
            record.fields,
            tempPath,
            output,
            '{iwpr_template}'
          )
        );
        repeaterTemplates.current[[...tempPath].join()] = null;
        tempPath.push('_index');
        result.push(tempPath);
      } else if (record.hasOwnProperty('fields')) {
        result.push(
          ...recursiveFieldSearch(record.fields, tempPath, output)
        );
      } else {
        result.push(tempPath);
        if (record.default) {
          defaultValues.current[[...tempPath].join('.')] = record.default;
        }
      }
    }

    return result;
  };

  const closeSelectModal = useCallback(() => {
    setShowSelectModal(false);
    setSelectModalField('');
  }, []);

  const showSelectModalFn = useCallback((fieldName, sub_path = '') => {
    debugLog('showSelectModal', fieldName, sub_path);
    setShowSelectModal((current) => !current);
    setShowSelectModalSubPath(sub_path);
    setSelectModalField(fieldName);
  }, []);

  const setAndCloseSelectModal = useCallback((selection) => {
    dispatch(setTemplate({ [selectModalField]: selection }));
    dispatch(
      fetchFieldPreview({
        id,
        fields: {
          [selectModalField]: selection,
        },
      })
    );
    closeSelectModal();
  }, [closeSelectModal, dispatch, id, selectModalField]);

  const save = useCallback((callback = () => {}) => {
    setSaving(true);
    const data = store.getState();

    const map_data = Object.keys(data.importer.template)
      .filter((key) => {
        return (
          !key.includes('{iwpr_template}') &&
          data.importer.template[key] !== null &&
          typeof data.importer.template[key] !== 'undefined'
        );
      })
      .reduce((obj, key) => {
        obj[key] = data.importer.template[key];
        return obj;
      }, {});

    const enable_data = Object.keys(data.importer.enabled).reduce(
      (obj, key) => {
        obj[key] = data.importer.enabled[key];
        return obj;
      },
      {}
    );

    importer
      .save({
        id,
        map: map_data,
        enabled: enable_data,
      })
      .then(() => {
        setSaving(false);
        callback();
      })
      .catch((error) => {
        onError(error);
        setSaving(false);
      });
  }, [id, onError]);

  const onSave = useCallback(() => {
    save();
  }, [save]);

  const onSubmit = useCallback(() => {
    save(() => {
      complete();
    });
  }, [complete, save]);

  useEffect(() => {
    let cancelled = false;

    const load = async () => {
      try {
        const template_group = await importer.template(id);
        if (!template_group) {
          onErrorRef.current('Importer Template could not be found: ' + templateRef.current);
        }

        let nextGroups = template_group ? template_group.map : [];
        let templateState = {};
        let enabledFields = {};

        if (nextGroups) {
          recursiveFieldSearch(nextGroups).map((field) => {
            const fieldKey = field.join('.');
            if (field[field.length - 1] === '_index') {
              templateState[fieldKey] = 0;
            } else {
              templateState[fieldKey] = defaultValues.current[fieldKey]
                ? defaultValues.current[fieldKey]
                : '';
            }
          });

          Object.keys(repeaterTemplates.current).map((group) => {
            repeaterTemplates.current[group] = generateRepeaterTemplates(
              templateState,
              group
            );
          });

          let tmp = [];
          nextGroups.forEach((group) => {
            if (group.type === 'group') {
              group.fields.forEach((field) => {
                if (!field.hasOwnProperty('core') || field.core === false) {
                  enabledFields = {
                    ...enabledFields,
                    [`${group.id}.${field.id}`]: false,
                  };
                }
              });
            }

            let group_clone = JSON.parse(JSON.stringify(group));

            if (parserRef.current !== 'xml' && parserRef.current !== 'json') {
              const tmp_fields = group_clone.fields;
              group_clone.fields = tmp_fields.filter(
                (field) => field.id !== 'row_base'
              );
            }

            tmp = [...tmp, group_clone];
          });

          nextGroups = [...tmp];
        }

        enabledFields = {
          ...enabledFields,
          ...Object.keys(enabledRef.current).reduce((obj, key) => {
            obj[key] = enabledRef.current[key];
            return obj;
          }, {}),
        };

        if (cancelled) {
          return;
        }

        dispatch(resetEnabled(enabledFields));

        const nextTemplate = Object.keys(mapRef.current).reduce((obj, key) => {
          obj[key] = mapRef.current[key];
          return obj;
        }, {});

        dispatch(
          resetTemplate({
            ...templateState,
            ...nextTemplate,
          })
        );
        dispatch(clearPreview());
        dispatch(
          fetchFieldPreview({
            id,
            fields: nextTemplate,
          })
        );
        dispatch(resetRepeater({ ...repeaterTemplates.current }));
        setGroups(nextGroups);
        setLoaded(true);
      } catch (e) {
        onErrorRef.current('Error: ' + e);
        if (!cancelled) {
          setLoaded(true);
        }
      }
    };

    load();

    return () => {
      cancelled = true;
      importer.abort();
    };
    // Template bootstrap should run once per mount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const title =
    parser === 'csv'
      ? 'CSV Data Selector'
      : parser === 'xml'
        ? 'XML Data Selector'
        : parser === 'json'
          ? 'JSON Data Selector'
          : 'Data Selector';

  if (!loaded) {
    return <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />;
  }

  return (
    <>
      <Modal
        onClose={closeSelectModal}
        show={showSelectModal}
        title={title}
      >
        <DataSelector
          onSelect={setAndCloseSelectModal}
          onError={onError}
          id={id}
          parser={parser}
          settings={settings}
          selection={undefined}
          subPath={showSelectModalSubPath}
        ></DataSelector>
      </Modal>

      {groups.length > 0 &&
        groups.map((group) => {
          return (
            <FieldGroup
              key={group.id}
              group={group}
              showSelectModal={showSelectModalFn}
              importer_id={id}
            />
          );
        })}

      {window.iwp.hooks.applyFilters(
        'iwp_template_form_end',
        <div className="iwp-form iwp-form--mb">
          <p className="iwp-heading">Custom Fields</p>
          <UpgradeMessage message="Please upgrade to Import WP Pro to import custom fields." />
        </div>
      )}

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
    </>
  );
};

TemplateForm.propTypes = {
  id: PropTypes.number,
  complete: PropTypes.func,
  parser: PropTypes.string,
  settings: PropTypes.object,
  map: PropTypes.object,
  enabled: PropTypes.object,
  onError: PropTypes.func,
  template: PropTypes.string,
  pro: PropTypes.bool,
  templates: PropTypes.array,
};

const mapStateToProps = (state) => ({
  map: selectMap(state),
});

export default connect(mapStateToProps)(TemplateForm);
