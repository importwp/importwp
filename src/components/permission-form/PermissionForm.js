import React, { useCallback, useEffect, useState } from 'react';
import PropTypes from 'prop-types';

import './PermissionForm.scss';
import { importer } from '../../services/importer.service';
import FieldLabel from '../field-label/FieldLabel';
import CreatableSelect from 'react-select/creatable';
import { connect } from 'react-redux';
import InputField from '../InputField/InputField';
import InputFieldDataSelector from '../InputFieldDataSelector/InputFieldDataSelector';
import NoticeList from '../notice-list/NoticeList';
import { Tooltip } from 'react-tooltip';

const PermissionForm = ({
  id,
  permissions = {},
  settings = {},
  complete,
  onError = () => {},
  importer: importerData,
}) => {
  const initialCreatePermissions = (
    permissions.create && permissions.create.fields
      ? permissions.create.fields.join('\n')
      : ''
  );
  const initialUpdatePermissions = (
    permissions.update && permissions.update.fields
      ? permissions.update.fields.join('\n')
      : ''
  );

  const [create, setCreate] = useState(
    permissions.create && permissions.create.enabled == false
      ? permissions.create.enabled
      : true
  );
  const [create_type, setCreateType] = useState(
    permissions.create && permissions.create.type
      ? permissions.create.type
      : ''
  );
  const [create_permissions, setCreatePermissions] = useState(initialCreatePermissions);
  const [update, setUpdate] = useState(
    permissions.update && permissions.update.enabled == false
      ? permissions.update.enabled
      : true
  );
  const [update_type, setUpdateType] = useState(
    permissions.update && permissions.update.type
      ? permissions.update.type
      : ''
  );
  const [update_permissions, setUpdatePermissions] = useState(initialUpdatePermissions);
  const [remove, setRemove] = useState(
    permissions.remove && permissions.remove.enabled
      ? permissions.remove.enabled
      : false
  );
  const [remove_trash, setRemoveTrash] = useState(
    permissions.remove && permissions.remove.trash
      ? permissions.remove.trash
      : false
  );
  const [remove_media, setRemoveMedia] = useState(
    permissions.remove && permissions.remove.media
      ? permissions.remove.media
      : false
  );
  const [setting_unique_identifier, setSettingUniqueIdentifier] = useState(
    settings.unique_identifier ? settings.unique_identifier : ''
  );
  const [setting_unique_identifier_type, setSettingUniqueIdentifierType] = useState(
    settings.unique_identifier_type ? settings.unique_identifier_type : ''
  );
  const [setting_unique_identifier_ref, setSettingUniqueIdentifierRef] = useState(
    settings.unique_identifier_ref ? settings.unique_identifier_ref : ''
  );
  const [saving, setSaving] = useState(false);
  const [unique_identifiers, setUniqueIdentifiers] = useState([]);
  const [permission_fields, setPermissionFields] = useState([]);
  const [update_permission_fields, setUpdatePermissionFields] = useState(
    initialUpdatePermissions.split('\n')
  );
  const [create_permission_fields, setCreatePermissionFields] = useState(
    initialCreatePermissions.split('\n')
  );
  const [isLoading, setIsLoading] = useState(false);

  const hasNewUniqueIdentifierUI = useCallback(() => {
    const { version = 0 } = importerData || {};
    return version >= 2 || setting_unique_identifier_type;
  }, [importerData, setting_unique_identifier_type]);

  const disabled = !((create || update || remove) && (
    !hasNewUniqueIdentifierUI() ||
    (setting_unique_identifier_type == 'field' && setting_unique_identifier) ||
    (setting_unique_identifier_type == 'custom' && setting_unique_identifier_ref)
  ));

  const onChange = (event) => {
    const target = event.target;
    let value = target.type === 'checkbox' ? target.checked : target.value;
    const name = target.name;
    const setters = {
      create: setCreate,
      create_type: setCreateType,
      create_permissions: setCreatePermissions,
      update: setUpdate,
      update_type: setUpdateType,
      update_permissions: setUpdatePermissions,
      remove: setRemove,
      remove_trash: setRemoveTrash,
      remove_media: setRemoveMedia,
      setting_unique_identifier: setSettingUniqueIdentifier,
      setting_unique_identifier_type: setSettingUniqueIdentifierType,
      setting_unique_identifier_ref: setSettingUniqueIdentifierRef,
    };
    if (setters[name]) {
      setters[name](value);
    }
  };

  const setPermissionFieldsFn = (section, fields = [], add = true) => {
    const isUpdate = section == 'update';
    const current = isUpdate ? update_permission_fields : create_permission_fields;
    const next = add
      ? [...current, ...fields]
      : [...current.filter(item => !fields.includes(item))];

    if (isUpdate) {
      setUpdatePermissionFields(next);
    } else {
      setCreatePermissionFields(next);
    }

    const joined = next.filter(item => {
      for (const [key, value] of Object.entries(permission_fields)) {
        if (Object.keys(value).includes(item)) {
          return true;
        }
      }
      return false;
    }).join('\n');

    if (isUpdate) {
      setUpdatePermissions(joined);
    } else {
      setCreatePermissions(joined);
    }
  };

  const save = (callback = () => {}) => {
    const payload = {
      create: {
        enabled: create,
        type: create_type,
        fields: create_permissions,
      },
      update: {
        enabled: update,
        type: update_type,
        fields: update_permissions,
      },
      remove: {
        enabled: remove,
        trash: remove_trash,
        media: remove_media
      },
    };

    setSaving(true);

    let data = {
      id: id,
      permissions: payload,
      setting_unique_identifier: setting_unique_identifier,
    };

    if (hasNewUniqueIdentifierUI()) {
      data = {
        ...data,
        setting_unique_identifier_type: setting_unique_identifier_type,
        setting_unique_identifier_ref: setting_unique_identifier_ref,
      }
    }

    importer
      .save(data)
      .then(() => {
        setSaving(false);
        callback();
      })
      .catch((error) => {
        onError(error);
        setSaving(false);
      });
  };

  const onSave = () => {
    save();
  };

  const onSubmit = () => {
    save(() => {
      complete();
    });
  };

  const onUniqueIdentifierTypeChange = (e) => {
    setSettingUniqueIdentifierType(e.target.value);
  };

  useEffect(() => {
    let cancelled = false;
    const load = async () => {
      try {
        setIsLoading(true);
        const unique_identifiers_result = await importer.templateUniqueIdentifiers(id);
        let nextIdentifiers = unique_identifiers_result.options;
        if (setting_unique_identifier.length > 0 && !nextIdentifiers.find(item => item.value == setting_unique_identifier)) {
          nextIdentifiers = [...nextIdentifiers, { label: 'Custom: ' + setting_unique_identifier, value: setting_unique_identifier }];
        }
        if (!cancelled) {
          setUniqueIdentifiers(nextIdentifiers);
          setIsLoading(false);
        }

        const template_group = await importer.template(id);
        if (!cancelled) {
          setPermissionFields(template_group.permission_fields);
        }
      } catch (e) {
        onError('Error: ' + e);
      }
    };

    load();
    return () => {
      cancelled = true;
    };
  }, [id]);

  // Adjust local permission toggles when parent importer permissions change.
  const [prevPermissions, setPrevPermissions] = useState(permissions);
  if (permissions !== prevPermissions) {
    setPrevPermissions(permissions);
    if (permissions.create) {
      setCreate(permissions.create.enabled);
    }
    if (permissions.update) {
      setUpdate(permissions.update.enabled);
    }
    if (permissions.remove) {
      setRemove(permissions.remove.enabled);
    }
  }

  const permission_field_selector = (section, active_fields = []) => {
    const btn_styles = {
      background: 'none',
      border: 'none',
      textDecoration: 'underline'
    };

    return <>
      {Object.keys(permission_fields).map((group) => <div key={group}>
          <p>
            {group !== 'core' && <span style={{ fontWeight: 'bold' }}>{group} </span>}
            (<button style={btn_styles} type='button' onClick={() => {
              setPermissionFieldsFn(section, Object.keys(permission_fields[group]), true);
            }}>Check All</button>,
            <button style={btn_styles} type='button' onClick={() => {
              setPermissionFieldsFn(section, Object.keys(permission_fields[group]), false);
            }}>Uncheck All</button>)
          </p>

          {Object.keys(permission_fields[group]).map(field => <label key={field} style={{ display: 'block' }}><input type="checkbox" checked={active_fields.includes(field)} onChange={() => {
            setPermissionFieldsFn(section, [field], !active_fields.includes(field));
          }} /> {permission_fields[group][field]}</label>)}
        </div>)}
      </>;
    }

    return (
      <React.Fragment>

        <div className="iwp-form iwp-form--mb">
          <form>
            <p className="iwp-heading iwp-heading--has-tooltip">Permissions. <a href="https://www.importwp.com/docs/permissions/?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=importer" target='_blank' className='iwp-label__tooltip'>?</a></p>

            {hasNewUniqueIdentifierUI() ? <>
              <div>
                <p className="iwp-form__label iwp-label--has-tooltip iwp-label--inline-block" style={{ marginBlock: '10px', paddingBottom: 0 }}>
                  Unique identifier:
                  <span className="iwp-label__tooltip" data-tooltip-id={'iwp-tooltip_uid_heading'}>
                    ?
                  </span>
                </p>
                <Tooltip id='iwp-tooltip_uid_heading' effect="solid" delayHide={300} className="iwp-react-tooltip">
                  <p>Set how each record in the import file should be identified during the import process, either by using a previously populated template field, or by creating a custom identifier made from one or more sections of the import file.</p>
                  <p>This unique identifier is then used to either create new records if no match is found, update existing records, or delete records no longer found in the import file</p>
                </Tooltip>
              </div>

              {/* <p style={{ fontStyle: 'italic' }}>Set how each record in the import file should be identified, using a previously populated template field, or by creating a custom identifier mode from one or more sections of the import file. This unique identifier is then used to either create new records if no match is found, update existing records, or delete records no longer found in the import file.</p> */}
              <div className='iwp-permissions'>
                <div className='iwp-permission__block iwp-permission__block--first'>
                  <div className='iwp-block__handle'>
                    <input type='radio' id="setting_unique_identifier_type__field" name="setting_unique_identifier_type" value="field" defaultChecked={setting_unique_identifier_type === 'field'} onChange={onUniqueIdentifierTypeChange} />
                    <label htmlFor='setting_unique_identifier_type__field'>Select a template field to be used as the unique identifier for each record.</label>
                  </div>
                  <div className='iwp-block__content' style={{
                    display: setting_unique_identifier_type === 'field' ? 'block' : 'none',
                    paddingBottom: '10px',
                    paddingTop: '10px',
                  }}>
                    <div className="iwp-field__left">
                      <FieldLabel
                        label="Template Field"
                        field="setting_unique_identifier"
                        id="setting_unique_identifier"
                        tooltip="Select from the predefined list of fields or manually type to a field name."
                      />
                    </div>
                    <div className="iwp-field__right">
                      <CreatableSelect
                        id="setting_unique_identifier"
                        name="setting_unique_identifier"
                        isClearable
                        isLoading={isLoading}
                        options={unique_identifiers}
                        value={unique_identifiers.find(item => item.value == setting_unique_identifier)}
                        onChange={(data) => {

                          let value = data?.value;

                          if (value) {
                            if (!unique_identifiers.find(item => item.value == value)) {
                              setUniqueIdentifiers([...unique_identifiers, { label: 'Custom: ' + value, value }]);
                            }
                          } else {
                            value = '';
                          }


                          setSettingUniqueIdentifier(value);
                        }}
                        className="iwp-form__select"
                        placeholder="Select a field from the importer template."
                      />

                      {setting_unique_identifier === 'ID' && <NoticeList notices={[
                        { message: 'Using ID as the unqiue identifier field will match against existing wordpress ID\'s. Please note that the importer cannot create records with a specific ID and in that case may create duplicate records. (If you want to use ID as a unique identfier and it does not need to match the WordPress ID, i would suggest instead using the "Select data from your import file" option and reference the ID that way). ', type: 'info' },
                      ]} />}
                    </div>
                  </div>
                </div>

                <div className='iwp-permission__block'>
                  <div className='iwp-block__handle'>
                    <input type='radio' id="setting_unique_identifier_type__custom" name="setting_unique_identifier_type" value="custom" defaultChecked={setting_unique_identifier_type === 'custom'} onChange={onUniqueIdentifierTypeChange} />
                    <label htmlFor='setting_unique_identifier_type__custom'>Select data from your import file to be used as the unique identifier per record.</label>
                  </div>
                  <div className='iwp-block__content' style={{
                    display: setting_unique_identifier_type === 'custom' ? 'block' : 'none',
                    paddingBottom: '10px',
                    paddingTop: '10px',
                  }}>
                    <div className="iwp-field__left">
                      <FieldLabel
                        label='Identifier'
                        id='setting_unique_identifier_ref'
                        field='setting_unique_identifier_ref'
                        tooltip="Select one or more sections of your import file that can be combined to create an identifier for each row / record being imported."
                      />
                    </div>
                    <div className="iwp-field__right">
                      <InputField
                        name="setting_unique_identifier_ref"
                        value={setting_unique_identifier_ref}
                        onChange={val => setSettingUniqueIdentifierRef(val)}
                      >
                        <InputFieldDataSelector
                          value={setting_unique_identifier_ref}
                          onClose={(selection) => {
                            setSettingUniqueIdentifierRef(selection !== null ? selection : setting_unique_identifier_ref);
                          }} />
                      </InputField>
                    </div>
                  </div>
                </div>
              </div>
            </> : <>
              <div className="iwp-form__grid">
                <div className="iwp-form__row iwp-form__row--left">
                  <FieldLabel
                    label="Unique Identifier"
                    field="setting_unique_identifier"
                    id="setting_unique_identifier"
                    tooltip="Set which field should be used to uniquely identify each record, Either select from the predefined list of fields, manually type to set a custom identifier, or Leave empty to use the template default."
                    display="inline-block"
                  />
                  <CreatableSelect
                    id="setting_unique_identifier"
                    name="setting_unique_identifier"
                    isClearable
                    isLoading={isLoading}
                    options={unique_identifiers}
                    value={unique_identifiers.find(item => item.value == setting_unique_identifier)}
                    onChange={(data) => {

                      let value = data?.value;

                      if (value) {
                        if (!unique_identifiers.find(item => item.value == value)) {
                          setUniqueIdentifiers([...unique_identifiers, { label: 'Custom: ' + value, value }]);
                        }
                      } else {
                        value = '';
                      }


                      setSettingUniqueIdentifier(value);
                    }}
                    className="iwp-form__select"
                    placeholder="Leave empty to use the templates default."
                  />
                </div>
              </div>

              {(setting_unique_identifier.length > 0 && importerData.template !== 'jet-engine-cct') && <>
                <NoticeList notices={[
                  { message: 'Please backup your site database before enabling the new unique identifier Interface, The new unique identifier interface is not required for the importer to still run, and if enabled may change how your importer currently finds existing records.', type: 'error' },
                ]} />

                <button type="button" className='button button-primary' onClick={() => {
                  setSettingUniqueIdentifierType('field')
                }}>Enable new unique identifier interface</button>
              </>}
            </>}



            <p className="iwp-form__label">
              Restrict which fields can be imported:
            </p>
            <div className="iwp-permissions">
              <div className="iwp-permission__block iwp-permission__block--create">
                <div className="iwp-block__handle">
                  <label>
                    <input
                      type="checkbox"
                      name="create"
                      checked={create}
                      onChange={onChange}
                    />{' '}
                    Create - <em>Allow the creation of new records when no unique identifer match has been found.</em>
                  </label>
                </div>
                {create && (
                  <div className="iwp-block__content">
                    <p>
                      Allow / Disallow which fields are imported when a new
                      record is created.
                    </p>

                    <div className="iwp-field">
                      <div className="iwp-field__left">
                        <FieldLabel
                          label="Import"
                          field="create_type"
                          id="create_type"
                        />
                      </div>
                      <div className="iwp-field__right">
                        <select
                          id="create_type"
                          name="create_type"
                          onChange={onChange}
                          value={create_type}
                        >
                          <option value="">All Fields</option>
                          <option value="include">
                            Only the following Fields
                          </option>
                          <option value="exclude">
                            None of the following Fields
                          </option>
                        </select>
                      </div>
                    </div>
                    {create_type !== '' && (
                      <div className="iwp-field">
                        <div className="iwp-field__left">
                          <FieldLabel
                            label="Fields"
                            field="create_permissions"
                            id="create_permissions"
                          // tooltip="Enter each field name on a new line, use * to match field names. E.g. 'field_name', starts with 'field_*', ends with '*_field', or match all '*'"
                          />
                        </div>
                        <div className="iwp-field__right">
                          {Object.keys(permission_fields).length > 0 && permission_field_selector('create', create_permission_fields)}
                          <textarea
                            id="create_permissions"
                            name="create_permissions"
                            onChange={onChange}
                            value={create_permissions}
                            style={Object.keys(permission_fields).length ? { display: 'none' } : {}}
                          ></textarea>

                        </div>
                      </div>
                    )}
                  </div>
                )}
              </div>

              <div className="iwp-permission__block iwp-permission__block--edit">
                <div className="iwp-block__handle">
                  <label>
                    <input
                      type="checkbox"
                      name="update"
                      checked={update}
                      onChange={onChange}
                    />{' '}
                    Update - <em>Allow updating of existing records when a unique identifier match has been found.</em>
                  </label>
                </div>
                {update && (
                  <div className="iwp-block__content">
                    <p>
                      Restrict which fields are imported when updating existing
                      records.
                    </p>

                    <div className="iwp-field">
                      <div className="iwp-field__left">
                        <FieldLabel
                          label="Import"
                          field="update_type"
                          id="update_type"
                        />
                      </div>
                      <div className="iwp-field__right">
                        <select
                          id="update_type"
                          name="update_type"
                          onChange={onChange}
                          value={update_type}
                        >
                          <option value="">All Fields</option>
                          <option value="include">
                            Only the following Fields
                          </option>
                          <option value="exclude">
                            None of the following Fields
                          </option>
                        </select>
                      </div>
                    </div>

                    {update_type !== '' && (
                      <div className="iwp-field">
                        <div className="iwp-field__left">
                          <FieldLabel
                            label="Fields"
                            field="update_permissions"
                            id="update_permissions"
                          // tooltip="Enter each field name on a new line, use * to match field names. E.g. 'field_name', starts with 'field_*', ends with '*_field', or match all '*'"
                          />
                        </div>
                        <div className="iwp-field__right">

                          {Object.keys(permission_fields).length > 0 && permission_field_selector('update', update_permission_fields)}

                          <textarea
                            id="update_permissions"
                            name="update_permissions"
                            onChange={onChange}
                            value={update_permissions}
                            style={Object.keys(permission_fields).length ? { display: 'none' } : {}}
                          ></textarea>

                        </div>
                      </div>
                    )}
                  </div>
                )}
              </div>
              <div className="iwp-permission__block iwp-permission__block--delete">
                <div className="iwp-block__handle">
                  <label>
                    <input
                      type="checkbox"
                      name="remove"
                      checked={remove}
                      onChange={onChange}
                    />{' '}
                    Delete -{' '}
                    <em>Allow deletion of previously imported records that are no longer in the import file.</em>
                  </label>
                </div>
                {remove && (
                  <div className="iwp-block__content">
                    <p>
                      <label>
                        <input
                          type="checkbox"
                          name="remove_trash"
                          checked={remove_trash}
                          onChange={onChange}
                        />{' '}
                        Move items to trash - <em>Only if trash is enabled</em>.
                      </label>
                    </p>
                    <p>
                      <label>
                        <input
                          type="checkbox"
                          name="remove_media"
                          checked={remove_media}
                          onChange={onChange}
                        />{' '}
                        Remove related media - <em>Removes attached media, does not work with trash</em>.
                      </label>
                    </p>
                  </div>
                )}
              </div>
            </div>
          </form>
        </div >

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
      </React.Fragment >
    )
};

PermissionForm.propTypes = {
  id: PropTypes.number,
  permissions: PropTypes.object,
  settings: PropTypes.object,
  complete: PropTypes.func,
  onError: PropTypes.func,
  template: PropTypes.string,
};

const mapStateToProps = (state, props) => ({
  importer: state.importer.importer,
});

export default connect(mapStateToProps)(PermissionForm);
