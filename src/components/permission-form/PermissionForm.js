import React, { Component } from 'react';
import PropTypes from 'prop-types';

import './PermissionForm.scss';
import { importer } from '../../services/importer.service';
import FieldLabel from '../field-label/FieldLabel';

class PermissionForm extends Component {
  constructor(props) {
    super(props);

    this.state = {
      create:
        props.permissions.create && props.permissions.create.enabled == false
          ? props.permissions.create.enabled
          : true,
      create_type:
        props.permissions.create && props.permissions.create.type
          ? props.permissions.create.type
          : '',
      create_permissions:
        props.permissions.create && props.permissions.create.fields
          ? props.permissions.create.fields.join('\n')
          : '',
      update:
        props.permissions.update && props.permissions.update.enabled == false
          ? props.permissions.update.enabled
          : true,
      update_type:
        props.permissions.update && props.permissions.update.type
          ? props.permissions.update.type
          : '',
      update_permissions:
        props.permissions.update && props.permissions.update.fields
          ? props.permissions.update.fields.join('\n')
          : '',
      remove:
        props.permissions.remove && props.permissions.remove.enabled
          ? props.permissions.remove.enabled
          : false,
      remove_trash:
        props.permissions.remove && props.permissions.remove.trash
          ? props.permissions.remove.trash
          : false,
      setting_unique_identifier:
        props.permissions.remove && props.settings.unique_identifier
          ? props.settings.unique_identifier
          : '',
      saving: false,
      disabled: true,
    };

    this.onChange = this.onChange.bind(this);
    this.save = this.save.bind(this);
    this.onSave = this.onSave.bind(this);
    this.onSubmit = this.onSubmit.bind(this);
    this.isDisabled = this.isDisabled.bind(this);
  }

  onChange(event) {
    const target = event.target;
    let value = target.type === 'checkbox' ? target.checked : target.value;
    const name = target.name;

    this.setState(
      {
        [name]: value,
      },
      this.isDisabled
    );
  }

  isDisabled() {
    if (this.state.create || this.state.update || this.state.remove) {
      // can save if there are some permissions enabled
      this.setState({ disabled: false });
    } else {
      this.setState({ disabled: true });
    }
  }

  componentDidMount() {
    this.isDisabled();
  }

  componentDidUpdate(prevProps) {
    if (this.props.permissions.create && prevProps.permissions.create) {
      const create = this.props.permissions.create.enabled;
      const prevCreate = prevProps.permissions.create.enabled;
      if (create !== prevCreate) {
        this.setState({ create: this.props.permissions.create.enabled });
      }
    }

    if (this.props.permissions.update && prevProps.permissions.update) {
      const update = this.props.permissions.update.enabled;
      const prevUpdate = prevProps.permissions.update.enabled;
      if (update !== prevUpdate) {
        this.setState({ update: this.props.permissions.update.enabled });
      }
    }

    if (this.props.permissions.remove && prevProps.permissions.remove) {
      const remove = this.props.permissions.remove.enabled;
      const prevRemove = prevProps.permissions.remove.enabled;
      if (remove !== prevRemove) {
        this.setState({ remove: this.props.permissions.remove.enabled });
      }
    }
  }

  save(callback = () => { }) {
    const { id } = this.props;

    const {
      create,
      create_type,
      create_permissions,
      update,
      update_type,
      update_permissions,
      remove,
      remove_trash,
      setting_unique_identifier,
    } = this.state;
    const permissions = {
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
      },
    };

    this.setState({ saving: true });

    importer
      .save({
        id: id,
        permissions: permissions,
        setting_unique_identifier: setting_unique_identifier,
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

  render() {
    const {
      create,
      create_type,
      create_permissions,
      update,
      update_type,
      update_permissions,
      remove,
      saving,
      disabled,
      setting_unique_identifier,
      remove_trash,
    } = this.state;
    return (
      <React.Fragment>
        <div className="iwp-form iwp-form--mb">
          <form>
            <p className="iwp-heading">Permissions.</p>

            <div className="iwp-form__grid">
              <div className="iwp-form__row iwp-form__row--left">
                <FieldLabel
                  label="Unique Identifier"
                  field="setting_unique_identifier"
                  id="setting_unique_identifier"
                  tooltip="Set the field to be used to uniquely identify each record."
                  display="inline-block"
                />
                <input
                  type="text"
                  className="iwp-form__input"
                  id="setting_unique_identifier"
                  name="setting_unique_identifier"
                  placeholder="Leave empty to use the templates default."
                  onChange={this.onChange}
                  value={setting_unique_identifier}
                />
              </div>
            </div>

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
                      onChange={this.onChange}
                    />{' '}
                    Create - <em>Allow the creation of new records.</em>
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
                          onChange={this.onChange}
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
                            tooltip="Enter each field name on a new line, use * to match field names. E.g. 'field_name', starts with 'field_*', ends with '*_field', or match all '*'"
                          />
                        </div>
                        <div className="iwp-field__right">
                          <textarea
                            id="create_permissions"
                            name="create_permissions"
                            onChange={this.onChange}
                            value={create_permissions}
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
                      onChange={this.onChange}
                    />{' '}
                    Update - <em>Allow updating of existing records.</em>
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
                          onChange={this.onChange}
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
                            tooltip="Enter each field name on a new line, use * to match field names. E.g. 'field_name', starts with 'field_*', ends with '*_field', or match all '*'"
                          />
                        </div>
                        <div className="iwp-field__right">
                          <textarea
                            id="update_permissions"
                            name="update_permissions"
                            onChange={this.onChange}
                            value={update_permissions}
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
                      onChange={this.onChange}
                    />{' '}
                    Delete -{' '}
                    <em>Allow deletion of previously imported records.</em>
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
                          onChange={this.onChange}
                        />{' '}
                        Move items to trash - <em>Only if trash is enabled</em>.
                      </label>
                    </p>
                  </div>
                )}
              </div>
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

PermissionForm.propTypes = {
  id: PropTypes.number,
  permissions: PropTypes.object,
  settings: PropTypes.object,
  complete: PropTypes.func,
  onError: PropTypes.func,
  template: PropTypes.string,
};

PermissionForm.defaultProps = {
  permissions: {},
  settings: {},
  onError: () => { },
};

export default PermissionForm;
