import React, { useCallback, useMemo, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import Switch from 'react-switch';

import './ImporterForm.scss';
import { importer } from '../../services/importer.service';
import SettingField from '../setting-field/SettingField';
import UpgradeMessage from '../upgrade-message/UpgradeMessage';
import FieldLabel from '../field-label/FieldLabel';
import ImportFilter from '../import-filter/ImportFilter';
import { debugLog } from '../../util/debug';

const default_schedule = {
  setting_cron_disabled: false,
  setting_cron_schedule: 'month',
  setting_cron_day: 0,
  setting_cron_hour: 0,
  setting_cron_minute: 0,
  setting_run_fetch: false,
};

const ImporterForm = ({
  complete = () => {},
  id,
  template = '',
  settings = {},
  onRun = () => {},
  onError = () => {},
  pro = false,
  templates = [],
}) => {
  const template_settings = useMemo(() => {
    const found = templates.find((data) => data.id === template);
    return found ? found['settings'] : [];
  }, [template, templates]);

  const initialState = useMemo(() => {
    let settings_state = {};
    if (template_settings) {
      template_settings.forEach((field) => {
        settings_state['setting_' + field.id] =
          settings && settings[field.id] ? settings[field.id] : '';
      });
    }

    return {
      ...settings_state,
      setting_import_method:
        settings && settings.import_method ? settings.import_method : 'run',
      setting_cron_schedule:
        settings && settings.cron_schedule ? settings.cron_schedule : 'month',
      setting_cron_day:
        settings && settings.cron_day ? parseInt(settings.cron_day) : 0,
      setting_cron_hour:
        settings && settings.cron_hour ? parseInt(settings.cron_hour) : 0,
      setting_cron_minute:
        settings && settings.cron_minute ? parseInt(settings.cron_minute) : 0,
      setting_cron_disabled:
        settings && settings.cron_disabled ? settings.cron_disabled : false,
      setting_run_fetch:
        settings && settings.run_fetch ? settings.run_fetch : false,
      setting_cron:
        settings && settings.cron ? settings.cron : [default_schedule],
      setting_filters:
        settings && settings.filters ? settings.filters : [],
      setting_hash_check: settings && settings.hash_check ? settings.hash_check : false,
      disabled:
        pro === false && settings && settings.import_method === 'schedule',
      saving: false,
      setting_max_row: settings.max_row,
      setting_start_row: settings.start_row,
    };
  }, [pro, settings, template_settings]);

  const [form, setForm] = useState(initialState);
  const formRef = useRef(form);
  formRef.current = form;

  const setSaving = useCallback((saving) => {
    setForm((current) => ({ ...current, saving }));
  }, []);

  const onChange = useCallback((event) => {
    const target = event.target;
    const value = target.type === 'checkbox' ? target.checked : target.value;
    const name = target.name;

    debugLog('ImporterForm change', name, value);

    setForm((current) => {
      const next = {
        ...current,
        [name]: value,
      };
      next.disabled = pro === false && next.setting_import_method === 'schedule';
      return next;
    });
  }, [pro]);

  const onCronChange = useCallback((event, i) => {
    const target = event.target;
    const value = target.type === 'checkbox' ? target.checked : target.value;
    const name = target.name;

    setForm((current) => ({
      ...current,
      setting_cron: current.setting_cron.map((item, index) =>
        index === i ? { ...item, [name]: value } : item
      ),
    }));
  }, []);

  const addNewSchedule = useCallback(() => {
    setForm((current) => ({
      ...current,
      setting_cron: [...current.setting_cron, default_schedule],
    }));
  }, []);

  const removeSchedule = useCallback((i) => {
    setForm((current) => ({
      ...current,
      setting_cron: current.setting_cron.filter((_, index) => index !== i),
    }));
  }, []);

  const save = useCallback((callback = () => {}) => {
    const current = formRef.current;
    setSaving(true);

    const data = Object.keys(current)
      .filter((key) => key.startsWith('setting_'))
      .reduce((obj, key) => {
        obj[key] = current[key];
        return obj;
      }, { id });

    importer.save(data).then(
      () => {
        callback(current);
      },
      (error) => {
        onError(error);
        setSaving(false);
      }
    );
  }, [id, onError, setSaving]);

  const onSave = useCallback(() => {
    save(() => {
      setSaving(false);
    });
  }, [save, setSaving]);

  const runNow = useCallback(() => {
    setSaving(true);

    importer.init(id).then(
      (init_response) => {
        setSaving(false);
        onRun(init_response.session);
      },
      (error) => {
        onError(error);
        setSaving(false);
      }
    );
  }, [id, onError, onRun, setSaving]);

  const onSubmit = useCallback(() => {
    save((current) => {
      if (current.setting_import_method === 'background') {
        importer.init(id).then(
          () => {
            setSaving(false);
          },
          (error) => {
            onError(error);
            setSaving(false);
          }
        );
        return;
      }

      if (current.setting_import_method !== 'run') {
        setSaving(false);
        return;
      }

      importer.init(id).then(
        (init_response) => {
          setSaving(false);
          onRun(init_response.session);
        },
        (error) => {
          onError(error);
          setSaving(false);
        }
      );
    });
  }, [id, onError, onRun, save, setSaving]);

  const {
    setting_import_method,
    disabled,
    saving,
    setting_start_row,
    setting_max_row,
    setting_cron,
    setting_filters,
    setting_run_fetch,
    setting_hash_check
  } = form;

    return (
      <React.Fragment>
        <div className="iwp-form">
          <form>
            <p className="iwp-heading iwp-heading--has-tooltip">Run Importer. <a href="https://www.importwp.com/docs/run-import/?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=importer" target='_blank' className='iwp-label__tooltip'>?</a></p>

            <div className="iwp-form__grid">
              <div className="iwp-form__row iwp-form__row--left">
                <FieldLabel
                  label="Start row"
                  field="setting_start_row"
                  id="setting_start_row"
                  tooltip="Set the row you wish to start your import from."
                  display="inline-block"
                />
                <input
                  type="number"
                  className="iwp-form__input"
                  id="setting_start_row"
                  name="setting_start_row"
                  min="0"
                  placeholder="Leave empty to import from the start."
                  onChange={onChange}
                  value={setting_start_row}
                />
              </div>

              <div className="iwp-form__row iwp-form__row--right">
                <FieldLabel
                  label="Number of rows"
                  field="setting_max_row"
                  id="setting_max_row"
                  tooltip="Maximum number of rows to import, leave '0' to ignore."
                  display="inline-block"
                />
                <input
                  type="number"
                  className="iwp-form__input"
                  id="setting_max_row"
                  name="setting_max_row"
                  min="0"
                  placeholder="Leave empty to import until the last record."
                  onChange={onChange}
                  value={setting_max_row}
                />
              </div>

              <div className="iwp-form__row iwp-form__row--right">
                <label className="iwp-form__label iwp-form__label--switch">
                  <span>Update records only when data has changed.</span>
                  <Switch
                    checked={setting_hash_check}
                    name='setting_hash_check'
                    height={20}
                    width={40}
                    onColor="#22c48f"
                    onChange={checked => {

                      onChange({
                        target: {
                          name: 'setting_hash_check',
                          type: 'checkbox',
                          checked
                        }
                      });
                    }}
                  />
                </label>
              </div>
            </div>

            {template_settings &&
              template_settings.map((field) => (
                <SettingField
                  key={field.id}
                  id={field.id}
                  label={field.label}
                  type={field.type}
                  value={form['setting_' + field.id]}
                  onChange={onChange}
                />
              ))}

            <ImportFilter
              complete={complete}
              id={id}
              template={template}
              settings={settings}
              onRun={onRun}
              onError={onError}
              pro={pro}
              templates={templates}
              onFilterChange={(filters) => {
                setForm((current) => ({
                  ...current,
                  setting_filters: filters,
                }));
              }}
              filters={setting_filters}
            />

            <div className="iwp-accordion__block iwp-accordion__block--first">
              <div className="iwp-block__handle">
                <label>
                  <input
                    type="radio"
                    name="setting_import_method"
                    value="run"
                    checked={setting_import_method === 'run'}
                    onChange={onChange}
                  />{' '}
                  Run Now - <em>Start the import straight away.</em>
                </label>
              </div>
              {setting_import_method === 'run' && (
                <div className="iwp-block__content">
                  <label className="iwp-form__label iwp-form__label--switch">
                    <span>Download new file before import.</span>
                    <Switch
                      checked={setting_run_fetch}
                      name='setting_run_fetch'
                      height={20}
                      width={40}
                      onColor="#22c48f"
                      onChange={checked => {

                        onChange({
                          target: {
                            name: 'setting_run_fetch',
                            type: 'checkbox',
                            checked
                          }
                        });
                      }}
                    />
                  </label>
                </div>
              )}
            </div>
            <div className="iwp-accordion__block">
              <div className="iwp-block__handle">
                <label>
                  <input
                    type="radio"
                    name="setting_import_method"
                    value="background"
                    checked={setting_import_method === 'background'}
                    onChange={onChange}
                  />{' '}
                  Run in the background - <em>Start the import and let it run in the background.</em>
                </label>
              </div>
              {setting_import_method === 'background' && (
                <div className="iwp-block__content">
                  {React.cloneElement(
                    window.iwp.hooks.applyFilters(
                      'iwp_background_import_method',
                      <UpgradeMessage message="Please upgrade to Import WP Pro v2.11+ to run imports in the background." />
                    ),
                    {
                    },
                    <label className="iwp-form__label iwp-form__label--switch">
                      <span>Download new file before import.</span>
                      <Switch
                        checked={setting_run_fetch}
                        name='setting_run_fetch'
                        height={20}
                        width={40}
                        onColor="#22c48f"
                        onChange={checked => {

                          onChange({
                            target: {
                              name: 'setting_run_fetch',
                              type: 'checkbox',
                              checked
                            }
                          });
                        }}
                      />
                    </label>
                  )}
                </div>
              )}
            </div>
            <div className="iwp-accordion__block">
              <div className="iwp-block__handle">
                <label>
                  <input
                    type="radio"
                    name="setting_import_method"
                    value="schedule"
                    checked={setting_import_method === 'schedule'}
                    onChange={onChange}
                  />{' '}
                  Schedule - <em>Run the import at a later date.</em>
                </label>
              </div>
              {setting_import_method === 'schedule' && (
                <div className="iwp-block__content">
                  {React.cloneElement(
                    window.iwp.hooks.applyFilters(
                      'iwp_scheduler',
                      <UpgradeMessage message="Please upgrade to Import WP Pro to Schedule this importer." />
                    ),
                    {
                      setting_cron: setting_cron,
                      onCronChange: onCronChange,
                      removeSchedule: removeSchedule,
                      addNewSchedule: addNewSchedule,
                    }
                  )}
                </div>
              )}
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
              {saving
                ? 'Saving'
                : (setting_import_method === 'run' || setting_import_method === 'background')
                  ? 'Save & Run'
                  : 'Save & Schedule'}
            </button>{' '}
            {setting_import_method == 'schedule' && pro === true && (
              <button
                className="button button-link"
                type="button"
                onClick={runNow}
                disabled={disabled}
              >
                Run manually
              </button>
            )}
          </div>
        </div>
      </React.Fragment>
    )
};

ImporterForm.propTypes = {
  complete: PropTypes.func,
  id: PropTypes.number,
  template: PropTypes.string,
  settings: PropTypes.object,
  onRun: PropTypes.func,
  onError: PropTypes.func,
  pro: PropTypes.bool,
  templates: PropTypes.array,
};

export default ImporterForm;
